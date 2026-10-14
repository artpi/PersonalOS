<?php
/**
 * Personal AI Chat package.
 *
 * @package PersonalAIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Split packages use their package text domain while root PHPCS still expects personalos.

/**
 * AI Chat Knowledge and setup surface.
 */
class Personal_AI_Chat_Plugin extends PersonalOS_Plugin_Base {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-ai-chat',
				'display_name' => 'Personal AI Chat',
				'text_domain'  => 'personal-ai-chat',
				'version'      => PERSONAL_AI_CHAT_VERSION,
				'plugin_file'  => PERSONAL_AI_CHAT_FILE,
				'app'          => array(
					'path'         => 'ai-chat',
					'name'         => 'AI Chat',
					'action_label' => 'Open App',
					'capability'   => 'edit_posts',
					'icon'         => 'dashicons-format-chat',
				),
				'settings'     => array(
					'default_prompt' => array(
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
		$this->register_wp_app( array( $this, 'render_admin_page' ) );
		add_action( 'template_redirect', array( $this, 'redirect_conversation_link' ), 5 );
		add_filter( 'post_type_link', array( $this, 'filter_conversation_permalink' ), 10, 2 );
		add_filter( 'get_shortlink', array( $this, 'filter_conversation_shortlink' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			add_action(
				'admin_notices',
				function () {
					PersonalOS_Admin_Notice_Helper::missing_ai_client( $this->display_name() );
				}
			);
		}

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		$this->register_common_knowledge_meta();
		$this->knowledge()->register_post_meta( 'pos_model' );
		$this->knowledge()->register_post_meta( 'pos_chat_prompt_id' );
		$this->knowledge()->register_post_meta( 'pos_last_response_id' );
		$this->knowledge()->register_post_meta( '_pos_placeholder_title' );
		$this->knowledge()->register_post_meta( '_personalos_chat_provider_response_id' );
		$this->register_blocks();
	}

	/**
	 * Register package-owned AI blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$this->register_block_from_package( 'build/blocks/message' );
		$this->register_block_from_package( 'build/blocks/tool' );
	}

	/**
	 * Return the AI Chat app URL for a conversation.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when the post is not an AI Chat conversation.
	 */
	public function get_conversation_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $this->is_conversation( $post ) ) {
			return '';
		}

		return add_query_arg( 'conversation', $post->ID, home_url( '/ai-chat/' ) );
	}

	/** Filter a conversation permalink to its AI Chat interface URL. */
	public function filter_conversation_permalink( $url, $post ) {
		$conversation_url = $post instanceof WP_Post ? $this->get_conversation_url( $post->ID ) : '';

		return $conversation_url ? $conversation_url : $url;
	}

	/** Filter a conversation shortlink to its AI Chat interface URL. */
	public function filter_conversation_shortlink( $shortlink, $post_id ) {
		$conversation_url = $this->get_conversation_url( $post_id );

		return $conversation_url ? $conversation_url : $shortlink;
	}

	/** Redirect an editable legacy ?p= link to the AI Chat conversation. */
	public function redirect_conversation_link() {
		$post_id = absint( get_query_var( 'p' ) );
		$conversation_url = $this->get_conversation_url( $post_id );
		if ( get_query_var( 'preview' ) || ! $conversation_url || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_safe_redirect( $conversation_url );
		exit;
	}

	/**
	 * Register package REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'personal-ai-chat/v1',
			'/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_list_conversations' ),
					'permission_callback' => array( $this, 'can_read_conversations' ),
					'args'                => $this->collection_rest_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_conversation' ),
					'permission_callback' => array( $this, 'can_create_conversation' ),
					'args'                => $this->conversation_rest_args(),
				),
			)
		);

		register_rest_route(
			'personal-ai-chat/v1',
			'/conversations/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_conversation' ),
					'permission_callback' => array( $this, 'can_read_conversation' ),
					'args'                => $this->id_rest_args(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_conversation' ),
					'permission_callback' => array( $this, 'can_edit_conversation' ),
					'args'                => array_merge( $this->id_rest_args(), $this->conversation_rest_args() ),
				),
			)
		);

		register_rest_route(
			'personal-ai-chat/v1',
			'/conversations/(?P<id>[\d]+)/messages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_append_message' ),
				'permission_callback' => array( $this, 'can_edit_conversation' ),
				'args'                => array_merge(
					$this->id_rest_args(),
					array(
						'role'    => array(
							'type'              => 'string',
							'default'           => 'user',
							'enum'              => array( 'user', 'assistant', 'system' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'content' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					)
				),
			)
		);

		register_rest_route(
			'personal-ai-chat/v1',
			'/conversations/(?P<id>[\d]+)/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_generate_message' ),
				'permission_callback' => array( $this, 'can_edit_conversation' ),
				'args'                => array_merge(
					$this->id_rest_args(),
					array(
						'message'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'pos_model' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);

		register_rest_route(
			'personal-ai-chat/v1',
			'/abilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_list_abilities' ),
				'permission_callback' => array( $this, 'can_read_conversations' ),
			)
		);
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$this->add_app_admin_page_hook(
			add_menu_page(
				'AI Chat',
				'AI Chat',
				'edit_posts',
				'personal-ai-chat',
				array( $this, 'render_admin_page' ),
				'dashicons-format-chat',
				5
			)
		);
	}

	/**
	 * Render setup shell.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$health = new PersonalOS_Plugin_Health( $this->knowledge() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->app_display_name() ); ?></h1>
			<?php if ( ! $this->knowledge()->is_available() ) : ?>
				<p><?php esc_html_e( 'Knowledge is not available yet.', 'personal-ai-chat' ); ?></p>
			<?php else : ?>
				<div id="personal-ai-chat-admin-app" class="personal-ai-chat-admin"></div>
			<?php endif; ?>
			<table class="widefat striped personal-ai-chat-admin-fallback">
				<tbody>
					<?php foreach ( $health->data() as $key => $value ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $key ); ?></th>
							<td><?php echo esc_html( is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : $value ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'ai_client_available', 'personal-ai-chat' ); ?></th>
						<td><?php echo function_exists( 'wp_ai_client_prompt' ) ? 'yes' : 'no'; ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'abilities_available', 'personal-ai-chat' ); ?></th>
						<td><?php echo function_exists( 'wp_get_abilities' ) ? 'yes' : 'no'; ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * List readable chat transcript Knowledge rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_list_conversations( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 20;
		$per_page = min( 50, max( 1, $per_page ) );
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$page     = max( 1, $page );
		$args     = array(
			'posts_per_page' => $per_page,
			'offset'         => ( $page - 1 ) * $per_page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( '' !== (string) $request->get_param( 'search' ) ) {
			$args['s'] = sanitize_text_field( $request->get_param( 'search' ) );
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}

		$conversations = $this->query_knowledge_posts( $args, array( 'conversation', 'ai-chat' ) );

		return rest_ensure_response( array_map( array( $this, 'format_conversation' ), $conversations ) );
	}

	/**
	 * Create a chat transcript Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_create_conversation( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$post_id = $this->create_conversation(
			sanitize_text_field( $request->get_param( 'title' ) ),
			wp_kses_post( $request->get_param( 'content' ) ),
			$this->conversation_meta_from_request( $request )
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$response = rest_ensure_response( $this->format_conversation( get_post( $post_id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Get one chat transcript Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_conversation( WP_REST_Request $request ) {
		$conversation = get_post( (int) $request['id'] );

		if ( ! $this->is_conversation( $conversation ) ) {
			return new WP_Error( 'personal_ai_chat_not_found', __( 'Conversation not found.', 'personal-ai-chat' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->format_conversation( $conversation ) );
	}

	/**
	 * Update one chat transcript Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_update_conversation( WP_REST_Request $request ) {
		$conversation = get_post( (int) $request['id'] );

		if ( ! $this->is_conversation( $conversation ) ) {
			return new WP_Error( 'personal_ai_chat_not_found', __( 'Conversation not found.', 'personal-ai-chat' ), array( 'status' => 404 ) );
		}

		$data = array( 'ID' => $conversation->ID );
		foreach ( array(
			'title'   => 'post_title',
			'content' => 'post_content',
		) as $request_key => $post_key ) {
			if ( null === $request->get_param( $request_key ) ) {
				continue;
			}

			$data[ $post_key ] = 'content' === $request_key ? wp_kses_post( $request->get_param( $request_key ) ) : sanitize_text_field( $request->get_param( $request_key ) );
		}

		$meta = $this->conversation_meta_from_request( $request );
		if ( ! empty( $meta ) ) {
			$data['meta_input'] = $meta;
		}

		if ( count( $data ) > 1 ) {
			$updated = wp_update_post( $data, true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		return rest_ensure_response( $this->format_conversation( get_post( $conversation->ID ) ) );
	}

	/**
	 * Append one message block to a conversation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_append_message( WP_REST_Request $request ) {
		$conversation = get_post( (int) $request['id'] );

		if ( ! $this->is_conversation( $conversation ) ) {
			return new WP_Error( 'personal_ai_chat_not_found', __( 'Conversation not found.', 'personal-ai-chat' ), array( 'status' => 404 ) );
		}

		$message = $this->message_block_content(
			sanitize_key( $request->get_param( 'role' ) ),
			sanitize_textarea_field( $request->get_param( 'content' ) )
		);
		$content = trim( $conversation->post_content . "\n\n" . $message );

		$updated = wp_update_post(
			array(
				'ID'           => $conversation->ID,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return rest_ensure_response( $this->format_conversation( get_post( $conversation->ID ) ) );
	}

	/**
	 * Generate an assistant response with WordPress AI Client.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_generate_message( WP_REST_Request $request ) {
		$conversation = get_post( (int) $request['id'] );

		if ( ! $this->is_conversation( $conversation ) ) {
			return new WP_Error( 'personal_ai_chat_not_found', __( 'Conversation not found.', 'personal-ai-chat' ), array( 'status' => 404 ) );
		}

		$user_message = sanitize_textarea_field( $request->get_param( 'message' ) );
		if ( '' === trim( $user_message ) ) {
			return new WP_Error( 'personal_ai_chat_empty_message', __( 'Message cannot be empty.', 'personal-ai-chat' ), array( 'status' => 400 ) );
		}

		$model = null !== $request->get_param( 'pos_model' ) ? sanitize_text_field( $request->get_param( 'pos_model' ) ) : get_post_meta( $conversation->ID, 'pos_model', true );
		$reply = $this->generate_ai_text( $this->build_generation_prompt( $conversation, $user_message ), $conversation, $user_message, $model );

		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		$content = trim(
			$conversation->post_content .
			"\n\n" .
			$this->message_block_content( 'user', $user_message ) .
			"\n\n" .
			$this->message_block_content( 'assistant', $reply )
		);

		$data = array(
			'ID'           => $conversation->ID,
			'post_content' => $content,
		);

		if ( $model ) {
			$data['meta_input'] = array(
				'pos_model' => $model,
			);
		}

		$updated = wp_update_post( $data, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return rest_ensure_response( $this->format_conversation( get_post( $conversation->ID ) ) );
	}

	/**
	 * List AI-callable abilities.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_list_abilities() {
		return rest_ensure_response( array_values( array_map( array( $this, 'format_ability' ), $this->discover_abilities() ) ) );
	}

	/**
	 * Create a chat transcript Knowledge row.
	 *
	 * @param string $title   Title.
	 * @param string $content Transcript content.
	 * @param array  $meta    Optional meta.
	 * @param int    $user_id Optional owner.
	 * @return int|WP_Error
	 */
	public function create_conversation( $title, $content, $meta = array(), $user_id = 0 ) {
		$title = $title ? $title : __( 'Untitled Conversation', 'personal-ai-chat' );

		return $this->create_knowledge_post(
			array(
				'post_title'        => $title,
				'post_content'      => $content,
				'post_status'       => 'private',
				'meta_input'        => $meta,
				'personalos_source' => 'native:personal-ai-chat',
			),
			array( 'artifact', 'conversation', 'ai-chat', 'personalos' ),
			$user_id
		);
	}

	/**
	 * Format a conversation for REST responses.
	 *
	 * @param WP_Post $conversation Conversation post.
	 * @return array
	 */
	public function format_conversation( WP_Post $conversation ) {
		$terms = wp_get_object_terms( $conversation->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		return array(
			'id'                   => $conversation->ID,
			'title'                => $conversation->post_title,
			'content'              => $conversation->post_content,
			'excerpt'              => wp_trim_words( wp_strip_all_tags( $conversation->post_content ), 24 ),
			'status'               => $conversation->post_status,
			'author'               => (int) $conversation->post_author,
			'date_gmt'             => $conversation->post_date_gmt,
			'modified_gmt'         => $conversation->post_modified_gmt,
			'edit_url'             => get_edit_post_link( $conversation->ID, 'raw' ),
			'terms'                => $terms,
			'pos_model'            => get_post_meta( $conversation->ID, 'pos_model', true ),
			'pos_chat_prompt_id'   => (int) get_post_meta( $conversation->ID, 'pos_chat_prompt_id', true ),
			'pos_last_response_id' => get_post_meta( $conversation->ID, 'pos_last_response_id', true ),
			'abilities_available'  => function_exists( 'wp_get_abilities' ),
			'ai_client_available'  => function_exists( 'wp_ai_client_prompt' ),
		);
	}

	/**
	 * Discover registered abilities for chat tools.
	 *
	 * @return array
	 */
	public function discover_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		return wp_get_abilities();
	}

	/**
	 * Can the current user list conversations?
	 *
	 * @return bool
	 */
	public function can_read_conversations() {
		return is_user_logged_in();
	}

	/**
	 * Can the current user create a conversation?
	 *
	 * @return bool
	 */
	public function can_create_conversation() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Can the current user read this conversation?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_read_conversation( WP_REST_Request $request ) {
		return current_user_can( 'read_post', (int) $request['id'] );
	}

	/**
	 * Can the current user edit this conversation?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_conversation( WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Args for collection reads.
	 *
	 * @return array
	 */
	private function collection_rest_args() {
		return array(
			'search'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Args for conversation create/update.
	 *
	 * @return array
	 */
	private function conversation_rest_args() {
		return array(
			'title'                => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'              => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'pos_model'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'pos_chat_prompt_id'   => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'pos_last_response_id' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Args for item routes.
	 *
	 * @return array
	 */
	private function id_rest_args() {
		return array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Extract conversation meta from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function conversation_meta_from_request( WP_REST_Request $request ) {
		$meta = array();

		foreach ( array( 'pos_model', 'pos_last_response_id' ) as $meta_key ) {
			if ( null !== $request->get_param( $meta_key ) ) {
				$meta[ $meta_key ] = sanitize_text_field( $request->get_param( $meta_key ) );
			}
		}

		if ( null !== $request->get_param( 'pos_chat_prompt_id' ) ) {
			$meta['pos_chat_prompt_id'] = absint( $request->get_param( 'pos_chat_prompt_id' ) );
		}

		return $meta;
	}

	/**
	 * Build a saved AI message block.
	 *
	 * @param string $role    Message role.
	 * @param string $content Message content.
	 * @return string
	 */
	private function message_block_content( $role, $content ) {
		return get_comment_delimited_block_content(
			'pos/ai-message',
			array(
				'role'    => in_array( $role, array( 'user', 'assistant', 'system' ), true ) ? $role : 'user',
				'content' => $content,
			),
			'<span class="ai-message-text">' . esc_html( $content ) . '</span>'
		);
	}

	/**
	 * Build the prompt sent to AI Client for the next assistant reply.
	 *
	 * @param WP_Post $conversation Conversation post.
	 * @param string  $user_message New user message.
	 * @return string
	 */
	private function build_generation_prompt( WP_Post $conversation, $user_message ) {
		$messages = $this->extract_conversation_messages( $conversation->post_content );
		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		$lines = array_map(
			function ( $message ) {
				return ucfirst( $message['role'] ) . ': ' . $message['content'];
			},
			$messages
		);

		return trim(
			implode(
				"\n\n",
				array_filter(
					array(
						$this->get_setting( 'default_prompt', (int) $conversation->post_author ),
						__( 'Continue this conversation as the assistant. Return only the assistant reply.', 'personal-ai-chat' ),
						implode( "\n\n", $lines ),
					)
				)
			)
		);
	}

	/**
	 * Generate text through AI Client, with a testable preflight filter.
	 *
	 * @param string  $prompt       Prompt text.
	 * @param WP_Post $conversation Conversation post.
	 * @param string  $user_message New user message.
	 * @param string  $model        Optional model preference.
	 * @return string|WP_Error
	 */
	private function generate_ai_text( $prompt, WP_Post $conversation, $user_message, $model = '' ) {
		$pre = apply_filters( 'personal_ai_chat_pre_generate_text', null, $prompt, $conversation, $user_message, $model );
		if ( null !== $pre ) {
			return $this->normalize_ai_text_result( $pre );
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'personal_ai_chat_missing_ai_client', __( 'WordPress AI Client is not available.', 'personal-ai-chat' ), array( 'status' => 503 ) );
		}

		$builder = wp_ai_client_prompt( $prompt );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}

		if ( $model && is_object( $builder ) && method_exists( $builder, 'using_model' ) ) {
			$builder = $builder->using_model( $model );
		}

		if ( ! is_object( $builder ) || ! method_exists( $builder, 'generate_text' ) ) {
			return new WP_Error( 'personal_ai_chat_invalid_ai_client', __( 'WordPress AI Client returned an invalid prompt builder.', 'personal-ai-chat' ), array( 'status' => 500 ) );
		}

		return $this->normalize_ai_text_result( $builder->generate_text() );
	}

	/**
	 * Normalize an AI Client text result.
	 *
	 * @param mixed $result AI result.
	 * @return string|WP_Error
	 */
	private function normalize_ai_text_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_scalar( $result ) || ( is_object( $result ) && method_exists( $result, '__toString' ) ) ) {
			$text = trim( (string) $result );
		} else {
			$text = wp_json_encode( $result );
		}

		if ( '' === $text ) {
			return new WP_Error( 'personal_ai_chat_empty_ai_response', __( 'AI Client returned an empty response.', 'personal-ai-chat' ), array( 'status' => 502 ) );
		}

		return $text;
	}

	/**
	 * Extract saved AI message blocks from conversation content.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function extract_conversation_messages( $content ) {
		$messages = array();

		foreach ( parse_blocks( $content ) as $block ) {
			if ( 'pos/ai-message' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}

			$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$role       = isset( $attributes['role'] ) ? sanitize_key( $attributes['role'] ) : 'assistant';
			$message    = isset( $attributes['content'] ) ? (string) $attributes['content'] : wp_strip_all_tags( $block['innerHTML'] ?? '' );
			$message    = trim( $message );

			if ( '' === $message ) {
				continue;
			}

			$messages[] = array(
				'role'    => in_array( $role, array( 'user', 'assistant', 'system' ), true ) ? $role : 'assistant',
				'content' => $message,
			);
		}

		if ( empty( $messages ) && '' !== trim( wp_strip_all_tags( $content ) ) ) {
			$messages[] = array(
				'role'    => 'assistant',
				'content' => trim( wp_strip_all_tags( $content ) ),
			);
		}

		return $messages;
	}

	/**
	 * Format a discovered ability for REST.
	 *
	 * @param mixed $ability Ability object/array.
	 * @return array
	 */
	private function format_ability( $ability ) {
		if ( is_object( $ability ) && is_callable( array( $ability, 'get_name' ) ) ) {
			return array(
				'name'          => sanitize_text_field( $ability->get_name() ),
				'label'         => sanitize_text_field( $ability->get_label() ),
				'description'   => sanitize_text_field( $ability->get_description() ),
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $ability->get_meta(),
			);
		}

		$ability = (array) $ability;

		return array(
			'name'          => sanitize_text_field( $ability['name'] ?? '' ),
			'label'         => sanitize_text_field( $ability['label'] ?? ( $ability['name'] ?? '' ) ),
			'description'   => sanitize_text_field( $ability['description'] ?? '' ),
			'input_schema'  => isset( $ability['input_schema'] ) ? $ability['input_schema'] : null,
			'output_schema' => isset( $ability['output_schema'] ) ? $ability['output_schema'] : null,
			'meta'          => isset( $ability['meta'] ) ? $ability['meta'] : array(),
		);
	}

	/**
	 * Is this post an AI Chat conversation Knowledge row?
	 *
	 * @param WP_Post|mixed $post Post.
	 * @return bool
	 */
	private function is_conversation( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->knowledge()->is_available() || $post->post_type !== $this->knowledge()->post_type() ) {
			return false;
		}

		$terms = wp_get_object_terms( $post->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );

		return ! is_wp_error( $terms ) && in_array( 'conversation', $terms, true ) && in_array( 'ai-chat', $terms, true );
	}

	/**
	 * Standard missing Knowledge REST error.
	 *
	 * @return WP_Error
	 */
	private function missing_knowledge_error() {
		return new WP_Error( 'personalos_missing_knowledge', __( 'Knowledge is not available.', 'personal-ai-chat' ), array( 'status' => 503 ) );
	}
}
