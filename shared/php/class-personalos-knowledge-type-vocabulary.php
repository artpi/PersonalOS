<?php
/**
 * Shared Knowledge type vocabulary and hierarchy.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Knowledge_Type_Vocabulary' ) ) {
	/**
	 * Owns Personal shared Knowledge type terms.
	 */
	class PersonalOS_Knowledge_Type_Vocabulary {
		/**
		 * Whether the core Knowledge Type REST response filter is registered.
		 *
		 * @var bool
		 */
		private static $rest_descriptions_registered = false;

		/**
		 * Knowledge bridge.
		 *
		 * @var PersonalOS_Knowledge_Bridge
		 */
		private $bridge;

		/**
		 * Constructor.
		 *
		 * @param PersonalOS_Knowledge_Bridge $bridge Knowledge bridge.
		 */
		public function __construct( PersonalOS_Knowledge_Bridge $bridge ) {
			$this->bridge = $bridge;
		}

		/**
		 * Register label filters for known runtime APIs.
		 *
		 * @return void
		 */
		public function register_type_labels() {
			add_filter( 'wp_knowledge_types', array( $this, 'filter_type_labels' ) );
		}

		/**
		 * Register derived descriptions on core Knowledge Type REST responses.
		 *
		 * @return void
		 */
		public function register_rest_descriptions() {
			if ( ! self::$rest_descriptions_registered ) {
				add_filter( 'rest_prepare_wp_knowledge_type', array( $this, 'add_rest_term_description' ), 10, 3 );
				self::$rest_descriptions_registered = true;
			}
		}

		/**
		 * Describe otherwise empty Knowledge Type terms in core REST responses.
		 *
		 * Native term descriptions remain user-authored content. The response-only
		 * fallback lets clients that cannot resolve the full term hierarchy identify
		 * structural terms and Personal Stuff places from a single paginated row.
		 *
		 * @param WP_REST_Response $response REST response.
		 * @param WP_Term          $term     Prepared Knowledge Type term.
		 * @param WP_REST_Request  $request  REST request.
		 * @return WP_REST_Response
		 */
		public function add_rest_term_description( WP_REST_Response $response, WP_Term $term, WP_REST_Request $request ) {
			if ( '' !== trim( $term->description ) ) {
				return $response;
			}

			$data                = $response->get_data();
			$data['description'] = $this->rest_term_description( $term );
			$response->set_data( $data );

			return $response;
		}

		/**
		 * Build a derived description for a Knowledge Type REST row.
		 *
		 * @param WP_Term $term Knowledge Type term.
		 * @return string
		 */
		private function rest_term_description( WP_Term $term ) {
			$description = $this->rest_term_role_description( $term );
			$path        = $this->rest_term_path( $term );

			return sprintf(
				/* translators: 1: Knowledge Type role, 2: hierarchical term path. */
				__( '%1$s Path: %2$s', 'personalos' ),
				$description,
				implode( ' › ', $path )
			);
		}

		/**
		 * Get the derived role description for a Knowledge Type term.
		 *
		 * @param WP_Term $term Knowledge Type term.
		 * @return string
		 */
		private function rest_term_role_description( WP_Term $term ) {
			$path_slugs = $this->rest_term_path_slugs( $term );

			if ( in_array( 'stuff-places', $path_slugs, true ) ) {
				return __( 'Personal Stuff place.', 'personalos' );
			}

			if ( in_array( 'stuff-tags', $path_slugs, true ) ) {
				return __( 'Personal Stuff tag.', 'personalos' );
			}

			$descriptions = array(
				'artifact'     => __( 'Knowledge artifact container.', 'personalos' ),
				'note'         => __( 'Personal Notes type.', 'personalos' ),
				'daily-note'   => __( 'Personal Notes daily-note type.', 'personalos' ),
				'todo'         => __( 'Personal TODO type.', 'personalos' ),
				'conversation' => __( 'Personal AI Chat conversation type.', 'personalos' ),
				'memory'       => __( 'Knowledge memory type.', 'personalos' ),
				'skill'        => __( 'Knowledge skill type.', 'personalos' ),
				'source'       => __( 'Knowledge source container.', 'personalos' ),
				'manual'       => __( 'Manually created Knowledge source.', 'personalos' ),
				'synced'       => __( 'Synced Knowledge source.', 'personalos' ),
				'readwise'     => __( 'Readwise Knowledge source.', 'personalos' ),
				'evernote'     => __( 'Evernote Knowledge source.', 'personalos' ),
				'ai-chat'      => __( 'Personal AI Chat Knowledge source.', 'personalos' ),
				'personalos'   => __( 'PersonalOS Knowledge source.', 'personalos' ),
				'status'       => __( 'Knowledge status container.', 'personalos' ),
				'inbox'        => __( 'Knowledge inbox status.', 'personalos' ),
				'now'          => __( 'Knowledge current-status type.', 'personalos' ),
				'later'        => __( 'Knowledge later-status type.', 'personalos' ),
				'follow-up'    => __( 'Knowledge follow-up status.', 'personalos' ),
				'project'      => __( 'PARA projects container.', 'personalos' ),
				'area'         => __( 'PARA areas container.', 'personalos' ),
				'resource'     => __( 'PARA resources container.', 'personalos' ),
				'reference'    => __( 'PARA reference type.', 'personalos' ),
				'archive'      => __( 'PARA archive container.', 'personalos' ),
				'starred'      => __( 'Knowledge starred marker.', 'personalos' ),
				'stuff'        => __( 'Personal Stuff container.', 'personalos' ),
				'stuff-item'   => __( 'Personal Stuff item type.', 'personalos' ),
				'stuff-places' => __( 'Personal Stuff places container.', 'personalos' ),
				'stuff-tags'   => __( 'Personal Stuff tags container.', 'personalos' ),
			);

			return isset( $descriptions[ $term->slug ] ) ? $descriptions[ $term->slug ] : __( 'Knowledge Type.', 'personalos' );
		}

		/**
		 * Get the term path formatted with names and IDs.
		 *
		 * @param WP_Term $term Knowledge Type term.
		 * @return string[]
		 */
		private function rest_term_path( WP_Term $term ) {
			$terms = $this->rest_term_path_terms( $term );

			return array_map(
				function ( $path_term ) {
					return sprintf(
						/* translators: 1: Knowledge Type name, 2: Knowledge Type ID. */
						__( '%1$s (%2$d)', 'personalos' ),
						$path_term->name,
						$path_term->term_id
					);
				},
				$terms
			);
		}

		/**
		 * Get the term slugs in its root-to-leaf path.
		 *
		 * @param WP_Term $term Knowledge Type term.
		 * @return string[]
		 */
		private function rest_term_path_slugs( WP_Term $term ) {
			return array_map(
				function ( $path_term ) {
					return $path_term->slug;
				},
				$this->rest_term_path_terms( $term )
			);
		}

		/**
		 * Get a term's root-to-leaf path without following malformed cycles.
		 *
		 * @param WP_Term $term Knowledge Type term.
		 * @return WP_Term[]
		 */
		private function rest_term_path_terms( WP_Term $term ) {
			$terms     = array( $term );
			$seen_ids  = array( (int) $term->term_id );
			$parent_id = (int) $term->parent;

			while ( $parent_id && ! in_array( $parent_id, $seen_ids, true ) ) {
				$parent = get_term( $parent_id, $this->bridge->type_taxonomy() );
				if ( ! $parent || is_wp_error( $parent ) ) {
					break;
				}

				array_unshift( $terms, $parent );
				$seen_ids[] = (int) $parent->term_id;
				$parent_id  = (int) $parent->parent;
			}

			return $terms;
		}

		/**
		 * Adds labels for PersonalOS terms.
		 *
		 * @param array $types Existing type labels.
		 * @return array
		 */
		public function filter_type_labels( $types ) {
			foreach ( $this->terms() as $slug => $term ) {
				$types[ $slug ] = array(
					'title' => $term['label'],
				);
			}

			return $types;
		}

		/**
		 * Ensure all or selected shared terms exist and are normalized.
		 *
		 * @param string[] $slugs Optional term slugs.
		 * @return array|WP_Error Slug to term ID map.
		 */
		public function ensure_terms( $slugs = array() ) {
			if ( ! $this->bridge->is_available() ) {
				return new WP_Error( 'personalos_missing_knowledge', __( 'Knowledge is not available.', 'personalos' ) );
			}

			$terms = array();
			if ( empty( $slugs ) ) {
				$slugs = array_keys( $this->terms() );
			}

			foreach ( $slugs as $slug ) {
				$term_id = $this->ensure_term( $slug );

				if ( is_wp_error( $term_id ) ) {
					return $term_id;
				}

				$terms[ $slug ] = $term_id;
			}

			return $terms;
		}

		/**
		 * Resolve assignable term IDs, rejecting container-only slugs.
		 *
		 * @param array $slugs_or_ids Term slugs or IDs.
		 * @return int[]|WP_Error
		 */
		public function resolve_assignable_term_ids( $slugs_or_ids ) {
			$term_ids = array();

			foreach ( array_unique( array_filter( (array) $slugs_or_ids ) ) as $term_slug_or_id ) {
				if ( is_numeric( $term_slug_or_id ) ) {
					$term = get_term( (int) $term_slug_or_id, $this->bridge->type_taxonomy() );

					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}

					$slug = $term->slug;
				} else {
					$slug = sanitize_title( $term_slug_or_id );
					$term = null;
				}

				if ( $this->is_container_only( $slug ) ) {
					return new WP_Error(
						'personalos_container_term_not_assignable',
						sprintf(
							/* translators: %s: term slug */
							__( 'The %s Knowledge term is a container and cannot be assigned directly.', 'personalos' ),
							$slug
						)
					);
				}

				if ( ! $term ) {
					$term = get_term_by( 'slug', $slug, $this->bridge->type_taxonomy() );

					if ( $term && ! is_wp_error( $term ) ) {
						$term_id = (int) $term->term_id;
					} else {
						$term_id = $this->ensure_term( $slug );

						if ( is_wp_error( $term_id ) ) {
							return $term_id;
						}
					}
				} else {
					$term_id = (int) $term->term_id;
				}

				$term_ids[] = $term_id;
			}

			return array_values( array_unique( array_map( 'intval', $term_ids ) ) );
		}

		/**
		 * Expand parent/container filters to child term IDs.
		 *
		 * @param array $slugs_or_ids Slugs or IDs.
		 * @return int[]|WP_Error
		 */
		public function expand_terms_for_query( $slugs_or_ids ) {
			$taxonomy = $this->bridge->type_taxonomy();
			$term_ids = array();

			foreach ( array_unique( array_filter( (array) $slugs_or_ids ) ) as $term_slug_or_id ) {
				if ( is_numeric( $term_slug_or_id ) ) {
					$term = get_term( (int) $term_slug_or_id, $taxonomy );
				} else {
					$term_id = $this->ensure_term( sanitize_title( $term_slug_or_id ) );
					if ( is_wp_error( $term_id ) ) {
						return $term_id;
					}
					$term = get_term( $term_id, $taxonomy );
				}

				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				if ( $this->is_container_only( $term->slug ) ) {
					$children = get_term_children( $term->term_id, $taxonomy );
					if ( ! is_wp_error( $children ) ) {
						$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
					}
					continue;
				}

				$term_ids[] = (int) $term->term_id;
			}

			return array_values( array_unique( $term_ids ) );
		}

		/**
		 * Get assignable terms for UI pickers.
		 *
		 * @return WP_Term[]
		 */
		public function get_assignable_terms() {
			$terms = get_terms(
				array(
					'taxonomy'   => $this->bridge->type_taxonomy(),
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return array();
			}

			return array_values(
				array_filter(
					$terms,
					function ( $term ) {
						return ! $this->is_container_only( $term->slug );
					}
				)
			);
		}

		/**
		 * Ensure a custom child term below a known container.
		 *
		 * @param string $name        Term name.
		 * @param string $slug        Term slug.
		 * @param string $parent_slug Parent slug.
		 * @return int|WP_Error
		 */
		public function ensure_child_term( $name, $slug, $parent_slug ) {
			if ( ! in_array( $parent_slug, array( 'project', 'area', 'resource', 'archive' ), true ) ) {
				return new WP_Error( 'personalos_invalid_parent_term', __( 'Custom Knowledge terms must live under a supported PARA container.', 'personalos' ) );
			}

			$taxonomy  = $this->bridge->type_taxonomy();
			$parent_id = $this->ensure_term( $parent_slug );

			if ( is_wp_error( $parent_id ) ) {
				return $parent_id;
			}

			$slug     = sanitize_title( $slug );
			$existing = term_exists( $slug, $taxonomy );

			if ( ! $existing ) {
				$inserted = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'slug'   => $slug,
						'parent' => $parent_id,
					)
				);

				if ( is_wp_error( $inserted ) ) {
					return $inserted;
				}

				return (int) $inserted['term_id'];
			}

			$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$term    = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) && (int) $term->parent !== (int) $parent_id ) {
				$updated = wp_update_term( $term_id, $taxonomy, array( 'parent' => $parent_id ) );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			return $term_id;
		}

		/**
		 * Is this slug a container-only PARA term?
		 *
		 * @param string $slug Term slug.
		 * @return bool
		 */
		public function is_container_only( $slug ) {
			return in_array( $slug, $this->container_slugs(), true );
		}

		/**
		 * Container-only slugs.
		 *
		 * @return string[]
		 */
		public function container_slugs() {
			return array( 'status', 'project', 'area', 'resource', 'archive' );
		}

		/**
		 * Ensure one term and its parent exist.
		 *
		 * @param string $slug Term slug.
		 * @return int|WP_Error
		 */
		private function ensure_term( $slug ) {
			$terms = $this->terms();

			if ( ! isset( $terms[ $slug ] ) ) {
				return new WP_Error(
					'personalos_unknown_knowledge_term',
					sprintf(
						/* translators: %s: term slug */
						__( 'Unknown Knowledge term: %s.', 'personalos' ),
						$slug
					)
				);
			}

			$taxonomy = $this->bridge->type_taxonomy();
			$term     = $terms[ $slug ];
			$parent   = 0;

			if ( ! empty( $term['parent'] ) ) {
				$parent = $this->ensure_term( $term['parent'] );
				if ( is_wp_error( $parent ) ) {
					return $parent;
				}
			}

			$existing = term_exists( $slug, $taxonomy );
			if ( ! $existing ) {
				$inserted = wp_insert_term(
					$term['label'],
					$taxonomy,
					array(
						'slug'   => $slug,
						'parent' => $parent,
					)
				);

				if ( is_wp_error( $inserted ) ) {
					return $inserted;
				}

				return (int) $inserted['term_id'];
			}

			$term_id     = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$term_object = get_term( $term_id, $taxonomy );

			if ( ! $term_object || is_wp_error( $term_object ) ) {
				return $term_id;
			}

			$update = array();
			if ( (int) $term_object->parent !== (int) $parent ) {
				$update['parent'] = $parent;
			}
			if ( $term_object->name !== $term['label'] ) {
				$update['name'] = $term['label'];
			}

			if ( ! empty( $update ) ) {
				$updated = wp_update_term( $term_id, $taxonomy, $update );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			return $term_id;
		}

		/**
		 * Shared term config.
		 *
		 * @return array
		 */
		private function terms() {
			return array(
				'artifact'     => array( 'label' => 'Artifact' ),
				'note'         => array(
					'label'  => 'Note',
					'parent' => 'artifact',
				),
				'daily-note'   => array(
					'label'  => 'Daily Note',
					'parent' => 'note',
				),
				'todo'         => array(
					'label'  => 'TODO',
					'parent' => 'artifact',
				),
				'conversation' => array(
					'label'  => 'Conversation',
					'parent' => 'artifact',
				),
				'memory'       => array( 'label' => 'Memory' ),
				'skill'        => array( 'label' => 'Skill' ),
				'source'       => array( 'label' => 'Source' ),
				'manual'       => array(
					'label'  => 'Manual',
					'parent' => 'source',
				),
				'synced'       => array(
					'label'  => 'Synced',
					'parent' => 'source',
				),
				'readwise'     => array(
					'label'  => 'Readwise',
					'parent' => 'source',
				),
				'evernote'     => array(
					'label'  => 'Evernote',
					'parent' => 'source',
				),
				'ai-chat'      => array(
					'label'  => 'AI Chat',
					'parent' => 'source',
				),
				'personalos'   => array(
					'label'  => 'PersonalOS',
					'parent' => 'source',
				),
				'status'       => array( 'label' => '1-Status' ),
				'inbox'        => array(
					'label'  => 'Inbox',
					'parent' => 'status',
				),
				'now'          => array(
					'label'  => 'Now',
					'parent' => 'status',
				),
				'later'        => array(
					'label'  => 'Later',
					'parent' => 'status',
				),
				'follow-up'    => array(
					'label'  => 'Follow Up',
					'parent' => 'status',
				),
				'project'      => array( 'label' => '2-Projects' ),
				'area'         => array( 'label' => '3-Areas' ),
				'resource'     => array( 'label' => '4-Resources' ),
				'reference'    => array(
					'label'  => 'Reference',
					'parent' => 'resource',
				),
				'archive'      => array( 'label' => '5-Archive' ),
				'starred'      => array( 'label' => 'Starred' ),
			);
		}
	}
}
