<?php

class TODO_Module extends POS_Module {
	public $id   = 'todo';
	public $name = 'TODO';

	public function register() {
		$this->register_post_type(
			array(
				'supports'     => array( 'title', 'excerpt', 'custom-fields', 'comments'/*, 'page-attributes' */ ),
				'taxonomies'   => array( 'notebook' ),
				'show_in_menu' => false,
				// 'hierarchical' => true,
			),
			true
		);

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_filter( 'manage_notebook_custom_column', array( $this, 'notebook_taxonomy_column' ), 10, 3 );
		add_filter( 'manage_edit-notebook_columns', array( $this, 'notebook_taxonomy_columns' ) );
		add_action( 'add_meta_boxes', array( $this, 'pos_add_todo_dependency_meta_box' ) );
		add_action( 'save_post_todo', array( $this, 'pos_save_todo_dependency_meta' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'unblock_todos_when_completing' ), 10, 2 );
		add_action( 'pos_todo_scheduled', array( $this, 'pos_todo_scheduled' ), 10, 1 );
		add_action( 'post_updated', array( $this, 'save_todo_notes' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'save_todo_notes_terms' ), 10, 6 );
		add_action( 'load-post.php', array( $this, 'load_todo_edit_page' ) );

		register_meta(
			'post',
			'reminders_id',
			array(
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => true,
				'object_subtype' => $this->id,
			)
		);

		register_meta(
			'post',
			'pos_blocked_pending_term',
			array(
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => true,
				'object_subtype' => $this->id,
			)
		);

		register_meta(
			'post',
			'pos_blocked_by',
			array(
				'type'           => 'integer',
				'single'         => true,
				'show_in_rest'   => true,
				'object_subtype' => $this->id,
			)
		);

		register_meta(
			'post',
			'pos_recurring_days',
			array(
				'type'           => 'integer',
				'single'         => true,
				'show_in_rest'   => true,
				'object_subtype' => $this->id,
			)
		);

		register_meta(
			'post',
			'url',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);

		register_rest_field(
			'todo',
			'blocking',
			array(
				'get_callback' => function( $todo ) {
					$blocked_todos = get_posts(
						array(
							'post_type'      => 'todo',
							'post_status'    => array( 'publish', 'private', 'pending' ),
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'meta_query'     => array(
								array(
									'key'   => 'pos_blocked_by',
									'value' => $todo['id'],
								),
							),
						)
					);
					return $blocked_todos;
				},
				'schema'       => array(
					'description' => __( 'TODOs that are blocked by this TODO.' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'items'       => array(
						'type' => 'integer',
					),
				),
			)
		);

		register_rest_field(
			'todo',
			'scheduled',
			array(
				'get_callback' => function( $todo ) {
					return wp_next_scheduled( 'pos_todo_scheduled', array( $todo['id'] ) );
				},
				'schema'       => array(
					'description' => __( 'Scheduled time for this TODO.' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
		add_filter( 'pos_openai_tools', array( $this, 'register_openai_tools' ) );
	}

	public function register_openai_tools( $tools ) {
		$tools[] = new OpenAI_Tool( 'todo_get_items', 'List TODOs', array(), array( $this, 'get_items_for_openai' ) );
		return $tools;
	}

	public function get_items_for_openai( $args ) {
		$items = $this->list([], 'now');
		return array_map( function( $item ) {
			return [ 'title' => $item->post_title, 'excerpt' => $item->post_excerpt, 'url' => get_permalink( $item ) ];
		}, $items );
	}

	public function load_todo_edit_page() {
		global $typenow;

		if ( $typenow === $this->id ) {
			$inbox = get_term_by( 'slug', 'inbox', 'notebook' );
			$now = get_term_by( 'slug', 'now', 'notebook' );
			if ( ! $now ) {
				$now = $inbox;
			}
			$data = array(
				'defaultNotebook' => $inbox->term_id,
				'nowNotebook'     => $now->term_id,
				'possibleFlags'   => apply_filters( 'pos_notebook_flags', array() ),
				'id'              => sanitize_text_field( wp_unslash( $_GET['post'] ) ),
			);
			wp_add_inline_script(
				'pos',
				'wp.domReady( () => {
				const currentEditor = document.querySelector("#wpbody-content");
				currentEditor.style.display = "none";
				const todoRoot = document.createElement("div");
				todoRoot.id = "todo-root";
				todoRoot.classList.add( "pos__todo-edit" );
				document.querySelector( "#wpbody" ).appendChild( todoRoot );
				window.renderTodoEdit( todoRoot, ' . wp_json_encode( $data ) . ', currentEditor );
			} );',
				'after'
			);

			wp_enqueue_script( 'pos' );
			wp_enqueue_style( 'pos' );
		}
	}

	public function add_admin_menu(): void {
		add_submenu_page( 'personalos', 'TODO', 'TODO', 'read', 'pos-todo', array( $this, 'render_admin_page' ) );
	}

	public function create( $data = array(), $notebooks = array() ) {
		$default_data = array(
			'post_type'    => $this->id,
			'post_title'   => '',
			'post_status'  => 'private',
			'post_excerpt' => '',
			'post_date'    => gmdate( 'Y-m-d H:i:s' ),
			'meta_input'   => array(
				'url'                      => '',
				'pos_recurring_days'       => 0,
				'pos_blocked_by'           => 0,
				'pos_blocked_pending_term' => '',
			),
		);
		$data = wp_parse_args( $data, $default_data );
		$id = wp_insert_post( $data );
		if ( ! empty( $notebooks ) ) {
			wp_set_object_terms( $id, $notebooks, 'notebook' );
		}
		return $id;
	}

	public function list( $args = array(), $taxonomy = '' ) {
		$defaults = array(
			'post_type'   => $this->id,
			'post_status' => array( 'private', 'publish', 'future' ),
		);
		if ( $taxonomy ) {
			$defaults['tax_query'] = array(
				array(
					'taxonomy' => 'notebook',
					'field'    => is_numeric( $taxonomy ) ? 'id' : 'slug',
					'terms'    => array( $taxonomy ),
				),
			);
		}
		$args = wp_parse_args( $args, $defaults );
		return get_posts( $args );
	}

	public function render_admin_page(): void {
		$id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : null;

		?>
		<div class="wrap">
			<div id="todo-root" class="pos__dataview"></div>
		</div>
		<?php
		$inbox = get_term_by( 'slug', 'inbox', 'notebook' );
		$now = get_term_by( 'slug', 'now', 'notebook' );
		if ( ! $now ) {
			$now = $inbox;
		}
		$data = array(
			'defaultNotebook' => $inbox->term_id,
			'nowNotebook'     => $now->term_id,
			'possibleFlags'   => apply_filters( 'pos_notebook_flags', array() ),
		);

		wp_enqueue_script( 'pos' );
		wp_enqueue_style( 'pos' );

		if ( $id ) {
			$data['id'] = $id;
			wp_add_inline_script( 'pos', 'wp.domReady( () => { window.renderTodoEdit( document.getElementById( "todo-root" ), ' . wp_json_encode( $data ) . ' ); } );', 'after' );
		} else {
			wp_add_inline_script( 'pos', 'wp.domReady( () => { window.renderTodoAdmin( document.getElementById( "todo-root" ), ' . wp_json_encode( $data ) . ' ); } );', 'after' );

		}
	}

	public function save_todo_notes( $post_id, $post, $old_post ) {
		if ( $post->post_type !== $this->id || $old_post->post_type !== $this->id || ! $post ) {
			return;
		}

		$changes = array();

		// $time = strtotime( $post->post_date_gmt . ' GMT' );
		// if ( $time > time() && ! wp_next_scheduled( 'pos_todo_scheduled', array( $post_id ) ) && $post->post_status !== 'trash' ) {
		// 	$this->log( "TODO scheduled: {$post_id} at {$time}" );
		// 	$scheduled = wp_schedule_single_event( $time, 'pos_todo_scheduled', array( $post_id ) );
		// 	if ( $scheduled ) {
		// 		$changes[] = 'Scheduled at ' . wp_date( 'Y-m-d H:i:s', $time );
		// 	}
		// }

		if ( ! $old_post ) {
			return;
		}

		if ( $old_post->post_title !== $post->post_title ) {
			$changes[] = "Title changed from '<b><i>{$old_post->post_title}</i></b>' to '<b><i>{$post->post_title}</i></b>'";
		}

		if ( $old_post->post_excerpt !== $post->post_excerpt ) {
			if ( strlen( $old_post->post_excerpt ) > 0 ) {
				$changes[] = "<strike>{$old_post->post_excerpt}</strike>";
			}
			$changes[] = "{$post->post_excerpt}";
		}
		$this->todo_notes( $post_id, $changes );
	}

	private function todo_notes( $post_id, array $changes ) {
		if ( empty( $changes ) ) {
			return;
		}
		$changes = array_map(
			function( $change ) {
				return '<li>' . $change . '</li>';
			},
			$changes
		);

		$comment_content = '<h4>' . wp_date( 'Y-m-d H:i:s', time() ) . '</h4><ul>' . implode( "\n\n", $changes ) . '</ul>';
		wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => $comment_content,
				'user_id'         => get_current_user_id(),
				'comment_type'    => 'todo_note',
			)
		);
	}
	public function save_todo_notes_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $taxonomy !== 'notebook' ) {
			return;
		}

		$post = get_post( $object_id );
		if ( ! $post || is_wp_error( $post ) || $post->post_type !== $this->id ) {
			return;
		}

		$changes = array();
		$added_terms = array_diff( $tt_ids, $old_tt_ids );
		$removed_terms = array_diff( $old_tt_ids, $tt_ids );
		if ( ! empty( $added_terms ) ) {
			$added_terms_names = array_map(
				function( $term_id ) {
					$term = get_term_by( 'term_taxonomy_id', $term_id, 'notebook' );
					if ( ! $term ) {
						return $term_id;
					}
					return '<b>' . $term->name . '</b>';
				},
				$added_terms
			);
			$changes[] = 'Added to notebook: ' . implode( ', ', $added_terms_names );
		}
		if ( ! empty( $removed_terms ) ) {
			$removed_terms_names = array_map(
				function( $term_id ) {
					$term = get_term_by( 'term_taxonomy_id', $term_id, 'notebook' );
					if ( ! $term ) {
						return $term_id;
					}
					return '<b>' . $term->name . '</b>';
				},
				$removed_terms
			);
			$changes[] = 'Removed from notebook: <strike>' . implode( ', ', $removed_terms_names ) . '</strike>';
		}

		$this->todo_notes( $object_id, $changes );
	}

	public function notebook_taxonomy_columns( $columns ) {
		$columns['todos'] = 'TODOs';
		$columns['posts'] = 'Notes';
		return $columns;
	}
	public function notebook_taxonomy_column( $output, $column_name, $term_id ) {
		$term = get_term( $term_id );
		if ( $column_name === 'todos' ) {
			$query = new WP_Query(
				array(
					'post_type' => $this->id,
					'tax_query' => array(
						array(
							'taxonomy' => 'notebook',
							'field'    => 'id',
							'terms'    => array( $term_id ),
						),
					),
				)
			);
			return "<a href='edit.php?notebook={$term->slug}&post_type={$this->id}'>{$query->found_posts}</a>";
		}
		return $output;
	}

	public function pos_add_todo_dependency_meta_box() {
		add_meta_box(
			'pos_todo_dependency',
			'TODO Dependency',
			array( $this, 'pos_todo_dependency_meta_box_callback' ),
			'todo',
			'normal',
			'default'
		);
	}
	public function pos_todo_dependency_meta_box_callback( $post ) {
		wp_nonce_field( 'pos_save_todo_dependency_meta', 'pos_todo_dependency_meta_nonce' );

		$blocked_by = get_post_meta( $post->ID, 'pos_blocked_by', true );
		$blocked_pending_term = get_post_meta( $post->ID, 'pos_blocked_pending_term', true );

		$todos = get_posts(
			array(
				'post_type'      => 'todo',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'exclude'        => array( $post->ID ),
			)
		);

		?>
		<p>
			<label for="pos_blocked_by">This TODO depends on:</label>
			<select name="pos_blocked_by" id="pos_blocked_by">
				<option value="">This task does not depend on any other</option>
				<?php foreach ( $todos as $todo ) : ?>
					<option value="<?php echo esc_attr( $todo->ID ); ?>" <?php selected( $blocked_by, $todo->ID ); ?>>
						<?php echo esc_html( $todo->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="pos_blocked_pending_term">When unblocked move to:</label>
			<?php
			$dropdown_args = array(
				'taxonomy'          => 'notebook',
				'hide_empty'        => 0,
				'name'              => 'pos_blocked_pending_term',
				'orderby'           => 'name',
				'selected'          => empty( $blocked_pending_term ) ? 'now' : $blocked_pending_term,
				'hierarchical'      => true,
				'show_option_none'  => __( 'Select a notebook' ),
				'option_none_value' => '',
				'value_field'       => 'slug',
			);
			wp_dropdown_categories( $dropdown_args );
			?>
		</p>
		<?php
	}

	public function pos_save_todo_dependency_meta( $post_id, $post ) {
		$todo_pending_action = false;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['pos_todo_dependency_meta_nonce'] ) && ! wp_verify_nonce( $_POST['pos_todo_dependency_meta_nonce'], 'pos_save_todo_dependency_meta' ) ) {
			// We are saving data from the meta box.
			$blocked_by = sanitize_text_field( $_POST['pos_blocked_by'] );
			update_post_meta( $post_id, 'pos_blocked_by', $blocked_by );
			$todo_pending_action = true;
		}

		// This change can come from API or even from code.
		$time = strtotime( $post->post_date_gmt . ' GMT' );
		if ( $time > time() && ! wp_next_scheduled( 'pos_todo_scheduled', array( $post_id ) ) && $post->post_status !== 'trash' ) {
			$this->log( "TODO scheduled: {$post_id} at {$time}" );
			$scheduled = wp_schedule_single_event( $time, 'pos_todo_scheduled', array( $post_id ) );
			wp_insert_comment(
				array(
					'comment_post_ID' => $post_id,
					'comment_content' => 'Scheduled at ' . wp_date( 'Y-m-d H:i:s', $scheduled ),
					'user_id'         => get_current_user_id(),
					'comment_type'    => 'todo_note',
				)
			);
			$todo_pending_action = true;
		}

		if ( $todo_pending_action && isset( $_POST['pos_blocked_pending_term'] ) ) {
			update_post_meta( $post_id, 'pos_blocked_pending_term', sanitize_text_field( $_POST['pos_blocked_pending_term'] ) );
		}
	}

	public function pos_todo_scheduled( $post_id ) {
		$this->log( "TODO scheduled now: {$post_id}" );
		$this->perform_pending_action( get_post( $post_id ) );
	}

	// TODO: tests
	private function duplicate_todo( $post, $changes ) {
		$blocked_pending_term = get_post_meta( $post->ID, 'pos_blocked_pending_term', true );
		if ( empty( $blocked_pending_term ) ) {
			$this->log( "TODO recurring: {$post->ID} but no pending term" );
			return;
		}

		// By default we will duplicate all meta except private ones.
		$meta = array_filter(
			get_post_meta( $post->ID ),
			function( $key ) {
				return substr( $key, 0, 1 ) !== '_';
			},
			ARRAY_FILTER_USE_KEY
		);
		$meta = array_map(
			function( $value ) {
				if ( is_array( $value ) && count( $value ) === 1 ) {
					return $value[0];
				}
				return $value;
			},
			$meta
		);

		$current_notebooks = wp_get_object_terms( $post->ID, 'notebook', array( 'fields' => 'ids' ) );
		$current_notebooks_minus_pending = array_diff( $current_notebooks, array( get_term_by( 'slug', $blocked_pending_term, 'notebook' )->term_id ) );

		$new_post_defaults = array(
			'post_title'   => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
			'post_date'    => $post->post_date,
			'post_status'  => 'private',
			'post_type'    => $post->post_type,
			'meta_input'   => $meta,
			//'post_parent' => $post->ID,
			'tax_input'    => array(
				'notebook' => $current_notebooks_minus_pending,
			),
		);

		$new_post_data = wp_parse_args( $changes, $new_post_defaults );
		$new_post = wp_insert_post( $new_post_data );
		wp_insert_comment(
			array(
				'comment_post_ID' => $new_post,
				'comment_content' => 'Duplicated from ' . $post->ID,
				'user_id'         => get_current_user_id(),
				'comment_type'    => 'todo_note',
			)
		);

	}

	public function unblock_todos_when_completing( $post_id, $previous_status ) {
		$post = get_post( $post_id );

		// Check if the trashed post is a 'todo'
		if ( $post->post_type !== $this->id ) {
			return;
		}

		$recurring_days = get_post_meta( $post_id, 'pos_recurring_days', true );
		if ( $recurring_days && $recurring_days > 0 ) {
			$this->duplicate_todo(
				$post,
				array(
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+ ' . $recurring_days . ' days' ) ),
					'post_status' => $previous_status,
				)
			);
		}

		// get todos blocked by this todo
		$blocked_posts = get_posts(
			array(
				'post_type'      => $this->id,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'meta_key'       => 'pos_blocked_by',
				'meta_value'     => $post_id,
			)
		);

		foreach ( $blocked_posts as $blocked_post ) {
			$this->perform_pending_action( $blocked_post );
		}

		// Is this a scheduled todo that needs to be unscheduled?
		$scheduled = wp_next_scheduled( 'pos_todo_scheduled', array( $post_id ) );
		$this->log( "Unscheduling {$scheduled}" );
		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, 'pos_todo_scheduled', array( $post_id ) );
		}
	}

	private function perform_pending_action( $blocked_post ) {
		$blocked_pending_term_slug = get_post_meta( $blocked_post->ID, 'pos_blocked_pending_term', true );
		if ( empty( $blocked_pending_term_slug ) ) {
			$this->log( "TODO unblocked: {$blocked_post->ID}. No pending term", E_USER_ERROR );
			return;
		}

		$blocked_pending_term = get_term_by( 'id', $blocked_pending_term_slug, 'notebook' );
		if ( ! $blocked_pending_term ) {
			$blocked_pending_term = get_term_by( 'slug', $blocked_pending_term_slug, 'notebook' );
		}
		if ( ! $blocked_pending_term ) {
			$this->log( "TODO unblocked: {$blocked_post->ID}. Moving to {$blocked_pending_term_slug} but term not found", E_USER_ERROR );
			return;
		}
		wp_set_object_terms( $blocked_post->ID, array( $blocked_pending_term->term_id ), 'notebook', true );
		$this->log( "TODO unblocked: {$blocked_post->ID} by completing blocking post. Moving now to {$blocked_pending_term_slug}" );
		//Cleanup for the blocking todo
		// delete_post_meta( $blocked_post->ID, 'pos_blocked_pending_term' );
		delete_post_meta( $blocked_post->ID, 'pos_blocked_by' );
	}
}
