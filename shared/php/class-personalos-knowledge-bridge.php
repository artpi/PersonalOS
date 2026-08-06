<?php
/**
 * Shared Knowledge runtime bridge.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Knowledge_Bridge' ) ) {
	/**
	 * Resolves the shared Knowledge runtime.
	 */
	class PersonalOS_Knowledge_Bridge {
		/**
		 * Cached post type.
		 *
		 * @var string|null
		 */
		private $post_type = null;

		/**
		 * Cached type taxonomy.
		 *
		 * @var string|null
		 */
		private $type_taxonomy = null;

		/**
		 * Package slug.
		 *
		 * @var string
		 */
		private $package_slug;

		/**
		 * Constructor.
		 *
		 * @param string $package_slug Package slug.
		 */
		public function __construct( $package_slug = 'personalos' ) {
			$this->package_slug = sanitize_key( $package_slug );
		}

		/**
		 * Returns whether the Knowledge runtime is available.
		 *
		 * @return bool
		 */
		public function is_available() {
			return '' !== $this->post_type() && '' !== $this->type_taxonomy();
		}

		/**
		 * Resolve the Knowledge post type.
		 *
		 * @return string
		 */
		public function post_type() {
			if ( null !== $this->post_type ) {
				return $this->post_type;
			}

			$this->post_type = post_type_exists( 'wp_knowledge' ) ? 'wp_knowledge' : '';
			return $this->post_type;
		}

		/**
		 * Resolve the Knowledge type taxonomy.
		 *
		 * @return string
		 */
		public function type_taxonomy() {
			if ( null !== $this->type_taxonomy ) {
				return $this->type_taxonomy;
			}

			$this->type_taxonomy = taxonomy_exists( 'wp_knowledge_type' ) ? 'wp_knowledge_type' : '';
			return $this->type_taxonomy;
		}

		/**
		 * Resolve the REST base for the active Knowledge post type.
		 *
		 * @return string
		 */
		public function rest_base() {
			$post_type = $this->post_type();

			if ( '' === $post_type ) {
				return '';
			}

			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && ! empty( $post_type_object->rest_base ) ) {
				return $post_type_object->rest_base;
			}

			return 'knowledge';
		}

		/**
		 * Resolve the source/provenance meta key.
		 *
		 * @return string
		 */
		public function source_meta_key() {
			if ( $this->is_registered_post_meta( 'knowledge_source' ) ) {
				return 'knowledge_source';
			}

			return '_personalos_source';
		}

		/**
		 * Registers post meta against the resolved Knowledge post type.
		 *
		 * @param string $key  Meta key.
		 * @param array  $args Meta registration args.
		 * @return bool
		 */
		public function register_post_meta( $key, $args = array() ) {
			$post_type = $this->post_type();

			if ( '' === $post_type ) {
				return false;
			}

			$args = wp_parse_args(
				$args,
				array(
					'object_subtype'    => $post_type,
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => array( $this, 'meta_auth_callback' ),
				)
			);

			$args['object_subtype'] = $post_type;

			return register_post_meta( $post_type, $key, $args );
		}

		/**
		 * Registers term meta against the resolved Knowledge type taxonomy.
		 *
		 * @param string $key  Meta key.
		 * @param array  $args Meta registration args.
		 * @return bool
		 */
		public function register_type_meta( $key, $args = array() ) {
			$taxonomy = $this->type_taxonomy();

			if ( '' === $taxonomy ) {
				return false;
			}

			$args = wp_parse_args(
				$args,
				array(
					'object_subtype'    => $taxonomy,
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => array( $this, 'term_meta_auth_callback' ),
				)
			);

			$args['object_subtype'] = $taxonomy;

			return register_term_meta( $taxonomy, $key, $args );
		}

		/**
		 * Default post-meta auth callback.
		 *
		 * @param bool   $allowed  Whether access is allowed.
		 * @param string $meta_key Meta key.
		 * @param int    $post_id  Post ID.
		 * @return bool
		 */
		public function meta_auth_callback( $allowed, $meta_key, $post_id ) {
			unset( $allowed, $meta_key );
			return current_user_can( 'edit_post', $post_id );
		}

		/**
		 * Default term-meta auth callback.
		 *
		 * @return bool
		 */
		public function term_meta_auth_callback() {
			return current_user_can( 'manage_categories' ) || current_user_can( 'manage_options' );
		}

		/**
		 * Checks whether a post-meta key is registered for the active object subtype.
		 *
		 * @param string $key Meta key.
		 * @return bool
		 */
		private function is_registered_post_meta( $key ) {
			$post_type = $this->post_type();

			if ( '' === $post_type || ! function_exists( 'get_registered_meta_keys' ) ) {
				return false;
			}

			$registered = get_registered_meta_keys( 'post', $post_type );

			return isset( $registered[ $key ] );
		}

		/**
		 * Clears cached runtime resolution.
		 *
		 * Tests use this after registering fake post types and taxonomies.
		 *
		 * @return void
		 */
		public function reset_cache() {
			$this->post_type     = null;
			$this->type_taxonomy = null;
		}
	}
}
