<?php
/**
 * Personal Evernote Sync package.
 *
 * @package PersonalEvernoteSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Split packages use their package text domain while root PHPCS still expects personalos.

/**
 * Syncs Evernote notes into Knowledge.
 */
class Personal_Evernote_Sync_Plugin extends PersonalOS_Sync_Plugin_Base {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-evernote-sync',
				'display_name' => 'Personal Evernote Sync',
				'text_domain'  => 'personal-evernote-sync',
				'version'      => PERSONAL_EVERNOTE_SYNC_VERSION,
				'plugin_file'  => PERSONAL_EVERNOTE_SYNC_FILE,
				'app'          => array(
					'path'         => 'evernote',
					'name'         => 'Evernote Sync',
					'action_label' => 'Open App',
					'capability'   => 'read',
					'icon'         => 'dashicons-media-document',
				),
				'settings'     => array(
					'token'             => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => '',
					),
					'synced_notebooks'  => array(
						'type'    => 'array',
						'scope'   => 'user',
						'default' => array(),
					),
					'active'            => array(
						'type'    => 'bool',
						'scope'   => 'user',
						'default' => '',
					),
					'usn'               => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => 0,
					),
					'last_sync'         => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => 0,
					),
					'last_update_count' => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => 0,
					),
					'cached_data'       => array(
						'type'    => 'textarea',
						'scope'   => 'user',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Register hooks and runtime integrations.
	 *
	 * @return void
	 */
	public function register() {
		$this->vocabulary()->register_type_labels();
		$this->register_missing_knowledge_notice();
		$this->register_wp_app( array( $this, 'render_settings_page' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		$this->register_common_knowledge_meta();
		$this->knowledge()->register_post_meta( 'evernote_guid' );
		$this->knowledge()->register_post_meta( 'evernote_content_hash' );
		$this->knowledge()->register_type_meta( 'evernote_notebook_guid' );
		$this->knowledge()->register_type_meta( 'evernote_type' );

		if ( ! empty( $this->get_active_user_ids() ) ) {
			$this->register_sync( 'hourly' );
		}
	}

	/**
	 * Add settings menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$this->add_admin_page_hook(
			add_options_page(
				'Personal Evernote Sync',
				'Personal Evernote Sync',
				'read',
				'personal-evernote-sync',
				array( $this, 'render_settings_page' )
			)
		);
	}

	/**
	 * Render per-user settings.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_POST['personal_evernote_sync_action'] ) ) {
			check_admin_referer( 'personal_evernote_sync_settings' );
			$this->update_setting( 'token', sanitize_text_field( wp_unslash( $_POST['personal_evernote_sync_token'] ?? '' ) ), get_current_user_id() );
			$this->update_setting( 'active', ! empty( $_POST['personal_evernote_sync_active'] ) ? '1' : '', get_current_user_id() );
			$this->update_setting( 'synced_notebooks', array_filter( array_map( 'sanitize_text_field', explode( "\n", wp_unslash( $_POST['personal_evernote_sync_synced_notebooks'] ?? '' ) ) ) ), get_current_user_id() );
		}

		$synced_notebooks = $this->get_setting( 'synced_notebooks', get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->app_display_name() ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'personal_evernote_sync_settings' ); ?>
				<input type="hidden" name="personal_evernote_sync_action" value="save">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="personal_evernote_sync_token"><?php esc_html_e( 'Evernote Developer Token', 'personal-evernote-sync' ); ?></label></th>
							<td><input class="regular-text" type="password" id="personal_evernote_sync_token" name="personal_evernote_sync_token" value="<?php echo esc_attr( $this->get_setting( 'token', get_current_user_id() ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active', 'personal-evernote-sync' ); ?></th>
							<td><label><input type="checkbox" name="personal_evernote_sync_active" value="1" <?php checked( $this->get_setting( 'active', get_current_user_id() ) ); ?>> <?php esc_html_e( 'Run sync for my Evernote account.', 'personal-evernote-sync' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="personal_evernote_sync_synced_notebooks"><?php esc_html_e( 'Synced notebook GUIDs', 'personal-evernote-sync' ); ?></label></th>
							<td><textarea class="large-text" rows="5" id="personal_evernote_sync_synced_notebooks" name="personal_evernote_sync_synced_notebooks"><?php echo esc_textarea( implode( "\n", (array) $synced_notebooks ) ); ?></textarea></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Settings', 'personal-evernote-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sync all active users.
	 *
	 * @return void
	 */
	public function sync() {
		foreach ( $this->get_active_user_ids() as $user_id ) {
			try {
				$this->run_for_user( $user_id, array( $this, 'sync_user' ) );
			} catch ( Exception $exception ) {
				$this->log( 'Evernote sync failed for user ' . $user_id . ': ' . $exception->getMessage(), E_USER_WARNING );
			}
		}
	}

	/**
	 * Sync one user's Evernote account.
	 *
	 * This keeps the split package boundary in place. The detailed ENML/resource
	 * conversion from the monolith can move here without depending on Notes.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function sync_user( $user_id ) {
		$token            = $this->get_setting( 'token', $user_id );
		$synced_notebooks = (array) $this->get_setting( 'synced_notebooks', $user_id );

		if ( ! $this->get_setting( 'active', $user_id ) || ! $token || empty( $synced_notebooks ) ) {
			return false;
		}

		$payload = $this->fetch_notes_for_user( $user_id, $token, $synced_notebooks );
		if ( is_wp_error( $payload ) ) {
			$this->log( 'Evernote sync failed for user ' . $user_id . ': ' . $payload->get_error_message(), E_USER_WARNING );
			return false;
		}

		$notes  = isset( $payload['notes'] ) ? (array) $payload['notes'] : (array) $payload;
		$synced = 0;

		foreach ( $notes as $note ) {
			if ( ! $this->should_sync_note( $note, $synced_notebooks ) ) {
				continue;
			}

			$result = $this->upsert_note( $note, $user_id );
			if ( is_wp_error( $result ) ) {
				$this->log( 'Evernote note sync failed for user ' . $user_id . ': ' . $result->get_error_message(), E_USER_WARNING );
				continue;
			}

			if ( $result ) {
				++$synced;
			}
		}

		if ( isset( $payload['last_sync'] ) ) {
			$this->update_setting( 'last_sync', (string) $payload['last_sync'], $user_id );
		} else {
			$this->update_setting( 'last_sync', gmdate( 'c' ), $user_id );
		}

		if ( isset( $payload['usn'] ) ) {
			$this->update_setting( 'usn', (string) absint( $payload['usn'] ), $user_id );
		}

		if ( isset( $payload['last_update_count'] ) ) {
			$this->update_setting( 'last_update_count', (string) absint( $payload['last_update_count'] ), $user_id );
		}

		if ( isset( $payload['cached_data'] ) ) {
			$this->update_setting( 'cached_data', is_string( $payload['cached_data'] ) ? $payload['cached_data'] : wp_json_encode( $payload['cached_data'] ), $user_id );
		}

		if ( ! empty( $payload['has_more'] ) ) {
			$this->unschedule_sync();
			$this->schedule_single_sync( 60 );
		}

		return $synced;
	}

	/**
	 * Upsert a normalized Evernote note object into Knowledge.
	 *
	 * @param object $note    Evernote-like note object.
	 * @param int    $user_id User ID.
	 * @return int|WP_Error|null
	 */
	public function upsert_note( $note, $user_id ) {
		if ( empty( $note->guid ) ) {
			return null;
		}

		$post = $this->find_by_guid( $note->guid, $user_id );
		$content = isset( $note->content ) ? (string) $note->content : '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$hash = ! empty( $note->contentHash ) ? bin2hex( $note->contentHash ) : hash( 'sha256', $content );
		$meta = array(
			'evernote_guid'           => sanitize_text_field( $note->guid ),
			'evernote_content_hash'   => $hash,
			'_personalos_external_id' => sanitize_text_field( $note->guid ),
			'_personalos_synced_at'   => gmdate( 'c' ),
			'_personalos_source_hash' => $hash,
		);

		if ( ! empty( $note->attributes->sourceURL ) ) {
			$meta['url'] = esc_url_raw( $note->attributes->sourceURL );
			$meta['_personalos_source_url'] = esc_url_raw( $note->attributes->sourceURL );
		}

		if ( $post ) {
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_title'   => sanitize_text_field( $note->title ?? '' ),
					'post_content' => wp_kses_post( $content ),
					'meta_input'   => $meta,
				)
			);

			return $post->ID;
		}

		return $this->create_knowledge_post(
			array(
				'post_title'        => sanitize_text_field( $note->title ?? '' ),
				'post_content'      => wp_kses_post( $content ),
				'post_status'       => 'private',
				'meta_input'        => $meta,
				'personalos_source' => 'native:personal-evernote-sync:' . sanitize_text_field( $note->guid ),
			),
			array( 'artifact', 'note', 'evernote', 'synced', 'reference' ),
			$user_id
		);
	}

	/**
	 * Get users with token and active sync enabled.
	 *
	 * @return int[]
	 */
	private function get_active_user_ids() {
		return array_values(
			array_filter(
				$this->get_user_ids_with_setting( 'token' ),
				function ( $user_id ) {
					return (bool) $this->get_setting( 'active', $user_id );
				}
			)
		);
	}

	/**
	 * Fetch normalized notes for one user.
	 *
	 * A package-local transport can hook this without making the split plugin
	 * depend on the old monolith vendor layout.
	 *
	 * @param int    $user_id          User ID.
	 * @param string $token            Evernote token.
	 * @param array  $synced_notebooks Configured notebook/tag GUIDs.
	 * @return array|WP_Error
	 */
	private function fetch_notes_for_user( $user_id, $token, $synced_notebooks ) {
		$payload = apply_filters( 'personal_evernote_sync_fetch_notes', null, $user_id, $token, $synced_notebooks, $this );

		if ( null !== $payload ) {
			return $payload;
		}

		$client = $this->create_evernote_client( $token, $user_id );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		try {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Evernote SDK response fields use camelCase.
			$note_store        = $client->getNoteStore();
			$sync_state        = $note_store->getSyncState();
			$usn               = absint( $this->get_setting( 'usn', $user_id ) );
			$last_sync         = absint( $this->get_setting( 'last_sync', $user_id ) );
			$last_update_count = absint( $this->get_setting( 'last_update_count', $user_id ) );

			if ( $last_update_count && (int) $sync_state->updateCount === $last_update_count ) {
				return array(
					'notes'             => array(),
					'usn'               => $usn,
					'last_sync'         => (string) $sync_state->currentTime,
					'last_update_count' => (string) $sync_state->updateCount,
					'cached_data'       => $this->get_cached_data_for_user( $user_id ),
				);
			}

			if ( ! empty( $sync_state->fullSyncBefore ) && $sync_state->fullSyncBefore > $last_sync ) {
				$usn = 0;
			}

			$sync_filter = new \EDAM\NoteStore\SyncChunkFilter(
				array(
					'includeNotes'          => true,
					'includeNotebooks'      => true,
					'includeTags'           => true,
					'includeNoteAttributes' => true,
					'includeExpunged'       => false,
					'includeNoteResources'  => true,
				)
			);

			$sync_chunk  = $note_store->getFilteredSyncChunk( $usn, 100, $sync_filter );
			$cached_data = $this->update_cached_data_from_sync_chunk( $user_id, $sync_chunk );
			$notes       = array();

			foreach ( (array) ( $sync_chunk->notes ?? array() ) as $note ) {
				if ( ! $this->should_sync_note( $note, $synced_notebooks ) ) {
					continue;
				}

				$notes[] = $this->normalize_evernote_note( $note, $note_store );
			}

			return array(
				'notes'             => $notes,
				'usn'               => (string) absint( $sync_chunk->chunkHighUSN ?? $usn ),
				'last_sync'         => (string) $sync_state->currentTime,
				'last_update_count' => (string) $sync_state->updateCount,
				'cached_data'       => $cached_data,
				'has_more'          => ! empty( $sync_chunk->chunkHighUSN ) && $sync_chunk->chunkHighUSN > $usn,
			);
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		} catch ( Throwable $exception ) {
			return new WP_Error( 'personal_evernote_sync_transport_error', $exception->getMessage(), array( 'status' => 502 ) );
		}
	}

	/**
	 * Create an Evernote SDK client.
	 *
	 * @param string $token   Evernote token.
	 * @param int    $user_id User ID.
	 * @return object|WP_Error
	 */
	private function create_evernote_client( $token, $user_id ) {
		$client = apply_filters( 'personal_evernote_sync_client', null, $token, $user_id, $this );
		if ( null !== $client ) {
			return $client;
		}

		if ( ! $this->load_evernote_sdk() ) {
			return new WP_Error( 'personal_evernote_sync_sdk_missing', __( 'Evernote SDK is not available in this package build.', 'personal-evernote-sync' ), array( 'status' => 503 ) );
		}

		return new \Evernote\AdvancedClient( $token, false );
	}

	/**
	 * Load the bundled or monorepo Evernote SDK classes.
	 *
	 * @return bool
	 */
	private function load_evernote_sdk() {
		if ( class_exists( 'Evernote\AdvancedClient' ) && class_exists( 'EDAM\NoteStore\SyncChunkFilter' ) ) {
			return true;
		}

		foreach ( $this->evernote_sdk_roots() as $root ) {
			if ( is_dir( $root['sdk'] ) ) {
				$this->register_evernote_sdk_autoloader( $root['sdk'], $root['psr_log'] );
			}
		}

		return class_exists( 'Evernote\AdvancedClient' ) && class_exists( 'EDAM\NoteStore\SyncChunkFilter' );
	}

	/**
	 * Candidate SDK roots for ZIP installs and monorepo development.
	 *
	 * @return array
	 */
	private function evernote_sdk_roots() {
		$plugin_dir = plugin_dir_path( PERSONAL_EVERNOTE_SYNC_FILE );
		$repo_dir   = dirname( PERSONAL_EVERNOTE_SYNC_FILE, 3 );

		return array(
			array(
				'sdk'     => $plugin_dir . 'vendor/evernote/evernote-cloud-sdk-php/src',
				'psr_log' => $plugin_dir . 'vendor/psr/log/Psr/Log',
			),
			array(
				'sdk'     => $repo_dir . '/vendor/evernote/evernote-cloud-sdk-php/src',
				'psr_log' => $repo_dir . '/vendor/psr/log/Psr/Log',
			),
		);
	}

	/**
	 * Register class loading for the Evernote SDK without the SDK's global autoload() function.
	 *
	 * @param string $sdk_root     Evernote SDK src directory.
	 * @param string $psr_log_root PSR log source directory.
	 * @return void
	 */
	private function register_evernote_sdk_autoloader( $sdk_root, $psr_log_root ) {
		spl_autoload_register(
			function ( $class_name ) use ( $sdk_root, $psr_log_root ) {
				$class_name = ltrim( $class_name, '\\' );
				$file       = '';

				if ( 0 === strpos( $class_name, 'EDAM\\' ) ) {
					$parts = explode( '\\', $class_name );
					if ( count( $parts ) < 3 ) {
						return;
					}

					$namespace = $parts[1];
					$short     = end( $parts );
					$file_name = 0 === strpos( $short, $namespace ) ? $namespace . '.php' : 'Types.php';
					$file      = $sdk_root . '/EDAM/' . $namespace . '/' . $file_name;
				} elseif ( 0 === strpos( $class_name, 'Evernote\\' ) || 0 === strpos( $class_name, 'Thrift\\' ) ) {
					$file = $sdk_root . '/' . str_replace( '\\', '/', $class_name ) . '.php';
				} elseif ( 0 === strpos( $class_name, 'Psr\\Log\\' ) && is_dir( $psr_log_root ) ) {
					$file = $psr_log_root . '/' . str_replace( '\\', '/', substr( $class_name, 8 ) ) . '.php';
				}

				if ( $file && is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}

	/**
	 * Normalize an SDK note into the package-owned sync shape.
	 *
	 * @param object $note       Evernote note.
	 * @param object $note_store Evernote note store.
	 * @return object
	 */
	private function normalize_evernote_note( $note, $note_store ) {
		if ( empty( $note->content ) && ! empty( $note->guid ) && method_exists( $note_store, 'getNoteContent' ) ) {
			$note->content = $note_store->getNoteContent( $note->guid );
		}

		$note->content = self::enml_to_html( (string) ( $note->content ?? '' ) );

		return $note;
	}

	/**
	 * Convert enough ENML to safe WordPress HTML for imported Knowledge.
	 *
	 * @param string $content ENML content.
	 * @return string
	 */
	private static function enml_to_html( $content ) {
		$content = preg_replace( '/<\?xml.*?\?>/s', '', $content );
		$content = preg_replace( '/<!DOCTYPE[^>]+>/i', '', $content );

		if ( preg_match( '/<en-note[^>]*>(.*?)<\/en-note>/s', $content, $matches ) ) {
			$content = $matches[1];
		}

		$content = preg_replace_callback(
			'/<en-todo[^>]*checked="(?P<checked>[^"]+)"[^\/]*?\/>/',
			function ( $match ) {
				return '<span class="personal-evernote-sync-todo">' . ( 'true' === $match['checked'] ? '[x]' : '[ ]' ) . '</span>';
			},
			$content
		);

		$content = preg_replace( '/<en-todo[^\/]*?\/>/', '<span class="personal-evernote-sync-todo">[ ]</span>', $content );
		$content = preg_replace( '/<en-media[^>]*\/>/', '<p>[Evernote attachment]</p>', $content );

		return trim( $content );
	}

	/**
	 * Read cached notebook/tag metadata for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_cached_data_for_user( $user_id ) {
		$cached_data = json_decode( (string) $this->get_setting( 'cached_data', $user_id ), true );

		if ( ! is_array( $cached_data ) ) {
			$cached_data = array();
		}

		$cached_data['notebooks'] = isset( $cached_data['notebooks'] ) && is_array( $cached_data['notebooks'] ) ? $cached_data['notebooks'] : array();
		$cached_data['tags']      = isset( $cached_data['tags'] ) && is_array( $cached_data['tags'] ) ? $cached_data['tags'] : array();

		return $cached_data;
	}

	/**
	 * Merge notebook/tag metadata from a sync chunk into cached user data.
	 *
	 * @param int    $user_id    User ID.
	 * @param object $sync_chunk Evernote sync chunk.
	 * @return array
	 */
	private function update_cached_data_from_sync_chunk( $user_id, $sync_chunk ) {
		$cached_data = $this->get_cached_data_for_user( $user_id );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Evernote SDK response fields use camelCase.
		foreach ( (array) ( $sync_chunk->notebooks ?? array() ) as $notebook ) {
			if ( empty( $notebook->guid ) ) {
				continue;
			}

			$cached_data['notebooks'][ $notebook->guid ] = array(
				'name'  => sanitize_text_field( $notebook->name ?? $notebook->guid ),
				'stack' => sanitize_text_field( $notebook->stack ?? '' ),
			);

			if ( ! empty( $notebook->defaultNotebook ) ) {
				$cached_data['notebooks'][ $notebook->guid ]['default'] = true;
			}
		}

		foreach ( (array) ( $sync_chunk->tags ?? array() ) as $tag ) {
			if ( empty( $tag->guid ) ) {
				continue;
			}

			$cached_data['tags'][ $tag->guid ] = array(
				'name' => sanitize_text_field( $tag->name ?? $tag->guid ),
			);

			if ( ! empty( $tag->parentGuid ) ) {
				$cached_data['tags'][ $tag->guid ]['parent'] = sanitize_text_field( $tag->parentGuid );
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $cached_data;
	}

	/**
	 * Is an Evernote note inside the configured notebook/tag scope?
	 *
	 * @param object $note              Evernote-like note object.
	 * @param array  $synced_notebooks Configured notebook/tag GUIDs.
	 * @return bool
	 */
	private function should_sync_note( $note, $synced_notebooks ) {
		$note_data    = (array) $note;
		$linked_guids = array();

		if ( ! empty( $note_data['notebookGuid'] ) ) {
			$linked_guids[] = (string) $note_data['notebookGuid'];
		}

		if ( ! empty( $note_data['tagGuids'] ) ) {
			$linked_guids = array_merge( $linked_guids, array_map( 'strval', (array) $note_data['tagGuids'] ) );
		}

		return ! empty( array_intersect( array_map( 'strval', $synced_notebooks ), $linked_guids ) );
	}

	/**
	 * Find existing Knowledge row by Evernote GUID and owner.
	 *
	 * @param string $guid    Evernote GUID.
	 * @param int    $user_id User ID.
	 * @return WP_Post|null
	 */
	private function find_by_guid( $guid, $user_id ) {
		$posts = get_posts(
			array(
				'post_type'      => $this->knowledge()->post_type(),
				'post_status'    => array( 'private', 'publish', 'future' ),
				'author'         => (int) $user_id,
				'posts_per_page' => 1,
				'meta_key'       => 'evernote_guid',
				'meta_value'     => sanitize_text_field( $guid ),
			)
		);

		return empty( $posts ) ? null : $posts[0];
	}
}
