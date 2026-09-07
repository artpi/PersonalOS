<?php

class Readwise extends External_Service_Module {
	public $id              = 'readwise';
	public $name            = 'Readwise';
	public $description     = 'Syncs with readwise service';
	public $parent_notebook = null;
	public $category_names  = array(
		'books'         => 'Books',
		'articles'      => 'Articles',
		'podcasts'      => 'Podcasts',
		'tweets'        => 'Tweets',
		'supplementals' => 'Supplementals',
	);

	public $settings = array(
		'token'   => array(
			'type'  => 'text',
			'name'  => 'Readwise API Token',
			'label' => 'You can get it from <a href="https://readwise.io/access_token">here</a>',
			'scope' => 'user',
		),
		'autotag' => array(
			'type'     => 'callback',
			'callback' => 'autotag_setting_callback',
			'name'     => 'Notebook for incoming highlights',
			'label'    => 'Automatically add new highlights to this notebook. They will also be added to the "Readwise" notebook.',
			'scope'    => 'user',
		),
	);

	public Notes_Module|null $notes_module = null;

	public function __construct( Notes_Module $notes_module ) {
		$this->notes_module = $notes_module;
		$this->register_sync( 'hourly' );
		$this->register_meta( 'readwise_id', $this->notes_module->id );
		$this->register_meta( 'readwise_category', $this->notes_module->id );
		$this->register_block( 'readwise' );
		$this->register_block( 'book-summary' );
	}


	public function autotag_setting_callback( string $option_name, $value, $setting ) {
		if ( ! $value ) {
			$value = 0;
		}
		\POS_Settings::wp_terms_select_form( 'notebook', $value, $option_name, 0, 'Don\'t autotag' );
	}

	public function setup_default_notebook() {
		$this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
		if ( $this->parent_notebook ) {
			return;
		}
		wp_insert_term( 'Readwise', 'notebook', array( 'slug' => 'readwise' ) );
		$this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
	}

	public function sync() {
		$user_ids = $this->get_user_ids_with_setting( 'token' );
		if ( empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			$token = $this->get_setting( 'token', $user_id );
			if ( ! $token ) {
				continue;
			}

			$this->run_for_user(
				$user_id,
				function () use ( $token, $user_id ) {
					try {
						$this->sync_user( $token, $user_id );
					} catch ( \Throwable $e ) {
						$this->log( sprintf( 'User %d readwise sync error: %s', $user_id, $e->getMessage() ), E_USER_WARNING );
					}
				}
			);
		}
	}

	protected function sync_user( string $token, int $user_id ) {
		$this->log( sprintf( '[DEBUG] Syncing Readwise for user %d', $user_id ) );
		$this->setup_default_notebook();

		$query_args  = array();
		$page_cursor = $this->get_user_state( 'page_cursor', null, $user_id );
		if ( $page_cursor ) {
			$query_args['pageCursor'] = $page_cursor;
		} else {
			$last_sync = $this->get_user_state( 'last_sync', null, $user_id );
			if ( $last_sync ) {
				$query_args['updatedAfter'] = $last_sync;
			}
		}

		$request = wp_remote_get(
			'https://readwise.io/api/v2/export/?' . http_build_query( $query_args ),
			array(
				'headers' => array(
					'Authorization' => 'Token ' . $token,
				),
			)
		);
		if ( is_wp_error( $request ) ) {
			$this->log( '[ERROR] Fetching readwise ' . $request->get_error_message(), E_USER_WARNING );
			return;
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );
		if ( empty( $data ) ) {
			$this->log( '[ERROR] Empty response from Readwise', E_USER_WARNING );
			return;
		}

		$this->log( "[DEBUG] Readwise Syncing {$data->count} highlights" );

		//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! empty( $data->nextPageCursor ) ) {
			wp_unschedule_hook( $this->get_sync_hook_name() );
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->set_user_state( 'page_cursor', $data->nextPageCursor, $user_id );
			wp_schedule_single_event( time() + 60, $this->get_sync_hook_name() );
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->log( "Scheduling next page sync with cursor {$data->nextPageCursor}" );
		} else {
			$this->log( '[DEBUG] Full sync completed' );
			$this->set_user_state( 'last_sync', gmdate( 'c' ), $user_id );
			$this->delete_user_state( 'page_cursor', $user_id );
		}

		if ( empty( $data->results ) ) {
			return;
		}

		foreach ( $data->results as $book ) {
			$this->sync_book( $book );
		}
	}

	public function find_note_by_readwise_id( $readwise_id ) {
		$posts = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => $this->notes_module->id,
				'post_status'    => array( 'private', 'publish', 'pending', 'draft', 'future' ),
				'author'         => get_current_user_id(),
				'meta_key'       => 'readwise_id',
				'meta_value'     => $readwise_id,
			)
		);
		if ( empty( $posts ) ) {
			return null;
		}
		return $posts[0];
	}

	public static function wrap_highlight( $highlight ) {
		return get_comment_delimited_block_content(
			'pos/readwise',
			array(
				'readwise_url' => $highlight->readwise_url,
			),
			'<div class="wp-block-pos-readwise">' . $highlight->text . '</div>'
		);
	}

	public function sync_book( $book ) {
		$previous = $this->find_note_by_readwise_id( $book->user_book_id );
		$this->log( '[DEBUG] Readwise ' . ( $previous ? 'Updating' : 'Creating' ) . " {$book->title}" );

		$content = array_map(
			array( __CLASS__, 'wrap_highlight' ),
			$book->highlights
		);

		if ( count( $content ) === 0 ) {
			$this->log( 'Somehow no notes' );
			return;
		}

		$parent_notebook = $this->parent_notebook;
		$term_names      = array_map(
			function( $tag ) {
				return $tag->name;
			},
			$book->book_tags
		);

		if ( ! empty( $book->category ) ) {
			$term_names[] = $this->category_names[ $book->category ] ?? $book->category;
		}

		$term_ids = array_map(
			function( $name ) use ( $parent_notebook ) {
				$matching_notebooks = get_terms(
					array(
						'taxonomy'   => 'notebook',
						'hide_empty' => false,
						'name'       => $name,
						'child_of'   => $parent_notebook->term_id,
					)
				);

				if ( count( $matching_notebooks ) > 0 ) {
					return $matching_notebooks[0]->term_id;
				}
				$term = wp_insert_term( $name, 'notebook', array( 'parent' => $parent_notebook->term_id ) );

				return $term['term_id'];
			},
			$term_names
		);

		$term_ids   = array_filter( $term_ids );
		$term_ids[] = $parent_notebook->term_id;

		if ( $this->get_setting( 'autotag' ) > 0 ) {
			$term_ids[] = $this->get_setting( 'autotag' );
		}

		if ( $previous ) {
			$existing_terms = wp_get_post_terms( $previous->ID, 'notebook', array( 'fields' => 'ids' ) );
			$term_ids       = array_unique( array_merge( $term_ids, $existing_terms ) );
			wp_update_post(
				array(
					'ID'           => $previous->ID,
					'post_content' => $previous->post_content . "\n" . implode( "\n", $content ),
					'tax_input'    => array(
						'notebook' => $term_ids,
					),
				)
			);
		} else {
			$data = array(
				'post_title'   => $book->title,
				'post_type'    => $this->notes_module->id,
				'post_content' => implode( "\n", $content ),
				'post_status'  => 'private',
				'post_author'  => get_current_user_id(),
				'meta_input'   => array(
					'readwise_id'       => $book->user_book_id,
					'readwise_category' => $book->category,
					'readwise_author'   => $book->author,
					'url'               => $book->source_url,
				),
				'tax_input'    => array(
					'notebook' => $term_ids,
				),
			);
			if ( $book->summary ) {
				$data['post_excerpt'] = $book->summary;
			}
			$last_highlight = end( $book->highlights );
			if ( $last_highlight ) {
				$data['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( $last_highlight->created_at ) );
			}
			$post_id = wp_insert_post( $data );
		}
	}
}
