<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

/**
 * Plugin Name:     Personal OS
 * Description:     Manage your life.
 * Version:         0.3.0
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
	public static $version = '0.3.0';

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

		// Register ability category before modules register abilities
		add_action( 'wp_abilities_api_categories_init', array( 'POS', 'register_ability_category' ) );

		self::load_modules();
		add_filter( 'map_meta_cap', array( 'POS', 'map_meta_cap' ), 10, 4 );
		add_action( 'enqueue_block_editor_assets', array( 'POS', 'enqueue_assets' ) );
		if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'pos populate', array( 'POS', 'populate_starter_content' ) );
		}
	}

	/**
	 * Register the PersonalOS ability category.
	 */
	public static function register_ability_category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'personalos',
				array(
					'label'       => __( 'PersonalOS', 'personalos' ),
					'description' => __( 'Abilities provided by PersonalOS plugin', 'personalos' ),
				)
			);
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
		self::ensure_capabilities();
		$data_version = get_option( 'pos_data_version', false );
		$plugin_data = get_plugin_data( __FILE__ );
		self::$version = $plugin_data['Version'];

		if ( ! $data_version ) {
			// TODO: filter to not do this?
			self::populate_starter_content();
			update_option( 'pos_data_version', self::$version );
			return;
		} elseif ( version_compare( $data_version, self::$version, '>=' ) ) {
			return;
		}
		foreach ( self::$modules as $module ) {
			$module->fix_old_data( $data_version );
		}
		update_option( 'pos_data_version', self::$version );
	}

	/**
	 * Ensure PersonalOS capabilities are assigned to proper roles.
	 */
	public static function ensure_capabilities() {
		$role_caps = array(
			'editor'        => array( 'use_personalos' ),
			'administrator' => array( 'use_personalos', 'admin_personalos' ),
		);

		foreach ( $role_caps as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
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
		add_menu_page( 'Personal OS', 'Personal OS', 'use_personalos', 'personalos', false, 'dashicons-admin-generic', 3 );
		add_submenu_page( 'personalos', 'Your Dashboard', 'Dashboard', 'use_personalos', 'personalos-settings', array( 'POS', 'admin_page' ), 0 );
		add_submenu_page( 'personalos', 'Notebooks', 'Notebooks', 'use_personalos', 'edit-tags.php?taxonomy=notebook&post_type=notes' );
	}
	public static function enqueue_assets() {
		wp_enqueue_script( 'pos' );
		wp_enqueue_style( 'pos' );
	}

	public static function admin_page() {
		require plugin_dir_path( __FILE__ ) . 'dashboard.php';
	}
	/**
	 * Return the list of PersonalOS post types that require special permission handling.
	 *
	 * @return array
	 */
	public static function get_personal_post_types() {
		$types = array( 'notes', 'todo' );
		return apply_filters( 'pos_personal_post_types', $types );
	}

	/**
	 * Map meta capabilities for PersonalOS post types.
	 *
	 * @param string[] $caps    Primitive capabilities that the user must have.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param mixed[]  $args    Optional additional args. For post caps, includes the post ID.
	 *
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		$handled_caps = array( 'edit_post', 'delete_post', 'read_post' );
		if ( ! in_array( $cap, $handled_caps, true ) ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $post_id ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::get_personal_post_types(), true ) ) {
			return $caps;
		}

		$is_owner = (int) $post->post_author === (int) $user_id;

		if ( 'read_post' === $cap ) {
			if ( $is_owner ) {
				return array( 'use_personalos' );
			}

			if ( 'private' !== $post->post_status ) {
				return array( 'use_personalos' );
			}

			return array( 'admin_personalos' );
		}

		if ( $is_owner ) {
			return array( 'use_personalos' );
		}

		return array( 'admin_personalos' );
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
		require_once plugin_dir_path( __FILE__ ) . 'modules/openai/class-elevenlabs-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/bucketlist/class-bucketlist-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/slack/class-slack-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/perplexity/class.perplexity-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/todo/class-ics-module.php';
		require_once plugin_dir_path( __FILE__ ) . 'modules/imap/class-imap-module.php';

		// TODO: https://github.com/artpi/PersonalOS/issues/15 Introduce a setting to enable/disable modules. We don't want constructors to be fired when the module is not wanted.
		$todo          = new TODO_Module();
		$notes         = new Notes_Module();
		$openai        = new OpenAI_Module();
		$elevenlabs    = new ElevenLabs_Module();
		self::$modules = array(
			$notes,
			new Readwise( $notes ),
			new Evernote_Module( $notes ),
			$todo,
			$openai,
			new POS_Transcription( $openai, $notes ),
			new Daily_Module( $notes ),
			$elevenlabs,
			new POS_AI_Podcast_Module( $openai, $elevenlabs ),
			new Bucketlist_Module(),
			new Slack_Module(),
			new Perplexity_Module(),
			new ICS_Module(),
			new IMAP_Module(),
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
