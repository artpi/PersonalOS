<?php
/**
 * Personal Stuff's native WordPress integration.
 *
 * @package PersonalStuff
 */

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- This standalone package has its own text domain.

/**
 * Provision vocabulary, launch the app, and protect new upload filenames.
 */
class Personal_Stuff_Plugin extends PersonalOS_Plugin_Base {

	/** @var int Verified parent of the current Media REST upload. */
	private $upload_post = 0;

	/** Configure the bundled app helper. */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-stuff',
				'display_name' => 'Personal Stuff',
				'text_domain'  => 'personal-stuff',
				'version'      => PERSONAL_STUFF_VERSION,
				'plugin_file'  => PERSONAL_STUFF_FILE,
				'app'          => array(
					'path'       => 'stuff',
					'name'       => 'Stuff',
					'capability' => 'edit_posts',
					'icon'       => 'dashicons-archive',
				),
			)
		);
	}

	/** Register only native integrations; no package REST or storage. */
	public function register() {
		$this->vocabulary()->register_type_labels();
		$this->vocabulary()->register_rest_descriptions();
		$this->register_missing_knowledge_notice();
		$this->register_wp_app( array( $this, 'render_app' ) );
		add_action( 'template_redirect', array( $this, 'redirect_item_link' ), 5 );
		add_filter( 'post_type_link', array( $this, 'filter_item_permalink' ), 10, 2 );
		add_filter( 'get_shortlink', array( $this, 'filter_item_shortlink' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		// Recheck actual terms when Knowledge becomes available; no installed flag.
		$this->ensure_terms();
		if ( $this->knowledge()->is_available() ) {
			get_post_type_object( 'wp_knowledge' )->show_ui = true;
			get_taxonomy( 'wp_knowledge_type' )->show_ui = true;
		}
		add_filter( 'use_block_editor_for_post', array( $this, 'use_item_editor' ), 20, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_item_editor' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'begin_upload' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'end_upload' ), 10, 3 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'randomize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'randomize_upload' ) );
	}

	/**
	 * Ensure only fixed routing terms. Preserve names and user-created branches.
	 *
	 * @return array|WP_Error Resolved term IDs or the native insertion error.
	 */
	public function ensure_terms() {
		if ( ! taxonomy_exists( 'wp_knowledge_type' ) ) {
			return array();
		}
		$ids = array();
		foreach ( array(
			'artifact'     => array(
				'name'   => 'Artifact',
				'parent' => '',
			),
			'stuff'        => array(
				'name'   => 'Stuff',
				'parent' => '',
			),
			'stuff-item'   => array(
				'name'   => 'Stuff item',
				'parent' => 'stuff',
			),
			'stuff-places' => array(
				'name'   => 'Places',
				'parent' => 'stuff',
			),
			'stuff-tags'   => array(
				'name'   => 'Stuff tags',
				'parent' => 'stuff',
			),
		) as $slug => $config ) {
			$parent = $config['parent'] ? $ids[ $config['parent'] ] : 0;
			$term = get_term_by( 'slug', $slug, 'wp_knowledge_type' );
			if ( ! $term ) {
				$result = wp_insert_term(
					$config['name'],
					'wp_knowledge_type',
					array(
						'slug'   => $slug,
						'parent' => $parent,
					)
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$ids[ $slug ] = (int) $result['term_id'];
			} else {
				$ids[ $slug ] = (int) $term->term_id;
				if ( 'artifact' !== $slug && $parent !== (int) $term->parent ) {
					$result = wp_update_term( $term->term_id, 'wp_knowledge_type', array( 'parent' => $parent ) );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}
			}
		}
		return $ids;
	}

	/**
	 * Recognize items by canonical post type and subtype slug.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_item( $post_id ) {
		return 'wp_knowledge' === get_post_type( $post_id ) && has_term( 'stuff-item', 'wp_knowledge_type', $post_id );
	}

	/**
	 * Return the Stuff app URL for an item.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when the post is not a Stuff item.
	 */
	public function get_item_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! $this->is_item( $post->ID ) ) {
			return '';
		}

		return add_query_arg( 'item', $post->ID, home_url( '/stuff/' ) );
	}

	/**
	 * Point native Knowledge permalinks at the owning Stuff interface.
	 *
	 * @param string  $url  Existing permalink.
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function filter_item_permalink( $url, $post ) {
		$item_url = $post instanceof WP_Post ? $this->get_item_url( $post->ID ) : '';

		return $item_url ? $item_url : $url;
	}

	/**
	 * Point native shortlinks at the owning Stuff interface.
	 *
	 * @param string $shortlink Existing shortlink.
	 * @param int    $post_id   Post ID.
	 * @return string
	 */
	public function filter_item_shortlink( $shortlink, $post_id ) {
		$item_url = $this->get_item_url( $post_id );

		return $item_url ? $item_url : $shortlink;
	}

	/** Redirect an editable legacy ?p= link to the Stuff item interface. */
	public function redirect_item_link() {
		$post_id = absint( get_query_var( 'p' ) );
		$item_url = $this->get_item_url( $post_id );
		if ( get_query_var( 'preview' ) || ! $item_url || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_safe_redirect( $item_url );
		exit;
	}

	/**
	 * Keep empty Stuff items in Gutenberg even with Personal Notes active.
	 *
	 * @param bool    $use  Existing editor decision.
	 * @param WP_Post $post Edited post.
	 * @return bool
	 */
	public function use_item_editor( $use, $post ) {
		return $this->is_item( $post->ID ) ? use_block_editor_for_post_type( $post->post_type ) : $use;
	}

	/** Add the wp-admin launcher. */
	public function add_admin_menu() {
		$this->add_app_admin_page_hook( add_menu_page( __( 'Stuff', 'personal-stuff' ), __( 'Stuff', 'personal-stuff' ), 'edit_posts', 'personal-stuff', array( $this, 'render_app' ), 'dashicons-archive', 4 ) );
	}

	/** Render the app mount. */
	public function render_app() {
		if ( ! $this->knowledge()->is_available() ) {
			echo '<p>' . esc_html__( 'Knowledge is not available yet.', 'personal-stuff' ) . '</p>';
			return;
		}
		echo '<div id="personal-stuff-app"></div>';
	}

	/** Enqueue the JS app with existing core endpoint discovery. */
	public function enqueue_package_assets() {
		parent::enqueue_package_assets();
		if ( ! $this->knowledge()->is_available() ) {
			return;
		}
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_media();
		$taxonomy = get_taxonomy( 'wp_knowledge_type' );
		wp_localize_script(
			$this->script_handle(),
			'personalStuffSettings',
			array(
				'knowledgeRestPath' => rest_get_route_for_post_type_items( 'wp_knowledge' ),
				'taxonomyRestPath'  => rest_get_route_for_taxonomy_items( 'wp_knowledge_type' ),
				'taxonomyField'     => $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name,
				'editPostUrl'       => admin_url( 'post.php' ),
				'canManageTerms'    => current_user_can( $taxonomy->cap->manage_terms ),
				'canUpload'         => current_user_can( 'upload_files' ),
				'skillUrl'          => plugins_url( 'skills/personal-stuff/SKILL.md', PERSONAL_STUFF_FILE ),
			)
		);
	}

	/** Make Gutenberg's Media requests identify the authorized item. */
	public function enqueue_item_editor() {
		$post = get_post();
		if ( ! $post || ! $this->is_item( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		PersonalOS_Assets_Helper::register_script( 'personal-stuff-editor', PERSONAL_STUFF_FILE, 'build/editor.js' );
		wp_localize_script( 'personal-stuff-editor', 'personalStuffEditor', array( 'postId' => $post->ID ) );
		wp_enqueue_script( 'personal-stuff-editor' );
	}

	/**
	 * Scope filename filtering to the current authenticated Media create request.
	 *
	 * @param mixed           $response Response.
	 * @param array           $handler  Core handler.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function begin_upload( $response, $handler, $request ) {
		if ( '/wp/v2/media' === untrailingslashit( $request->get_route() ) && 'POST' === $request->get_method() ) {
			$this->upload_post = (int) $request->get_param( 'post' );
		}
		return $response;
	}

	/**
	 * Clear request-local context, including failed uploads.
	 *
	 * @param mixed           $response Response.
	 * @param array           $handler  Core handler.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function end_upload( $response, $handler, $request ) {
		if ( '/wp/v2/media' === untrailingslashit( $request->get_route() ) ) {
			$this->upload_post = 0;
		}
		return $response;
	}

	/**
	 * Randomize before WordPress moves a file into publicly accessible uploads.
	 *
	 * @param array $file Native upload data.
	 * @return array
	 */
	public function randomize_upload( $file ) {
		// Core async-upload verifies its nonce and upload capability before this hook.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_id = $this->upload_post ? $this->upload_post : absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! $this->is_item( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			return $file;
		}
		$type = wp_check_filetype( $file['name'], get_allowed_mime_types() );
		if ( ! $type['ext'] || ! $type['type'] ) {
			$file['error'] = __( 'This file type is not allowed.', 'personal-stuff' );
			return $file;
		}
		try {
			$file['name'] = bin2hex( random_bytes( 16 ) ) . '.' . strtolower( $type['ext'] );
		} catch ( Exception $error ) {
			$file['error'] = __( 'Could not generate a safe upload filename.', 'personal-stuff' );
		}
		return $file;
	}
}
