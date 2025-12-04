<?php
//phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

class POS_Module {
	public $id             = 'module_id';
	public $name           = 'Module Name';
	public $description    = '';
	public $rest_namespace = 'pos/v1';
	public $settings       = array();

	public function get_module_description() {
		return $this->description;
	}

	public function get_module_dir() {
		$reflection = new ReflectionClass( $this );
		return dirname( $reflection->getFileName() );
	}
	/**
	 * Get the documentation for the module.
	 * It will look for a README.md file in the module directory.
	 *
	 * @return string|false
	 */
	public function get_readme() {
		$dir = $this->get_module_dir();
		if ( file_exists( $dir . '/README.md' ) ) {
			$readme = file_get_contents( $dir . '/README.md' );
			// Replace readme urls with module links
			$readme = preg_replace( '/\(\.\.\/([a-z]+)\)/', '(?page=personalos-settings&module=$1)', $readme );
			return $readme;
		}
		return false;
	}

	public function populate_starter_content() {
		$dir = $this->get_module_dir();
		if ( file_exists( $dir . '/starter-content.php' ) ) {
			include $dir . '/starter-content.php';
		}
	}

	public function get_settings_fields() {
		return $this->settings;
	}

	public function fix_old_data( $data_version ) {
	}

	public function get_setting_option_name( $setting_id ) {
		return $this->id . '_' . $setting_id;
	}

	public function get_setting( $id, $user_id = null ) {
		if ( ! isset( $this->settings[ $id ] ) ) {
			return false;
		}

		$setting = $this->settings[ $id ];
		$scope   = isset( $setting['scope'] ) ? $setting['scope'] : 'global';
		$default = isset( $setting['default'] ) ? $setting['default'] : false;

		if ( 'user' === $scope ) {
			$user_id = $user_id ?? get_current_user_id();
			if ( ! $user_id ) {
				return $default;
			}
			$value = get_user_meta( $user_id, $this->get_user_setting_meta_key( $id ), true );
			if ( '' === $value || null === $value ) {
				return $default;
			}
			return $value;
		}

		return get_option( $this->get_setting_option_name( $id ), $default );
	}

	public function __construct() {
		$this->register();
	}

	public function register() {

	}

	public function register_block( $blockname, $args = array() ) {
		$dir = dirname( __DIR__ ) . "/build/{$this->id}/blocks/{$blockname}/";
		if ( ! file_exists( $dir . 'block.json' ) ) {
			return;
		}
		register_block_type( $dir, $args );
	}

	public function register_cli_command( $command, $method ) {
		if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( "pos {$this->id} {$command}", array( $this, $method ) );
		}
	}

	public function register_post_type( $args = array(), $redirect_to_admin = false ) {
		$labels = array(
			'name'          => $this->name,
			'singular_name' => $this->name,
			'add_new'       => 'Add New',
		);
		if ( isset( $args['labels'] ) ) {
			$labels = array_merge( $labels, $args['labels'] );
			unset( $args['labels'] );
		}

		if ( isset( $args['capabilities'] ) ) {
			$args['capabilities'] = array_merge( $this->get_default_capabilities(), $args['capabilities'] );
		} else {
			$args['capabilities'] = $this->get_default_capabilities();
		}

		$defaults = array_merge(
			array(
				'show_in_rest'          => true,
				'public'                => false,
				'show_ui'               => true,
				'has_archive'           => false,
				'show_in_menu'          => 'personalos',
				'publicly_queryable'    => false,
				'rest_controller_class' => 'POS_CPT_Rest_Controller',
				//'show_in_menu' => 'pos',
				'rest_namespace'        => $this->rest_namespace,
				'labels'                => $labels,
				'supports'              => array( 'title', 'excerpt', 'editor', 'custom-fields' ),
				'taxonomies'            => array(),
				'map_meta_cap'          => true,
			),
			$args
		);
		register_post_type( $this->id, $defaults );
		if ( $redirect_to_admin ) {
			add_action( 'template_redirect', array( $this, 'redirect_cpt_to_admin_edit' ) );
		}

		// Filter admin list to show only user's own posts (unless admin)
		add_action( 'pre_get_posts', array( $this, 'filter_admin_posts_list' ) );
	}

	/**
	 * Filter admin post list to show only current user's posts unless they have admin_personalos.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_admin_posts_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== $this->id ) {
			return;
		}

		// If user has admin_personalos, they can see all posts
		if ( current_user_can( 'admin_personalos' ) ) {
			return;
		}

		// Otherwise, only show their own posts
		$query->set( 'author', get_current_user_id() );
	}

	public function redirect_cpt_to_admin_edit() {
		$post = null;

		// We only want 404s to be redirected
		if ( is_singular() && ! is_404() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check query vars first
		if ( isset( $_GET[ $this->id ] ) ) {
			$slug = sanitize_text_field( $_GET[ $this->id ] );
			$post = get_page_by_path( $slug, OBJECT, $this->id );
		} elseif ( isset( $_GET['p'] ) ) {
			$post = get_post( sanitize_text_field( $_GET['p'] ) );
		} else {
			// Check permalink structure
			$request_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

			// Only proceed if we're using pretty permalinks
			if ( get_option( 'permalink_structure' ) ) {
				$path_parts = explode( '/', $request_path );

				// Check if the first part matches our post type
				if ( count( $path_parts ) >= 2 && $path_parts[0] === $this->id ) {
					$slug = sanitize_text_field( $path_parts[1] );
					$post = get_page_by_path( $slug, OBJECT, $this->id );
				}
			}

			if ( ! $post ) {
				return;
			}
		}

		if ( $post->post_type !== $this->id ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$edit_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
		wp_safe_redirect( $edit_url );
		exit;
	}

	public function jetpack_filter_whitelist_cpt_sync_with_dotcom( $types ) {
		$types[] = $this->id;
		return $types;
	}

	/**
	 * Add the custom post type to the Jetpack whitelist for syncing with WordPress.com
	 *
	 * @see https://developer.jetpack.com/hooks/rest_api_allowed_post_types/
	 */
	public function jetpack_whitelist_cpt_with_dotcom() {
		add_filter( 'rest_api_allowed_post_types', array( $this, 'jetpack_filter_whitelist_cpt_sync_with_dotcom' ) );
	}

	public function meta_auth_callback() {
		return current_user_can( 'edit_posts' );
	}

	public function register_meta( $key, $cpt = null ) {
		register_post_meta(
			$cpt ?? $this->id,
			$key,
			array(
				'auth_callback'     => array( $this, 'meta_auth_callback' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			)
		);
	}

	public function log( $message, $level = 'DEBUG' ) {
		$map = array(
			E_USER_NOTICE  => 'NOTICE',
			E_USER_WARNING => 'WARNING',
			E_USER_ERROR   => 'ERROR',
		);

		if ( array_key_exists( $level, $map ) ) {
			$level = $map[ $level ];
		} elseif ( ! is_string( $level ) ) {
			$level = 'DEBUG';
		}

        //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[{$level}] [{$this->id}] {$message}" );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::line( "[{$level}] [{$this->id}] {$message}" );
		}
	}

	protected function get_default_capabilities() {
		return array(
			'edit_posts'             => 'use_personalos',
			'edit_others_posts'      => 'admin_personalos',
			'publish_posts'          => 'use_personalos',
			'read_private_posts'     => 'admin_personalos',
			'delete_posts'           => 'use_personalos',
			'delete_others_posts'    => 'admin_personalos',
			'delete_private_posts'   => 'admin_personalos',
			'delete_published_posts' => 'use_personalos',
			'edit_private_posts'     => 'use_personalos',
			'edit_published_posts'   => 'use_personalos',
		);
	}

	protected function get_user_setting_meta_key( $setting_id ) {
		return 'pos_' . $this->id . '_' . $setting_id;
	}

	/**
	 * Locate a WordPress user by matching a user-scoped token setting.
	 *
	 * @param string $setting_id Token setting identifier.
	 * @param string $token      Token value to match.
	 * @return WP_User|null User when found and authorized, null otherwise.
	 */
	public function find_user_for_setting_token( string $setting_id, string $token ): ?WP_User {
		if ( strlen( $token ) < 3 ) {
			return null;
		}

		global $wpdb;
		$meta_key = $this->get_user_setting_meta_key( $setting_id );
		$user_id  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$meta_key,
				$token
			)
		);

		if ( ! $user_id ) {
			return null;
		}

		$user = get_user_by( 'ID', (int) $user_id );
		if ( ! $user instanceof WP_User ) {
			return null;
		}

		if ( ! user_can( $user, 'use_personalos' ) ) {
			return null;
		}

		return $user;
	}

	protected function get_user_ids_with_setting( $setting_id ) {
		global $wpdb;
		$meta_key = $this->get_user_setting_meta_key( $setting_id );
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''",
				$meta_key
			)
		);

		return array_map( 'intval', $user_ids );
	}

	public function update_setting( $id, $value, $user_id = null ) {
		if ( ! isset( $this->settings[ $id ] ) ) {
			return false;
		}

		$scope = isset( $this->settings[ $id ]['scope'] ) ? $this->settings[ $id ]['scope'] : 'global';
		if ( 'user' === $scope ) {
			$user_id = $user_id ?? get_current_user_id();
			if ( ! $user_id ) {
				return false;
			}
			return update_user_meta( $user_id, $this->get_user_setting_meta_key( $id ), $value );
		}

		return update_option( $this->get_setting_option_name( $id ), $value );
	}
}

class POS_CPT_Rest_Controller extends WP_REST_Posts_Controller {
	public function check_read_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		return current_user_can( 'read_post', $post->ID );
	}
}

class External_Service_Module extends POS_Module {
	public $id   = 'external_service';
	public $name = 'External Service';
	protected $current_sync_user_id = 0;

	public function get_sync_hook_name() {
		return 'pos_sync_' . $this->id;
	}

	public function register_sync( $interval = 'hourly' ) {
		$hook_name = $this->get_sync_hook_name();
		add_action( $hook_name, array( $this, 'sync' ) );
		if ( ! wp_next_scheduled( $hook_name ) ) {
			wp_schedule_event( time(), $interval, $hook_name );
		}
	}

	public function sync() {
		$this->log( 'EMPTY SYNC' );
	}

	protected function run_for_user( int $user_id, callable $callback ) {
		$previous_user_id      = get_current_user_id();
		$previous_sync_user_id = $this->current_sync_user_id;

		wp_set_current_user( $user_id );
		$this->current_sync_user_id = $user_id;
		$this->reset_user_context();

		try {
			return $callback( $user_id );
		} finally {
			$this->reset_user_context();
			$this->current_sync_user_id = $previous_sync_user_id;
			wp_set_current_user( $previous_user_id );
		}
	}

	protected function reset_user_context() {
		// Allow subclasses to reset cached data between user runs.
	}

	protected function get_user_state_meta_key( string $key ): string {
		return 'pos_' . $this->id . '_state_' . $key;
	}

	protected function get_user_state( string $key, $default = null, ?int $user_id = null ) {
		$user_id = $user_id ?? $this->current_sync_user_id;
		if ( ! $user_id ) {
			return $default;
		}

		$value = get_user_meta( $user_id, $this->get_user_state_meta_key( $key ), true );
		if ( '' === $value || null === $value ) {
			return $default;
		}

		return $value;
	}

	protected function set_user_state( string $key, $value, ?int $user_id = null ) {
		$user_id = $user_id ?? $this->current_sync_user_id;
		if ( ! $user_id ) {
			return false;
		}

		if ( null === $value ) {
			return delete_user_meta( $user_id, $this->get_user_state_meta_key( $key ) );
		}

		return update_user_meta( $user_id, $this->get_user_state_meta_key( $key ), $value );
	}

	protected function delete_user_state( string $key, ?int $user_id = null ) {
		$user_id = $user_id ?? $this->current_sync_user_id;
		if ( ! $user_id ) {
			return false;
		}

		return delete_user_meta( $user_id, $this->get_user_state_meta_key( $key ) );
	}
}

