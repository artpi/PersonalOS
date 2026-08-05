<?php
/**
 * Personal Readwise Sync package.
 *
 * @package PersonalReadwiseSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Split packages use their package text domain while root PHPCS still expects personalos.

/**
 * Syncs Readwise exports into Knowledge.
 */
class Personal_Readwise_Sync_Plugin extends PersonalOS_Sync_Plugin_Base {
	/**
	 * Readwise category labels.
	 *
	 * @var array
	 */
	private $category_names = array(
		'books'         => 'Books',
		'articles'      => 'Articles',
		'podcasts'      => 'Podcasts',
		'tweets'        => 'Tweets',
		'supplementals' => 'Supplementals',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-readwise-sync',
				'display_name' => 'Personal Readwise Sync',
				'text_domain'  => 'personal-readwise-sync',
				'version'      => PERSONAL_READWISE_SYNC_VERSION,
				'plugin_file'  => PERSONAL_READWISE_SYNC_FILE,
				'settings'     => array(
					'token'       => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => '',
					),
					'autotag'     => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => '',
					),
					'page_cursor' => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => '',
					),
					'last_sync'   => array(
						'type'    => 'text',
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		$this->register_common_knowledge_meta();
		$this->knowledge()->register_post_meta( 'readwise_id' );
		$this->knowledge()->register_post_meta( 'readwise_category' );
		$this->knowledge()->register_post_meta( 'readwise_author' );
		$this->register_blocks();

		if ( ! empty( $this->get_user_ids_with_setting( 'token' ) ) ) {
			$this->register_sync( 'hourly' );
		}
	}

	/**
	 * Register package-owned Readwise blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$this->register_block_from_package( 'build/blocks/readwise' );
		$this->register_block_from_package( 'build/blocks/book-summary' );
	}

	/**
	 * Register package REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'personal-readwise-sync/v1',
			'/book-summary',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_generate_book_summary' ),
				'permission_callback' => array( $this, 'can_generate_book_summary' ),
				'args'                => array(
					'prompt'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'system_prompt' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Can the current user generate a book summary?
	 *
	 * @return bool
	 */
	public function can_generate_book_summary() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Generate a book summary with AI Client.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_generate_book_summary( WP_REST_Request $request ) {
		$prompt = sanitize_textarea_field( (string) $request->get_param( 'prompt' ) );
		if ( '' === trim( $prompt ) ) {
			return new WP_Error( 'personal_readwise_empty_prompt', __( 'Prompt cannot be empty.', 'personal-readwise-sync' ), array( 'status' => 400 ) );
		}

		$system_prompt = sanitize_textarea_field( (string) $request->get_param( 'system_prompt' ) );
		$summary       = $this->generate_book_summary_text( $prompt, $system_prompt );

		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		return rest_ensure_response(
			array(
				'summary' => wp_kses_post( $summary ),
			)
		);
	}

	/**
	 * Generate a book summary through AI Client.
	 *
	 * @param string $prompt        User-facing summary prompt.
	 * @param string $system_prompt Optional system instructions.
	 * @return string|WP_Error
	 */
	private function generate_book_summary_text( $prompt, $system_prompt = '' ) {
		$full_prompt = trim(
			implode(
				"\n\n",
				array_filter(
					array(
						$system_prompt ? 'System instructions: ' . $system_prompt : '',
						$prompt,
					)
				)
			)
		);

		$pre = apply_filters( 'personal_readwise_sync_pre_generate_book_summary', null, $full_prompt, $prompt, $system_prompt, $this );
		if ( null !== $pre ) {
			return $this->normalize_book_summary_text_result( $pre );
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'personal_readwise_missing_ai_client', __( 'WordPress AI Client is not available.', 'personal-readwise-sync' ), array( 'status' => 503 ) );
		}

		$builder = wp_ai_client_prompt( $full_prompt );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		if ( ! is_object( $builder ) || ! method_exists( $builder, 'generate_text' ) ) {
			return new WP_Error( 'personal_readwise_invalid_ai_client', __( 'WordPress AI Client returned an invalid prompt builder.', 'personal-readwise-sync' ), array( 'status' => 500 ) );
		}

		return $this->normalize_book_summary_text_result( $builder->generate_text() );
	}

	/**
	 * Normalize an AI Client text result for the book summary block.
	 *
	 * @param mixed $result AI result.
	 * @return string|WP_Error
	 */
	private function normalize_book_summary_text_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_scalar( $result ) || ( is_object( $result ) && method_exists( $result, '__toString' ) ) ) {
			$text = trim( (string) $result );
		} else {
			$text = wp_json_encode( $result );
			$text = false === $text ? '' : trim( $text );
		}

		if ( '' === $text ) {
			return new WP_Error( 'personal_readwise_empty_ai_response', __( 'AI Client returned an empty response.', 'personal-readwise-sync' ), array( 'status' => 502 ) );
		}

		return $text;
	}

	/**
	 * Add settings menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$this->add_admin_page_hook(
			add_options_page(
				'Personal Readwise Sync',
				'Personal Readwise Sync',
				'read',
				'personal-readwise-sync',
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

		if ( isset( $_POST['personal_readwise_sync_action'] ) ) {
			check_admin_referer( 'personal_readwise_sync_settings' );
			$this->update_setting( 'token', sanitize_text_field( wp_unslash( $_POST['personal_readwise_sync_token'] ?? '' ) ), get_current_user_id() );
			$this->update_setting( 'autotag', sanitize_text_field( wp_unslash( $_POST['personal_readwise_sync_autotag'] ?? '' ) ), get_current_user_id() );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'personal_readwise_sync_settings' ); ?>
				<input type="hidden" name="personal_readwise_sync_action" value="save">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="personal_readwise_sync_token"><?php esc_html_e( 'Readwise API Token', 'personal-readwise-sync' ); ?></label></th>
							<td><input class="regular-text" type="password" id="personal_readwise_sync_token" name="personal_readwise_sync_token" value="<?php echo esc_attr( $this->get_setting( 'token', get_current_user_id() ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="personal_readwise_sync_autotag"><?php esc_html_e( 'Autotag term', 'personal-readwise-sync' ); ?></label></th>
							<td><input class="regular-text" type="text" id="personal_readwise_sync_autotag" name="personal_readwise_sync_autotag" value="<?php echo esc_attr( $this->get_setting( 'autotag', get_current_user_id() ) ); ?>"></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Settings', 'personal-readwise-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sync all configured users.
	 *
	 * @return void
	 */
	public function sync() {
		foreach ( $this->get_user_ids_with_setting( 'token' ) as $user_id ) {
			try {
				$this->run_for_user( $user_id, array( $this, 'sync_user' ) );
			} catch ( Exception $exception ) {
				$this->log( 'Readwise sync failed for user ' . $user_id . ': ' . $exception->getMessage(), E_USER_WARNING );
			}
		}
	}

	/**
	 * Sync one user's Readwise export.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function sync_user( $user_id ) {
		$token = $this->get_setting( 'token', $user_id );
		if ( ! $token || ! $this->knowledge()->is_available() ) {
			return false;
		}

		$query_args = array();
		$page_cursor = $this->get_setting( 'page_cursor', $user_id );
		if ( $page_cursor ) {
			$query_args['pageCursor'] = $page_cursor;
		} else {
			$last_sync = $this->get_setting( 'last_sync', $user_id );
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
			$this->log( 'Readwise request failed: ' . $request->get_error_message(), E_USER_WARNING );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $request ) );
		if ( ! $data || empty( $data->results ) ) {
			return false;
		}

		foreach ( $data->results as $book ) {
			$this->sync_book( $book, $user_id );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! empty( $data->nextPageCursor ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->update_setting( 'page_cursor', $data->nextPageCursor, $user_id );
			$this->unschedule_sync();
			$this->schedule_single_sync( 60 );
		} else {
			$this->update_setting( 'last_sync', gmdate( 'c' ), $user_id );
			$this->update_setting( 'page_cursor', '', $user_id );
		}

		return true;
	}

	/**
	 * Sync one Readwise book/article.
	 *
	 * @param object $book    Readwise item.
	 * @param int    $user_id User ID.
	 * @return int|WP_Error|null
	 */
	public function sync_book( $book, $user_id ) {
		if ( empty( $book->user_book_id ) || empty( $book->highlights ) ) {
			return null;
		}

		$content = array_map( array( __CLASS__, 'wrap_highlight' ), $book->highlights );
		$hash    = hash( 'sha256', wp_json_encode( $book ) );
		$post    = $this->find_by_readwise_id( $book->user_book_id, $user_id );
		$terms   = array( 'artifact', 'note', 'readwise', 'synced', 'reference' );
		$autotag = $this->get_setting( 'autotag', $user_id );
		if ( $autotag ) {
			$terms[] = $autotag;
		}

		$meta = array(
			'readwise_id'             => sanitize_text_field( $book->user_book_id ),
			'readwise_category'       => sanitize_text_field( $book->category ?? '' ),
			'readwise_author'         => sanitize_text_field( $book->author ?? '' ),
			'url'                     => esc_url_raw( $book->source_url ?? '' ),
			'_personalos_external_id' => sanitize_text_field( $book->user_book_id ),
			'_personalos_source_url'  => esc_url_raw( $book->source_url ?? '' ),
			'_personalos_synced_at'   => gmdate( 'c' ),
			'_personalos_source_hash' => $hash,
		);

		if ( $post ) {
			if ( get_post_meta( $post->ID, '_personalos_source_hash', true ) === $hash ) {
				return $post->ID;
			}

			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_title'   => sanitize_text_field( $book->title ?? '' ),
					'post_excerpt' => sanitize_text_field( $book->summary ?? '' ),
					'post_content' => implode( "\n", $content ),
					'meta_input'   => $meta,
				)
			);

			$term_ids = $this->vocabulary()->resolve_assignable_term_ids( $terms );
			if ( ! is_wp_error( $term_ids ) ) {
				$current_terms = wp_get_object_terms( $post->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'ids' ) );
				$current_terms = is_wp_error( $current_terms ) ? array() : $current_terms;
				wp_set_object_terms( $post->ID, array_unique( array_merge( $current_terms, $term_ids ) ), $this->knowledge()->type_taxonomy(), false );
			}

			return $post->ID;
		}

		$highlights = (array) $book->highlights;
		$last_highlight = end( $highlights );

		return $this->create_knowledge_post(
			array(
				'post_title'        => sanitize_text_field( $book->title ?? '' ),
				'post_excerpt'      => sanitize_text_field( $book->summary ?? '' ),
				'post_content'      => implode( "\n", $content ),
				'post_status'       => 'private',
				'post_date'         => $last_highlight && ! empty( $last_highlight->created_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( $last_highlight->created_at ) ) : gmdate( 'Y-m-d H:i:s' ),
				'meta_input'        => $meta,
				'personalos_source' => 'native:personal-readwise-sync:' . sanitize_text_field( $book->user_book_id ),
			),
			$terms,
			$user_id
		);
	}

	/**
	 * Find existing Knowledge row by Readwise ID and owner.
	 *
	 * @param string $readwise_id Readwise ID.
	 * @param int    $user_id     User ID.
	 * @return WP_Post|null
	 */
	private function find_by_readwise_id( $readwise_id, $user_id ) {
		$posts = get_posts(
			array(
				'post_type'      => $this->knowledge()->post_type(),
				'post_status'    => array( 'private', 'publish', 'future' ),
				'author'         => (int) $user_id,
				'posts_per_page' => 1,
				'meta_key'       => 'readwise_id',
				'meta_value'     => sanitize_text_field( $readwise_id ),
			)
		);

		return empty( $posts ) ? null : $posts[0];
	}

	/**
	 * Wrap a Readwise highlight in block markup.
	 *
	 * @param object $highlight Highlight.
	 * @return string
	 */
	public static function wrap_highlight( $highlight ) {
		return get_comment_delimited_block_content(
			'pos/readwise',
			array(
				'readwise_url' => esc_url_raw( $highlight->readwise_url ?? '' ),
			),
			'<div class="wp-block-pos-readwise">' . wp_kses_post( $highlight->text ?? '' ) . '</div>'
		);
	}
}
