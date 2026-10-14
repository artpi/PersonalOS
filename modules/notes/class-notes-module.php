<?php

class Notes_Module extends POS_Module {
	public $id   = 'notes';
	public $name = 'Notes';
	public $settings = array(
		'user' => array(
			'type'     => 'callback',
			'callback' => 'user_setting_callback',
			'name'     => 'User for sync jobs',
			'label'    => 'A user to run sync jobs as.',
		),
	);

	public function list( $args = array(), $taxonomy = '' ) {
		$defaults = array(
			'post_type'      => $this->id,
			'post_status'    => array( 'private', 'publish', 'future' ),
			'posts_per_page' => -1,
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

	/**
	 * @TODO: Unify list with get_notes
	 */
	public function get_notes( $args = array() ) {
		return get_posts(
			array_merge(
				array(
					'post_type'      => $this->id,
					'post_status'    => array( 'publish', 'private' ),
					'perm'           => 'readable',
					'posts_per_page' => -1,
				),
				$args
			)
		);
	}

	public function switch_to_user() {
		$user_id = $this->get_setting( 'user' );
		if ( ! $user_id ) {
			return;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}
		wp_set_current_user( $user_id, $user->user_login );
	}

	public function register() {
		register_taxonomy(
			'notebook',
			array( $this->id, 'todo' ),
			array(
				'label'             => 'Notebook',
				'public'            => false,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_in_menu'      => 'personalos',
				'default_term'      => array(
					'name'        => 'Inbox',
					'slug'        => 'inbox',
					'description' => 'Default notebook for notes and todos.',
				),
				'show_admin_column' => true,
				'query_var'         => true,
				'show_in_rest'      => true,
				'rest_namespace'    => $this->rest_namespace,
				'rewrite'           => array( 'slug' => 'notebook' ),
			)
		);
		$this->register_post_type(
			array(
				'supports'   => array( 'title', 'excerpt', 'editor', 'custom-fields', 'revisions' ),
				'taxonomies' => array( 'notebook', 'post_tag' ),
			),
			true
		);
		$this->jetpack_whitelist_cpt_with_dotcom();

		add_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10, 3 );
		add_action( 'wp_dashboard_setup', array( $this, 'init_admin_widgets' ) );

		$this->register_block(
			'note',
			array(
				'render_callback' => array( $this, 'render_note_block' ),
			)
		);
		$this->register_block( 'describe_img' );

		$this->settings['synced_notebooks']['callback'] = array( $this, 'synced_notebooks_setting_callback' );
		add_action( 'notebook_edit_form_fields', array( $this, 'notebook_edit_form_fields' ), 10, 2 );
		add_action( 'edited_notebook', array( $this, 'save_notebook_settings' ) );

		register_meta(
			'term',
			'flag',
			array(
				'object_subtype' => 'notebook',
				'type'           => 'string',
				'single'         => false,
				'show_in_rest'   => true,
			)
		);

		add_filter(
			'pos_notebook_flags',
			function( $flags ) {
				$flags[] = array(
					'id'    => 'star',
					'name'  => 'Star',
					'label' => 'Starred, it will show up in menu.',
				);
				$flags[] = array(
					'id'    => 'project',
					'name'  => 'Project',
					'label' => 'This is a currently active project.',
				);
				return $flags;
			}
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
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Register abilities
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register Notes module abilities with WordPress Abilities API.
	 */
	public function register_abilities() {
		// Register get_notebooks ability
		wp_register_ability(
			'pos/get-notebooks',
			array(
				'label'               => __( 'Get Notebooks', 'personalos' ),
				'description'         => __( 'Get all notebooks organized by flags. Notebooks represent areas of life, active projects and statuses of tasks.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'notebook_flag' => array(
							'type'        => 'string',
							'description' => 'The flag of the notebook to get.',
							'enum'        => array_map(
								function( $flag ) {
									return $flag['id'];
								},
								apply_filters(
									'pos_notebook_flags',
									array(
										array(
											'id' => 'all',
										),
									)
								)
							),
							'default'     => 'all',
						),
					),
					'required'             => array( 'notebook_flag' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of notebooks grouped by flags',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'flag_id'    => array( 'type' => 'string' ),
							'flag_name'  => array( 'type' => 'string' ),
							'flag_label' => array( 'type' => 'string' ),
							'notebooks'  => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'notebook_name' => array( 'type' => 'string' ),
										'notebook_id'   => array( 'type' => 'integer' ),
										'notebook_slug' => array( 'type' => 'string' ),
										'notebook_description' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_notebooks_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);
	}

	/**
	 * Get notebooks ability.
	 *
	 * @param array $parameters Parameters with notebook_flag.
	 * @return array Array of notebook data grouped by flags.
	 */
	public function get_notebooks_ability( $parameters ) {
		$notebook_flag = $parameters['notebook_flag'] ?? 'all';
		$flags = array_filter(
			apply_filters( 'pos_notebook_flags', array() ),
			function( $flag ) use ( $notebook_flag ) {
				return $flag['id'] === $notebook_flag || $notebook_flag === 'all';
			}
		);
		if ( $notebook_flag === 'all' ) {
			$flags[] = array(
				'id'    => null,
				'name'  => 'Rest of the notebooks',
				'label' => 'Notebooks without any special flag.',
			);
		}
		$notebooks = array_map(
			function( $flag ) {
				$notebooks = array_map(
					function( $notebook ) {
						return array(
							'notebook_name'        => $notebook->name,
							'notebook_id'          => $notebook->term_id,
							'notebook_slug'        => $notebook->slug,
							'notebook_description' => $notebook->description,
						);
					},
					$this->get_notebooks_by_flag( $flag['id'] )
				);
				return array(
					'flag_id'    => $flag['id'] ?? 'null',
					'flag_name'  => $flag['name'],
					'flag_label' => $flag['label'],
					'notebooks'  => $notebooks,
				);
			},
			array_values( $flags )
		);
		return $notebooks;
	}

	public function get_notebooks_by_flag( $flag ) {
		$args = array(
			'taxonomy'   => 'notebook',
			'hide_empty' => false,
		);
		if ( is_string( $flag ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'flag',
					'value'   => $flag,
					'compare' => '=',
				),
			);
		} elseif ( $flag === null ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'flag',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		// When passed false it will return all notebooks
		return get_terms( $args );
	}

	public function admin_menu() {
		$terms = $this->get_notebooks_by_flag( 'star' );

		foreach ( $terms as $term ) {
			$count = count(
				get_posts(
					array(
						'nopaging'    => true,
						'fields'      => 'ids',
						'post_type'   => 'todo',
						'post_status' => array( 'private', 'publish' ),
						'tax_query'   => array(
							array(
								'taxonomy' => 'notebook',
								'field'    => 'slug',
								'terms'    => $term->slug,
							),
						),
					)
				)
			);
			if ( $count > 0 ) {
				add_submenu_page( 'personalos', $term->name, $term->name . ' todos <span class="awaiting-mod" style="background-color: #0073aa;"><span class="pending-count" aria-hidden="true">' . $count . '</span></span>', 'read', 'admin.php?page=pos-todo#' . $term->slug );
			}
			$count = count(
				get_posts(
					array(
						'nopaging'    => true,
						'fields'      => 'ids',
						'post_type'   => 'notes',
						'post_status' => array( 'private', 'publish' ),
						'tax_query'   => array(
							array(
								'taxonomy' => 'notebook',
								'field'    => 'slug',
								'terms'    => $term->slug,
							),
						),
					)
				)
			);
			if ( $count > 0 ) {
				add_submenu_page( 'personalos', $term->name, $term->name . ' notes <span class="awaiting-mod" style="background-color: #0073aa;"><span class="pending-count" aria-hidden="true">' . $count . '</span></span>', 'read', 'edit.php?post_type=notes&notebook=' . $term->slug );
			}
		}
	}

	public function notebook_edit_form_fields( $term, $taxonomy ) {
		$value = get_term_meta( $term->term_id, 'flag', false );
		$possible_flags = apply_filters( 'pos_notebook_flags', array() );
		?>
		<table class="form-table" role="presentation"><tbody>
			<tr class="form-field term-parent-wrap">
			<th scope="row"><label>Notebook Flags</label></th>
			<td>
				<?php foreach ( $possible_flags as $flag ) : ?>
					<label style="display: block; margin-bottom: 5px;">
						<input type="checkbox" name="pos_flag[]" value="<?php echo esc_attr( $flag['id'] ); ?>"
							<?php checked( in_array( $flag['id'], $value, true ) ); ?>>
						<?php echo esc_html( $flag['label'] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">Select one or more flags for this notebook. These flags determine how the notebook is treated in various contexts.</p>
			</td>
			</tr>
		</tbody></table>
		<?php
		wp_nonce_field( 'notebook_edit', 'notebook_edit_nonce' );
	}

	public function save_notebook_settings( int $term_id ) {
		if ( ! isset( $_POST['notebook_edit_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['notebook_edit_nonce'] ?? '', 'notebook_edit' ) ) {
			return;
		}

		if ( empty( $_POST['pos_flag'] ) ) {
			delete_term_meta( $term_id, 'flag' );
		} else {
			$flags = array_map( 'sanitize_text_field', $_POST['pos_flag'] );
			delete_term_meta( $term_id, 'flag' );
			foreach ( $flags as $flag ) {
				add_term_meta( $term_id, 'flag', $flag );
			}
		}
	}

	public function user_setting_callback( $option_name, $value, $setting ) {
		wp_dropdown_users(
			array(
				'name'     => $option_name,
				'selected' => $value,
			)
		);
	}

	public function render_note_block( $attributes, $content ) {
		$post = get_post( $attributes['note_id'] );
		if ( ! $post ) {
			return $content;
		}
		return $post->post_content;
	}

	public function autopublish_drafts( $post_id, $post, $updating ) {
		if ( $post->post_status === 'draft' ) {
			remove_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10 );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'private',
				)
			);
			add_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10, 3 );
		}
	}

	public function create( $title, $content, $notebooks = array(), $args = array() ) {
		$post    = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'private',
			'post_type'    => $this->id,
		);
		$post = wp_parse_args( $post, $args );
		$post_id = wp_insert_post( $post );
		if ( ! empty( $notebooks ) ) {
			wp_set_object_terms( $post_id, $notebooks, 'notebook' );
		}
		return $post_id;
	}

	public function create_term_if_not_exists( $name, $slug, $meta = array(), $args = array() ) {
		$term = get_term_by( 'slug', $slug, 'notebook' );
		if ( $term ) {
			$term_id = $term->term_id;
		} else {
			$term = wp_insert_term(
				$name,
				'notebook',
				array_merge( array( 'slug' => $slug ), $args )
			);
			$term_id = $term['term_id'];
		}

		foreach ( $meta as $value ) {
			update_term_meta( $term_id, $value[0], $value[1] );
		}
		return $term_id;
	}

	public function init_admin_widgets() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'notebook',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => 'flag',
						'value'   => 'star',
						'compare' => '=',
					),
				),
			)
		);
		foreach ( $terms as $term ) {
			$this->register_notebook_admin_widget( $term );
		}
		wp_enqueue_style( 'pos-notes-widgets-css', plugin_dir_url( __FILE__ ) . 'admin-widgets.css', array(), '1.0' );

		// TODO create widget
		wp_add_dashboard_widget(
			'pos_todo_create',
			'Quick TODO',
			array( $this, 'todo_create_widget' ),
			null
		);
		wp_add_dashboard_widget(
			'pos_note_create',
			'Quick Note',
			array( $this, 'note_create_widget' ),
			null
		);
	}

	public function todo_create_widget() {
		?>
		<form id="quicktodo" method="get" class="initial-form hide-if-no-js">
		<div class="input-text-wrap">
			<input type="text" name="post_title" autocomplete="off">
		</div>
		<ul id="quicktodo_feedback"></ul>
		</form>
		<?php
		wp_enqueue_script( 'wp-api-fetch' );
		wp_add_inline_script(
			'wp-api-fetch',
			"
        document.getElementById('quicktodo').addEventListener( 'submit', function( e ) {
            e.preventDefault();
            var data = new FormData( e.target );
            var title = data.get( 'post_title' );
            if( title.length < 5 ) {
                document.getElementById('quicktodo_feedback').innerHTML += '<li>Title too short!</li>';
                return;

            }
            wp.apiFetch( {
                path: '/pos/v1/todo',
                method: 'POST',
                data: {
                    title: title,
                    status: 'private',
                }
            } ).then( function( response ) {
                console.log( response );
                document.getElementById('quicktodo_feedback').innerHTML += '<li><a href=\"post.php?action=edit&post=' + response.id + '\">' + response.title.raw + '</a></li>';
                e.target.reset();
            } );
        } );
        ",
			'after'
		);
	}
	public function note_create_widget() {
		?>
		<form id="quicknote" method="get" class="initial-form hide-if-no-js">
		<div class="textarea-wrap">
			<textarea name="content" placeholder="What's on your mind?" class="mceEditor" rows="3" cols="15" autocomplete="off" style="overflow: hidden; height: 170px;"></textarea>
		</div>
		<p class="submit">
			<input type="submit" name="save" id="save-post" disabled class="button button-primary" value="Save Note" style="width:100%">
			<br class="clear">
		</p>
		<ul id="quicktodo_feedback"></ul>
		</form>
		<?php
		wp_enqueue_script( 'wp-api-fetch' );
		wp_add_inline_script(
			'wp-api-fetch',
			"
        var saveNoteTimer = null;
        var postId = null;
        var editedNoteId = 0;

        function saveNote() {
            clearTimeout( saveNoteTimer );
            document.querySelector('#save-post').setAttribute( 'disabled', true );
            console.log( 'saving', document.querySelector('#quicknote textarea').value );

            wp.apiFetch( {
                path: editedNoteId ? ( '/pos/v1/notes/' + editedNoteId ) : '/pos/v1/notes',
                method: 'POST',
                data: {
                    title: 'Quick Note',
                    status: 'private',
                    content: document.querySelector('#quicknote textarea').value,
                }
            } ).then( function( response ) {
                console.log( response );
                editedNoteId = response.id;
            } );
        }

        document.querySelector('#quicknote textarea').addEventListener( 'input', function( e ) {
            if ( saveNoteTimer ) {
                clearTimeout( saveNoteTimer );
            }
            saveNoteTimer = setTimeout( saveNote, 5000 );
            if ( e.target.value.length > 5 ) {
                document.querySelector('#save-post').removeAttribute( 'disabled' );
            }
        } );
        document.getElementById('quicktodo').addEventListener( 'submit', function( e ) {
            e.preventDefault();
            var data = new FormData( e.target );
        } );
        ",
			'after'
		);
	}
	public function register_notebook_admin_widget( $term ) {
		wp_add_dashboard_widget(
			'pos_notebook_' . $term->slug,
			$term->name,
			array( $this, 'notebook_admin_widget' ),
			null,
			$term
		);
	}

	protected function get_notebook_widget_posts( $post_type, $notebook_slug ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 25,
			'tax_query'      => array(
				array(
					'taxonomy' => 'notebook',
					'field'    => 'slug',
					'terms'    => array( $notebook_slug ),
				),
			),
		);

		if ( ! current_user_can( 'admin_personalos' ) ) {
			$current_user_id = get_current_user_id();
			if ( $current_user_id ) {
				$args['author'] = $current_user_id;
			}
		}

		$posts = get_posts( $args );
		return array_filter(
			$posts,
			function( $post ) {
				return current_user_can( 'read_post', $post->ID );
			}
		);
	}

	public function notebook_admin_widget( $widget_config, $conf ) {
		$check = '<?xml version="1.0" ?><svg height="20px" version="1.1" viewBox="0 0 20 20" width="20px" xmlns="http://www.w3.org/2000/svg" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns" xmlns:xlink="http://www.w3.org/1999/xlink"><title/><desc/><defs/><g fill="none" fill-rule="evenodd" id="Page-1" stroke="none" stroke-width="1"><g fill="#000000" id="Core" transform="translate(-170.000000, -86.000000)"><g id="check-circle-outline-blank" transform="translate(170.000000, 86.000000)"><path d="M10,0 C4.5,0 0,4.5 0,10 C0,15.5 4.5,20 10,20 C15.5,20 20,15.5 20,10 C20,4.5 15.5,0 10,0 L10,0 Z M10,18 C5.6,18 2,14.4 2,10 C2,5.6 5.6,2 10,2 C14.4,2 18,5.6 18,10 C18,14.4 14.4,18 10,18 L10,18 Z" id="Shape"/></g></g></g></svg>';

		// Custom allowed HTML tags including SVG for the check icon.
		$allowed_html = array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'svg'   => array(
					'height'       => true,
					'version'      => true,
					'viewbox'      => true,
					'width'        => true,
					'xmlns'        => true,
					'xmlns:sketch' => true,
					'xmlns:xlink'  => true,
				),
				'g'     => array(
					'fill'         => true,
					'fill-rule'    => true,
					'id'           => true,
					'stroke'       => true,
					'stroke-width' => true,
					'transform'    => true,
				),
				'path'  => array(
					'd'  => true,
					'id' => true,
				),
				'title' => array(),
				'desc'  => array(),
				'defs'  => array(),
			)
		);
		$notes = $this->get_notebook_widget_posts( $this->id, $conf['args']->slug );
		if ( count( $notes ) > 0 ) {
			echo '<h3><a href="edit.php?notebook=' . esc_attr( $conf['args']->slug ) . '&post_type=notes">' . esc_html( $conf['args']->name ) . ': Notes</a></h3>';
			$notes = array_map(
				function( $note ) {
					return "<li><a href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'><h5>{$note->post_title}</h5><time datetime='{$note->post_date}'>" . gmdate( 'F j, Y', strtotime( $note->post_date ) ) . '</time><p>' . get_the_excerpt( $note ) . '</p></a></li>';
				},
				$notes
			);

			echo '<ul class="pos_admin_widget_notes">' . wp_kses_post( implode( '', $notes ) ) . '</ul>';
		}
		$notes = $this->get_notebook_widget_posts( 'todo', $conf['args']->slug );
		if ( count( $notes ) > 0 ) {
			echo '<h3><a href="admin.php?page=pos-todo#' . esc_attr( $conf['args']->slug ) . '">' . esc_html( $conf['args']->name ) . ': TODOs</a></h3>';
			$notes = array_map(
				function( $note ) use ( $check ) {
					return "<li><a href='" . esc_url( wp_nonce_url( "post.php?action=trash&amp;post=$note->ID", 'trash-post_' . $note->ID ) ) . "'>{$check}<a style='font-weight:bold;margin: 0 5px 0 0 ' href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'>{$note->post_title}</a></li>";
				},
				$notes
			);

			echo '<ul class="pos_admin_widget_todos">' . wp_kses( implode( '', $notes ), $allowed_html ) . '</ul>';
		}

		//$term = get_term_by( 'slug', $conf['args']['notebook'], 'notebook' );
		//$query = new WP_Query( array( 'post_type' => $this->id, 'tax_query' => [ [ 'taxonomy' => 'notebook', 'field' => 'slug', 'terms' => [  ] ] ] ) );
		//echo "<p>Notes in this  notebook: {$query->found_posts}</p>";
	}
}
