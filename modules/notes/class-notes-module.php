<?php

class Notes_Module extends POS_Module {
	public $id   = 'notes';
	public $name = 'Notes';

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
				'taxonomies' => array( 'notebook', 'post_tag' ),
			)
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
			wp_publish_post( $post );
		}
	}

	public function create( $title, $content, $inbox = false ) {
		$post    = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => $this->id,
		);
		$post_id = wp_insert_post( $post );
		return $post_id;
	}
	public function init_admin_widgets() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'notebook',
				'hide_empty' => false,
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
			<textarea name="content" placeholder="What’s on your mind?" class="mceEditor" rows="3" cols="15" autocomplete="off" style="overflow: hidden; height: 170px;"></textarea>
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
                    status: 'publish',
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
	public function notebook_admin_widget( $widget_config, $conf ) {
		$check = '<?xml version="1.0" ?><svg height="20px" version="1.1" viewBox="0 0 20 20" width="20px" xmlns="http://www.w3.org/2000/svg" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns" xmlns:xlink="http://www.w3.org/1999/xlink"><title/><desc/><defs/><g fill="none" fill-rule="evenodd" id="Page-1" stroke="none" stroke-width="1"><g fill="#000000" id="Core" transform="translate(-170.000000, -86.000000)"><g id="check-circle-outline-blank" transform="translate(170.000000, 86.000000)"><path d="M10,0 C4.5,0 0,4.5 0,10 C0,15.5 4.5,20 10,20 C15.5,20 20,15.5 20,10 C20,4.5 15.5,0 10,0 L10,0 Z M10,18 C5.6,18 2,14.4 2,10 C2,5.6 5.6,2 10,2 C14.4,2 18,5.6 18,10 C18,14.4 14.4,18 10,18 L10,18 Z" id="Shape"/></g></g></g></svg>';
		$notes = get_posts(
			array(
				'post_type'      => $this->id,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 25,
				'tax_query'      => array(
					array(
						'taxonomy' => 'notebook',
						'field'    => 'slug',
						'terms'    => array(
							$conf['args']->slug,
						),
					),
				),
			)
		);
		$notes = array_filter(
			$notes,
			function( $post ) {
				return current_user_can( 'read_post', $post->ID );
			}
		);
		if ( count( $notes ) > 0 ) {
			echo '<h3>' . esc_html( $conf['args']->name ) . ': Notes</h3>';
			$notes = array_map(
				function( $note ) {
					return "<li><a href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'><h5>{$note->post_title}</h5><time datetime='{$note->post_date}'>" . gmdate( 'F j, Y', strtotime( $note->post_date ) ) . '</time><p>' . get_the_excerpt( $note ) . '</p></a></li>';
				},
				$notes
			);

			echo '<ul class="pos_admin_widget_notes">' . esc_html( implode( '', $notes ) ) . '</ul>';
		}
		$notes = get_posts(
			array(
				'post_type'      => 'todo',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 25,
				'tax_query'      => array(
					array(
						'taxonomy' => 'notebook',
						'field'    => 'slug',
						'terms'    => array(
							$conf['args']->slug,
						),
					),
				),
			)
		);
		$notes = array_filter(
			$notes,
			function( $post ) {
				return current_user_can( 'read_post', $post->ID );
			}
		);
		if ( count( $notes ) > 0 ) {
			echo '<h3>' . esc_html( $conf['args']->name ) . ': TODOs</h3>';
			$notes = array_map(
				function( $note ) use ( $check ) {
					return "<li><a href='" . esc_url( wp_nonce_url( "post.php?action=trash&amp;post=$note->ID", 'trash-post_' . $note->ID ) ) . "'>{$check}<a style='font-weight:bold;margin: 0 5px 0 0 ' href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'>{$note->post_title}</a></li>";
				},
				$notes
			);

			echo '<ul class ="pos_admin_widget_todos" >' . esc_html( implode( '', $notes ) ) . '</ul>';
		}

		//$term = get_term_by( 'slug', $conf['args']['notebook'], 'notebook' );
		//$query = new WP_Query( array( 'post_type' => $this->id, 'tax_query' => [ [ 'taxonomy' => 'notebook', 'field' => 'slug', 'terms' => [  ] ] ] ) );
		//echo "<p>Notes in this  notebook: {$query->found_posts}</p>";
	}
}
