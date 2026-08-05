<?php
/**
 * Plugin Name:       Personal Evernote Sync
 * Description:       Sync Evernote notes into WordPress Knowledge records.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.2.24
 * Author:            Artur Piszek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       personal-evernote-sync
 *
 * @package PersonalEvernoteSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERSONAL_EVERNOTE_SYNC_VERSION', '0.1.0' );
define( 'PERSONAL_EVERNOTE_SYNC_FILE', __FILE__ );

/**
 * Load a shared helper from a packaged copy or the monorepo source checkout.
 *
 * @param string $file File name.
 * @return void
 */
function personal_evernote_sync_require_shared( $file ) {
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
	'class-personalos-sync-plugin-base.php',
) as $personal_evernote_sync_shared_file ) {
	personal_evernote_sync_require_shared( $personal_evernote_sync_shared_file );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-personal-evernote-sync-plugin.php';

/**
 * Bootstrap Personal Evernote Sync.
 *
 * @return void
 */
function personal_evernote_sync_bootstrap() {
	$plugin = new Personal_Evernote_Sync_Plugin();
	$GLOBALS['personal_evernote_sync_plugin'] = $plugin;
	add_action( 'init', array( $plugin, 'register' ), 20 );
}

add_action( 'plugins_loaded', 'personal_evernote_sync_bootstrap' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function personal_evernote_sync_deactivate() {
	if ( isset( $GLOBALS['personal_evernote_sync_plugin'] ) && $GLOBALS['personal_evernote_sync_plugin'] instanceof PersonalOS_Sync_Plugin_Base ) {
		$GLOBALS['personal_evernote_sync_plugin']->unschedule_sync();
	}
}

register_deactivation_hook( __FILE__, 'personal_evernote_sync_deactivate' );
