<?php

Class Evernote extends External_Service_Module {
    public $id = 'evernote';
    public $name = "Evernote";
    public $description = "Syncs with evernote service";
    public $parent_notebook = null;
    public $simple_client = null;
    public $advanced_client = null;

    public $settings = [
        'token' => [
            'type' => 'text',
            'name' => 'Evernote Developer API Token',
            'label' => 'You can get it from <a href="https://www.evernote.com/api/DeveloperToken.action">here</a>',
        ],
        'synced_notebooks' => [
            'type' => 'callback',
            'name' => 'Synced notebooks',
            'label' => 'Comma separated list of notebooks to sync',
        ],
    ];

    public $notes_module = null;

    function __construct( $notes_module ) {
        $this->settings['synced_notebooks']['callback'] = [ $this, 'synced_notebooks_setting_callback' ];
        $this->notes_module = $notes_module;
        $this->register_sync( 'hourly' );

        $this->register_meta( 'evernote_guid', $this->notes_module->id );
        $this->register_meta( 'evernote_content_hash', $this->notes_module->id );

        // $this->register_block( 'readwise' );
        // $this->register_block( 'book-summary' );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        $this->connect();
    }

    function get_app_link_from_guid( $guid ) {
        $user = $this->advanced_client->getUserStore()->getUser();
        $note_url = "evernote:///view/{$user->id}/{$user->shardId}/{$guid}/{$guid}/";
        return $note_url;
    }

    public function register_rest_routes() {
        register_rest_route( $this->rest_namespace, '/evernote-redirect/(?P<post_id>\w+)/', [
            'methods' => 'GET',
            'callback' => function( $request ) {
                $post_id = $request->get_param( 'post_id' );
                $guid = get_post_meta( $post_id, 'evernote_guid', true );
                $note_url = $this->get_app_link_from_guid( $guid );
                header( "Location: $note_url" );
                die();
            },
            'permission_callback' => '__return_true',
        ] );
    }

    public function synced_notebooks_setting_callback ( $option_name, $value, $setting ) {
        nl2br( print_r( $value ) );
        if( ! $this->simple_client ) {
            echo '<p>Please enter a valid token</p>';
            return;
        }
        $notebooks = $this->simple_client->listNotebooks();
        $stacks = [];
        $nostack = [];
        foreach( $notebooks as $notebook ) {
            $n = $notebook->getEdamNotebook();
            if( $n && $n->stack ) {
                if( ! isset( $stacks[$n->stack] ) ) {
                    $stacks[$n->stack] = [];
                }
                $stacks[$n->stack][$n->name] = $n;
            } else {
                $nostack[] = $n;
            }
        }
        ksort($stacks);

        echo "<ul style='list-style:initial'>";
        foreach( $stacks as $stackname => $stack ) {
            echo "<li><b>{$stackname}</b><ul style='list-style:initial; padding-left: 25px'>";
            ksort($stack);

            foreach( $stack as $n ) {
                $checked = ( is_array($value ) && in_array( $n->guid, $value ) ) ? 'checked' : '';
                echo "<li><input name='{$option_name}[]' type='checkbox' value='{$n->guid}' {$checked}>{$n->name}</li>";
            }
            echo "</ul></li>";
        }
        echo "</ul>";
    }


    function connect() {
        $token = $this->get_setting( 'token' );
        if( ! $token ) {
            return false;
        }
        require_once( plugin_dir_path( __FILE__ ) . '/../../vendor/autoload.php' );
        $this->simple_client = new \Evernote\Client( $token, false );
        $this->advanced_client = new \Evernote\AdvancedClient( $token, false );
        return $this->simple_client;
    }

    function sync() {
        error_log( "[DEBUG] Syncing Evernote triggering " );
        $notebooks = $this->get_setting( 'synced_notebooks' );
        if( ! $notebooks || ! $this->advanced_client ) {
            return [];
        }
        $usn = get_option(  $this->get_setting_option_name( 'usn' ), 0 );
        $last_sync = get_option( $this->get_setting_option_name( 'last_sync' ), 0 );
        $last_update_count = get_option( $this->get_setting_option_name( 'last_update_count' ), 0 );
        $sync_state = $this->advanced_client->getNoteStore()->getSyncState();
        $sync_filter = new \EDAM\NoteStore\SyncChunkFilter( [
            'includeNotes' => true,
            'includeNotebooks' => false,
            'includeNoteAttributes' => true,
            'includeExpunged' => false,
            //'notebookGuids' => $notebooks,
        ] );
        $sync_filter->includeExpunged = false;
        update_option( $this->get_setting_option_name( 'last_sync' ), $sync_state->currentTime );
        delete_option( $this->get_setting_option_name( 'last_update_count' ), $sync_state->updateCount );

        error_log( print_r( [ $sync_state, $last_update_count, $usn ], true ) );
        if ( $sync_state->updateCount === $last_update_count || $sync_state->updateCount === $usn ) {
            error_log( "[DEBUG] Evernote: No updates since last sync" );
            return;
        }

        if ( $sync_state->fullSyncBefore > $last_sync ) {
            // Retriggering full sync
            $usn = 0;
        }

        $sync_chunk = $this->advanced_client->getNoteStore()->getFilteredSyncChunk( $usn, 100, $sync_filter );
        if ( ! $sync_chunk ) {
            error_log( "[ERROR] Evernote: Sync failed" );
            return;
        }

        if( empty ( $sync_chunk->notes ) ) {
            error_log( "[DEBUG] Evernote: No notes to sync" );
            return;
        }

        // We have to manually filter for the notebooks we marked
        $filtered_notes = array_filter( $sync_chunk->notes, function( $note ) use ( $notebooks ) {
            return ( in_array( $note->notebookGuid, $notebooks ) && empty( $note->deleted ) );
        } );
        $filtered_notes = array_values( $filtered_notes );
    
        if ( ! empty ( $sync_chunk->chunkHighUSN ) && $sync_chunk->chunkHighUSN > $usn ) {
            // We want to unschedule any regular sync events until we have initial sync complete.
            wp_unschedule_hook( $this->get_sync_hook_name() );
            // We will schedule ONE TIME sync event for the next page.
            update_option( $this->get_setting_option_name( 'usn' ), $sync_chunk->chunkHighUSN );
            wp_schedule_single_event( time() + 60, $this->get_sync_hook_name() );
            error_log( "Scheduling next page chunk with cursor {$sync_chunk->chunkHighUSN}" );
        } else {
            error_log( "[DEBUG] Evernote: Full sync completed" );
        }

        foreach( $filtered_notes as $note ) {
            $this->sync_note( $note );
        }
    }

    function get_notebook_by_guid( $guid ) {
        $args = array(
            'hide_empty' => false,
            'meta_query' => array(
                array(
                   'key'       => 'evernote_notebook_guid',
                   'value'     => $guid,
                   'compare'   => 'LIKE'
                )
            ),
            'taxonomy'  => 'notebook',
        );
        $terms = get_terms( $args );
        if( count( $terms ) > 0 ) {
            return $terms[0]->term_id;
        }

        // We have to create this notebook, under "Evernote" parent

        $notebook = $this->advanced_client->getNoteStore()->getNotebook( $guid );
        error_log( "[DEBUG] Evernote Creating " . print_r( $notebook, true ) );
        $name = $notebook->name;

        if ( ! $this->parent_notebook ) {
            $this->parent_notebook = get_term_by( 'slug', 'evernote', 'notebook' );
        }
        if ( ! $this->parent_notebook ) {
            wp_insert_term( 'Evernote', 'notebook', [ 'slug' => 'evernote' ] );
            $this->parent_notebook = get_term_by( 'slug', 'evernote', 'notebook' );
        }
        $term = wp_insert_term( $name, 'notebook', [ 'parent' => $this->parent_notebook->term_id ] );
        update_term_meta( $term['term_id'], 'evernote_notebook_guid', $guid );
        return $term['term_id'];
    }

    function get_note_html( $note ) {
        // TODO: Handle resources like <en-media hash="0a35baf77505fa7867468ec2b1b21865" type="audio/m4a" />
        $content = $this->advanced_client->getNoteStore()->getNoteContent( $note->guid );
        if( class_exists( 'XSLTProcessor'  ) ) {
            $c = new Evernote\Enml\Converter\EnmlToHtmlConverter();
            $content = $c->convertToHtml( $content );
        } else {
            error_log( "[WARN] No processor for xslt. Will convert notes the dum way" );
            if ( preg_match( '/<en-note[^>]*>(.*?)<\/en-note>/s', $content, $matches ) ){
                $content = $matches[1];
            }
        }
        return $content;
    }

    function sync_note( $note ) {
        error_log( "[DEBUG] Syncing " . print_r( $note, true ) );

        $existing = get_posts( [
            'post_type' => $this->notes_module->id,
            'meta_query' => [
                [
                    'key' => 'evernote_guid',
                    'value' => $note->guid,
                ],
            ],
        ] );
        if ( count( $existing ) > 0 ) {
            $existing = $existing[0];
            $update_array = [];
            if ( bin2hex( $note->contentHash ) !== get_post_meta( $existing->ID, 'evernote_content_hash', true ) ) {
                $update_array['post_content'] = $this->get_note_html( $note );
                if ( ! isset( $data['meta_input'] ) ) {
                    $data['meta_input'] = [];
                }
                $data['meta_input']['evernote_content_hash'] = bin2hex( $note->contentHash );
            }
            if ( $note->updated > strtotime( $existing->post_modified ) ) {
                $update_array['post_date'] = date( 'Y-m-d H:i:s', ( $note->updated / 1000 ) );
            }

            if( $note->title !== $existing->post_title ) {
                $update_array['post_title'] = $note->title;
            }

            if ( $note->attributes->sourceURL !== get_post_meta( $existing->ID, 'url', true ) ) {
                if ( ! isset( $data['meta_input'] ) ) {
                    $data['meta_input'] = [];
                } 
                $data['meta_input']['url'] = $note->attributes->sourceURL;
            }

            // TODO: change notebook too


            if ( count( $update_array ) > 0 ) {
                $update_array['ID'] = $existing->ID;
                error_log( "[DEBUG] Evernote Updating " . print_r( $update_array, true ) );
                wp_update_post( $update_array );
            }

        } else {
            error_log( "[DEBUG] Evernote Creating {$note->title}" );
            $data = [
                'post_title' => $note->title,
                'post_type' => $this->notes_module->id,
                'post_content' => $this->get_note_html( $note ),
                'post_status' => 'publish',
                'post_date' => date( 'Y-m-d H:i:s', $note->created / 1000 ),
                'meta_input' => [
                    'evernote_guid' => $note->guid,
                    'evernote_content_hash' => bin2hex( $note->contentHash ),
                ],
            ];
            if( ! empty( $note->attributes->sourceURL ) ) {
                $data['meta_input']['url'] = $note->attributes->sourceURL;
            }
            $post_id = wp_insert_post( $data );
            wp_set_post_terms( $post_id, [ $this->get_notebook_by_guid( $note->notebookGuid ) ], 'notebook', true );
        }
    }

    // function setup_default_notebook() {
    //     $this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
    //     if ( $this->parent_notebook ) {
    //         return;
    //     }
    //     wp_insert_term( 'Readwise', 'notebook', [ 'slug' => 'readwise' ] );
    //     $this->parent_notebook = get_term_by( 'slug', 'readwise', 'notebook' );
    // }

    // function sync() {
    //     error_log( "[DEBUG] Syncing readwise triggering " );

    //     $token = $this->get_setting( 'token' );
    //     if( ! $token ) {
    //         return false;
    //     }
    //     $this->setup_default_notebook();

    //     $query_args = [];
    //     $page_cursor = get_option(  $this->get_setting_option_name( 'page_cursor' ), null );
    //     if ( $page_cursor ) {
    //         $query_args['pageCursor'] = $page_cursor;
    //     } else {
    //         $last_sync = get_option( $this->get_setting_option_name( 'last_sync' ) );
    //         if ( $last_sync ) {
    //             $query_args['updatedAfter'] = $last_sync;
    //         }
    //     }

    //     $request = wp_remote_get(
    //         'https://readwise.io/api/v2/export/?' . http_build_query( $query_args ),
    //         array(
    //             'headers' => array(
    //                 'Authorization' => 'Token ' . $token
    //             )
    //         )
    //     );
    //     if ( is_wp_error( $request ) ) {
    //         error_log( '[ERROR] Fetching readwise ' . $request->get_error_message() );
    //         return false; // Bail early
    //     }
        
    //     $body = wp_remote_retrieve_body( $request );
    //     $data = json_decode( $body );
    //     error_log( "[DEBUG] Readwise Syncing {$data->count} highlights" );

        // if ( ! empty ( $data->nextPageCursor ) ) {
        //     // We want to unschedule any regular sync events until we have initial sync complete.
        //     wp_unschedule_hook( $this->get_sync_hook_name() );
        //     // We will schedule ONE TIME sync event for the next page.
        //     update_option( $this->get_setting_option_name( 'page_cursor' ), $data->nextPageCursor );
        //     wp_schedule_single_event( time() + 60, $this->get_sync_hook_name() );
        //     error_log( "Scheduling next page sync with cursor {$data->nextPageCursor}" );
        // } else {
        //     error_log( "[DEBUG] Full sync completed" );
        //     update_option( $this->get_setting_option_name( 'last_sync' ), date( 'c' ) );
        //     delete_option( $this->get_setting_option_name( 'page_cursor' ) );
        // }

    //     foreach( $data->results as $book ) {
    //         $this->sync_book( $book );
    //     }
    // }

    // function find_note_by_readwise_id( $readwise_id ) {
    //     $posts = get_posts( array(
    //         'posts_per_page'   => -1,
    //         'post_type'        => $this->notes_module->id,
    //         'post_status'      => 'publish', // TODO: Remove this after testing - the post from trash should not be duplicated.
    //         'meta_key'         => 'readwise_id',
    //         'meta_value'       => $readwise_id
    //     ) );
    //     if ( empty( $posts ) ) {
    //         return null;
    //     }
    //     return $posts[0];
    // }

    // function sync_book( $book ) {
    //     $previous = $this->find_note_by_readwise_id( $book->user_book_id );
    //     error_log( "[DEBUG] Readwise " . ( $previous ? 'Updating' : 'Creating' ) .  " {$book->title}" );

    //     $content = array_map(
    //         function( $highlight ) {
    //             return get_comment_delimited_block_content(
    //                 'pos/readwise',
    //                 [
    //                     'readwise_url' => $highlight->readwise_url,
    //                 ],
    //                 '<p class="wp-block-pos-readwise">' . $highlight->text . '</p>'
    //             );
    //         },
    //         $book->highlights
    //     );

    //     if( count( $content ) === 0 ){
    //         return;
    //     }

    //     $parent_notebook = $this->parent_notebook;
    //     $term_names = array_map( function( $tag ) {
    //         return $tag->name;
    //     }, $book->book_tags );

    //     if ( ! empty( $book->category ) ) {
    //         $term_names[] = $this->category_names[ $book->category ] ?? $book->category;
    //     }

    //     $term_ids = array_map( function( $name ) use ( $parent_notebook ) {
    //         $matching_notebooks = get_terms( [
    //             'taxonomy' => 'notebook',
    //             'hide_empty' => false,
    //             'name' => $name,
    //             'child_of' => $parent_notebook->term_id,
    //         ] );

    //         if( count( $matching_notebooks ) > 0) {
    //             return $matching_notebooks[0]->term_id;
    //         }
    //         $term = wp_insert_term( $name, 'notebook', [ 'parent' => $parent_notebook->term_id ]);

    //         return $term['term_id'];
    //     }, $term_names );

    //     $term_ids = array_filter( $term_ids );
    //     $term_ids[] = get_term_by( 'slug', 'inbox', 'notebook' )->term_id;

    //     if ( $previous ) {
    //         $post_id = $previous->ID;
    //         wp_update_post( [ 
    //             'ID' => $previous->ID,
    //             'content' => $previous->post_content . "\n" . implode( "\n", $content ),
    //         ] );
    //     } else {
    //         $data = [
    //             'post_title' => $book->title,
    //             'post_type' => $this->notes_module->id,
    //             'post_content' => implode( "\n", $content ),
    //             'post_status' => 'publish',
    //             'meta_input' => [
    //                 'readwise_id' => $book->user_book_id,
    //                 'readwise_category' => $book->category,
    //                 'readwise_author' => $book->author,
    //                 'url' => $book->source_url,
    //             ],
    //         ];
    //         if ( $book->summary ) {
    //             $data[ 'post_excerpt' ] = $book->summary;
    //         }
    //         $last_highlight = end( $book->highlights );
    //         if ( $last_highlight ) {
    //             $data[ 'post_date' ] = date( 'Y-m-d H:i:s', strtotime( $last_highlight->created_at ) );
    //         }
    //         $post_id = wp_insert_post( $data );
    //     }
    //     if ( $post_id && count( $term_ids ) > 0 ) {
    //         wp_set_post_terms( $post_id, $term_ids, 'notebook', true );
    //     }
    // }
}
