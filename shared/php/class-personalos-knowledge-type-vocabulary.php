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
