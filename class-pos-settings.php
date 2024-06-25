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
					$option_name = $module->get_setting_option_name( $setting_id );
					register_setting( 'pos', $option_name );

					add_settings_field(
						'pos_field_' . $setting['name'],
						$setting['name'],
						function() use ( $setting, $option_name ) {
							$value = get_option( $option_name );

							if ( $setting['type'] === 'text' ) {
								printf(
									'<input class="large-text" type="text" name="%1$s" id="pos_field_%1$s" value="%2$s"><br/><label for="pos_field_%1$s">%3$s</label>',
									esc_attr( $option_name ),
									wp_kses_post( $value ),
									wp_kses_post( $setting['label'] ) ?? ''
								);
							} elseif( $setting['type'] === 'bool' ) {
								printf(
									'<label for="pos_field_%1$s"><input name="%1$s" type="checkbox" id="pos_field_%1$s" value="1" %3$s>%2$s</label>',
									esc_attr( $option_name ),
									wp_kses_post( $setting['label'] ) ?? '',
									$value ? 'checked' : ''
								);
							} elseif ( $setting['type'] === 'callback' && is_callable( $setting['callback'] ) ) {
								call_user_func( $setting['callback'], $option_name, $value, $setting );
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

}
