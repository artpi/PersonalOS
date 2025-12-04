<?php

class POS_Settings {
	public $modules;

	public function __construct( $modules ) {
		$this->modules = $modules;
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'options_page' ) );
			add_action( 'admin_init', array( $this, 'settings_init' ) );
			add_action( 'show_user_profile', array( $this, 'render_user_settings_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'render_user_settings_fields' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_settings_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_settings_fields' ) );
			add_action( 'admin_post_pos_save_user_settings', array( $this, 'handle_user_settings_form' ) );
		}
	}

	/**
	 * Initialize settings for all modules.
	 *
	 * Each module's settings are registered to a separate option group (pos_{module_id}).
	 * This ensures that saving settings for one module does not affect other modules' settings.
	 * WordPress's Settings API only processes options that are registered to the submitted group.
	 */
	public function settings_init() {

		foreach ( $this->modules as $module ) {
			$settings        = $module->get_settings_fields();
			$global_settings = array_filter(
				$settings,
				function( $setting ) {
					return ( $setting['scope'] ?? 'global' ) === 'global';
				}
			);

			if ( empty( $global_settings ) ) {
				continue;
			}

			add_settings_section(
				'pos_section_' . $module->id,
				$module->name,
				function() use ( $module ) {
					echo '<p>' . esc_html( $module->get_module_description() ) . '</p>';
				},
				'pos_' . $module->id
			);

			foreach ( $global_settings as $setting_id => $setting ) {
				if ( empty( $setting['type'] ) || empty( $setting['name'] ) ) {
					continue;
				}

				$option_name = $module->get_setting_option_name( $setting_id );

				register_setting(
					'pos_' . $module->id,
					$option_name,
					array(
						'sanitize_callback' => function( $value ) use ( $setting ) {
							return $this->sanitize_setting_value( $setting, $value );
						},
					)
				);

				add_settings_field(
					'pos_field_' . $setting['name'],
					$setting['name'],
					function() use ( $setting, $option_name, $module, $setting_id ) {
						$value    = $module->get_setting( $setting_id );
						$field_id = $this->generate_field_id( $option_name );
						$this->render_setting_input( $setting, $option_name, $value, $field_id, $module );
					},
					'pos_' . $module->id,
					'pos_section_' . $module->id,
					array()
				);
			}
		}
	}

	/**
	 * Generate select options HTML.
	 *
	 * @param array  $options Array of option value => label pairs.
	 * @param string $value Currently selected value.
	 * @return string HTML for select options.
	 */
	public function get_select_options( $options, $value ) {
		$html = '';
		foreach ( $options as $option_value => $option_label ) {
			$html .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option_value ), selected( $option_value, $value, false ), esc_html( $option_label ) );
		}
		return $html;
	}

	private function render_setting_input( $setting, $field_name, $value, $field_id, $module ) {
		$label = $setting['label'] ?? '';

		if ( $setting['type'] === 'text' ) {
			printf(
				'<input class="large-text" type="text" name="%1$s" id="%2$s" value="%3$s"/><br/><span class="description">%4$s</span>',
				esc_attr( $field_name ),
				esc_attr( $field_id ),
				esc_attr( $value ),
				wp_kses_post( $label )
			);
		} elseif ( $setting['type'] === 'textarea' ) {
			printf(
				'<textarea class="large-text" name="%1$s" id="%2$s" placeholder="%4$s">%3$s</textarea><br/><span class="description">%5$s</span>',
				esc_attr( $field_name ),
				esc_attr( $field_id ),
				esc_textarea( $value ),
				esc_attr( $setting['default'] ?? '' ),
				wp_kses_post( $label )
			);
		} elseif ( $setting['type'] === 'select' ) {
			printf(
				'<select name="%1$s" id="%2$s">%3$s</select><br/><span class="description">%4$s</span>',
				esc_attr( $field_name ),
				esc_attr( $field_id ),
				wp_kses(
					$this->get_select_options( $setting['options'], $value ),
					array(
						'option' => array(
							'value'    => array(),
							'selected' => array(),
						),
					)
				),
				wp_kses_post( $label )
			);
		} elseif ( $setting['type'] === 'bool' ) {
			printf(
				'<label for="%2$s"><input name="%1$s" type="checkbox" id="%2$s" value="1" %3$s> %4$s</label>',
				esc_attr( $field_name ),
				esc_attr( $field_id ),
				checked( ! empty( $value ), true, false ),
				wp_kses_post( $label )
			);
		} elseif ( $setting['type'] === 'callback' && ! empty( $setting['callback'] ) && is_callable( $setting['callback'] ) ) {
			call_user_func( $setting['callback'], $field_name, $value, $setting );
		} elseif ( $setting['type'] === 'callback' && ! empty( $setting['callback'] ) && is_callable( array( $module, $setting['callback'] ) ) ) {
			call_user_func( array( $module, $setting['callback'] ), $field_name, $value, $setting );
		}
	}

	private function sanitize_setting_value( $setting, $value ) {
		switch ( $setting['type'] ) {
			case 'bool':
				return ! empty( $value ) ? '1' : '';
			case 'text':
				return sanitize_text_field( $value ?? '' );
			case 'textarea':
				return sanitize_textarea_field( $value ?? '' );
			case 'select':
				return sanitize_text_field( $value ?? '' );
			case 'callback':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
			default:
				return $value;
		}
	}

	private function generate_field_id( $field_name ) {
		return sanitize_key( str_replace( array( '[', ']' ), '_', $field_name ) );
	}

	private function get_settings_by_scope( $module, $scope ) {
		$settings = $module->get_settings_fields();
		return array_filter(
			$settings,
			function( $setting ) use ( $scope ) {
				return ( $setting['scope'] ?? 'global' ) === $scope;
			}
		);
	}

	private function get_module_by_id( $module_id ) {
		foreach ( $this->modules as $module ) {
			if ( $module->id === $module_id ) {
				return $module;
			}
		}
		return null;
	}

	private function render_module_user_settings_form( $module ) {
		$user_settings = $this->get_settings_by_scope( $module, 'user' );
		if ( empty( $user_settings ) ) {
			return;
		}

		echo '<div class="pos-user-settings">';
		echo '<h2>' . esc_html__( 'My Settings (stored per user)', 'personalos' ) . '</h2>';
		echo '<p>' . esc_html__( 'These settings apply only to your account.', 'personalos' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pos-user-settings">';
		wp_nonce_field( 'pos_save_user_settings', 'pos_user_settings_nonce' );
		echo '<input type="hidden" name="action" value="pos_save_user_settings" />';
		echo '<input type="hidden" name="pos_user_settings_module" value="' . esc_attr( $module->id ) . '" />';
		echo '<table class="form-table">';
		foreach ( $user_settings as $setting_id => $setting ) {
			$field_name = sprintf( 'pos_user_settings[%s]', $setting_id );
			$field_id   = $this->generate_field_id( $field_name );
			$value      = $module->get_setting( $setting_id );
			echo '<tr>';
			printf( '<th scope="row"><label for="%s">%s</label></th>', esc_attr( $field_id ), esc_html( $setting['name'] ) );
			echo '<td>';
			$this->render_setting_input( $setting, $field_name, $value, $field_id, $module );
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button( __( 'Save My Settings', 'personalos' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Add the top level menu page.
	 */
	public function options_page() {
		add_options_page(
			'Personal OS',
			'PersonalOS',
			'manage_options',
			'pos',
			array( $this, 'page_html' )
		);
	}

	public function render_user_settings_fields( $user ) {
		$has_fields = false;

		foreach ( $this->modules as $module ) {
			$user_settings = $this->get_settings_by_scope( $module, 'user' );
			if ( empty( $user_settings ) ) {
				continue;
			}

			if ( ! $has_fields ) {
				echo '<h2>' . esc_html__( 'PersonalOS Settings', 'personalos' ) . '</h2>';
				echo '<p>' . esc_html__( 'Configure module settings that are specific to your account.', 'personalos' ) . '</p>';
				wp_nonce_field( 'pos_save_user_settings', 'pos_user_settings_nonce' );
				echo '<input type="hidden" name="pos_user_settings_present" value="1" />';
				$has_fields = true;
			}

			echo '<h3>' . esc_html( $module->name ) . '</h3>';
			echo '<table class="form-table">';
			foreach ( $user_settings as $setting_id => $setting ) {
				$field_name = sprintf( 'pos_user_settings[%s][%s]', $module->id, $setting_id );
				$field_id   = $this->generate_field_id( $field_name );
				$value      = $module->get_setting( $setting_id, $user->ID );
				echo '<tr>';
				printf( '<th scope="row"><label for="%s">%s</label></th>', esc_attr( $field_id ), esc_html( $setting['name'] ) );
				echo '<td>';
				$this->render_setting_input( $setting, $field_name, $value, $field_id, $module );
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}

	public function save_user_settings_fields( $user_id ) {
		if ( empty( $_POST['pos_user_settings_present'] ) ) {
			return;
		}

		if ( ! isset( $_POST['pos_user_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pos_user_settings_nonce'] ) ), 'pos_save_user_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$submitted = isset( $_POST['pos_user_settings'] ) ? wp_unslash( $_POST['pos_user_settings'] ) : array();

		foreach ( $this->modules as $module ) {
			$user_settings = $this->get_settings_by_scope( $module, 'user' );
			if ( empty( $user_settings ) ) {
				continue;
			}

			foreach ( $user_settings as $setting_id => $setting ) {
				$field_value = $submitted[ $module->id ][ $setting_id ] ?? null;
				if ( $setting['type'] === 'bool' ) {
					$field_value = isset( $submitted[ $module->id ][ $setting_id ] ) ? '1' : '';
				}
				$sanitized_value = $this->sanitize_setting_value( $setting, $field_value );
				$module->update_setting( $setting_id, $sanitized_value, $user_id );
			}
		}
	}

	public function handle_user_settings_form() {
		if ( ! isset( $_POST['pos_user_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pos_user_settings_nonce'] ) ), 'pos_save_user_settings' ) ) {
			wp_die( esc_html__( 'Invalid nonce specified. Settings not saved.', 'personalos' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to save settings.', 'personalos' ) );
		}

		$user_id   = get_current_user_id();
		$module_id = isset( $_POST['pos_user_settings_module'] ) ? sanitize_key( wp_unslash( $_POST['pos_user_settings_module'] ) ) : '';
		$module    = $this->get_module_by_id( $module_id );

		if ( ! $module ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=pos' ) );
			exit;
		}

		$submitted     = isset( $_POST['pos_user_settings'] ) ? wp_unslash( $_POST['pos_user_settings'] ) : array();
		$user_settings = $this->get_settings_by_scope( $module, 'user' );

		foreach ( $user_settings as $setting_id => $setting ) {
			$field_value = $submitted[ $setting_id ] ?? null;
			if ( 'bool' === $setting['type'] ) {
				$field_value = isset( $submitted[ $setting_id ] ) ? '1' : '';
			}
			$sanitized = $this->sanitize_setting_value( $setting, $field_value );
			$module->update_setting( $setting_id, $sanitized, $user_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'pos', 'module' => $module_id ), admin_url( 'options-general.php' ) ) );
		exit;
	}


	public function page_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// add error/update messages

		// check if the user have submitted the settings
		// WordPress will add the "settings-updated" $_GET parameter to the url
		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error( 'pos_messages', 'wporg_message', __( 'Settings Saved', 'personalos' ), 'updated' );
		}

		// show error/update messages
		settings_errors( 'pos_messages' );

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['module'] ) ? sanitize_key( $_GET['module'] ) : 'notes';

		// Validate that the current tab is a valid module ID
		$valid_module_ids = array_map(
			function( $mod ) {
				return $mod->id;
			},
			$this->modules
		);
		if ( ! in_array( $current_tab, $valid_module_ids, true ) ) {
			$current_tab = 'notes';
		}
		?>

		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php
				foreach ( $this->modules as $mod ) {
					// CSS class for a current tab
					$current = $mod->id === $current_tab ? ' nav-tab-active' : '';
					// URL
					$url = add_query_arg(
						array(
							'page'   => 'pos',
							'module' => $mod->id,
						),
						admin_url( 'options-general.php' )
					);
					// printing the tab link
					printf(
						'<a class="nav-tab%s" href="%s">%s</a>',
						esc_attr( $current ),
						esc_url( $url ),
						esc_html( $mod->name )
					);
				}
				?>
		</nav>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "wporg"
				settings_fields( 'pos_' . $current_tab );
				// output setting sections and their fields
				// (sections are registered for "wporg", each field is registered to a specific section)
				do_settings_sections( 'pos_' . $current_tab );
				// output save settings button
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php

		$current_module = $this->get_module_by_id( $current_tab );
		if ( $current_module ) {
			$this->render_module_user_settings_form( $current_module );
		}
	}

	public static function wp_terms_select_form( $taxonomy, $selected, $select_name = '', $none_value = false, $none_label = 'Select a notebook' ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// Function to build nested terms

		function build_terms_dropdown( $terms, $selected, $parent = 0, $level = 0 ) {
			foreach ( $terms as $term ) {
				if ( $term->parent === $parent ) {
					echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $term->term_id, $selected, false ) . '>' . esc_html( str_repeat( '&nbsp;', $level * 4 ) ) . esc_html( $term->name ) . '</option>';
					build_terms_dropdown( $terms, $selected, $term->term_id, $level + 1 );
				}
			}
		}

		echo '<select id="' . esc_attr( $select_name ) . '" name="' . esc_attr( $select_name ? $select_name : $taxonomy ) . '">';

		if ( $none_value !== false ) {
			echo '<option value="' . esc_html( $none_value ) . '">' . esc_html( $none_label ) . '</option>';
		}

		build_terms_dropdown( $terms, $selected );

		echo '</select>';
	}

}
