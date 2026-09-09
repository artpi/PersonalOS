<?php
/**
 * Plugin Name:       Personal Stuff
 * Description:       Find and organize belongings, places, tags, and photos stored as Knowledge records.
 * Version:           0.1.2
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Artur Piszek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       personal-stuff
 *
 * @package PersonalStuff
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERSONAL_STUFF_VERSION', '0.1.2' );
define( 'PERSONAL_STUFF_FILE', __FILE__ );

/**
 * Load a shared helper from a packaged copy or the monorepo source checkout.
 *
 * @param string $file File name.
 * @return void
 */
function personal_stuff_require_shared( $file ) {
	$candidates = array(
		plugin_dir_path( __FILE__ ) . 'includes/shared/' . $file,
		dirname( __DIR__, 2 ) . '/shared/php/' . $file,
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			require_once $candidate;
			return;
		}
	}
}

foreach ( array(
	'class-personalos-knowledge-bridge.php',
	'class-personalos-knowledge-type-vocabulary.php',
	'class-personalos-settings-helper.php',
	'class-personalos-admin-notice-helper.php',
	'class-personalos-assets-helper.php',
	'class-personalos-plugin-health.php',
	'class-personalos-wp-app.php',
	'class-personalos-plugin-base.php',
) as $personal_stuff_shared_file ) {
	personal_stuff_require_shared( $personal_stuff_shared_file );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-personal-stuff-plugin.php';

/**
 * Bootstrap Personal Stuff.
 *
 * @return void
 */
function personal_stuff_bootstrap() {
	$plugin = new Personal_Stuff_Plugin();
	$GLOBALS['personal_stuff_plugin'] = $plugin;
	add_action( 'init', array( $plugin, 'register' ), 20 );
}

add_action( 'plugins_loaded', 'personal_stuff_bootstrap' );

/**
 * Activation hook.
 *
 * @return void
 */
function personal_stuff_activate() {
	( new Personal_Stuff_Plugin() )->ensure_terms();
	PersonalOS_Wp_App::activate();
}

register_activation_hook( __FILE__, 'personal_stuff_activate' );

/**
 * Remove the app route after deactivation.
 *
 * @return void
 */
function personal_stuff_deactivate() {
	PersonalOS_Wp_App::deactivate();
}

register_deactivation_hook( __FILE__, 'personal_stuff_deactivate' );
