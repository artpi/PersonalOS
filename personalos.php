<?php

/**
 * Plugin Name:     Personal OS
 * Description:     Manage your life.
 * Version:         0.0.1
 * Author:          Artur Piszek (artpi)
 * Author URI:      https://piszek.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     pos
 *
 * @package         artpi
 */


class POS {
	public static $modules = array();

	public static function init() {
		add_action( 'admin_menu', array( 'POS', 'admin_menu' ) );
		self::load_modules();
		add_action( 'enqueue_block_editor_assets', array( 'POS', 'enqueue_assets' ) );
	}

	public static function admin_menu() {
		add_menu_page( 'Personal OS', 'Personal OS', 'manage_options', 'personalos', false, 'dashicons-admin-generic', 3 );
		add_submenu_page( 'personalos', 'Your Dashboard', 'Dashboard', 'manage_options', 'personalos-settings', array( 'POS', 'admin_page' ), 0 );
		add_submenu_page( 'personalos', 'Notebooks', 'Notebooks', 'manage_options', 'edit-tags.php?taxonomy=notebook&post_type=notes' );
	}
	public static function enqueue_assets() {
		$script_asset = require plugin_dir_path( __FILE__ ) . '/build/index.asset.php';
		wp_enqueue_script(
			'pos',
			plugins_url( 'build/index.js', __FILE__ ),
			$script_asset['dependencies'],
			$script_asset['version']
		);
	}

	public static function admin_page() {
		require plugin_dir_path( __FILE__ ) . 'dashboard.php';
	}
	public static function load_modules() {
		require_once plugin_dir_path( __FILE__ ) . 'modules/module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/notes/index.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/readwise/index.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/evernote/index.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/todo/index.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/openai.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/transcription.php';

		$todo          = new TODO_Module();
		$notes         = new Notes_Module();
		$openai        = new OpenAI_Module();
		self::$modules = array(
			$notes,
			new Readwise( $notes ),
			new Evernote( $notes ),
			$todo,
			$openai,
			new POS_Transcription( $openai, $notes ),
		);
		require_once plugin_dir_path( __FILE__ ) . 'settings.php';
		$settings = new POS_Settings( self::$modules );
	}
}
add_action( 'init', 'POS::init' );

// For debugging trackbacks:
// add_action( 'pre_ping', function( $data ) {
//     error_log( 'PING: ' . print_r( $data, true ) );
//     return $data;
// }, 10, 1 );
