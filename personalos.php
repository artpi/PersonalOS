<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Plugin Name:     Personal OS
 * Description:     Manage your life.
 * Version:         0.2.1
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
	public static $version = '0.2.1';

	public static function init() {
		add_action( 'admin_menu', array( 'POS', 'admin_menu' ) );
		$script_asset = require plugin_dir_path( __FILE__ ) . '/build/index.asset.php';
		wp_register_script(
			'pos',
			plugins_url( 'build/index.js', __FILE__ ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'pos',
			plugins_url( 'build/style-index.css', __FILE__ ),
			array(
				'wp-components',
			),
			$script_asset['version'],
		);
		self::load_modules();
		add_action( 'enqueue_block_editor_assets', array( 'POS', 'enqueue_assets' ) );
		if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'pos populate', array( 'POS', 'populate_starter_content' ) );
		}
	}

	/**
	 * Populate starter content for all modules.
	 *
	 * @return void
	 */
	public static function populate_starter_content() {
		if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			wp_set_current_user( 1 );
		}
		foreach ( self::$modules as $module ) {
			$module->populate_starter_content();
		}
	}

	public static function fix_versions() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data_version = get_option( 'pos_data_version', false );
		if ( ! $data_version ) {
			// TODO: filter to not do this?
			self::populate_starter_content();
			return;
		}
		$plugin_data = get_plugin_data( __FILE__ );
		self::$version = $plugin_data['Version'];

		if ( version_compare( $data_version, self::$version, '>=' ) ) {
			return;
		}
		foreach ( self::$modules as $module ) {
			$module->fix_old_data( $data_version );
		}
		update_option( 'pos_data_version', self::$version );
	}

	public static function get_module_by_id( $id ) {
		foreach ( self::$modules as $module ) {
			if ( $module->id === $id ) {
				return $module;
			}
		}
		return null;
	}

	public static function admin_menu() {
		add_menu_page( 'Personal OS', 'Personal OS', 'manage_options', 'personalos', false, 'dashicons-admin-generic', 3 );
		add_submenu_page( 'personalos', 'Your Dashboard', 'Dashboard', 'manage_options', 'personalos-settings', array( 'POS', 'admin_page' ), 0 );
		add_submenu_page( 'personalos', 'Notebooks', 'Notebooks', 'manage_options', 'edit-tags.php?taxonomy=notebook&post_type=notes' );
	}
	public static function enqueue_assets() {
		wp_enqueue_script( 'pos' );
		wp_enqueue_style( 'pos' );
	}

	public static function admin_page() {
		require plugin_dir_path( __FILE__ ) . 'dashboard.php';
	}
	public static function load_modules() {
		require_once plugin_dir_path( __FILE__ ) . 'modules/class-pos-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/notes/class-notes-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/readwise/class-readwise.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/evernote/class-evernote-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/todo/class-todo-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/class-openai-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/class-pos-transcription.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/daily/class-daily-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/class-pos-ai-podcast-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/bucketlist/class-bucketlist-module.php';

		// TODO: https://github.com/artpi/PersonalOS/issues/15 Introduce a setting to enable/disable modules. We don't want constructors to be fired when the module is not wanted.
		$todo          = new TODO_Module();
		$notes         = new Notes_Module();
		$openai        = new OpenAI_Module();
		self::$modules = array(
			$notes,
			new Readwise( $notes ),
			new Evernote_Module( $notes ),
			$todo,
			$openai,
			new POS_Transcription( $openai, $notes ),
			new Daily_Module( $notes ),
			new POS_AI_Podcast_Module( $openai ),
			new Bucketlist_Module(),
		);
		self::fix_versions();
		require_once plugin_dir_path( __FILE__ ) . 'class-pos-settings.php';
		$settings = new POS_Settings( self::$modules );
	}
}
add_action( 'init', 'POS::init' );

// For debugging trackbacks:
// add_action( 'pre_ping', function( $data ) {
//     error_log( 'PING: ' . print_r( $data, true ) );
//     return $data;
// }, 10, 1 );
