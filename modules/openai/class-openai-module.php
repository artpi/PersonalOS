<?php

require_once __DIR__ . '/class-pos-ollama-server.php';
require_once __DIR__ . '/class-openai-email-responder.php';

class OpenAI_Module extends POS_Module {
	public $id          = 'openai';
	public $name        = 'OpenAI';
	public $description = 'OpenAI module';
	public $settings    = array(
		'api_key'               => array(
			'type'  => 'text',
			'name'  => 'OpenAI API Key',
			'label' => 'You can get it from <a href="https://platform.openai.com/account/api-keys">here</a>',
		),
		'prompt_describe_image' => array(
			'type'    => 'textarea',
			'name'    => 'Prompt for describing image',
			'label'   => 'This prompt will be used to describe the image.',
			'default' => <<<EOF
				Please describe the content of this image
				- If Image presents some kind of assortment of items without people in it, assume that your role is to list everything present in the image. Do not describe the scene, but instead list every item in the image. Default to listing all individual items instead of whole groups.
				- If the image presents a scene with people in it, describe what it's presenting.
			EOF,
		),
	);

	/**
	 * Email responder instance.
	 *
	 * @var OpenAI_Email_Responder
	 */
	protected $email_responder;

	public function is_configured() {
		return ! empty( $this->settings['api_key'] );
	}

	public function register() {
		new POS_Ollama_Server( $this );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		$this->register_cli_command( 'tool', 'cli_openai_tool' );

		$this->register_cli_command( 'responses', 'cli_openai_responses' );
		$this->register_cli_command( 'system-prompt', 'cli_openai_system_prompt' );
		$this->register_block( 'tool', array( 'render_callback' => array( $this, 'render_tool_block' ) ) );
		$this->register_block( 'message', array() );

		require_once __DIR__ . '/chat-page.php';
		require_once __DIR__ . '/voice-chat-page.php';
		require_once __DIR__ . '/custom-gpt-page.php';

		$this->email_responder = new OpenAI_Email_Responder( $this );

		// Register user meta for REST API access.
		register_meta(
			'user',
			'pos_last_chat_model',
			array(
				'type'         => 'string',
				'description'  => 'Stores the last chat model used by the user.',
				'single'       => true,
				'show_in_rest' => true,
			)
		);

		// Register abilities
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register OpenAI module abilities with WordPress Abilities API.
	 */
	public function register_abilities() {
		// Register list_posts ability
		wp_register_ability(
			'pos/list-posts',
			array(
				'label'               => __( 'List Posts', 'personalos' ),
				'description'         => __( 'List publicly accessible posts on this blog.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						// phpcs:ignore WordPress.WP.PostsPerPage -- This is a schema definition, not a query parameter.
						'posts_per_page' => array(
							'type'        => 'integer',
							'description' => 'Number of posts to return. Default to 10',
						),
						'post_type'      => array(
							'type'        => 'string',
							'description' => 'Post type to return. Posts are blog posts, pages are static pages.',
							'enum'        => array( 'post', 'page' ),
						),
						'post_status'    => array(
							'type'        => 'string',
							'description' => 'Status of posts to return. Published posts are publicly accessible, drafts are not. Future are scheduled ones.',
							'enum'        => array( 'publish', 'draft', 'future' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of post objects',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array( 'type' => 'integer' ),
							'title'   => array( 'type' => 'string' ),
							'date'    => array( 'type' => 'string' ),
							'excerpt' => array( 'type' => 'string' ),
							'url'     => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_posts_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);

		// Register ai_memory ability
		wp_register_ability(
			'pos/ai-memory',
			array(
				'label'               => __( 'AI Memory', 'personalos' ),
				'description'         => __( 'Store information in the memory. Use this tool when you need to store additional information relevant for future conversations.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'ID'           => array(
							'type'        => 'integer',
							'description' => 'ID of the memory to update. Only provide when updating existing memory. Set to 0 when creating a new memory.',
						),
						'post_title'   => array(
							'type'        => 'string',
							'description' => 'Short title describing the memory. Describe what is the memory about specifically.',
						),
						'post_content' => array(
							'type'        => 'string',
							'description' => 'Actual content of the memory that will be stored and used for future conversations.',
						),
					),
					'required'             => array( 'ID', 'post_title', 'post_content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => 'Created or updated memory information',
					'properties'  => array(
						'url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'create_ai_memory_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
					),
				),
			)
		);

		// Register get_ai_memories ability
		wp_register_ability(
			'pos/get-ai-memories',
			array(
				'label'               => __( 'Get AI Memories', 'personalos' ),
				'description'         => __( 'Get all previously stored AI memories. These are pieces of information stored for future conversations.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of AI memory objects',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title'   => array( 'type' => 'string' ),
							'content' => array( 'type' => 'string' ),
							'date'    => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_ai_memories_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);

		// Register system_state ability
		wp_register_ability(
			'pos/system-state',
			array(
				'label'               => __( 'System State', 'personalos' ),
				'description'         => __( 'Get current system state including user information, system time, and PersonalOS description.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'user_display_name' => array( 'type' => 'string' ),
						'user_description'  => array( 'type' => 'string' ),
						'system_time'       => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'get_system_state_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);
	}

	public function render_tool_block( $attributes ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return '';
		}

		// Convert tool name to ability name if needed
		$ability_name = $attributes['tool'] ?? '';
		if ( empty( $ability_name ) ) {
			return '';
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return '';
		}
		$result = $ability->execute( (array) ( $attributes['parameters'] ?? null ) );

		// Filter output fields if specified
		$output_fields = $attributes['outputFields'] ?? null;
		if ( ! empty( $output_fields ) && is_array( $output_fields ) ) {
			$result = $this->filter_output_fields( $result, $output_fields );
		}

		// Format output
		$output_format = $attributes['outputFormat'] ?? 'json';
		if ( 'xml' === $output_format ) {
			// Wrap in root element for proper XML structure
			$xml_content = $this->array_to_xml( $result );
			$xml_output = '<root>' . "\n" . $xml_content . "\n" . '</root>';
			return '<pre>' . $xml_output . '</pre>';
		}

		return '<pre>' . wp_json_encode( $result, JSON_PRETTY_PRINT ) . '</pre>';
	}

	/**
	 * Filter output fields from result based on selected fields.
	 *
	 * @param mixed  $result Result from ability execution.
	 * @param array  $output_fields Selected output field names.
	 * @return mixed Filtered result.
	 */
	private function filter_output_fields( $result, $output_fields ) {
		if ( is_array( $result ) ) {
			// Handle array of objects
			if ( isset( $result[0] ) && is_array( $result[0] ) ) {
				return array_map(
					function( $item ) use ( $output_fields ) {
						return $this->filter_object_fields( $item, $output_fields );
					},
					$result
				);
			}
			// Handle single object
			return $this->filter_object_fields( $result, $output_fields );
		}
		return $result;
	}

	/**
	 * Filter fields from an object/array.
	 *
	 * @param array $object Object to filter.
	 * @param array $output_fields Fields to keep.
	 * @return array Filtered object.
	 */
	private function filter_object_fields( $object, $output_fields ) {
		if ( ! is_array( $object ) ) {
			return $object;
		}

		$filtered = array();
		foreach ( $output_fields as $field ) {
			if ( isset( $object[ $field ] ) ) {
				$filtered[ $field ] = $object[ $field ];
			}
		}
		return $filtered;
	}

	/**
	 * Test OpenAI abilities
	 *
	 * Lists available abilities and allows testing individual abilities with arguments.
	 * If no ability is specified, displays all available abilities. If an ability name is provided,
	 * executes that specific ability with the optional arguments.
	 *
	 * ## OPTIONS
	 *
	 * [<ability>]
	 * : Name of the ability to test (e.g., pos/todo-get-items)
	 *
	 * [<args>]
	 * : JSON string of arguments to pass to the ability
	 */
	public function cli_openai_tool( $args ) {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			WP_CLI::error( 'Abilities API not available' );
		}

		// Ensure abilities are registered
		if ( ! did_action( 'wp_abilities_api_init' ) ) {
			do_action( 'wp_abilities_api_init' );
		}

		$abilities = wp_get_abilities();

		if ( empty( $args[0] ) ) {
			$items = array_map(
				function( $ability ) {
					$input_schema = $ability->get_input_schema();
					return array(
						'name'        => $ability->get_name(),
						'description' => $ability->get_description(),
						'parameters'  => wp_json_encode( $input_schema['properties'] ?? array() ),
					);
				},
				$abilities
			);

			WP_CLI\Utils\format_items( 'table', $items, array( 'name', 'description', 'parameters' ) );
			return;
		}

		$ability_name = $args[0];
		if ( empty( $ability_name ) ) {
			WP_CLI::error( 'Ability name is required' );
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			WP_CLI::error( 'Ability not found: ' . $ability_name );
		}
		$result = $ability->execute( ! empty( $args[1] ) ? json_decode( $args[1], true ) : array() );
		WP_CLI::log( print_r( $result, true ) );
	}

	/**
	 * Test OpenAI responses API using WP-CLI.
	 *
	 * Allows sending a messages payload to the new responses endpoint and outputs
	 * streamed events and the final message list for inspection.
	 *
	 * ## OPTIONS
	 *
	 * [<messages>]
	 * : JSON encoded array of messages to send. If omitted, a sample prompt is used.
	 *
	 * [--file=<path>]
	 * : Path to a JSON file containing the messages payload.
	 *
	 * [--messages=<json>]
	 * : Alternative way to provide JSON messages payload.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pos openai responses
	 *     wp pos openai responses '[{"role":"user","content":[{"type":"input_text","text":"Hello!"}]}]'
	 *     wp pos openai responses --file=messages.json
	 *
	 * @param array $args       Positional CLI arguments.
	 * @param array $assoc_args Associative CLI arguments.
	 *
	 * @return void
	 */
	public function cli_openai_responses( array $args, array $assoc_args = array() ): void {
		$messages_payload = '';

		if ( ! empty( $assoc_args['file'] ) ) {
			$file_path = $assoc_args['file'];
			if ( ! file_exists( $file_path ) ) {
				WP_CLI::error(
					sprintf(
						'File not found: %s',
						$file_path
					)
				);
			}
			// Reading local file path provided via CLI.
			global $wp_filesystem;
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
			$messages_payload = $wp_filesystem->get_contents( $file_path );
			if ( false === $messages_payload ) {
				WP_CLI::error(
					sprintf(
						'Unable to read file: %s',
						$file_path
					)
				);
			}
		} elseif ( ! empty( $assoc_args['messages'] ) ) {
			$messages_payload = (string) $assoc_args['messages'];
		} elseif ( ! empty( $args[0] ) ) {
			$messages_payload = (string) $args[0];
		}

		if ( '' !== $messages_payload ) {
			$messages = json_decode( $messages_payload, true );
			if ( null === $messages && JSON_ERROR_NONE !== json_last_error() ) {
				WP_CLI::error(
					sprintf(
						'Invalid JSON provided. Error: %s',
						json_last_error_msg()
					)
				);
			}
			if ( ! is_array( $messages ) ) {
				WP_CLI::error( 'Messages payload must decode to an array.' );
			}
		} else {
			$messages = array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'input_text',
							'text' => 'Introduce yourself and summarise the PersonalOS plugin.',
						),
					),
				),
			);
			WP_CLI::log( 'No messages provided, using default sample prompt.' );
		}

		$result = $this->complete_responses(
			$messages,
			function ( string $event_type, $event ) {
				switch ( $event_type ) {
					case 'tool_call':
						$tool_name = 'unknown';
						if ( isset( $event->name ) ) {
							$tool_name = $event->name;
						} elseif ( isset( $event->function->name ) ) {
							$tool_name = $event->function->name;
						}
						WP_CLI::log(
							sprintf(
								'Tool call requested: %s',
								$tool_name
							)
						);
						break;
					case 'tool_result':
						WP_CLI::log(
							sprintf(
								'Tool result (%s): %s',
								isset( $event['name'] ) ? $event['name'] : 'unknown',
								isset( $event['content'] ) && is_string( $event['content'] ) ? $event['content'] : wp_json_encode( $event, JSON_PRETTY_PRINT )
							)
						);
						break;
					case 'message':
						$content = array();
						if ( isset( $event->content ) && is_array( $event->content ) ) {
							foreach ( $event->content as $chunk ) {
								if ( isset( $chunk->text ) ) {
									$content[] = $chunk->text;
								}
							}
						}
						if ( ! empty( $content ) ) {
							WP_CLI::log(
								sprintf(
									'[assistant] %s',
									implode( "\n", $content )
								)
							);
						}
						break;
					default:
						WP_CLI::log(
							sprintf(
								'Unhandled event (%s): %s',
								$event_type,
								wp_json_encode( $event, JSON_PRETTY_PRINT )
							)
						);
						break;
				}
			}
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			if ( ! empty( $error_data ) && is_array( $error_data ) && isset( $error_data[0] ) ) {
				WP_CLI::log( 'Raw API response:' );
				WP_CLI::log( wp_json_encode( $error_data[0], JSON_PRETTY_PRINT ) );
			}
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::log(
			wp_json_encode(
				$result,
				JSON_PRETTY_PRINT
			)
		);

		WP_CLI::success( 'Responses API call completed.' );
	}

	/**
	 * Output the system prompt used for OpenAI API calls.
	 *
	 * Allows viewing the system prompt that will be used for OpenAI API calls.
	 * Optionally accepts a post ID to use a specific prompt post's content.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : Post ID of a prompt post to use. If provided, the post content will be
	 *   converted to markdown and used as the system prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pos openai system-prompt
	 *     wp pos openai system-prompt 123
	 *
	 * @param array $args       Positional CLI arguments.
	 * @param array $assoc_args Associative CLI arguments.
	 *
	 * @return void
	 */
	public function cli_openai_system_prompt( array $args = array(), array $assoc_args = array() ): void {
		$prompt = null;

		if ( ! empty( $args[0] ) ) {
			$post_id = intval( $args[0] );
			$prompt = get_post( $post_id );
			if ( ! $prompt ) {
				WP_CLI::error(
					sprintf(
						'Post not found: %d',
						$post_id
					)
				);
			}
		}

		$config = $this->get_prompt_config( $prompt );

		WP_CLI::log( $config['prompt_string'] );
		WP_CLI::log( 'Model: ' . $config['model'] );
		WP_CLI::success( 'System prompt output completed.' );
	}

	public function admin_menu() {
		add_submenu_page(
			'tools.php',
			'Custom GPT',
			'Custom GPT',
			'manage_options',
			'pos-custom-gpt',
			'pos_render_custom_gpt_page'
		);
		add_submenu_page(
			'personalos',
			'Voice Chat',
			'Voice Chat',
			'manage_options',
			'pos-voice-chat',
			'pos_render_voice_chat_page'
		);
		add_action(
			'admin_head',
			function() {
				if ( get_current_screen()->id !== 'personal-os_page_pos-voice-chat' ) {
					return;
				}
				?>
				<meta name="apple-mobile-web-app-capable" content="yes">
				<meta name="apple-mobile-web-app-status-bar-style" content="default">

				<!-- Set the app title -->
				<meta name="apple-mobile-web-app-title" content="PersonalOS Voice Mode">
				<?php
			}
		);
	}

	/**
	 * List posts ability.
	 *
	 * @param array $args Arguments for get_posts (posts_per_page, post_type, post_status).
	 * @return array Array of formatted post objects.
	 */
	public function list_posts_ability( $args ) {
		return array_map(
			function( $post ) {
				return array(
					'id'      => $post->ID,
					'title'   => $post->post_title,
					'date'    => $post->post_date,
					'excerpt' => $post->post_excerpt,
					'url'     => get_permalink( $post ),
				);
			},
			get_posts( $args )
		);
	}

	/**
	 * Create or update an AI memory ability.
	 *
	 * @param array $args Arguments with ID, post_title, and post_content.
	 * @return array Array with URL of created/updated memory.
	 */
	public function create_ai_memory_ability( $args ) {
		$memory_id = wp_insert_post(
			array_merge(
				$args,
				array(
					'post_type'   => 'notes',
					'post_status' => 'publish',
				)
			)
		);
		wp_set_object_terms( $memory_id, array( 'ai-memory' ), 'notebook' );
		return array(
			'url' => get_permalink( $memory_id ),
		);
	}

	/**
	 * Get AI memories ability.
	 *
	 * @param array $args Arguments (currently unused, but required by ability interface).
	 * @return array Array of memory objects with title, content, and date.
	 */
	public function get_ai_memories_ability( $args ) {
		return array_map(
			function( $memory ) {
				return array(
					'title'   => $memory->post_title,
					'content' => $memory->post_content,
					'date'    => $memory->post_date,
				);
			},
			get_posts(
				array(
					'post_type'   => 'notes',
					'post_status' => 'publish',
					'tax_query'   => array(
						array(
							'taxonomy' => 'notebook',
							'field'    => 'slug',
							'terms'    => 'ai-memory',
						),
					),
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * Get system state ability.
	 *
	 * @param array $args Arguments (currently unused, but required by ability interface).
	 * @return array System state with me, system, and you variables.
	 */
	public function get_system_state_ability( $args ) {
		$current_user = wp_get_current_user();
		return array(
			'user_display_name' => $current_user->display_name,
			'user_description'  => $current_user->description,
			'system_time'       => gmdate( 'Y-m-d H:i:s' ),
		);
	}



	public function rest_api_init() {
		register_rest_route(
			$this->rest_namespace,
			'/openai/realtime/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'realtime_session' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->rest_namespace,
			'/openai/chat/completions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_api' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/chat/assistant',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_assistant' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/media/describe/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'media_describe' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/vercel/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'vercel_chat' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/responses',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'responses_api' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public function realtime_session( WP_REST_Request $request ) {
		$prompt_config = $this->get_prompt_config( null, 'gpt-4o-realtime-preview-2024-12-17' );

		$result = $this->api_call(
			'https://api.openai.com/v1/realtime/sessions',
			array(
				'model'                     => $prompt_config['model'],
				'instructions'              => $prompt_config['prompt_string'],
				'voice'                     => 'ballad',
				'temperature'               => 0.6,
				'input_audio_transcription' => array(
					'model' => 'whisper-1',
				),
				// 'modalities' => array(
				// 	'text',
				// ),
				'turn_detection'            => array(
					'type'              => 'server_vad',
					'threshold'         => 0.8,
					'prefix_padding_ms' => 100,
				),
				'tools'                     => $this->get_abilities_as_tools( 'realtime' ),
			)
		);
		return $result;
	}

	/**
	 * Get prompt configuration including system prompt string, model, and post object.
	 *
	 * This consolidates all prompt-related logic: loading the default prompt,
	 * extracting the model from meta, and converting post content to markdown.
	 *
	 * @TODO: This could parse Gutenberg blocks properly instead of just HTML conversion.
	 *
	 * @param WP_Post|null $prompt Optional prompt post. If null, loads default prompt.
	 * @param string       $default_model Default model to use if not specified in prompt meta.
	 * @return array Configuration array with:
	 *               - 'prompt_string': The system prompt text (HTML converted to markdown)
	 *               - 'model': The model to use (from pos_model meta or default)
	 *               - 'post': The WP_Post object (or null if not found)
	 */
	public function get_prompt_config( $prompt = null, $default_model = 'gpt-4o' ) {
		// Load default prompt if not provided
		if ( ! $prompt instanceof \WP_Post ) {
			$notes_module = POS::get_module_by_id( 'notes' );
			$prompts = $notes_module->list( array( 'name' => 'prompt_default' ), 'prompts-chat' );
			$prompt = ! empty( $prompts ) ? $prompts[0] : null;
		}

		$model = $default_model;
		$prompt_string = '';

		if ( $prompt ) {
			// Get model from prompt meta
			$pos_model = get_post_meta( $prompt->ID, 'pos_model', true );
			if ( $pos_model ) {
				$model = $pos_model;
			}

			// Convert post content to markdown-like format
			$content = apply_filters( 'the_content', $prompt->post_content );

			// Convert headings to markdown
			$content = preg_replace_callback(
				'/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i',
				function( $matches ) {
					$level = $matches[1];
					$text = $matches[2];
					return str_repeat( '#', intval( $level ) ) . ' ' . $text;
				},
				$content
			);

			// Convert list items to markdown
			$content = preg_replace_callback(
				'/<li[^>]*>(.*?)<\/li>/i',
				function( $matches ) {
					return '- ' . $matches[1];
				},
				$content
			);

			$prompt_string = wp_strip_all_tags( $content );
		}

		return array(
			'prompt_string' => $prompt_string,
			'model'         => $model,
			'post'          => $prompt,
		);
	}

	/**
	 * Recursively converts an array to XML string
	 *
	 * @param mixed  $data    The data to convert
	 * @param string $indent  Current indentation level
	 * @return string The XML string
	 */
	private function array_to_xml( $data, string $indent = '' ): string {
		if ( is_string( $data ) || is_numeric( $data ) || is_bool( $data ) ) {
			// Escape XML special characters for simple values
			return htmlspecialchars( (string) $data, ENT_XML1, 'UTF-8' );
		}

		if ( ! is_array( $data ) ) {
			return '';
		}

		$xml = array();
		$is_indexed = array_keys( $data ) === range( 0, count( $data ) - 1 );

		foreach ( $data as $key => $value ) {
			// Handle indexed arrays (numeric keys)
			if ( is_int( $key ) ) {
				$xml[] = "{$indent}<item>";
				if ( is_array( $value ) ) {
					$xml[] = $this->array_to_xml( $value, $indent . "\t" );
				} else {
					$escaped_value = htmlspecialchars( (string) $value, ENT_XML1, 'UTF-8' );
					$xml[] = $indent . "\t" . $escaped_value;
				}
				$xml[] = "{$indent}</item>";
				continue;
			}

			// Sanitize element name for XML
			$element_name = preg_replace( '/[^a-z0-9_-]/i', '_', (string) $key );
			if ( empty( $element_name ) ) {
				$element_name = 'item';
			}

			// Start element
			$xml[] = "{$indent}<{$element_name}>";

			// Handle value based on type
			if ( is_array( $value ) ) {
				$xml[] = $this->array_to_xml( $value, $indent . "\t" );
			} else {
				// Escape XML special characters in values
				$escaped_value = htmlspecialchars( (string) $value, ENT_XML1, 'UTF-8' );
				$xml[] = $indent . "\t" . $escaped_value;
			}

			// Close element
			$xml[] = "{$indent}</{$element_name}>";
		}

		return implode( "\n", $xml );
	}

	public function media_describe( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		$media = wp_get_attachment_url( $id );
		if ( ! $media ) {
			return new WP_Error( 'no-media', 'Media not found' );
		}
		$result = $this->api_call(
			'https://api.openai.com/v1/chat/completions',
			array(
				'model'    => 'gpt-4o',
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => $this->get_setting( 'prompt_describe_image' ),
					),
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'image_url' => $media,
							),
						),
					),
				),
			)
		);
		$this->log( 'Media describe result: ' . print_r( $result, true ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $result->error ) ) {
			return new WP_Error( 'openai-error', $result->error->message );
		}
		if ( ! isset( $result->choices[0]->message->content ) ) {
			return new WP_Error( 'no-response', 'No response from OpenAI' );
		}
		$description = $result->choices[0]->message->content;
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $description,
			)
		);

		return array( 'description' => $result->choices[0]->message->content );
	}

	public function chat_api( WP_REST_Request $request ) {
		return $this->api_call( 'https://api.openai.com/v1/chat/completions', $request->get_json_params() );
	}

	public function chat_assistant( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$backscroll = $params['messages'];
		return $this->complete_backscroll( $backscroll );
	}

	public function responses_api( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$messages = $params['messages'] ?? array();
		return $this->complete_responses( $messages );
	}

	public function complete_responses( array $messages, callable $callback = null, ?string $previous_response_id = null, ?\WP_Post $prompt = null, ?array $persistence = null ) {
		$should_persist = is_array( $persistence );
		$persist_append = $should_persist ? (bool) ( $persistence['append'] ?? true ) : true;
		$persist_search_args = $should_persist ? (array) ( $persistence['search_args'] ?? array() ) : array();
		$persist_post_id = $should_persist && isset( $persist_search_args['ID'] ) ? (int) $persist_search_args['ID'] : null;

		// Persist initial user messages if persistence is enabled
		if ( $should_persist ) {
			$result = $this->save_backscroll( $messages, $persist_search_args, $persist_append );
			if ( ! is_wp_error( $result ) ) {
				$persist_post_id = $result;
				$persist_search_args['ID'] = $persist_post_id;
				$persist_append = true; // After initial write, always append subsequent messages
				// Emit post_id callback so callers can capture the created/updated post ID
				if ( $callback ) {
					$callback( 'post_id', $persist_post_id );
				}
			} else {
				$this->log( '[complete_responses] Failed to save backscroll: ' . $result->get_error_message(), 'ERROR' );
			}
		}

		// Get abilities for Responses API (excluding perplexity-search since we have built-in web_search)
		$tools_result = $this->get_abilities_as_tools(
			'responses',
			array(
				'exclude'           => array( 'pos/perplexity-search' ),
				'builtin_tools'     => array( array( 'type' => 'web_search' ) ),
				'include_abilities' => true,
			)
		);
		$tools = $tools_result['abilities'];
		$tool_definitions = $tools_result['definitions'];

		// Get prompt configuration (model, prompt string, and post object)
		$prompt_config = $this->get_prompt_config( $prompt );
		$model = $prompt_config['model'];

		if ( $prompt_config['post'] ) {
			$this->log( '[complete_responses] Using prompt: ' . $prompt_config['post']->post_title . ' (ID: ' . $prompt_config['post']->ID . ') with model: ' . $model );
		} else {
			$this->log( '[complete_responses] No prompt provided, using default model: ' . $model );
		}

		$max_loops = 10;
		$full_messages = $messages; // Keep full history for return value
		$should_store_remote = $should_persist || ! empty( $previous_response_id );
		do {
			--$max_loops;
			$has_function_calls = false;

			// Build the API request payload
			$request_data = array(
				'model'        => $model,
				'instructions' => $prompt_config['prompt_string'],
				'tools'        => $tool_definitions,
				'store'        => $should_store_remote,
			);

			// Use previous_response_id if available, otherwise use input
			if ( $previous_response_id ) {
				$request_data['previous_response_id'] = $previous_response_id;
				// Add tool results as new input items (only tool results, not full history)
				if ( ! empty( $messages ) ) {
					$request_data['input'] = $messages;
				}
			} else {
				$request_data['input'] = $messages;
			}

			$response = $this->api_call(
				'https://api.openai.com/v1/responses',
				$request_data
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( isset( $response->error ) ) {
				return new WP_Error( $response->error->code ?? 'openai-error', $response->error->message ?? 'OpenAI API error' );
			}

			if ( isset( $response->message, $response->code ) ) {
				return new WP_Error( $response->code, $response->message );
			}

			if ( ! isset( $response->status ) ) {
				return new WP_Error( 'no-status', 'No status in response', array( $response, $messages ) );
			}

			// Capture the response ID for use in subsequent calls
			if ( isset( $response->id ) ) {
				$previous_response_id = $response->id;
				if ( $should_persist && $persist_post_id ) {
					update_post_meta( $persist_post_id, 'pos_last_response_id', $response->id );
				}
				if ( $callback ) {
					$callback( 'response_id', $response->id );
				}
			}

			// Handle function calls from output array (Responses API format)
			$tool_results = array();
			$has_builtin_tool_calls = false;

			// If status is in_progress, continue polling (especially for built-in tools like web_search)
			if ( isset( $response->status ) && $response->status === 'in_progress' ) {
				// Continue the loop to poll for results
				$has_builtin_tool_calls = true;
				continue;
			}
			if ( ! empty( $response->output ) ) {
				foreach ( $response->output as $output_item ) {
					// Handle function calls
					if ( isset( $output_item->type ) && $output_item->type === 'function_call' ) {
						$has_function_calls = true;
						if ( $callback ) {
							$callback( 'tool_call', $output_item );
						}

						// Built-in tools like web_search are handled by OpenAI automatically
						// Check if this is a built-in tool that doesn't need local execution
						$is_builtin_tool = isset( $output_item->name ) && in_array( $output_item->name, array( 'web_search' ), true );

						if ( $is_builtin_tool ) {
							// Built-in tools are executed by OpenAI automatically
							// We need to continue the loop to get the results, but don't submit tool results
							$has_builtin_tool_calls = true;
							continue;
						}

						$arguments = json_decode( $output_item->arguments ?? '{}', true );

						// Find the ability by tool name (need to convert BEM-style to ability name)
						$ability = null;
						foreach ( $tools as $tool_ability ) {
							$tool_name = $this->get_tool_id_from_ability_name( $tool_ability->get_name() );
							if ( $tool_name === $output_item->name ) {
								$ability = $tool_ability;
								break;
							}
						}

						if ( ! $ability ) {
							return new WP_Error( 'ability-not-found', 'Ability not found: ' . $output_item->name );
						}

						try {
							$result = $ability->execute( $arguments );
						} catch ( \Exception $e ) {
							$this->log( 'Ability execution error for ' . $output_item->name . ': ' . $e->getMessage() );
							$result = new WP_Error(
								'ability-execution-error',
								sprintf(
									'Error executing ability %s: %s',
									$output_item->name,
									$e->getMessage()
								)
							);
						}

						if ( is_wp_error( $result ) ) {
							// Convert WP_Error to a string result so the conversation can continue
							$result = sprintf(
								'Error: %s',
								$result->get_error_message()
							);
						}
						if ( ! is_string( $result ) ) {
							$result = wp_json_encode( $result, JSON_PRETTY_PRINT );
						}
						// Format tool result for Responses API
						// When using previous_response_id, tool results must be formatted as function_call_output
						// with the call_id matching the function call from the previous response
						$res = array(
							'type'    => 'function_call_output',
							'call_id' => $output_item->call_id ?? null,
							'output'  => $result,
						);
						if ( $callback ) {
							$callback( 'tool_result', $res );
						}
						$tool_results[] = $res;
						// Add to full history - wrap function_call_output for return value
						$full_messages[] = array(
							'role'    => 'assistant',
							'content' => array( $res ),
						);
					} elseif ( isset( $output_item->type ) && $output_item->type === 'message' ) {
						// Add messages to the conversation for final return
						$full_messages[] = $output_item;
						if ( $callback ) {
							$callback( 'message', $output_item );
						}
						if ( $should_persist ) {
							$assistant_text = '';
							if ( isset( $output_item->content ) && is_array( $output_item->content ) ) {
								foreach ( $output_item->content as $chunk ) {
									if ( is_object( $chunk ) ) {
										$chunk = (array) $chunk;
									}
									if ( isset( $chunk['text'] ) ) {
										$assistant_text .= $chunk['text'];
									}
								}
							} elseif ( isset( $output_item->content ) && is_string( $output_item->content ) ) {
								$assistant_text = $output_item->content;
							}

							if ( '' !== $assistant_text ) {
								$result = $this->save_backscroll(
									array(
										array(
											'role'    => 'assistant',
											'content' => $assistant_text,
										),
									),
									$persist_search_args,
									$persist_append
								);
								if ( ! is_wp_error( $result ) ) {
									$persist_post_id = $result;
									$persist_search_args['ID'] = $persist_post_id;
									$persist_append = true; // After initial write, always append subsequent messages
								} else {
									$this->log( '[complete_responses] Failed to save backscroll: ' . $result->get_error_message(), 'ERROR' );
								}
							}
						}
					}
				}
			}

			// For subsequent calls, only pass tool results (not full history)
			if ( $previous_response_id && ! empty( $tool_results ) ) {
				$messages = $tool_results;
			} elseif ( $previous_response_id && $has_builtin_tool_calls ) {
				// For built-in tools, don't pass any input - OpenAI handles execution automatically
				$messages = array();
			} elseif ( $previous_response_id ) {
				// If no tool results but we have a previous response, use empty array
				$messages = array();
			}
		} while ( ( $has_function_calls || $has_builtin_tool_calls ) && $max_loops > 0 );
		return $full_messages;
	}

	/**
	 * Convert an ability name to tool ID format.
	 * This will turn the namespacing / into BEM-like __ and - into _.
	 * Order matters: we replace / first to avoid ambiguity.
	 *
	 * @param string $ability_name The ability name to convert.
	 * @return string The tool ID.
	 */
	private function get_tool_id_from_ability_name( $ability_name ) {
		// Replace / with __ first, then - with _
		$tool_id = str_replace( '/', '__', $ability_name );
		$tool_id = str_replace( '-', '_', $tool_id );
		return $tool_id;
	}

	/**
	 * Convert a tool ID back to ability name format.
	 * This reverses the BEM-style naming: __ becomes / and _ becomes -.
	 * Order matters: we replace __ first to avoid ambiguity.
	 *
	 * @param string $tool_name The tool ID to convert.
	 * @return string The ability name.
	 */
	private function get_ability_name_from_tool_id( $tool_name ) {
		// Replace __ with / first, then _ with -
		$ability_name = str_replace( '__', '/', $tool_name );
		$ability_name = str_replace( '_', '-', $ability_name );
		return $ability_name;
	}

	/**
	 * Convert a WP_Ability to OpenAI function signature format.
	 *
	 * @param WP_Ability $ability The ability to convert.
	 * @param string     $api API format: 'chat', 'responses', or 'realtime'. Default 'chat'.
	 * @return array The function signature.
	 */
	private function ability_to_function_signature( $ability, $api = 'chat' ) {
		$input_schema = $ability->get_input_schema();
		$properties   = $input_schema['properties'] ?? array();
		$required     = $input_schema['required'] ?? array();

		// Convert ability name to tool name format
		$tool_name = $this->get_tool_id_from_ability_name( $ability->get_name() );

		// For strict mode to work with OpenAI, ALL properties must be in the required array
		// If the ability has optional parameters, we can't use strict mode
		$use_strict = ! empty( $required ) && count( $required ) === count( $properties );

		$parameters = array(
			'type'                 => 'object',
			'properties'           => (object) $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);

		$responses_signature = array(
			'type'        => 'function',
			'name'        => $tool_name,
			'strict'      => $use_strict,
			'description' => $ability->get_description(),
			'parameters'  => $parameters,
		);

		if ( 'realtime' === $api ) {
			// Realtime API doesn't support 'strict' parameter
			return array(
				'type'        => 'function',
				'name'        => $tool_name,
				'description' => $ability->get_description(),
				'parameters'  => $parameters,
			);
		}

		if ( 'responses' === $api ) {
			return $responses_signature;
		}

		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $responses_signature['name'],
				'strict'      => $responses_signature['strict'],
				'description' => $responses_signature['description'],
				'parameters'  => $responses_signature['parameters'],
			),
		);
	}

	/**
	 * Get abilities formatted for OpenAI function calling.
	 *
	 * @param string $api API format: 'chat', 'responses', or 'realtime'. Default 'chat'.
	 * @param array  $options Optional configuration array:
	 *               - 'exclude': Array of ability names to exclude (e.g., array( 'pos/perplexity-search' ))
	 *               - 'builtin_tools': Array of built-in tools to add (e.g., array( array( 'type' => 'web_search' ) ))
	 *               - 'include_abilities': Whether to return ability objects alongside definitions. Default false.
	 * @return array If 'include_abilities' is false, returns array of tool definitions.
	 *               If 'include_abilities' is true, returns array with 'definitions' and 'abilities' keys.
	 */
	private function get_abilities_as_tools( $api = 'chat', $options = array() ) {
		$exclude = isset( $options['exclude'] ) ? (array) $options['exclude'] : array();
		$builtin_tools = isset( $options['builtin_tools'] ) ? (array) $options['builtin_tools'] : array();
		$include_abilities = isset( $options['include_abilities'] ) ? (bool) $options['include_abilities'] : false;

		if ( ! function_exists( 'wp_get_abilities' ) ) {
			if ( $include_abilities ) {
				return array(
					'definitions' => $builtin_tools,
					'abilities'   => array(),
				);
			}
			return $builtin_tools;
		}

		$abilities = wp_get_abilities();
		$tool_definitions = array();
		$filtered_abilities = array();

		foreach ( $abilities as $ability ) {
			// Only include PersonalOS abilities
			if ( strpos( $ability->get_name(), 'pos/' ) !== 0 ) {
				continue;
			}

			// Skip excluded abilities
			if ( in_array( $ability->get_name(), $exclude, true ) ) {
				continue;
			}

			$tool_definitions[] = $this->ability_to_function_signature( $ability, $api );
			$filtered_abilities[] = $ability;
		}

		// Add built-in tools at the end
		foreach ( $builtin_tools as $builtin_tool ) {
			$tool_definitions[] = $builtin_tool;
		}

		if ( $include_abilities ) {
			return array(
				'definitions' => $tool_definitions,
				'abilities'   => $filtered_abilities,
			);
		}

		return $tool_definitions;
	}

	/**
	 * Execute an ability by tool name.
	 *
	 * @param string $tool_name Tool name (with BEM-style naming).
	 * @param array  $arguments Arguments for the ability.
	 * @return mixed Result of ability execution or WP_Error.
	 */
	private function execute_ability( $tool_name, $arguments ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'abilities-not-available', 'Abilities API is not available' );
		}

		// Convert tool name back to ability name
		$ability_name = $this->get_ability_name_from_tool_id( $tool_name );

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return new WP_Error( 'ability-not-found', 'Ability not found: ' . $ability_name );
		}

		return $ability->execute( $arguments );
	}

	public function complete_backscroll( array $backscroll, callable $callback = null, ?\WP_Post $prompt = null ) {
		$prompt_config = $this->get_prompt_config( $prompt );
		$tool_definitions = $this->get_abilities_as_tools();
		$max_loops = 10;
		do {
			--$max_loops;
			$completion = $this->api_call(
				'https://api.openai.com/v1/chat/completions',
				array(
					'model'    => $prompt_config['model'],
					'messages' => array_merge(
						array(
							array(
								'role'    => 'system',
								'content' => $prompt_config['prompt_string'],
							),
						),
						$backscroll
					),
					'tools'    => $tool_definitions,
				)
			);
			//return $completion;
			if ( is_wp_error( $completion ) ) {
				return $completion;
			}

			if ( isset( $completion->message, $completion->code ) ) {
				return new WP_Error( $completion->code, $completion->message );
			}

			if ( ! isset( $completion->choices[0]->finish_reason ) ) {
				return new WP_Error( 'no-finish-reason', 'No finish reason', array( $completion, $backscroll ) );
			}

			$backscroll[] = $completion->choices[0]->message;
			if ( $completion->choices[0]->finish_reason === 'tool_calls' || ! empty( $completion->choices[0]->message->tool_calls ) ) {
				$tool_calls = $completion->choices[0]->message->tool_calls;
				foreach ( $tool_calls as $tool_call ) {
					if ( $callback ) {
						$callback( 'tool_call', $tool_call );
					}
					$arguments = json_decode( $tool_call->function->arguments, true );

					$result = $this->execute_ability( $tool_call->function->name, $arguments );

					if ( is_wp_error( $result ) ) {
						return $result;
					}
					if ( ! is_string( $result ) ) {
						$result = wp_json_encode( $result, JSON_PRETTY_PRINT );
					}
					$res = array(
						'role'         => 'tool',
						'name'         => $tool_call->function->name,
						'content'      => $result,
						'tool_call_id' => $tool_call->id,
					);
					if ( $callback ) {
						$callback( 'tool_result', $res );
					}
					$backscroll[] = $res;
				}
			} else {
				if ( $callback ) {
					$callback( 'message', $completion->choices[0]->message );
				}
			}
		} while ( ( $completion->choices[0]->finish_reason !== 'stop' || ! empty( $completion->choices[0]->message->tool_calls ) ) && $max_loops > 0 );
		return $backscroll;
	}

	public function api_call( $url, $data ) {
		$api_key = $this->get_setting( 'api_key' );

		// Log request details (sanitize sensitive data)
		$log_data = $data;
		if ( isset( $log_data['input'] ) && is_array( $log_data['input'] ) ) {
			// Log input messages but truncate long content
			$log_data['input'] = array_map(
				function( $item ) {
					if ( isset( $item['content'] ) && is_array( $item['content'] ) ) {
						$item['content'] = array_map(
							function( $content_item ) {
								if ( isset( $content_item['text'] ) && strlen( $content_item['text'] ) > 200 ) {
									$content_item['text'] = substr( $content_item['text'], 0, 200 ) . '... (truncated)';
								}
								return $content_item;
							},
							$item['content']
						);
					}
					return $item;
				},
				$log_data['input']
			);
		}
		$this->log( '[api_call] Request to: ' . $url );
		$this->log( '[api_call] Request data: ' . wp_json_encode( $log_data ) );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $response );
		$this->log( '[api_call] Response code: ' . $response_code );

		$body = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) ) {
			$this->log( '[api_call] WP_Error: ' . $response->get_error_message(), 'ERROR' );
			return $response;
		}

		$decoded_body = json_decode( $body );
		if ( $response_code >= 400 ) {
			$this->log( '[api_call] Error response body: ' . substr( $body, 0, 500 ), 'ERROR' );
		} else {
			// Log a summary of successful responses (truncate if too long)
			$response_summary = wp_json_encode( $decoded_body );
			if ( strlen( $response_summary ) > 500 ) {
				$response_summary = substr( $response_summary, 0, 500 ) . '... (truncated)';
			}
			$this->log( '[api_call] Response summary: ' . $response_summary );
		}

		return $decoded_body;
	}

	/**
	 * Save conversation backscroll as a note. This WILL NOT set any meta fields passed to meta_query.
	 *
	 * @param array $backscroll Array of conversation messages
	 * @param array $search_args Search arguments for get_posts to find existing notes, also used for new post configuration
	 *                           - 'ID': Exact Post ID to update/use
	 *                           - 'name': The post slug/name to search for and use when creating new posts
	 *                           - 'post_title': Title for new posts (optional, defaults to auto-generated)
	 *                           - 'notebook': Notebook slug to assign to new posts (optional, defaults to 'ai-chats')
	 *                           - Any other valid get_posts() arguments for finding existing posts
	 * @param bool $append       Whether to append the backscroll to existing content instead of overwriting
	 * @return int|WP_Error Post ID on success, WP_Error on failure
	 */
	public function save_backscroll( array $backscroll, array $search_args, bool $append = false ) {
		$notes_module = POS::get_module_by_id( 'notes' );
		if ( ! $notes_module ) {
			return new WP_Error( 'notes_module_not_found', 'Notes module not available' );
		}

		// If explicit ID is provided, check validity and ownership
		if ( ! empty( $search_args['ID'] ) ) {
			$post = get_post( $search_args['ID'] );
			if ( ! $post || 'notes' !== $post->post_type ) {
				return new WP_Error( 'invalid_post_id', 'Invalid Post ID provided.' );
			}
			// Assuming permission check happens upstream or we trust internal calls
			// But let's be safe if it's user context
			// if ( get_current_user_id() !== (int) $post->post_author && ! current_user_can( 'manage_options' ) ) {
			// 	return new WP_Error( 'permission_denied', 'You do not have permission to edit this conversation.' );
			// }
			$existing_posts = array( $post );
		} elseif ( ! empty( $search_args['name'] ) ) {
			// Only search for existing posts if a name/slug is provided
			// This allows finding posts by slug when we want to update an existing conversation
			$existing_posts = $notes_module->list( $search_args, 'ai-chats' );
		} elseif ( ! empty( $search_args['meta_query'] ) ) {
			$query_args = $search_args;
			unset( $query_args['post_title'], $query_args['notebook'] );
			$query_args = wp_parse_args(
				$query_args,
				array(
					'post_type'      => $notes_module->id,
					'post_status'    => 'any', // Match any status (private, publish, draft, etc.) in case user changed it
					'posts_per_page' => 1,
				)
			);
			$existing_posts = get_posts( $query_args );
		} else {
			// No ID and no name provided - always create a new post
			// This is the case when bootstrapping a new conversation
			$existing_posts = array();
		}

		// Check if existing post has a placeholder title (marked with meta flag)
		$has_placeholder_title = false;
		if ( ! empty( $existing_posts ) ) {
			$has_placeholder_title = (bool) get_post_meta( $existing_posts[0]->ID, '_pos_placeholder_title', true );
		}

		// Generate title if not provided and OpenAI is configured
		// Also generate if existing post has a placeholder title and we now have actual messages
		$post_title = $search_args['post_title'] ?? null;
		$should_generate_title = ! $post_title
			&& ! empty( $backscroll )
			&& $this->is_configured()
			&& ( empty( $existing_posts ) || $has_placeholder_title );

		if ( $should_generate_title ) {
			$post_title = $this->generate_conversation_title( $backscroll );
			// Clear the placeholder flag if we successfully generated a title
			if ( $post_title && $has_placeholder_title ) {
				delete_post_meta( $existing_posts[0]->ID, '_pos_placeholder_title' );
			}
		}

		// Fall back to default title if generation failed or not configured
		// Mark it as placeholder so we can regenerate later
		$is_placeholder_title = false;
		if ( ! $post_title && empty( $existing_posts ) ) {
			$post_title = 'Chat ' . gmdate( 'Y-m-d H:i:s' );
			$is_placeholder_title = true;
		}

		// Determine post ID for checking pos_last_response_id
		$post_id_for_meta = null;
		if ( ! empty( $search_args['ID'] ) ) {
			$post_id_for_meta = $search_args['ID'];
		} elseif ( ! empty( $existing_posts ) ) {
			$post_id_for_meta = $existing_posts[0]->ID;
		}

		// Create content from backscroll messages
		$content_blocks = array();
		foreach ( $backscroll as $message ) {
			if ( is_object( $message ) ) {
				$message = (array) $message;
			}

			// Normalize for Response API output format (e.g. function_call_output)
			// or just standard messages
			$role = $message['role'] ?? null;
			$content = $message['content'] ?? '';

			// Handle tool results (function_call_output) which might not have a standard 'role' field in raw API response
			// but here we expect standardized message objects if possible.
			// If it's a tool result from complete_responses callback, it might have a different structure
			// mapped to 'function_call_output' type in response output.
			// However, the callback constructs $res with 'type' => 'function_call_output'
			// We need to map this to a 'tool' or 'assistant' role block.

			if ( isset( $message['type'] ) && 'function_call_output' === $message['type'] ) {
				$role = 'assistant';
				$content = is_string( $message['output'] ) ? $message['output'] : wp_json_encode( $message['output'] );
			}

			// If content is array (e.g. multimodal), stringify or extract text
			if ( is_array( $content ) ) {
				// Try to extract text part
				$text_parts = array();
				foreach ( $content as $part ) {
					if ( isset( $part['type'] ) && 'input_text' === $part['type'] ) {
						$text_parts[] = $part['text'];
					} elseif ( isset( $part['text'] ) ) {
						$text_parts[] = $part['text'];
					}
				}
				if ( ! empty( $text_parts ) ) {
					$content = implode( "\n", $text_parts );
				} else {
					$content = wp_json_encode( $content );
				}
			}

			if ( ! $role ) {
				continue;
			}

			// Only save user and assistant messages, not tool/function calls
			if ( in_array( $role, array( 'user', 'assistant' ), true ) ) {
				// Generate message ID: use provided ID, or pos_last_response_id for assistant messages, or fallback to uniqid
				$message_id = $message['id'] ?? null;
				if ( ! $message_id ) {
					// For assistant messages, try to use pos_last_response_id if available
					if ( 'assistant' === $role && $post_id_for_meta ) {
						$response_id = get_post_meta( $post_id_for_meta, 'pos_last_response_id', true );
						if ( $response_id ) {
							$message_id = $response_id;
						}
					}
					// Fallback to uniqid with generated_ prefix
					if ( ! $message_id ) {
						$message_id = 'generated_' . uniqid();
					}
				}

				// Create message block with content in innerHTML (not attributes)
				// This preserves newlines naturally without escaping hacks
				$inner_html = '<span class="ai-message-text">' . esc_html( $content ) . '</span>';
				$content_blocks[] = get_comment_delimited_block_content(
					'pos/ai-message',
					array(
						'role' => $role,
						'id'   => $message_id,
					),
					$inner_html
				);
			}
		}

		// Prepare post data
		$post_data = array(
			'post_type'   => $notes_module->id,
			'post_status' => 'private',
		);

		if ( ! empty( $post_title ) ) {
			$post_data['post_title'] = $post_title;
		}
		if ( ! empty( $search_args['name'] ) ) {
			$post_data['post_name'] = $search_args['name'];
		}

		// Create or update post
		if ( ! empty( $existing_posts ) ) {
			$post_data['ID'] = $existing_posts[0]->ID;
			// Only keep post_title if we generated a new one (replacing placeholder)
			// Otherwise, don't update the title
			if ( empty( $post_title ) || ! $has_placeholder_title ) {
				unset( $post_data['post_title'] );
			}
			unset( $post_data['post_name'] );

			if ( $append ) {
				// Append new blocks to existing content
				$current_content = $existing_posts[0]->post_content;
				$new_content = implode( "\n\n", $content_blocks );
				if ( ! empty( $new_content ) ) {
					$post_data['post_content'] = $current_content . "\n\n" . $new_content;
				}
			} else {
				// Overwrite content
				$post_data['post_content'] = implode( "\n\n", $content_blocks );
			}

			// Note: wp_slash is handled by preserve_ai_message_newlines filter
			// to protect escaped newlines from wp_unslash

			// Only update if there is content to update or we are not just appending empty content
			// But here we might want to update just to ensure touch?
			if ( isset( $post_data['post_content'] ) || isset( $post_data['post_title'] ) || ! $append ) {
				$post_id = wp_update_post( $post_data );
			} else {
				$post_id = $post_data['ID'];
			}
		} else {
			// Create new
			// Note: wp_slash is handled by preserve_ai_message_newlines filter
			$post_data['post_content'] = implode( "\n\n", $content_blocks );
			// Ensure defaults for new post
			if ( empty( $post_data['post_title'] ) ) {
				$post_data['post_title'] = 'Chat ' . gmdate( 'Y-m-d H:i:s' );
				$is_placeholder_title = true;
			}
			if ( empty( $post_data['post_name'] ) ) {
				$post_data['post_name'] = 'chat-' . gmdate( 'Y-m-d-H-i-s' );
			}

			$post_id = wp_insert_post( $post_data );

			// Mark as placeholder title so we can regenerate later when we have messages
			if ( $is_placeholder_title && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_pos_placeholder_title', true );
			}

			// Add to specified notebook or default to OpenAI chats
			$notebook_slug = $search_args['notebook'] ?? 'ai-chats';
			$notebook = get_term_by( 'slug', $notebook_slug, 'notebook' );

			if ( ! $notebook ) {
				$notebook_name = 'ai-chats' === $notebook_slug ? 'AI Chats' : ucwords( str_replace( '-', ' ', $notebook_slug ) );
				$term_result = wp_insert_term( $notebook_name, 'notebook', array( 'slug' => $notebook_slug ) );
				if ( ! is_wp_error( $term_result ) ) {
					$notebook = get_term( $term_result['term_id'], 'notebook' );
				}
			}

			if ( $notebook ) {
				wp_set_object_terms( $post_id, array( $notebook->term_id ), 'notebook' );
			}
		}

		return $post_id;
	}

	/**
	 * Generate a title for a conversation using GPT-4o-mini
	 *
	 * @param array $backscroll Array of conversation messages
	 * @return string|null Generated title or null if generation failed
	 */
	private function generate_conversation_title( array $backscroll ): ?string {
		// Extract meaningful content from the conversation
		$conversation_content = '';
		$message_count = 0;

		foreach ( $backscroll as $message ) {
			if ( is_object( $message ) ) {
				$message = (array) $message;
			}

			if ( ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				continue;
			}

			// Only include user and assistant messages
			if ( in_array( $message['role'], array( 'user', 'assistant' ), true ) ) {
				// Extract text content - handle both string and array formats (Responses API)
				$content = $message['content'];
				if ( is_array( $content ) ) {
					$text_parts = array();
					foreach ( $content as $part ) {
						if ( is_object( $part ) ) {
							$part = (array) $part;
						}
						if ( isset( $part['type'] ) && 'input_text' === $part['type'] && isset( $part['text'] ) ) {
							$text_parts[] = $part['text'];
						} elseif ( isset( $part['text'] ) ) {
							$text_parts[] = $part['text'];
						}
					}
					$content = implode( "\n", $text_parts );
				}

				if ( empty( $content ) ) {
					continue;
				}

				$conversation_content .= $message['role'] . ': ' . $content . "\n";
				$message_count++;

				// Limit to first few exchanges to avoid token limits
				if ( $message_count >= 6 ) {
					break;
				}
			}
		}

		if ( empty( $conversation_content ) ) {
			return null;
		}

		$title_prompt = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful assistant that creates short, descriptive titles for conversations. Generate a concise title (3-8 words) that captures the main topic or purpose of the conversation. Do not use quotes or special formatting.',
			),
			array(
				'role'    => 'user',
				'content' => "Please create a short title for this conversation:\n\n" . $conversation_content,
			),
		);

		$generated_title = $this->chat_completion( $title_prompt, 'gpt-4o-mini' );

		if ( is_wp_error( $generated_title ) ) {
			return null;
		}

		// Clean up the title
		$generated_title = trim( $generated_title, '"\'`' );
		$generated_title = wp_strip_all_tags( $generated_title );

		// Ensure it's not too long
		if ( strlen( $generated_title ) > 100 ) {
			$generated_title = substr( $generated_title, 0, 97 ) . '...';
		}

		return $generated_title;
	}

	/**
	 * Persist a batch of conversation messages to the configured note.
	 *
	 * @param array $messages_to_save Messages to append/save.
	 * @param array $persist_search_args Reference to search args passed to save_backscroll.
	 * @param int|null $persist_post_id Reference to last persisted post ID.
	 * @param bool $persist_append Whether subsequent writes should append.
	 */
	/**
	 * Get chat prompts as an associative array keyed by slug.
	 *
	 * @param array $args Optional. Arguments to pass to ->list() method. Use 'p' for post ID or 'name' for slug. Empty array returns all prompts.
	 * @return array Associative array where keys are prompt slugs and values are arrays containing:
	 *               - 'id': string (post slug)
	 *               - 'post_id': int (WordPress post ID)
	 *               - 'name': string (post title)
	 *               - 'description': string (trimmed post content)
	 *               - 'model': string (pos_model meta value)
	 *               If $args filters to a single prompt, returns array with one element. Empty array if no matches.
	 */
	public function get_chat_prompts( array $args = array() ): array {
		$notes_module = POS::get_module_by_id( 'notes' );
		if ( ! $notes_module ) {
			return array();
		}

		// Get prompts using ->list() with args
		$prompts = $notes_module->list( $args, 'prompts-chat' );

		// Map results to config array
		$prompts_by_slug = array();
		foreach ( $prompts as $prompt_post ) {
			$pos_model = get_post_meta( $prompt_post->ID, 'pos_model', true );
			$prompts_by_slug[ $prompt_post->post_name ] = array(
				'id'          => $prompt_post->post_name,
				'post_id'     => $prompt_post->ID,
				'name'        => $prompt_post->post_title,
				'description' => $pos_model ? $pos_model : '',
				'model'       => $pos_model ? $pos_model : '',
			);
		}

		return $prompts_by_slug;
	}

	public function vercel_chat( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$this->log( '[vercel_chat] Request received. Params: ' . wp_json_encode( $params ) );

		// Validate Post ID (conversation_id)
		$post_id = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'invalid_id', 'Invalid Conversation ID.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'notes' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Conversation not found.', array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error( 'permission_denied', 'You do not have permission to access this conversation.', array( 'status' => 403 ) );
		}

		$user_message_content = null;
		if ( isset( $params['message']['content'] ) ) {
			$user_message_content = $params['message']['content'];
		} elseif ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) { // Vercel AI SDK often sends a messages array
			$last_message = end( $params['messages'] );
			if ( $last_message && isset( $last_message['content'] ) && 'user' === $last_message['role'] ) {
				$user_message_content = $last_message['content'];
			}
		}

		if ( ! $user_message_content ) {
			$this->log( '[vercel_chat] ERROR: Missing message content' );
			return new WP_Error( 'missing_message_content', 'User message content is required and could not be determined from the request.', array( 'status' => 400 ) );
		}

		$this->log( '[vercel_chat] User message: ' . $user_message_content );

		// Get prompt by slug if selectedChatModel is provided
		$prompt_config = null;
		if ( ! empty( $params['selectedChatModel'] ) ) {
			$this->log( '[vercel_chat] Looking for prompt with slug: ' . $params['selectedChatModel'] );
			$prompts_by_slug = $this->get_chat_prompts( array( 'name' => $params['selectedChatModel'] ) );
			$prompt_config = ! empty( $prompts_by_slug ) ? reset( $prompts_by_slug ) : null;
			if ( $prompt_config ) {
				$this->log( '[vercel_chat] Found prompt: ' . $prompt_config['name'] . ' (ID: ' . $prompt_config['post_id'] . ', slug: ' . $prompt_config['id'] . ', pos_model: ' . ( $prompt_config['model'] ? $prompt_config['model'] : 'none' ) . ')' );
				// Save prompt ID to meta if not already set or changed?
				// Maybe we just use the one from params for this turn.
				// But plan says "Read pos_chat_prompt_id meta".
				// Let's check if one is stored, if not store it.
				$stored_prompt_id = get_post_meta( $post_id, 'pos_chat_prompt_id', true );
				if ( ! $stored_prompt_id ) {
					update_post_meta( $post_id, 'pos_chat_prompt_id', $prompt_config['post_id'] );
				}
			} else {
				$this->log( '[vercel_chat] WARNING: Prompt not found for slug: ' . $params['selectedChatModel'] );
			}
		} else {
			$this->log( '[vercel_chat] No selectedChatModel provided in params' );
			// Try to load from meta
			$stored_prompt_id = get_post_meta( $post_id, 'pos_chat_prompt_id', true );
			if ( $stored_prompt_id ) {
				$prompts_by_slug = $this->get_chat_prompts( array( 'p' => (int) $stored_prompt_id ) );
				$prompt_config = ! empty( $prompts_by_slug ) ? reset( $prompts_by_slug ) : null;
			}
		}

		// Get WP_Post object if we have a prompt config (needed for complete_responses)
		$prompt = null;
		if ( $prompt_config ) {
			$prompt = get_post( $prompt_config['post_id'] );
		}

		// Get previous response ID from meta
		$previous_response_id = get_post_meta( $post_id, 'pos_last_response_id', true );

		$this->log( '[vercel_chat] Previous response ID from meta: ' . ( $previous_response_id ?? 'none' ) );

		// Convert user message to Responses API format
		$user_message = array(
			'role'    => 'user',
			'content' => array(
				array(
					'type' => 'input_text',
					'text' => $user_message_content,
				),
			),
		);

		// For Responses API, if we have a previous_response_id, we only send new input
		// Otherwise, we send the full message
		$messages = $previous_response_id ? array( $user_message ) : array( $user_message );

		$this->log( '[vercel_chat] Messages to send: ' . wp_json_encode( $messages ) );

		require_once __DIR__ . '/class.vercel-ai-sdk.php';
		$vercel_sdk = new Vercel_AI_SDK();
		Vercel_AI_SDK::sendHttpStreamHeaders();
		$vercel_sdk->startStep( $post_id );

		$this->log( '[vercel_chat] Started Vercel SDK step' );

		$conversation_id = $post_id; // Use the Post ID
		$module_instance = $this;

		$response = $this->complete_responses(
			$messages,
			function( $type, $data ) use ( $vercel_sdk, $conversation_id, $module_instance ) {
				$module_instance->log( '[vercel_chat] Callback event: ' . $type . ' - ' . wp_json_encode( $data ) );
				if ( $type === 'message' ) {
					// Responses API message format
					if ( isset( $data->content ) && is_array( $data->content ) ) {
						$full_text = '';
						foreach ( $data->content as $chunk ) {
							if ( isset( $chunk->text ) ) {
								$module_instance->log( '[vercel_chat] Sending text chunk: ' . substr( $chunk->text, 0, 100 ) );
								$vercel_sdk->sendText( $chunk->text );
								$full_text .= $chunk->text;
							}
						}
					}
				} elseif ( $type === 'tool_result' ) {
					// Responses API tool result format
					if ( is_array( $data ) && isset( $data['output'] ) ) {
						$call_id = $data['call_id'] ?? null;
						$module_instance->log( '[vercel_chat] Sending tool result for call_id: ' . $call_id );
						$vercel_sdk->sendToolResult( $call_id, $data['output'] );

						// Don't save tool results to conversation history

					} else {
						// Legacy format?
						$data = (object) $data;
						$module_instance->log( '[vercel_chat] Sending tool result for tool_call_id: ' . ( $data->tool_call_id ?? 'null' ) );
						$vercel_sdk->sendToolResult( $data->tool_call_id ?? null, $data->content ?? '' );
					}
				} elseif ( $type === 'tool_call' ) {
					// Responses API tool call format
					$data = (object) $data;
					$tool_name = $data->name ?? ( isset( $data->function->name ) ? $data->function->name : 'unknown' );
					$arguments = isset( $data->arguments ) ? json_decode( $data->arguments, true ) : array();
					if ( isset( $data->function->arguments ) ) {
						$arguments = json_decode( $data->function->arguments, true );
					}
					$call_id = $data->call_id ?? $data->id ?? null;
					$module_instance->log( '[vercel_chat] Sending tool call: ' . $tool_name . ' (call_id: ' . $call_id . ')' );
					$vercel_sdk->sendToolCall( $call_id, $tool_name, $arguments );
				} elseif ( $type === 'response_id' ) {
					$module_instance->log( '[vercel_chat] Received response ID: ' . $data );
				}
			},
			$previous_response_id,
			$prompt,
			array(
				'search_args' => array(
					'ID' => $post_id,
				),
				'append'      => true,
			)
		);

		$this->log( '[vercel_chat] complete_responses returned. Is WP_Error: ' . ( is_wp_error( $response ) ? 'yes' : 'no' ) );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_data = $response->get_error_data();
			$this->log( '[vercel_chat] ERROR: ' . $error_message );
			if ( ! empty( $error_data ) ) {
				$this->log( '[vercel_chat] ERROR data: ' . wp_json_encode( $error_data ) );
			}

			// If error is related to missing tool output, clear the response ID to start fresh
			if ( strpos( $error_message, 'No tool output found' ) !== false || strpos( $error_message, 'function call' ) !== false ) {
				$this->log( '[vercel_chat] Clearing response ID due to tool call error' );
				delete_post_meta( $conversation_id, 'pos_last_response_id' );
			}

			$vercel_sdk->finishStep(
				'error',
				array(
					'promptTokens'     => 0,
					'completionTokens' => 0,
				),
				false
			);
			die();
		}

		$this->log( '[vercel_chat] Finishing step successfully' );
		$vercel_sdk->finishStep(
			'stop',
			array(
				'promptTokens'     => 0,
				'completionTokens' => 0,
			),
			false
		);
		die();
	}

	public function chat_completion( $messages = array(), $model = 'gpt-4o' ) {
		// Skip API calls during tests to avoid external dependencies
		if ( defined( 'DOING_TEST' ) ) {
			return new WP_Error( 'test-mode', 'API calls disabled during tests' );
		}

		$data = array(
			'model'    => $model,
			'messages' => $messages,
		);
		$response = $this->api_call( 'https://api.openai.com/v1/chat/completions', $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response->choices[0]->message->content ) ) {
			return new WP_Error( 'no-response', 'No response from OpenAI' );
		}
		return $response->choices[0]->message->content;
	}

	/**
	 * Get the last 50 conversations for the current user
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The conversations list or error.
	 */
	public function tts( $messages, $voice = 'ballad', $data = array() ) {
		$file_name = 'speech-' . uniqid() . '.mp3';

		$openai_payload = array(
			'model'      => 'gpt-4o-audio-preview',
			'modalities' => array( 'text', 'audio' ),
			'audio'      => array(
				'voice'  => $voice,
				'format' => 'mp3',
			),
			'messages'   => $messages,
		);

		$response = $this->api_call( 'https://api.openai.com/v1/chat/completions', $openai_payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response->choices[0]->message->audio->data ) ) {
			return new WP_Error( 'no-audio', 'No audio data in response' );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tempfile = wp_tempnam();
		global $wp_filesystem;
		WP_Filesystem();

		$this->log( 'data from response: ' . print_r( $response->choices[0]->message->content, true ) );
		// Decode base64 audio data and write to temp file
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding audio data from OpenAI API response (benign use).
		$audio_data = base64_decode( $response->choices[0]->message->audio->data );
		$wp_filesystem->put_contents( $tempfile, $audio_data );

		$file = array(
			'name'     => ( $data['post_title'] ? $data['post_title'] . '-' . gmdate( 'Y-m-d' ) : wp_hash( time() ) ) . '-' . $file_name,
			'type'     => 'audio/mpeg',
			'tmp_name' => $tempfile,
			'error'    => 0,
			'size'     => filesize( $tempfile ),
		);

		$data = wp_parse_args(
			array(
				'post_content' => $response->choices[0]->message->content ?? 'test',
				'post_status'  => 'private',
			),
			$data
		);
		$media_id = media_handle_sideload( $file, 0, null, $data );

		return $media_id;
	}
}
