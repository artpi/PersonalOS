<?php

Class External_Service_Module extends POS_Module {
    public $id = 'external_service';
    public $name = "External Service";

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


Class Readwise extends External_Service_Module {
    public $id = 'readwise';
    public $name = "Readwise";
    public $description = "Syncs with readwise service";
    public $parent_notebook = null;
    public $category_names = [
        'books' => 'Books',
        'articles' => 'Articles',
        'podcasts' => 'Podcasts',
        'tweets' => 'Tweets',
        'supplementals' => 'Supplementals',
    ];

    public $settings = [
        'token' => [
            'type' => 'text',
            'name' => 'Readwise API Token',
            'label' => 'You can get it from <a href="https://readwise.io/access_token">here</a>',
        ],
    ];

    public $notes_module = null;

    function __construct( $notes_module ) {
        $this->notes_module = $notes_module;
        $this->register_sync( 'hourly' );

        $this->register_meta( 'readwise_id', $this->notes_module->id );
        $this->register_meta( 'readwise_category', $this->notes_module->id );
        $this->register_block( 'readwise' );
    }

    function setup_default_notebook() {
        $this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
        if ( $this->parent_notebook ) {
            return;
        }
        wp_insert_term( 'Readwise', 'notebook', [ 'slug' => 'readwise' ] );
        $this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
    }

    function sync() {
        error_log( "[DEBUG] Syncing readwise triggering " );

        $token = $this->get_setting( 'token' );
        if( ! $token ) {
            return false;
        }
        $this->setup_default_notebook();

        $query_args = [];
        $page_cursor = get_option(  $this->get_setting_option_name( 'page_cursor' ), null );
        if ( $page_cursor ) {
            $query_args['pageCursor'] = $page_cursor;
        } else {
            $last_sync = get_option( $this->get_setting_option_name( 'last_sync' ) );
            if ( $last_sync ) {
                $query_args['updatedAfter'] = $last_sync;
            }
        }

        $request = wp_remote_get(
            'https://readwise.io/api/v2/export/?' . http_build_query( $query_args ),
            array(
                'headers' => array(
                    'Authorization' => 'Token ' . $token
                )
            )
        );
        if ( is_wp_error( $request ) ) {
            error_log( '[ERROR] Fetching readwise ' . $request->get_error_message() );
            return false; // Bail early
        }
        
        $body = wp_remote_retrieve_body( $request );
        $data = json_decode( $body );
        error_log( "[DEBUG] Readwise Syncing {$data->count} highlights" );

        if ( ! empty ( $data->nextPageCursor ) ) {
            // We want to unschedule any regular sync events until we have initial sync complete.
            wp_unschedule_hook( $this->get_sync_hook_name() );
            // We will schedule ONE TIME sync event for the next page.
            update_option( $this->get_setting_option_name( 'page_cursor' ), $data->nextPageCursor );
            wp_schedule_single_event( time() + 60, $this->get_sync_hook_name() );
            error_log( "Scheduling next page sync with cursor {$data->nextPageCursor}" );
        } else {
            error_log( "[DEBUG] Full sync completed" );
            update_option( $this->get_setting_option_name( 'last_sync' ), date( 'c' ) );
            delete_option( $this->get_setting_option_name( 'page_cursor' ) );
        }

        foreach( $data->results as $book ) {
            $this->sync_book( $book );
        }
    }

    function find_note_by_readwise_id( $readwise_id ) {
        $posts = get_posts( array(
            'posts_per_page'   => -1,
            'post_type'        => $this->notes_module->id,
            'post_status'      => 'publish', // TODO: Remove this after testing - the post from trash should not be duplicated.
            'meta_key'         => 'readwise_id',
            'meta_value'       => $readwise_id
        ) );
        if ( empty( $posts ) ) {
            return null;
        }
        return $posts[0];
    }

    function sync_book( $book ) {
        $previous = $this->find_note_by_readwise_id( $book->user_book_id );
        error_log( "[DEBUG] Readwise " . ( $previous ? 'Updating' : 'Creating' ) .  " {$book->title}" );

        $content = array_map(
            function( $highlight ) {
                return get_comment_delimited_block_content(
                    'pos/readwise',
                    [
                        'readwise_url' => $highlight->readwise_url,
                    ],
                    '<p class="wp-block-pos-readwise">' . $highlight->text . '</p>'
                );
            },
            $book->highlights
        );

        if( count( $content ) === 0 ){
            return;
        }

        $parent_notebook = $this->parent_notebook;
        $term_names = array_map( function( $tag ) {
            return $tag->name;
        }, $book->book_tags );

        if ( ! empty( $book->category ) ) {
            $term_names[] = $this->category_names[ $book->category ] ?? $book->category;
        }

        $term_ids = array_map( function( $name ) use ( $parent_notebook ) {
            $matching_notebooks = get_terms( [
                'taxonomy' => 'notebook',
                'hide_empty' => false,
                'name' => $name,
                'child_of' => $parent_notebook->term_id,
            ] );

            if( count( $matching_notebooks ) > 0) {
                return $matching_notebooks[0]->term_id;
            }
            $term = wp_insert_term( $name, 'notebook', [ 'parent' => $parent_notebook->term_id ]);

            return $term['term_id'];
        }, $term_names );

        $term_ids = array_filter( $term_ids );
        $term_ids[] = get_term_by( 'slug', 'inbox', 'notebook' )->term_id;

        if ( $previous ) {
            $post_id = $previous->ID;
            wp_update_post( [ 
                'ID' => $previous->ID,
                'content' => $previous->post_content . "\n" . implode( "\n", $content ),
            ] );
        } else {
            $data = [
                'post_title' => $book->title,
                'post_type' => $this->notes_module->id,
                'post_content' => implode( "\n", $content ),
                'post_status' => 'publish',
                'meta_input' => [
                    'readwise_id' => $book->user_book_id,
                    'readwise_category' => $book->category,
                    'readwise_author' => $book->author,
                    'url' => $book->source_url,
                ],
            ];
            if ( $book->summary ) {
                $data[ 'post_excerpt' ] = $book->summary;
            }
            $last_highlight = end( $book->highlights );
            if ( $last_highlight ) {
                $data[ 'post_date' ] = date( 'Y-m-d H:i:s', strtotime( $last_highlight->created_at ) );
            }
            $post_id = wp_insert_post( $data );
        }
        if ( $post_id && count( $term_ids ) > 0 ) {
            wp_set_post_terms( $post_id, $term_ids, 'notebook', true );
        }
    }
}
