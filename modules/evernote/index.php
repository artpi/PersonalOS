<?php

/**
 * This is a module for syncing with Evernote.
 * It syncs both ways, notes and resources
 *
 * TODO: Comment everything, include strong typing, write unit tests for.
 * TODO: The WP <-> ENML conversion is hacky, it would be awesome if it used blocks, and just transformed blocks into enml and vice versa. Since ENML does not support comments, it could store the metadata in style the same ENML todos are stored.
 * TODO: Handle checkboxes <en-todo checked="true"/>
 * TODO: when something goes wrong in syncing a chunk, the rest of the changes are not synced. we should probably update USN after each successful note so that when we have an error, sync is picked up from the point it failed.
 * 
 */
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

    function __construct( \POS_Module $notes_module ) {
        $this->settings['synced_notebooks']['callback'] = [ $this, 'synced_notebooks_setting_callback' ];
        $this->notes_module = $notes_module;
        $this->register_sync( 'hourly' );

        $this->register_meta( 'evernote_guid', $this->notes_module->id );
        $this->register_meta( 'evernote_content_hash', $this->notes_module->id );
        // TODO: Hook this up only if the token is set.
        add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );


        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        $this->connect();
    }

    /**
     * This is hooked into the save_post action of the notes module.
     * Every time a post is updated, this will check if it is in the synced notebooks and sync it to evernote.
     * It will then receive the returned content and update the post, so some content may be lost if it is not handled by evernote
     * 
     * @param int $post_id
     * @param \WP_Post $post
     * @param bool $update
     */
    function sync_note_to_evernote( int $post_id, \WP_Post $post, bool $update ) {
        //return;//  Off for now
        $guid = get_post_meta( $post->ID, 'evernote_guid', true );

        if ( $guid ) {
            $note = $this->advanced_client->getNoteStore()->getNote( $guid, false, false, false, false );
            if ( $note ) {
                $note->title = $post->post_title;
                $note->content = self::html2enml( $post->post_content );
    
                try {
                    $result = $this->advanced_client->getNoteStore()->updateNote( $note );
                    $this->update_note_from_evernote( $result, $post );
                } catch( \EDAM\Error\EDAMSystemException $e ) {
                    // Silently fail because conflicts and stuff.
                    error_log( "[ERROR] Evernote: " . $e->getMessage() );
                    error_log( print_r( $post, true ) );
                } catch( \EdAM\Error\EDAMUserException $e ) {
                    error_log( "[ERROR] Evernote ENML probably misformatted: '" . $e->getMessage() . '" . While saving: ' . $note->content );
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
        try {
            $result = $this->advanced_client->getNoteStore()->createNote( $note );
            if ( $result ) {
                $this->update_note_from_evernote( $result, $post );
            }
        } catch( \EDAM\Error\EDAMUserException $e ) {
            error_log( "[ERROR] Evernote ENML probably misformatted: '" . $e->getMessage() . '" . While saving: ' . $note->content );
        }

    }

    /**
     * This is called when a note is updated from evernote.
     * It will update the post with the new data.
     * It is triggered by both directions of the sync:
     * - When a note is updated in evernote, it will be updated in wordpress
     * - When a note is updated in wordpress, it will be updated in evernote and then the return will be passed here.
     * 
     * @param \EDAM\Types\Note $note
     * @param \WP_Post $post
     * @param bool $sync_resources - If true, it will also upload note resources. We want this in most cases, EXCEPT when we are sending the data from WordPress and know the response will not have new resources for us.
     */
    function update_note_from_evernote( \EDAM\Types\Note $note, \WP_Post $post, $sync_resources = false ) {
        remove_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10 );

        $update_array = [];
        $force_rewrite_content = false;
        if ( ! empty ( $note->resources ) && $sync_resources ) {
            foreach( $note->resources as $resource ) {
                $media_id = $this->sync_resource( $resource );
                if( $media_id ) {
                    // Even though content did not change, we uploaded media and have to rewrite the content with new media.
                    $force_rewrite_content = true;
                }
            }
        }

        if ( $force_rewrite_content || bin2hex( $note->contentHash ) !== get_post_meta( $post->ID, 'evernote_content_hash', true ) ) {
            error_log( 'Content hashes:  new: ' . bin2hex( $note->contentHash ) . ' old: ' . get_post_meta( $post->ID, 'evernote_content_hash', true ) );
            // we are assuming resources only changed when note content changed
            $update_array['post_content'] = $this->get_note_html( $note );
            if ( ! isset( $update_array['meta_input'] ) ) {
                $update_array['meta_input'] = [];
            }
            $update_array['meta_input']['evernote_content_hash'] = bin2hex( $note->contentHash );
        }

        $stored_guid = get_post_meta( $post->ID, 'evernote_guid', true );
        if ( ! $stored_guid || $stored_guid !== $note->guid ) {
            if ( ! isset( $update_array['meta_input'] ) ) {
                $update_array['meta_input'] = [];
            }
            $update_array['meta_input']['evernote_guid'] = $note->guid;
        }

        $updated = floor( $note->updated / 1000 );
        if ( $updated > strtotime( $post->post_modified ) ) {
            $update_array['post_modified'] = date( 'Y-m-d H:i:s', $updated );
        }

        if( $note->title !== $post->post_title ) {
            $update_array['post_title'] = $note->title;
        }

        if ( ! empty( $note->attributes->sourceURL ) && $note->attributes->sourceURL !== get_post_meta( $post->ID, 'url', true ) ) {
            if ( ! isset( $update_array['meta_input'] ) ) {
                $update_array['meta_input'] = [];
            } 
            $update_array['meta_input']['url'] = $note->attributes->sourceURL;
        }

        // TODO: change notebook too
        if ( count( $update_array ) > 0 ) {
            $update_array['ID'] = $post->ID;
            error_log( "[DEBUG] Evernote: Updating post from evernote {$post->ID} {$note->guid} " . print_r( $update_array, true ) );
            wp_update_post( $update_array );
        }
        add_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10, 3 );
    }

    /**
     * This is a helper function to get the app link for a note. This URL opens the note in the Evernote app.
     */
    function get_app_link_from_guid( string $guid ) {
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
        // TODO: This should be using the special post described here "Downloading resource directly from the web" https://dev.evernote.com/doc/articles/resources.php
        // register_rest_route( $this->rest_namespace, '/evernote/file/(?P<guid>[a-z0-9\-]+)/', [
        //     'methods' => 'GET',
        //     'callback' => function( $request ) {
        //         $guid = $request->get_param( 'guid' );
        //         $file = $this->advanced_client->getNoteStore()->getResource( $guid, true, false, true, false );
        //         header( "Content-Disposition: inline;filename={$file->attributes->fileName}" );
        //         header( "Content-Type: {$file->mime}" );
        //         echo $file->data->body;
        //         die();
        //     },
        //     'permission_callback' => '__return_true',
        // ] );
    }

    /**
     * This is a callback for the settings page.
     * It will list all notebooks and allow the user to select which ones to sync.
     * 
     * @see Settings::array_setting_callback
     * @param string $option_name
     * @param mixed $value
     * @param \WP_Customize_Setting $setting
     */
    public function synced_notebooks_setting_callback ( string $option_name, $value, $setting ) {
        // TODO: create notebooks here.
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

    /**
     * Connect to Evernote and create a client
     */
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

    /**
     * Sync with Evernote. This is triggered by the cron job.
     * 
     * @see register_sync
     */
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
            //'includeResources' => true,
            'includeNoteResources' => true,
            //'notebookGuids' => $notebooks,
        ] );
        $sync_filter->includeExpunged = false;
        update_option( $this->get_setting_option_name( 'last_sync' ), $sync_state->currentTime );
        update_option( $this->get_setting_option_name( 'last_update_count' ), $sync_state->updateCount );

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

    /**
     * Sync individual resource
     * 
     * @param \EDAM\Types\Resource $resource
     * @return int|false - Post ID of the attachment or false if not uploaded
     */
    function sync_resource( \EDAM\Types\Resource $resource ): int|false {
        $notes = $this->get_notes_by_guid( $resource->noteGuid );
        if( count( $notes ) === 0 ) {
            //error_log( "[DEBUG] Note not in the lib " . $resource->guid );
            // This note is not in our lib and we don't care about it - its probably from another notebook
            return false;
        }

        $existing = $this->get_notes_by_guid( $resource->guid, 'attachment' );
        if ( count( $existing ) > 0 ) {
            $existing = $existing[0];
            return false;

            // if( ! empty( $resource->deleted ) ) {
            //     error_log( "[DEBUG] Evernote Deleting {$resource->guid}" );
            //     wp_trash_post( $existing->ID );
            //     return;
            // } else {
            //     //error_log( "[WARN] Resource edited or not edited at all, not implemented yet " . print_r( $resource, true )  );
            //     return;
            // }
        }

        // If we want to auto-upload all resources
        // TODO: Should this be a setting?
        if ( true ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );

            $tempfile = wp_tempnam();
            file_put_contents( $tempfile, $this->advanced_client->getNoteStore()->getResourceData( $resource->guid ) );
            if ( empty( $resource->attributes->fileName ) ) {
                // TODO: Could create but maybe better not?
                return false;
            }
            $filename = $resource->attributes->fileName;

            $file = array(
                'name'     => wp_hash( $resource->guid ) . "-" . $filename, // This hash is used to obfuscate the file names which should NEVER be exposed.
                'type'     => $resource->mime,
                'tmp_name' => $tempfile,
                'error'    => 0,
                'size'     => filesize( $tempfile ),
            );

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

            error_log( '[DEBUG] UPLOADED resource ' . $resource->guid . ' to : ' . $media_id );
            return $media_id;
        }
        return false;
    }

    /**
     * Get WordPress term id for a notebook by evernote guid.
     * Creates the notebook if it does not exist and is one of the synced ones.
     * 
     * @param string $guid
     * @return int
     */ 
    function get_notebook_by_guid( string $guid ) {
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

    /**
     * Get HTML for WordPress from ENML
     * 
     * @param \EDAM\Types\Note $note
     * @return string
     */
    function get_note_html( \EDAM\Types\Note $note ): string {
        // TODO: Handle resources like <en-media hash="0a35baf77505fa7867468ec2b1b21865" type="audio/m4a" />
        if( empty( $note->content ) ) {
            $note->content = $this->advanced_client->getNoteStore()->getNoteContent( $note->guid );
        }

        $content = '';

        if ( preg_match( '/<en-note[^>]*>(.*?)<\/en-note>/s', $note->content, $matches ) ){
            $content = $matches[1];
        }

        $pattern = '/<en-media .*?hash="(?P<hash>[a-f0-9]+)" type="(?P<type>[^"]+)"[^\/]*?\/>/';
        $content = preg_replace_callback( $pattern, function( $matches ) {
            $attachment = get_posts( [
                'post_type' => 'attachment',
                'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'),
                'meta_query' => [
                    [
                        'key' => 'evernote_content_hash',
                        'value' => $matches['hash'],
                    ],
                ],
            ] );
            if( count( $attachment ) < 1 ) {
                return $matches[0];
            }
            $attachment = $attachment[0];
            $edit_url = admin_url( sprintf( get_post_type_object( 'attachment')->_edit_link . '&action=edit', $attachment->ID ) );
            if ( stristr( $matches['type'], 'image' ) ) {
                $content = sprintf( '<img src="%1$s" alt="%2$s" />', wp_get_attachment_url( $attachment->ID ), $attachment->post_title );
            } else {
                $content = sprintf( '<a target="_blank" href="%1$s">%2$s</a>', $edit_url, $attachment->post_title );
            }

            return sprintf(
                '<div data-en-hash="%1$s" data-en-type="%2$s">%3$s</div>',
                $matches['hash'],
                $matches['type'],
                $content
            );
            
        }, $content );
        return $content;
    }

    /**
     * Santize HTML for ENML. This is a very basic sanitizer.
     * 
     * @param string $html
     * @return string
     */
    static function kses( string $html ): string {
        $permitted_enml_tags =  ['en-media', 'a', 'abbr', 'acronym', 'address', 'area', 'b', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'map', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'tt', 'u', 'ul', 'var', 'xmp'];
        $permitted_protocols = wp_allowed_protocols();
        $permitted_protocols[] = 'evernote'; // For evernote links

        $permittedENMLAttributes = [
            'hash', 'type',
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
        return wp_kses( $html, $kses_list, $permitted_protocols );
    }

    /**
     * Convert HTML to ENML
     * This is the reverse of get_note_html
     * 
     * @param string $html
     * @return string
     */
    static function html2enml( string $html ): string {
        // Media!
        $html = preg_replace( '#<div data-en-hash="(?P<hash>[a-f0-9]+)" data-en-type="(?P<type>[a-z0-9\/]+)">.+?<\/div>#is', '<en-media hash="\\1" type="\\2" />', $html );
        $html = self::kses( $html );

        $html = preg_replace( '/<p[^>]*>/', '<div>', $html );
        $html = preg_replace( '/<\/p>/', '</div>', $html );

        // Strip all comments
        $html = preg_replace( '/<!--.*?-->/', '', $html );
        
        return '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
        <en-note>' . $html . '</en-note>';
    }

    /**
     * Get notes by evernote guid
     * 
     * @param string $guid
     * @param string $post_type
     * @return \WP_Post[]
     */
    function get_notes_by_guid( string $guid, $post_type = null ) {
        if ( ! $post_type ) {
            $post_type = $this->notes_module->id;
        }

        return get_posts( [
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'),
            'meta_query' => [
                [
                    'key' => 'evernote_guid',
                    'value' => $guid,
                ],
            ],
        ] );
    }

    /**
     * Sync a single note from evernote
     * 
     * @param \EDAM\Types\Note $note
     */
    function sync_note( \EDAM\Types\Note $note ) {
        $existing = $this->get_notes_by_guid( $note->guid );

        if ( count( $existing ) > 0 ) {
            $existing = $existing[0];

            if( ! empty( $note->deleted ) ) {
                error_log( "[DEBUG] Evernote Deleting {$note->guid} {$note->title}" );
                wp_trash_post( $existing->ID );
                // Let's also get all attachments attached to this note that come from evernote.
                $attachments = get_posts( [
                    'numberposts' => -1,
                    'fields' => 'ids',
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
                error_log( "[DEBUG] Deleting attachments " . json_encode( $attachments ) );
                foreach( $attachments as $attachment ) {
                    wp_delete_attachment( $attachment );
                }

                return;
            }

            $this->update_note_from_evernote( $note, $existing, true );

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

            remove_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10 );
            $post_id = wp_insert_post( $data );
            wp_set_post_terms( $post_id, [ $this->get_notebook_by_guid( $note->notebookGuid ) ], 'notebook', true );
            add_action( 'save_post_' . $this->notes_module->id, [ $this, 'sync_note_to_evernote' ], 10, 3 );

            if ( ! empty ( $note->resources ) ) {
                $this->update_note_from_evernote( $note, get_post( $post_id ), true );
            }
        }
    }

}
