<?php
/**
 * Plugin Name:       Personal Notes
 * Description:       Create, edit, search, and organize personal notes stored as Knowledge records.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.2.24
 * Author:            Artur Piszek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       personal-notes
 *
 * @package PersonalNotes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERSONAL_NOTES_VERSION', '0.1.0' );
define( 'PERSONAL_NOTES_FILE', __FILE__ );

/**
 * Load a shared helper from a packaged copy or the monorepo source checkout.
 *
 * @param string $file File name.
 * @return void
 */
function personal_notes_require_shared( $file ) {
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
	'class-personalos-plugin-base.php',
) as $personal_notes_shared_file ) {
	personal_notes_require_shared( $personal_notes_shared_file );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-personal-notes-plugin.php';

/**
 * Bootstrap Personal Notes.
 *
 * @return void
 */
function personal_notes_bootstrap() {
	$plugin = new Personal_Notes_Plugin();
	$GLOBALS['personal_notes_plugin'] = $plugin;
	add_action( 'init', array( $plugin, 'register' ), 20 );
}

add_action( 'plugins_loaded', 'personal_notes_bootstrap' );

/**
 * Activation hook.
 *
 * @return void
 */
function personal_notes_activate() {
	// Activation intentionally avoids writes; terms are ensured when the runtime is available.
}

register_activation_hook( __FILE__, 'personal_notes_activate' );
