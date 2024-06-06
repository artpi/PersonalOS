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
        add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );


        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        $this->connect();
    }

    function sync_note_to_evernote( $post_id, \WP_Post $post, $update ) {
        $guid = get_post_meta( $post->ID, 'evernote_guid', true );

        if ( $guid ) {
            $note = $this->advanced_client->getNoteStore()->getNote( $guid, false, false, false, false );
            if ( $note ) {
                $note->title = $post->post_title;
                $note->content = self::html2enml( $post->post_content );
                
                try {
                    $note = $this->advanced_client->getNoteStore()->updateNote( $note );
                    $result = $this->advanced_client->getNoteStore()->updateNote( $note );
                    $this->update_note_from_evernote( $result, $post );
                } catch( \EDAM\Error\EDAMSystemException $e ) {
                    throw new \Exception( "Evernote: " . $e->message );
                }
            }
            return;
        }

        // is this in one of ev notebooks?
        $notebooks = wp_get_post_terms( $post->ID, 'notebook', [
            'fields' => 'ids',
            'meta_key' => 'evernote_notebook_guid',
            'meta_value' => $this->get_setting( 'synced_notebooks' ),
        ] );
        if( empty( $notebooks ) ) {
            return;
        }
        // There is something like "main category" in wordpress, but whatever
        $notebook = new \Evernote\Model\Notebook();
        $notebook->guid = get_term_meta( $notebooks[0], 'evernote_notebook_guid', true );

        // edam note
        $note = new \EDAM\Types\Note();
        $note->title = $post->post_title;
        $note->content = self::html2enml( $post->post_content );
        $note->notebookGuid = $notebook->guid;
        $result = $this->advanced_client->getNoteStore()->createNote( $note );
    
        if ( $result ) {
            $this->update_note_from_evernote( $result, $post );
        }

    }

    function update_note_from_evernote( $result, $post ) {
        error_log( "[DEBUG] Evernote: Updating post from evernote " . print_r( [ 'evernote' => $result, 'wp' => $post ], true ) );
        remove_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10 );
        update_post_meta( $post->ID, 'evernote_guid', $result->guid );
        update_post_meta( $post->ID, 'evernote_content_hash', bin2hex( $result->contentHash ) );
        $post->post_content = $this->get_note_html( $result );
        wp_update_post( $post );
        add_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10, 3 );
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
        // TODO: Auth
        register_rest_route( $this->rest_namespace, '/evernote/file/(?P<guid>[a-z0-9\-]+)/', [
            'methods' => 'GET',
            'callback' => function( $request ) {
                $guid = $request->get_param( 'guid' );
                $file = $this->advanced_client->getNoteStore()->getResource( $guid, true, false, true, false );
                header( "Content-Disposition: inline;filename={$file->attributes->fileName}" );
                header( "Content-Type: {$file->mime}" );
                echo $file->data->body;
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
        // print_r(wp_get_attachment_metadata( 2006 ));
        // return;
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
            'includeResources' => true,
            //'notebookGuids' => $notebooks,
        ] );
        $sync_filter->includeExpunged = false;
        update_option( $this->get_setting_option_name( 'last_sync' ), $sync_state->currentTime );
        update_option( $this->get_setting_option_name( 'last_update_count' ), $sync_state->updateCount );

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

        if ( ! empty ( $sync_chunk->notes ) ) {

            // We have to manually filter for the notebooks we marked
            $filtered_notes = array_filter( $sync_chunk->notes, function( $note ) use ( $notebooks ) {
                return ( in_array( $note->notebookGuid, $notebooks ) );
            } );
            $filtered_notes = array_values( $filtered_notes );

            // We are going to be updating notes and we don't want the hook to loop
            remove_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10 );
            foreach( $filtered_notes as $note ) {
                $this->sync_note( $note );
            }
            add_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10, 3 );
        }

        // Now that notes are synced, we are going to sync the resources
        if ( ! empty( $sync_chunk->resources ) ) {
            error_log( "[DEBUG] Syncing resources" );
            foreach( $sync_chunk->resources as $resource ) {
                $this->sync_resource( $resource );
            }
        } 

    }

    function sync_resource( $resource ) {
        $notes = $this->get_notes_by_guid( $resource->noteGuid );
        if( count( $notes ) === 0 ) {
            error_log( "[DEBUG] Note not in the lib " . print_r( $resource, true ) );
            // This note is not in our lib and we don't care about it - its probably from another notebook
            return;
        }
        error_log( "[DEBUG] Syncing resource " . print_r( $resource, true ), LOG_DEBUG );


        $existing = $this->get_notes_by_guid( $resource->guid, 'attachment' );
        if ( count( $existing ) > 0 ) {
            $existing = $existing[0];

            if( ! empty( $resource->deleted ) ) {
                error_log( "[DEBUG] Evernote Deleting {$resource->guid}" );
                wp_trash_post( $existing->ID );
                return;
            }
        }

        // If we want to auto-upload all resources
        if ( true ) {
            $tempfile = wp_tempnam();
            file_put_contents( $tempfile, $this->advanced_client->getNoteStore()->getResourceData( $resource->guid ) );
            error_log( "Tempfile: $tempfile" );
            if ( empty( $resource->attributes->fileName ) ) {
                // TODO: Could create but maybe better not?
                return;
            }
            $filename = $resource->attributes->fileName;

            $file = array(
                'name'     => wp_hash( $resource->guid ) . "-" . $filename, // This hash is used to obfuscate the file names which should NEVER be exposed.
                'type'     => $resource->mime,
                'tmp_name' => $tempfile,
                'error'    => 0,
                'size'     => filesize( $tempfile ),
            );

            error_log( 'Saving, setting post status private', LOG_DEBUG );
            $data = [
                'post_status' => 'private', // Always default to private instead of inherit because https://piszek.com/2024/02/17/wordpress-custom-post-types-and-read-permission-in-rest/
                'post_title' => preg_replace( '/\.[^.]+$/', '', wp_basename( $filename ) ),
                'meta_input' => [
                    'evernote_guid' => $resource->guid,
                    'evernote_content_hash' => bin2hex( $resource->data->bodyHash ),
                ],
            ];
            $media_id = media_handle_sideload($file, $notes[0]->ID, null, $data  );

            if ( stristr( $resource->mime, 'audio' ) ) { // if auto transcribe audio
                // We have to schedule transcription ourselves because the mime is not ready yet at this time.
                update_post_meta( $media_id, 'pos_transcribe', 1 );
                wp_schedule_single_event( time() + 10, 'pos_transcription', [ $media_id ] );
            }

            error_log( 'UPLOADED: ' . $media_id );
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

    static function html2enml( $html ) {

        $permitted_enml_tags =  ['a', 'abbr', 'acronym', 'address', 'area', 'b', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'map', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'tt', 'u', 'ul', 'var', 'xmp'];
        $permittedENMLAttributes = [
            'abbr', 'accept', 'accept-charset', 'accesskey', 'action', 'align', 'alink', 'alt', 'archive', 'axis',
            'background', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'char', 'charoff', 'charset', 'checked',
            'cite', 'classid', 'clear', 'code', 'codebase', 'codetype', 'color', 'cols', 'colspan', 'compact', 'content',
            'coords', 'data', 'datetime', 'declare', 'defer', 'dir', 'disabled', 'enctype', 'face', 'for', 'frame',
            'frameborder', 'headers', 'height', 'href', 'hreflang', 'hspace', 'http-equiv', 'ismap', 'label', 'lang', 'language',
            'link', 'longdesc', 'marginheight', 'marginwidth', 'maxlength', 'media', 'method', 'multiple', 'name', 'nohref',
            'noresize', 'noshade', 'nowrap', 'object', 'onblur', 'onchange', 'onclick', 'ondblclick', 'onfocus', 'onkeydown',
            'onkeypress', 'onkeyup', 'onload', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onreset',
            'onselect', 'onsubmit', 'onunload', 'profile', 'prompt', 'readonly', 'rel', 'rev', 'rows', 'rowspan', 'rules',
            'scheme', 'scope', 'scrolling', 'selected', 'shape', 'size', 'span', 'src', 'standby', 'start', 'style', 'summary',
            'target', 'text', 'title', 'type', 'usemap', 'valign', 'value', 'valuetype', 'version', 'vlink', 'vspace', 'width'
        ];

        $kses_list = [];
        foreach( $permitted_enml_tags as $tag ) {
            $kses_list[$tag] = [];
            // Yeah not all attributes are for all tags, but we are going to be lazy
            foreach( $permittedENMLAttributes as $attr ) {
                $kses_list[$tag][$attr] = true;
            }
        }
        $html = wp_kses( $html, $kses_list );

        $html = preg_replace( '/<p[^>]*>/', '<div>', $html );
        $html = preg_replace( '/<\/p>/', '</div>', $html );

        // Strip all comments
        $html = preg_replace( '/<!--.*?-->/', '', $html );
        
        return '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
        <en-note>' . $html . '</en-note>';
    }

    function get_notes_by_guid( $guid, $post_type = null ) {
        if ( ! $post_type ) {
            $post_type = $this->notes_module->id;
        }

        return get_posts( [
            'post_type' => $post_type,
            'meta_query' => [
                [
                    'key' => 'evernote_guid',
                    'value' => $guid,
                ],
            ],
        ] );
    }
    function sync_note( $note ) {
        error_log( "[DEBUG] Syncing " . print_r( $note, true ) );
        $existing = $this->get_notes_by_guid( $note->guid );

        if ( count( $existing ) > 0 ) {
            $existing = $existing[0];

            if( ! empty( $note->deleted ) ) {
                error_log( "[DEBUG] Evernote Deleting {$note->title}" );
                wp_trash_post( $existing->ID );
                // Let's also get all attachments attached to this note that come from evernote.
                $attachments = get_posts( [
                    'post_type' => 'attachment',
                    'post_parent' => $existing->ID,
                    'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'),
                    'meta_query'  => array(
                        'relation' => 'AND',
                        array(
                            'key'     => 'evernote_guid',
                            'compare' => 'EXISTS',
                        ),
                    ),
                ] );
                error_log( "[DEBUG] Deleting attachments " . print_r( $attachments, true ) );
                foreach( $attachments as $attachment ) {
                    wp_delete_attachment( $attachment->ID );
                }

                return;
            }

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

        } else if ( empty( $note->deleted ) ) {
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

}
