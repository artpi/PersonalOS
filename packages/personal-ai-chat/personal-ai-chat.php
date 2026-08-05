<?php
/**
 * Plugin Name:       Personal AI Chat
 * Description:       Chat with WordPress AI Client using Knowledge and registered abilities.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Artur Piszek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       personal-ai-chat
 *
 * @package PersonalAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERSONAL_AI_CHAT_VERSION', '0.1.0' );
define( 'PERSONAL_AI_CHAT_FILE', __FILE__ );

/**
 * Load a shared helper from a packaged copy or the monorepo source checkout.
 *
 * @param string $file File name.
 * @return void
 */
function personal_ai_chat_require_shared( $file ) {
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
) as $personal_ai_chat_shared_file ) {
	personal_ai_chat_require_shared( $personal_ai_chat_shared_file );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-personal-ai-chat-plugin.php';

/**
 * Bootstrap Personal AI Chat.
 *
 * @return void
 */
function personal_ai_chat_bootstrap() {
	$plugin = new Personal_AI_Chat_Plugin();
	$GLOBALS['personal_ai_chat_plugin'] = $plugin;
	add_action( 'init', array( $plugin, 'register' ), 20 );
}

add_action( 'plugins_loaded', 'personal_ai_chat_bootstrap' );

/**
 * Schedule app rewrite rules after activation.
 *
 * @return void
 */
function personal_ai_chat_activate() {
	PersonalOS_Wp_App::activate();
}

register_activation_hook( __FILE__, 'personal_ai_chat_activate' );

/**
 * Remove the app route after deactivation.
 *
 * @return void
 */
function personal_ai_chat_deactivate() {
	PersonalOS_Wp_App::deactivate();
}

register_deactivation_hook( __FILE__, 'personal_ai_chat_deactivate' );
