<?php

class POS_Module {
	public $id             = 'module_id';
	public $name           = 'Module Name';
	public $description    = '';
	public $rest_namespace = 'pos/v1';
	public $settings       = array();

	function get_module_description() {
		return $this->description;
	}

	function get_settings_fields() {
		return $this->settings;
	}

	function get_setting_option_name( $setting_id ) {
		return $this->id . '_' . $setting_id;
	}

	function get_setting( $id ) {
		return get_option( $this->get_setting_option_name( $id ) );
	}

	function __construct() {
		$this->register();
	}

	function register() {

	}

	function register_block( $blockname ) {
		$dir = dirname( __DIR__ ) . "/build/{$this->id}/blocks/{$blockname}/";
		register_block_type( $dir );
	}

	function register_post_type( $args = array() ) {
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

	function meta_auth_callback() {
		return current_user_can( 'edit_posts' );
	}

	function register_meta( $key, $cpt = null ) {
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

	function log( $message, $level = 'DEBUG' ) {
		$map = array(
			E_USER_NOTICE  => 'NOTICE',
			E_USER_WARNING => 'WARNING',
			E_USER_ERROR   => 'ERROR',
		);

		if ( in_array( $level, $map ) ) {
			$level = $map[ $level ];
		} elseif ( ! is_string( $level ) ) {
			$level = 'DEBUG';
		}

        //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[{$level}] [{$this->id}] {$message}" );
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

	function get_sync_hook_name() {
		return 'pos_sync_' . $this->id;
	}

	function register_sync( $interal = 'hourly' ) {
		$hook_name = $this->get_sync_hook_name();
		add_action( $hook_name, array( $this, 'sync' ) );
		if ( ! wp_next_scheduled( $hook_name ) ) {
			wp_schedule_event( time(), $interal, $hook_name );
		}
	}

	public function sync() {
		error_log( 'EMPTY SYNC' );
	}
}

