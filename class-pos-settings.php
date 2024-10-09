<?php

class POS_Settings {
	public $modules;

	public function __construct( $modules ) {
		$this->modules = $modules;
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'options_page' ) );
			add_action( 'admin_init', array( $this, 'settings_init' ) );
		}
	}

	public function settings_init() {

		foreach ( $this->modules as $module ) {

			$settings = $module->get_settings_fields();
			if ( ! empty( $settings ) ) {
				add_settings_section(
					'pos_section_' . $module->id,
					$module->name,
					function( $args ) use ( $module ) {
						echo '<p>' . esc_html( $module->get_module_description() ) . '</p>';
					},
					'pos'
				);
				foreach ( $settings as $setting_id => $setting ) {
					if ( empty( $setting['type'] ) || empty( $setting['name'] ) ) {
						continue;
					}
					$option_name = $module->get_setting_option_name( $setting_id );
					register_setting( 'pos', $option_name );

					add_settings_field(
						'pos_field_' . $setting['name'],
						$setting['name'],
						function() use ( $setting, $option_name, $module ) {
							$value = get_option( $option_name, $setting['default'] ?? '' );

							if ( $setting['type'] === 'text' ) {
								printf(
									'<input class="large-text" type="text" name="%1$s" id="pos_field_%1$s" value="%2$s"><br/><label for="pos_field_%1$s">%3$s</label>',
									esc_attr( $option_name ),
									wp_kses_post( $value ),
									wp_kses_post( $setting['label'] ) ?? ''
								);
							} elseif ( $setting['type'] === 'textarea' ) {
								printf(
									'<textarea class="large-text" name="%1$s" id="pos_field_%1$s" placeholder="%4$s">%2$s</textarea><br/><label for="pos_field_%1$s">%3$s</label>',
									esc_attr( $option_name ),
									wp_kses_post( $value ),
									wp_kses_post( $setting['label'] ) ?? '',
									wp_kses_post( $setting['default'] ) ?? ''
								);
							} elseif ( $setting['type'] === 'select' ) {
								printf(
									'<select name="%1$s" id="pos_field_%1$s">%2$s</select><br/><label for="pos_field_%1$s">%3$s</label>',
									esc_attr( $option_name ),
									wp_kses( $this->get_select_options( $setting['options'], $value ), array( 'option' => array( 'value' => array(), 'selected' => array() ) ) ),
									wp_kses_post( $setting['label'] ) ?? ''
								);
							} elseif ( $setting['type'] === 'bool' ) {
								printf(
									'<label for="pos_field_%1$s"><input name="%1$s" type="checkbox" id="pos_field_%1$s" value="1" %3$s>%2$s</label>',
									esc_attr( $option_name ),
									wp_kses_post( $setting['label'] ) ?? '',
									$value ? 'checked' : ''
								);
							} elseif ( $setting['type'] === 'callback' && ! empty( $setting['callback'] ) && is_callable( $setting['callback'] ) ) {
								call_user_func( $setting['callback'], $option_name, $value, $setting );
							} elseif ( $setting['type'] === 'callback' && ! empty( $setting['callback'] ) && is_callable( array( $module, $setting['callback'] ) ) ) {
								call_user_func( array( $module, $setting['callback'] ), $option_name, $value, $setting );
							}

						},
						'pos',
						'pos_section_' . $module->id,
						array()
					);
				}
			}
		}

	}

	public function get_select_options( $options, $value ) {
		$html = '';
		foreach ( $options as $option_value => $option_label ) {
			$html .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option_value ), selected( $option_value, $value, false ), esc_html( $option_label ) );
		}
		return $html;
	}

	/**
	 * Add the top level menu page.
	 */
	public function options_page() {
		add_submenu_page(
			'options-general.php',
			'Personal OS',
			'PersonalOS',
			'manage_options',
			'pos',
			array( $this, 'page_html' )
		);
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
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "wporg"
				settings_fields( 'pos' );
				// output setting sections and their fields
				// (sections are registered for "wporg", each field is registered to a specific section)
				do_settings_sections( 'pos' );
				// output save settings button
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
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
