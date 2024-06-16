<?php

/**
 * This is a module for syncing with Evernote.
 * It syncs both ways, notes and resources
 *
 * TODO: Comment everything, include strong typing, write unit tests for.
 * TODO: The WP <-> ENML conversion is hacky, it would be awesome if it used blocks, and just transformed blocks into enml and vice versa. Since ENML does not support comments, it could store the metadata in style the same ENML todos are stored.
 * TODO: when something goes wrong in syncing a chunk, the rest of the changes are not synced. we should probably update USN after each successful note so that when we have an error, sync is picked up from the point it failed.
 *
 */
class Evernote extends External_Service_Module {
	public $id               = 'evernote';
	public $name             = 'Evernote';
	public $description      = 'Syncs with evernote service';
	public $parent_notebook  = null;
	public $simple_client    = null;
	public $advanced_client  = null;
	private $token           = null;
	public $synced_notebooks = array();

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	public $settings = array(
		'token'            => array(
			'type'  => 'text',
			'name'  => 'Evernote Developer API Token',
			'label' => 'You can get it from <a href="https://www.evernote.com/api/DeveloperToken.action">here</a>',
		),
		'synced_notebooks' => array(
			'type'  => 'callback',
			'name'  => 'Synced notebooks',
			'label' => 'Comma separated list of notebooks to sync',
		),
	);

	public $notes_module = null;

	public function __construct( \POS_Module $notes_module ) {
		$this->notes_module = $notes_module;
		$this->token        = $this->get_setting( 'token' );
		if ( ! $this->token ) {
			return false;
		}
		$this->settings['synced_notebooks']['callback'] = array( $this, 'synced_notebooks_setting_callback' );
		$this->register_sync( 'hourly' );
		$this->register_meta( 'evernote_guid', $this->notes_module->id );
		$this->register_meta( 'evernote_content_hash', $this->notes_module->id );

		add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_post_evernote_resource', array( $this, 'proxy_media' ) );
	}

	public function proxy_media() {
		if ( ! isset( $_GET['evernote_guid'] ) ) {
			return;
		}
		$guid = sanitize_text_field( $_GET['evernote_guid'] );
		$this->connect();
		// TODO figure out this streaming trick
		//$user = $this->advanced_client->getUserStore()->getUser();
		// $userInfo = $this->advanced_client->getUserStore()->getPublicUserInfo( $user->username );
		// $resUrl = $userInfo->webApiUrlPrefix . '/res/' . $guid;

		$file = $this->advanced_client->getNoteStore()->getResource( $guid, true, false, true, false );
		header( "Content-Disposition: inline;filename={$file->attributes->fileName}" );
		header( "Content-Type: {$file->mime}" );
		//phpcs:ignore WordPress.Security.EscapeOutput
		echo $file->data->body;
		die();
	}

	public function get_note_evernote_notebook_guid( int $id ) : array|false {
		$notebooks = wp_get_post_terms(
			$id,
			'notebook',
			array(
				'fields'   => 'ids',
				'meta_key' => 'evernote_notebook_guid',
				//'meta_value' => $this->get_setting( 'synced_notebooks' ),
			)
		);
		if ( empty( $notebooks ) ) {
			return false;
		}
		return array(
			'guid' => get_term_meta( $notebooks[0], 'evernote_notebook_guid', true ),
			'id'   => $notebooks[0],
		);
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
	public function sync_note_to_evernote( int $post_id, \WP_Post $post, bool $update ) {
		//return;//  Off for now
		$this->connect();
		$guid = get_post_meta( $post->ID, 'evernote_guid', true );

		if ( $guid ) {
			$note = $this->advanced_client->getNoteStore()->getNote( $guid, false, false, false, false );
			if ( $note ) {
				$note->title   = $post->post_title;
				$note->content = self::html2enml( $post->post_content );

				try {
					$result = $this->advanced_client->getNoteStore()->updateNote( $note );
					$this->update_note_from_evernote( $result, $post );
				} catch ( \EDAM\Error\EDAMSystemException $e ) {
					// Silently fail because conflicts and stuff.
					$this->log( 'Evernote: ' . $e->getMessage() . ' ' . print_r( $post, true ), E_USER_WARNING );
				} catch ( \EdAM\Error\EDAMUserException $e ) {
					$this->log( 'Evernote ENML probably misformatted: ' . $e->getMessage() . ' While saving: ' . $note->content, E_USER_WARNING );
				}
			}
			return;
		}

		// There is something like "main category" in WordPress, but whatever
		$notebook       = new \Evernote\Model\Notebook();
		$notebook->guid = $this->get_note_evernote_notebook_guid( $post->ID )['guid'];
		if ( ! $notebook->guid || ! in_array( $notebook->guid, $this->get_setting( 'synced_notebooks' ), true ) ) {
			return;
		}

		// edam note
		$note               = new \EDAM\Types\Note();
		$note->title        = $post->post_title;
		$note->content      = self::html2enml( $post->post_content );
		$note->notebookGuid = $notebook->guid;
		try {
			$result = $this->advanced_client->getNoteStore()->createNote( $note );
			if ( $result ) {
				$this->update_note_from_evernote( $result, $post );
			}
		} catch ( \EDAM\Error\EDAMUserException $e ) {
			$this->log( "Evernote ENML probably misformatted: '" . $e->getMessage() . '" . While saving: ' . $note->content, E_USER_WARNING );
		}

	}

	/**
	 * This is called when a note is updated from evernote.
	 * It will update the post with the new data.
	 * It is triggered by both directions of the sync:
	 * - When a note is updated in evernote, it will be updated in WordPress
	 * - When a note is updated in WordPress, it will be updated in evernote and then the return will be passed here.
	 *
	 * @param \EDAM\Types\Note $note
	 * @param \WP_Post $post
	 * @param bool $sync_resources - If true, it will also upload note resources. We want this in most cases, EXCEPT when we are sending the data from WordPress and know the response will not have new resources for us.
	 */
	public function update_note_from_evernote( \EDAM\Types\Note $note, \WP_Post $post, $sync_resources = false ) {
		remove_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10 );

		$update_array          = array();
		$force_rewrite_content = false;
		if ( ! empty( $note->resources ) && $sync_resources ) {
			// Even though content did not change, we uploaded media and have to rewrite the content with new media.
			$force_rewrite_content = true;
			foreach ( $note->resources as $resource ) {
				$media_id = $this->sync_resource( $resource );
			}
		}
		if ( $force_rewrite_content || bin2hex( $note->contentHash ) !== get_post_meta( $post->ID, 'evernote_content_hash', true ) ) {
			$this->log( 'Content hashes:  new: ' . bin2hex( $note->contentHash ) . ' old: ' . get_post_meta( $post->ID, 'evernote_content_hash', true ) );
			// we are assuming resources only changed when note content changed
			$update_array['post_content'] = $this->get_note_html( $note );
			if ( ! isset( $update_array['meta_input'] ) ) {
				$update_array['meta_input'] = array();
			}
			$update_array['meta_input']['evernote_content_hash'] = bin2hex( $note->contentHash );
		}

		$stored_guid = get_post_meta( $post->ID, 'evernote_guid', true );
		if ( ! $stored_guid || $stored_guid !== $note->guid ) {
			if ( ! isset( $update_array['meta_input'] ) ) {
				$update_array['meta_input'] = array();
			}
			$update_array['meta_input']['evernote_guid'] = $note->guid;
		}

		$updated = floor( $note->updated / 1000 );
		if ( $updated > strtotime( $post->post_modified ) ) {
			$update_array['post_modified'] = gmdate( 'Y-m-d H:i:s', $updated );
		}

		if ( $note->title !== $post->post_title ) {
			$update_array['post_title'] = $note->title;
		}

		if ( ! empty( $note->attributes->sourceURL ) && get_post_meta( $post->ID, 'url', true ) !== $note->attributes->sourceURL ) {
			if ( ! isset( $update_array['meta_input'] ) ) {
				$update_array['meta_input'] = array();
			}
			$update_array['meta_input']['url'] = $note->attributes->sourceURL;
		}

		$current_notebook = $this->get_note_evernote_notebook_guid( $post->ID );
		if ( ! $current_notebook ) {
			wp_set_object_terms( $post->ID, $this->get_notebook_by_guid( $note->notebookGuid ), 'notebook', true );
		} elseif ( $current_notebook['guid'] !== $note->notebookGuid ) {
			wp_remove_object_terms( $post->ID, $current_notebook['id'], 'notebook' );
			wp_set_object_terms( $post->ID, $this->get_notebook_by_guid( $note->notebookGuid ), 'notebook', true );
		}
		// We are removing this filter so that kses wont strip the `evernote` scheme
		//$post_kses_filter_removed = remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( count( $update_array ) > 0 ) {
			$update_array['ID'] = $post->ID;
			$this->log( "Evernote: Updating post from evernote {$post->ID} {$note->guid} " . print_r( $update_array, true ) );
			wp_update_post( $update_array );
			//error_log( 'CONTENT POST UPDATE: '. get_post($post->ID)->post_content );
		}
		// if ( $post_kses_filter_removed ) {
		//     add_filter( 'content_save_pre', 'wp_filter_post_kses' );
		// }
		add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );
	}

	/**
	 * This is a helper function to get the app link for a note. This URL opens the note in the Evernote app.
	 */
	public function get_app_link_from_guid( string $guid ) {
		$user     = $this->advanced_client->getUserStore()->getUser();
		$note_url = "evernote:///view/{$user->id}/{$user->shardId}/{$guid}/{$guid}/";
		return $note_url;
	}

	public function register_rest_routes() {
		register_rest_route(
			$this->rest_namespace,
			'/evernote-redirect/(?P<post_id>\w+)/',
			array(
				'methods'             => 'GET',
				'callback'            => function( $request ) {
					$this->connect();
					$post_id  = $request->get_param( 'post_id' );
					$guid     = get_post_meta( $post_id, 'evernote_guid', true );
					$note_url = $this->get_app_link_from_guid( $guid );
					header( "Location: $note_url" );
					die();
				},
				'permission_callback' => '__return_true',
			)
		);
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
	public function synced_notebooks_setting_callback( string $option_name, $value, $setting ) {
		// TODO: create notebooks here.
		nl2br( print_r( $value ) );
		$this->connect();
		if ( ! $this->simple_client ) {
			echo '<p>Please enter a valid token</p>';
			return;
		}
		$notebooks = $this->simple_client->listNotebooks();
		$stacks    = array();
		$nostack   = array();
		foreach ( $notebooks as $notebook ) {
			$n = $notebook->getEdamNotebook();
			if ( $n && $n->stack ) {
				if ( ! isset( $stacks[ $n->stack ] ) ) {
					$stacks[ $n->stack ] = array();
				}
				$stacks[ $n->stack ][ $n->name ] = $n;
			} else {
				$nostack[] = $n;
			}
		}
		ksort( $stacks );

		echo "<ul style='list-style:initial'>";
		foreach ( $stacks as $stackname => $stack ) {
			printf(
				'<li><b>%s</b><ul style="list-style:initial; padding-left: 25px">',
				esc_html( $stackname ),
			);
			ksort( $stack );

			foreach ( $stack as $n ) {
				printf(
					'<li><input name="%s[]" type="checkbox" value="%s" %s>%s</li>',
					esc_attr( $option_name ),
					esc_attr( $n->guid ),
					( is_array( $value ) && in_array( $n->guid, $value, true ) ) ? 'checked' : '',
					esc_attr( $n->name )
				);
			}
			echo '</ul></li>';
		}
		echo '</ul>';
	}

	/**
	 * Connect to Evernote and create a client
	 */
	protected function connect() {
		require_once plugin_dir_path( __FILE__ ) . '/../../vendor/autoload.php';
		$this->simple_client   = new \Evernote\Client( $this->token, false );
		$this->advanced_client = new \Evernote\AdvancedClient( $this->token, false );
		return $this->simple_client;
	}

	/**
	 * Sync with Evernote. This is triggered by the cron job.
	 *
	 * @see register_sync
	 */
	public function sync() {
		$this->log( 'Syncing Evernote triggering ' );
		$this->connect();
		$this->synced_notebooks = $this->get_setting( 'synced_notebooks' );
		if ( empty( $this->synced_notebooks ) || ! $this->advanced_client ) {
			return array();
		}
		$usn               = get_option( $this->get_setting_option_name( 'usn' ), 0 );
		$last_sync         = get_option( $this->get_setting_option_name( 'last_sync' ), 0 );
		$last_update_count = get_option( $this->get_setting_option_name( 'last_update_count' ), 0 );
		$sync_state        = $this->advanced_client->getNoteStore()->getSyncState();
		$sync_filter       = new \EDAM\NoteStore\SyncChunkFilter(
			array(
				'includeNotes'          => true,
				'includeNotebooks'      => false,
				'includeNoteAttributes' => true,
				'includeExpunged'       => false,
				//'includeResources' => true,
				'includeNoteResources'  => true,
			//'notebookGuids' => $notebooks,
			)
		);
		$sync_filter->includeExpunged = false;
		update_option( $this->get_setting_option_name( 'last_sync' ), $sync_state->currentTime );
		update_option( $this->get_setting_option_name( 'last_update_count' ), $sync_state->updateCount );

		if ( $sync_state->updateCount === $last_update_count || $sync_state->updateCount === $usn ) {
			$this->log( 'Evernote: No updates since last sync' );
			return;
		}

		if ( $sync_state->fullSyncBefore > $last_sync ) {
			// Retriggering full sync
			$usn = 0;
		}

		$sync_chunk = $this->advanced_client->getNoteStore()->getFilteredSyncChunk( $usn, 100, $sync_filter );
		if ( ! $sync_chunk ) {
			$this->log( 'Evernote: Sync failed', E_USER_WARNING );
			return;
		}
		if ( ! empty( $sync_chunk->chunkHighUSN ) && $sync_chunk->chunkHighUSN > $usn ) {
			// We want to unschedule any regular sync events until we have initial sync complete.
			wp_unschedule_hook( $this->get_sync_hook_name() );
			// We will schedule ONE TIME sync event for the next page.
			update_option( $this->get_setting_option_name( 'usn' ), $sync_chunk->chunkHighUSN );
			wp_schedule_single_event( time() + 60, $this->get_sync_hook_name() );
			$this->log( "Scheduling next page chunk with cursor {$sync_chunk->chunkHighUSN}" );
		} else {
			$this->log( 'Evernote: Full sync completed' );
		}

		if ( ! empty( $sync_chunk->notes ) ) {
			// We are going to be updating notes and we don't want the hook to loop
			remove_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10 );
			foreach ( $sync_chunk->notes as $note ) {
				$this->sync_note( $note );
			}
			add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );
		}

		// Now that notes are synced, we are going to sync the resources
		if ( ! empty( $sync_chunk->resources ) ) {
			$this->log( 'Syncing resources' );
			foreach ( $sync_chunk->resources as $resource ) {
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
	public function sync_resource( \EDAM\Types\Resource $resource ): int|false {
		$notes = $this->get_notes_by_guid( $resource->noteGuid );
		if ( count( $notes ) === 0 ) {
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
		// phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found
		if ( true ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tempfile = wp_tempnam();
			global $wp_filesystem;
			WP_Filesystem();
			$wp_filesystem->put_contents( $tempfile, $this->advanced_client->getNoteStore()->getResourceData( $resource->guid ) );
			if ( empty( $resource->attributes->fileName ) ) {
				$extension = self::get_extension_from_mime( $resource->mime );
				if ( $extension === false ) {
					return false;
				}

				$filename = $resource->guid . '.' . $extension;
			} else {
				$filename = $resource->attributes->fileName;
			}

			$file = array(
				'name'     => wp_hash( $resource->guid ) . '-' . $filename, // This hash is used to obfuscate the file names which should NEVER be exposed.
				'type'     => $resource->mime,
				'tmp_name' => $tempfile,
				'error'    => 0,
				'size'     => filesize( $tempfile ),
			);

			$data     = array(
				'post_status' => 'private', // Always default to private instead of inherit because https://piszek.com/2024/02/17/wordpress-custom-post-types-and-read-permission-in-rest/
				'post_title'  => preg_replace( '/\.[^.]+$/', '', wp_basename( $filename ) ),
				'meta_input'  => array(
					'evernote_guid'         => $resource->guid,
					'evernote_content_hash' => bin2hex( $resource->data->bodyHash ),
				),
			);
			$media_id = media_handle_sideload( $file, $notes[0]->ID, null, $data );

			if ( is_wp_error( $media_id ) ) {
				$this->log( 'Error uploading file:' . print_r( array( $media_id->get_error_message(), $resource->mime, $filename ), true ), E_USER_WARNING );
				return false;
			}

			if ( stristr( $resource->mime, 'audio' ) ) { // if auto transcribe audio
				// We have to schedule transcription ourselves because the mime is not ready yet at this time.
				update_post_meta( $media_id, 'pos_transcribe', 1 );
				wp_schedule_single_event( time() + 10, 'pos_transcription', array( $media_id ) );
			}

			$this->log( 'UPLOADED resource ' . $resource->guid . ' to : ' . $media_id );
			return $media_id;
		}
		return false;
	}

	public static function get_extension_from_mime( $mime ) {
		$extension_to_mime = wp_get_mime_types();
		$mime_to_extension = array_flip( $extension_to_mime );
		// Evernote specific types
		$mime_to_extension['audio/m4a'] = 'm4a';
		$mime_to_extension['audio/amr'] = 'amr';
		if ( empty( $mime_to_extension[ $mime ] ) ) {
			return false;
		}

		$extensions = explode( '|', $mime_to_extension[ $mime ] );
		return $extensions[0];
	}
	/**
	 * Get WordPress term id for a notebook by evernote guid.
	 * Creates the notebook if it does not exist and is one of the synced ones.
	 *
	 * @param string $guid
	 * @return int
	 */
	public function get_notebook_by_guid( string $guid ) {
		$args  = array(
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'     => 'evernote_notebook_guid',
					'value'   => $guid,
					'compare' => 'LIKE',
				),
			),
			'taxonomy'   => 'notebook',
		);
		$terms = get_terms( $args );
		if ( count( $terms ) > 0 ) {
			return $terms[0]->term_id;
		}

		// We have to create this notebook, under "Evernote" parent

		$notebook = $this->advanced_client->getNoteStore()->getNotebook( $guid );
		$this->log( 'Evernote Creating ' . print_r( $notebook, true ) );
		$name = $notebook->name;

		if ( ! $this->parent_notebook ) {
			$this->parent_notebook = get_term_by( 'slug', 'evernote', 'notebook' );
		}
		if ( ! $this->parent_notebook ) {
			wp_insert_term( 'Evernote', 'notebook', array( 'slug' => 'evernote' ) );
			$this->parent_notebook = get_term_by( 'slug', 'evernote', 'notebook' );
		}
		$term = wp_insert_term( $name, 'notebook', array( 'parent' => $this->parent_notebook->term_id ) );
		update_term_meta( $term['term_id'], 'evernote_notebook_guid', $guid );
		return $term['term_id'];
	}

	public static function enml2html( $content ) {
		if ( preg_match( '/<en-note[^>]*>(.*?)<\/en-note>/s', $content, $matches ) ) {
			$content = $matches[1];
		}

		$content = preg_replace_callback( '/<en-media(?P<pre>[^>]*?)hash="(?P<hash>[a-f0-9]+)"(?P<middle>[^>]*?)type="(?P<type>[^"]+)"(?P<post>[^>]*?)\/>/', array( '\Evernote', 'en_media_replace_callback' ), $content );
		$content = preg_replace_callback( '/<en-media(?P<pre>[^>]*?)type="(?P<type>[^"]+)"(?P<middle>[^>]*?)hash="(?P<hash>[a-f0-9]+)"(?P<post>[^>]*?)\/>/', array( '\Evernote', 'en_media_replace_callback' ), $content );
		$content = preg_replace_callback( '/<en-media(?P<pre>[^>]*?)hash="(?P<hash>[a-f0-9]+)"(?P<middle>[^>]*?)type="(?P<type>[^"]+)"(?P<post>[^>]*?)><\/en-media>/', array( '\Evernote', 'en_media_replace_callback' ), $content );
		$content = preg_replace_callback( '/<en-media(?P<pre>[^>]*?)type="(?P<type>[^"]+)"(?P<middle>[^>]*?)hash="(?P<hash>[a-f0-9]+)"(?P<post>[^>]*?)><\/en-media>/', array( '\Evernote', 'en_media_replace_callback' ), $content );
		$content = preg_replace_callback(
			'/<en-todo .*?checked="(?P<checked>[^"]+)"[^\/]*?\/>/',
			function( $match ) {
				$checked = 'o';
				if ( $match['checked'] === 'true' ) {
					$checked = 'x';
				}
				return '<span class="pos-evernote-todo">' . $checked . '</span>';
			},
			$content
		);
		$content = preg_replace_callback(
			'/<a(?P<prehref>[^>]*?)href=[\'"](?P<href>evernote\:[^\'"]+)[\'"](?P<posthref>[^>]*?)>/',
			function( $match ) {
				//regex to match evernote links
				if ( ! preg_match( '/evernote:\/\/\/view\/(\d+)\/(s\d+)\/([a-f0-9\-]+)\/([a-f0-9\-]+)/', $match['href'], $evernote_link ) ) {
					// Something wrong
					return $match[0];
				}
				$evernote_web_url = "https://www.evernote.com/shard/{$evernote_link[2]}/nl/{$evernote_link[1]}/{$evernote_link[3]}";
				return sprintf(
					'<a%1$shref="%2$s" data-evernote-link="%3$s"%4$s>',
					$match['prehref'],
					$evernote_web_url,
					$match['href'],
					$match['posthref']
				);
			},
			$content
		);
		return $content;
	}
	/**
	 * Get HTML for WordPress from ENML
	 *
	 * @param \EDAM\Types\Note $note
	 * @return string
	 */
	public function get_note_html( \EDAM\Types\Note $note ): string {
		if ( empty( $note->content ) ) {
			$note->content = $this->advanced_client->getNoteStore()->getNoteContent( $note->guid );
		}

		return self::enml2html( $note->content );
	}

	public static function en_media_replace_callback( $matches ) {
		$attachment = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' ),
				'meta_query'  => array(
					array(
						'key'   => 'evernote_content_hash',
						'value' => $matches['hash'],
					),
				),
			)
		);
		if ( count( $attachment ) > 0 ) {
			$title    = $attachment[0]->post_title;
			$edit_url = admin_url( sprintf( get_post_type_object( 'attachment' )->_edit_link . '&action=edit', $attachment[0]->ID ) );
			$file_url = wp_get_attachment_url( $attachment[0]->ID );
		} else {
			$title    = 'Evernote Resource';
			$file_url = admin_url( "admin-post.php?action=evernote_resource&evernote_hash={$matches['hash']}" );
			$edit_url = $file_url;
		}

		if ( stristr( $matches['type'], 'image' ) ) {
			$content = sprintf( '<img src="%1$s" data-evernote-link="%2$s" />', $file_url, $title );
		} else {
			$content = sprintf( '<a target="_blank" href="%1$s">%2$s</a>', $edit_url, $title );
		}

		return sprintf(
			'<div%1$sdata-evernote-hash="%2$s"%3$sdata-evernote-type="%4$s"%5$s>%6$s</div>',
			$matches['pre'],
			$matches['hash'],
			$matches['middle'],
			$matches['type'],
			$matches['post'],
			$content
		);

	}

	/**
	 * Santize HTML for ENML. This is a very basic sanitizer.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function kses( string $html ): string {
		$permitted_enml_tags   = array( 'en-todo', 'en-media', 'a', 'abbr', 'acronym', 'address', 'area', 'b', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'map', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'tt', 'u', 'ul', 'var', 'xmp' );
		$permitted_protocols   = wp_allowed_protocols();
		$permitted_protocols[] = 'evernote'; // For evernote links

		$permitted_enml_attributes = array(
			'hash',
			'type',
			'evernote-link',
			'abbr',
			'accept',
			'accept-charset',
			'accesskey',
			'action',
			'align',
			'alink',
			'alt',
			'archive',
			'axis',
			'background',
			'bgcolor',
			'border',
			'cellpadding',
			'cellspacing',
			'char',
			'charoff',
			'charset',
			'checked',
			'cite',
			'clear',
			'code',
			'codebase',
			'codetype',
			'color',
			'cols',
			'colspan',
			'compact',
			'content',
			'coords',
			'data',
			'datetime',
			'declare',
			'defer',
			'dir',
			'disabled',
			'enctype',
			'face',
			'for',
			'frame',
			'frameborder',
			'headers',
			'height',
			'href',
			'hreflang',
			'hspace',
			'http-equiv',
			'ismap',
			'label',
			'lang',
			'language',
			'link',
			'longdesc',
			'marginheight',
			'marginwidth',
			'maxlength',
			'media',
			'method',
			'multiple',
			'name',
			'nohref',
			'noresize',
			'noshade',
			'nowrap',
			'object',
			'onblur',
			'onchange',
			'onclick',
			'ondblclick',
			'onfocus',
			'onkeydown',
			'onkeypress',
			'onkeyup',
			'onload',
			'onmousedown',
			'onmousemove',
			'onmouseout',
			'onmouseover',
			'onmouseup',
			'onreset',
			'onselect',
			'onsubmit',
			'onunload',
			'profile',
			'prompt',
			'readonly',
			'rel',
			'rev',
			'rows',
			'rowspan',
			'rules',
			'scheme',
			'scope',
			'scrolling',
			'selected',
			'shape',
			'size',
			'span',
			'src',
			'standby',
			'start',
			'style',
			'summary',
			'target',
			'text',
			'title',
			'type',
			'usemap',
			'valign',
			'value',
			'valuetype',
			'version',
			'vlink',
			'vspace',
			'width',
		);

		$kses_list = array();
		foreach ( $permitted_enml_tags as $tag ) {
			$kses_list[ $tag ] = array();
			// Yeah not all attributes are for all tags, but we are going to be lazy
			foreach ( $permitted_enml_attributes as $attr ) {
				$kses_list[ $tag ][ $attr ] = true;
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
	public static function html2enml( string $html ): string {
		// Media!
		$html = preg_replace( '#<div(?P<pre>[^>]*?)data-evernote-hash="(?P<hash>[a-f0-9]+)"(?P<middle>[^>]*?)data-evernote-type="(?P<type>[a-z0-9\/]+)"(?P<post>[^>]*?)>.+?<\/div>#is', '<en-media\\1hash="\\2"\\3type="\\4"\\5/>', $html );
		$html = preg_replace( '/<a(?P<prehref>[^>]*?)href="(?P<href>[^"]+)" data-evernote-link="(?P<evlink>evernote\:[^"]+)"(?P<posthref>[^>]*?)>/is', '<a\\1href="\\3"\\4>', $html );

		$html = preg_replace_callback(
			'#<span class="pos-evernote-todo">([a-z]*)<\/span>#',
			function( $match ) {
				$checked = ( strtolower( $match[1] ) === 'x' ) ? 'true' : 'false';
				return '<en-todo checked="' . $checked . '"/>';
			},
			$html
		);
		$html = self::kses( $html );

		$html = preg_replace( '/<p[^>]*>/', '<div>', $html );
		$html = preg_replace( '/<\/p>/', '</div>', $html );

		// Strip all comments
		$html = preg_replace( '/<!--.*?-->/', '', $html );

		return self::wrap_note( $html );
	}

	public static function wrap_note( $html ) {
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
	public function get_notes_by_guid( string $guid, $post_type = null ) {
		if ( ! $post_type ) {
			$post_type = $this->notes_module->id;
		}

		return get_posts(
			array(
				'post_type'   => $post_type,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' ),
				'meta_query'  => array(
					array(
						'key'   => 'evernote_guid',
						'value' => $guid,
					),
				),
			)
		);
	}

	/**
	 * Sync a single note from evernote
	 *
	 * @param \EDAM\Types\Note $note
	 */
	public function sync_note( \EDAM\Types\Note $note ) {
		$existing = $this->get_notes_by_guid( $note->guid );

		if ( count( $existing ) > 0 ) {
			$existing = $existing[0];

			if ( ! empty( $note->deleted ) ) {
				$this->log( "Evernote Deleting {$note->guid} {$note->title}" );
				wp_trash_post( $existing->ID );
				// Let's also get all attachments attached to this note that come from evernote.
				$attachments = get_posts(
					array(
						'numberposts' => -1,
						'fields'      => 'ids',
						'post_type'   => 'attachment',
						'post_parent' => $existing->ID,
						'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' ),
						'meta_query'  => array(
							'relation' => 'AND',
							array(
								'key'     => 'evernote_guid',
								'compare' => 'EXISTS',
							),
						),
					)
				);
				$this->log( 'Deleting attachments ' . wp_json_encode( $attachments ) );
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment );
				}

				return;
			}

			$this->update_note_from_evernote( $note, $existing, true );

		} elseif ( empty( $note->deleted ) ) {
			if ( ! in_array( $note->notebookGuid, $this->synced_notebooks ) ) {
				return;
			}
			$this->log( "Evernote Creating {$note->title}" );
			$data = array(
				'post_title'   => $note->title,
				'post_type'    => $this->notes_module->id,
				'post_content' => $this->get_note_html( $note ),
				'post_status'  => 'publish',
				'post_date'    => gmdate( 'Y-m-d H:i:s', $note->created / 1000 ),
				'meta_input'   => array(
					'evernote_guid'         => $note->guid,
					'evernote_content_hash' => bin2hex( $note->contentHash ),
				),
			);
			if ( ! empty( $note->attributes->sourceURL ) ) {
				$data['meta_input']['url'] = $note->attributes->sourceURL;
			}

			remove_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10 );

			// we are removing this filter because we have already ran this through kses.
			//$filter_removed = remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
			$post_id = wp_insert_post( $data );
			// if ( $filter_removed ) {
			//     add_filter( 'content_save_pre', 'wp_filter_post_kses' );
			// }
			//error_log( 'CONTENT AFTER SAVING:' . get_post($post_id)->post_content );

			wp_set_post_terms( $post_id, array( $this->get_notebook_by_guid( $note->notebookGuid ) ), 'notebook', true );
			add_action( 'save_post_' . $this->notes_module->id, array( $this, 'sync_note_to_evernote' ), 10, 3 );

			if ( ! empty( $note->resources ) ) {
				$this->update_note_from_evernote( $note, get_post( $post_id ), true );
			}
		}
	}

}
