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

	/**
	 * Get the documentation for the module.
	 * It will look for a README.md file in the module directory.
	 *
	 * @return string|false
	 */
	public function get_readme() {
		$reflection = new ReflectionClass( $this );
		$dir = dirname( $reflection->getFileName() );
		if ( file_exists( $dir . '/README.md' ) ) {
			$readme = file_get_contents( $dir . '/README.md' );
			// Replace readme urls with module links
			$readme = preg_replace( '/\(\.\.\/([a-z]+)\)/', '(?page=personalos-settings&module=$1)', $readme );
			return $readme;
		}
		return false;
	}

	public function get_settings_fields() {
		return $this->settings;
	}

	public function fix_old_data( $data_version ) {
	}

	public function get_setting_option_name( $setting_id ) {
		return $this->id . '_' . $setting_id;
	}

	public function get_setting( $id ) {
		$default = isset( $this->settings[ $id ]['default'] ) ? $this->settings[ $id ]['default'] : false;
		return get_option( $this->get_setting_option_name( $id ), $default );
	}

	public function __construct() {
		$this->register();
	}

	public function register() {

	}

	public function register_block( $blockname, $args = array() ) {
		$dir = dirname( __DIR__ ) . "/build/{$this->id}/blocks/{$blockname}/";
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
			),
			$args
		);
		register_post_type( $this->id, $defaults );
		if ( $redirect_to_admin ) {
			add_action( 'template_redirect', array( $this, 'redirect_cpt_to_admin_edit' ) );
		}
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

		if ( in_array( $level, $map, true ) ) {
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
}

