<?php

require_once __DIR__ . '/class-openai-tool.php';
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
		add_filter( 'pos_openai_tools', array( $this, 'register_openai_tools' ) );
		$this->register_cli_command( 'tool', 'cli_openai_tool' );

		$this->register_cli_command( 'responses', 'cli_openai_responses' );
		$this->register_block( 'tool', array( 'render_callback' => array( $this, 'render_tool_block' ) ) );
		$this->register_block( 'message', array() );

		require_once __DIR__ . '/chat-page.php';

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
	}

	public function render_tool_block( $attributes ) {
		$tool = OpenAI_Tool::get_tool( $attributes['tool'] );
		if ( ! $tool ) {
			return '';
		}
		$result = $tool->invoke( (array) $attributes['parameters'] ?? array() );
		return '<pre>' . wp_json_encode( $result, JSON_PRETTY_PRINT ) . '</pre>';
	}

	/**
	 * Test OpenAI tools
	 *
	 * Lists available OpenAI tools and allows testing individual tools with arguments.
	 * If no tool is specified, displays all available tools. If a tool name is provided,
	 * executes that specific tool with the optional arguments.
	 *
	 * ## OPTIONS
	 *
	 * [<tool>]
	 * : Name of the tool to test
	 *
	 * [<args>]
	 * : JSON string of arguments to pass to the tool
	 */
	public function cli_openai_tool( $args ) {
		$tools = apply_filters( 'pos_openai_tools', array() );
		if ( empty( $args[0] ) ) {
			$items = array_map(
				function( $tool ) {
					return array(
						'name'        => $tool->name,
						'description' => $tool->description,
						'parameters'  => json_encode( $tool->parameters ),
					);
				},
				$tools
			);

			WP_CLI\Utils\format_items( 'table', $items, array( 'name', 'description', 'parameters' ) );
			return;
		}
		$tool = OpenAI_Tool::get_tool( $args[0] );
		if ( ! $tool ) {
			WP_CLI::error( 'Tool not found' );
		}
		WP_CLI::log( print_r( $tool->invoke( ! empty( $args[1] ) ? json_decode( $args[1], true ) : array() ), true ) );
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
			$messages_payload = file_get_contents( $file_path );
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

	public function admin_menu() {
		add_submenu_page(
			'tools.php',
			'Custom GPT',
			'Custom GPT',
			'manage_options',
			'pos-custom-gpt',
			array( $this, 'custom_gpt_page' )
		);
		add_submenu_page(
			'personalos',
			'Voice Chat',
			'Voice Chat',
			'manage_options',
			'pos-voice-chat',
			array( $this, 'voice_chat_page' )
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

	public function register_openai_tools( $tools ) {
		$notes_module = POS::get_module_by_id( 'notes' );
		$tools[] = new OpenAI_Tool(
			'list_posts',
			'List publicly accessible posts on this blog.',
			array(
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
			function( $args ) {
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
		);
		$tools[] = new OpenAI_Tool_Writeable(
			'ai_memory',
			'Store information in the memory. Use this tool when you need to store additional information relevant for future conversations. For example, "Remembe to always talk like a pirate", or "I Just got a puppy", or "I am building a house" should trigger this tool. Very time-specific, ephemeral data should not.',
			array(
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
			function( $args ) {
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
		);
		return $tools;
	}

	public function voice_chat_page() {
		echo <<<EOF
		<div id="chat-container">
			<div id="messages">
				<!-- Chat messages will appear here -->
				<div class="message assistant">
					<b>OpenAI advanced voice mode</b>
				</div>
			</div>
			<div class="audio-container">
				<button id="start-session">Start Session</button>
			</div>
			<div id="input-container">
				<input type="text" id="message-input" placeholder="Type your message...">
				<button id="send-button">Send</button>
			</div>
			<div class="audio-controls">
				<span class="icon">🎤</span>
				<select id="audio-input"></select>
				<span class="icon">🎧</span>
				<select id="audio-output"></select>
			</div>
		</div>
		<style>
			#wpbody-content {
				margin-bottom:0;
				padding-bottom:0;
			}
			.audio-controls {
				display: flex;
				align-items: center;
				gap: 10px;
				display:flex;
				justify-content: space-around;
				padding: 10px;
			}
			.audio-controls .icon {
				font-size: 24px;
			}
			.audio-controls select {
				font-size: 11px;
			}
			#chat-container {
				width: 100%;
				max-width: 800px;
				margin: 0 auto;
				height: calc(100vh - 50px); /* Adjust for WP admin bar and padding */
				background-color: #ffffff;
				//box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
				border-radius: 8px;
				display: flex;
				flex-direction: column;
				overflow: hidden;
			}
			// #chat-container.session_active #input-container {
			// 	display: none;
			// }
			#messages {
				flex: 1;
				min-height: 0; /* Important for flex child scrolling */
				padding: 16px;
				overflow-y: auto;
				display: flex;
				flex-direction: column;
				gap: 12px;
			}
			.message {
				max-width: 70%;
				padding: 10px 14px;
				border-radius: 18px;
				font-size: 14px;
				line-height: 1.5;
			}
			.message.user {
				align-self: flex-end;
				background-color: #007bff;
				color: white;
				border-bottom-right-radius: 4px;
			}
			.message.assistant {
				align-self: flex-start;
				background-color: #e4e6eb;
				color: black;
				border-bottom-left-radius: 4px;
			}
			.message.tool {
				align-self: flex-start;
				background-color: #edf2f7;
				color: black;
				border-bottom-left-radius: 4px;
				cursor: pointer;
			}
			.message.tool pre {
				display: none;
				font-size: 0.75em;
				overflow-x: auto;
			}
			.message.tool:hover pre {
				display: block;
				border-bottom: 1px dashed #ccc;
			}

			#input-container {
				display: flex;
				padding: 12px;
				background-color: #f9f9f9;
				border-top: 1px solid #ddd;
			}
			#message-input {
				flex: 1;
				padding: 10px;
				border: 1px solid #ddd;
				border-radius: 20px;
				font-size: 14px;
			}
			#send-button {
				margin-left: 10px;
				padding: 10px 16px;
				border: none;
				border-radius: 20px;
				background-color: #007bff;
				color: white;
				font-size: 14px;
				cursor: pointer;
			}
			#send-button:hover {
				background-color: #0056b3;
			}

			/* Update pulsating orb button styles */
			#start-session {
				width: 80px;
				height: 80px;
				border-radius: 50%;
				background: radial-gradient(circle at 30% 30%, #5a9bff, #007bff);
				border: none;
				color: white;
				cursor: pointer;
				position: relative;
				box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Added default shadow */
				transition: transform 0.2s;
			}

			#chat-container.session_active #start-session {
				animation: pulse 2s infinite;
				background: radial-gradient(circle at 30% 30%, #ff5a5a, #ff0000);
			}

			#chat-container.speaking #start-session {
				animation: pulse 2s infinite, scale 0.3s infinite;
			}

			#start-session:hover {
				transform: scale(1.05);
			}

			@keyframes pulse {
				0% {
					box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
				}
				70% {
					box-shadow: 0 0 0 15px rgba(0, 123, 255, 0);
				}
				100% {
					box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
				}
			}

			@keyframes scale {
				0%, 100% {
					transform: scale(1);
				}
				50% {
					transform: scale(1.2);
				}
			}

			.audio-container {
				display: flex;
				justify-content: center;
				padding: 20px 0;
			}
		</style>
		EOF;

		wp_enqueue_script( 'voice-chat', plugins_url( 'assets/voice-chat.js', __FILE__ ), array( 'wp-api-fetch' ), time(), true );
		//wp_enqueue_style( 'voice-chat', plugins_url( 'assets/voice-chat.css', __FILE__ ) );

	}

	public function custom_gpt_page() {
		echo <<<EOF
		<h1>Configure your custom GPT</h1>
		<p><a href='https://chatgpt.com/gpts/mine' target='_blank'>First create a new custom GPT</a></p>
		<h2>System prompt</h2>
		<p>This is the system prompt for your custom GPT. Modify it to fit your needs.</p>
		<textarea style="width: 100%; height: 500px;">
		You are an assistant with access to my database of notes and todos.
		You will help me complete tasks and schedule my work.

		My work is organized in "notebooks"
		- Stuff to do right now is in notebook with the slug "now"
		- Stuff to do later is in notebook with the slug "later"
		- Default notebook has slug inbox

		You probably should download the list of notebooks to reference them while I am talking to you.
		When listing notes in particular notebook, use the notebook id and the notebook field of todo_get_items

		- NEVER say you created a todo without calling the appropriate action.
		- when I ask you to create a TODO, always return a URL
		- Alwas create new todos with 'private' status
		</textarea>
		EOF;
		$schema = file_get_contents( plugin_dir_path( __FILE__ ) . 'chatgpt_routes.json' );
		$schema = json_decode( $schema, true );
		$schema['servers'][0]['url'] = get_rest_url( null, '' );
		$schema = wp_json_encode( $schema, JSON_PRETTY_PRINT );
		$schema = wp_unslash( $schema );
		$login = esc_attr( wp_get_current_user()->user_login );
		$schema = esc_textarea( $schema );
		echo <<<EOF
		<h2>Schema</h2>
		<p>This is the schema for your custom GPT. It describes the API endpoints that your GPT can use. Copy it into your ChatGPT configuration.</p>
		<textarea style="width: 100%; height: 500px;">
		{$schema}
		</textarea>
		EOF;

		echo <<<EOF
		<h2>Auth</h2>
		<p>You can use basic request and application passwords to authenticate your requests:</p>
		<ol>
			<li><a href='authorize-application.php' target='_blank'>Create an Application Password for your user</a></li>
			<li>Paste the password and encode below using base64</li>
			<li>Use the encoded password as the token for basic auth in your ChatGPT configuration</li>
		</ol>
		<h3>Encode password</h3>
		<input type="hidden" id="app_username" value="{$login}">
		<input type="text" id="app_password" placeholder="Password">
		<button id="encode" onclick="encode()">Encode</button>
		<pre id="encoded"></pre>
		<script>
			function encode() {
				const username = document.getElementById('app_username').value;
				const password = document.getElementById('app_password').value;
				const encoded = btoa(username + ':' + password);
				document.getElementById('encoded').textContent = encoded;
			}
		</script>
		EOF;
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
			'/openai/realtime/function_call',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'function_call' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'name'      => array(
						'required' => true,
						'type'     => 'string',
					),
					'arguments' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/chat/tools',
			array(
				'methods'             => 'GET',
				'callback'            => function() {
					return array_map(
						function( $tool ) {
							return $tool->get_function_signature();
						},
						OpenAI_Tool::get_tools( false )
					);
				},
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
			'/openai/chat/system_prompt',
			array(
				'methods'             => 'GET',
				'callback'            => function( WP_REST_Request $request ) {
					$params = $request->get_query_params();
					if ( ! empty( $params['id'] ) ) {
						$params = get_post( $params['id'] );
					}
					return $this->create_system_prompt( $params );
				},
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

		$result = $this->api_call(
			'https://api.openai.com/v1/realtime/sessions',
			array(
				'model'                     => 'gpt-4o-realtime-preview-2024-12-17',
				'instructions'              => $this->create_system_prompt(),
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
				'tools'                     => array_map(
					function( $tool ) {
						return $tool->get_function_signature_for_realtime_api();
					},
					OpenAI_Tool::get_tools()
				),
			)
		);
		return $result;
	}

	public function system_prompt_defaults() {
		$note_module = POS::get_module_by_id( 'notes' );
		return array(
			'me'        => array(
				'name'        => wp_get_current_user()->display_name,
				'description' => wp_get_current_user()->description,
			),
			'system'    => array(
				'current_time' => gmdate( 'Y-m-d H:i:s' ),
			),
			'you'       => <<<EOF
				Your name is PersonalOS. You are a plugin installed on my WordPress site.
				Apart from WordPress functionality, you have certain modules enabled, and functionality exposed as tools.
				You can use these tools to perform actions on my behalf.
				Use simple markdown to format your responses.
				NEVER read the URLs (http://, https://, evernote://, etc) out loud in voice mode.
				When answering a question about my todos or notes, stick only to the information from the tools. DO NOT make up information.
			EOF,
			'notebooks' => array(
				'description' => 'My work is organized in "notebooks". They represent areas of my life, active projects and statuses of tasks.',
				'notebooks'   => array_map(
					function( $flag ) use ( $note_module ) {
						$notebooks = array_map(
							function( $notebook ) {
								return <<<EOF
									<notebook
										name="{$notebook->name}"
										id="{$notebook->term_id}"
										slug="{$notebook->slug}"
									>
										{$notebook->description}
									</notebook>
								EOF;
							},
							$note_module->get_notebooks_by_flag( $flag['id'] )
						);
						$notebooks = implode( "\n", $notebooks );
						return <<<EOF
						<notebook_type
							id="{$flag['id']}"
							name="{$flag['name']}"
							label="{$flag['label']}"
						>
							{$notebooks}
						</notebook_type>
						EOF;
					},
					apply_filters(
						'pos_notebook_flags',
						array(
						// array(
						// 	'id' => null,
						// 	'name' => 'Rest of the notebooks',
						// 	'label' => 'Notebooks without any special flag.',
						// ),
						)
					)
				),
			),
			'memories'  => array(
				'description' => 'You have previously stored some information in the AI Memory using the "ai_memory" tool.',
				'memories'    => array_map(
					function( $memory ) {
						return "<memory id='{$memory->ID}'>
							<title>{$memory->post_title}</title>
							<content>{$memory->post_content}</content>
						</memory>";
					},
					get_posts(
						array(
							'post_type'   => 'notes',
							'taxonomy'    => 'notebook',
							'term'        => 'ai-memory',
							'numberposts' => -1,
						)
					)
				),
			),
		);
	}

	/**
	 * Create a system prompt for the OpenAI API.
	 * @TODO: This could be achieved by using Gutenberg and notes to put this together.
	 *
	 * @return string The system prompt.
	 */
	public function create_system_prompt( $params = array() ) {
		if ( $params instanceof \WP_Post ) {
			$content = apply_filters( 'the_content', $params->post_content );
			$content = preg_replace_callback(
				'/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i',
				function( $matches ) {
					$level = $matches[1];
					$text = $matches[2];
					return str_repeat( '#', intval( $level ) ) . ' ' . $text;
				},
				$content
			);
			$content = preg_replace_callback(
				'/<li[^>]*>(.*?)<\/li>/i',
				function( $matches ) {
					return '- ' . $matches[1];
				},
				$content
			);
			$content = wp_strip_all_tags( $content );
			return $content;
		}
		$prompt = wp_parse_args( $params, $this->system_prompt_defaults() );
		return $this->array_to_xml( $prompt );
	}

	/**
	 * Recursively converts an array to XML string
	 *
	 * @param mixed  $data    The data to convert
	 * @param string $indent  Current indentation level
	 * @return string The XML string
	 */
	private function array_to_xml( $data, string $indent = '' ): string {
		if ( is_string( $data ) || is_numeric( $data ) ) {
			return $data;
		}

		$xml = array();

		foreach ( $data as $key => $value ) {
			// Skip numeric keys for indexed arrays
			if ( is_int( $key ) ) {
				$xml[] = $this->array_to_xml( $value, $indent );
				continue;
			}

			// Start element
			$xml[] = "{$indent}<{$key}>";

			// Handle value based on type
			if ( is_array( $value ) ) {
				$xml[] = $this->array_to_xml( $value, $indent . "\t" );
			} else {
				$xml[] = $indent . "\t" . $value;
			}

			// Close element
			$xml[] = "{$indent}</{$key}>";
		}

		return implode( "\n", $xml );
	}

	public function function_call( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$tool = OpenAI_Tool::get_tool( $params['name'] );
		if ( ! $tool ) {
			return new WP_Error( 'tool-not-found', 'Tool not found: ' . $params['name'] );
		}
		return array( 'result' => $tool->invoke_for_function_call( ! empty( $params['arguments'] ) ? json_decode( $params['arguments'], true ) : array() ) );
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

	public function complete_responses( array $messages, callable $callback = null, ?string $previous_response_id = null, ?\WP_Post $prompt = null ) {
		// Filter out perplexity_search when using Responses API since we have built-in web_search
		$tools = array_filter(
			OpenAI_Tool::get_tools(),
			function ( $tool ) {
				return $tool->name !== 'perplexity_search';
			}
		);
		$tool_definitions = array_map(
			function ( $tool ) {
				return $tool->get_function_signature_for_realtime_api();
			},
			array_values( $tools ),
		);
		// Add built-in web search tool
		$tool_definitions[] = array(
			'type' => 'web_search',
		);

		// Get model from prompt meta if available, otherwise use default
		$model = 'gpt-4o';
		if ( $prompt ) {
			$pos_model = get_post_meta( $prompt->ID, 'pos_model', true );
			if ( $pos_model ) {
				$model = $pos_model;
			}
			$this->log( '[complete_responses] Using prompt: ' . $prompt->post_title . ' (ID: ' . $prompt->ID . ') with model: ' . $model );
		} else {
			$this->log( '[complete_responses] No prompt provided, using default model: ' . $model );
		}

		$max_loops = 10;
		$full_messages = $messages; // Keep full history for return value
		do {
			--$max_loops;
			$has_function_calls = false;

			// Build the API request payload
			$request_data = array(
				'model'        => $model,
				'instructions' => $this->create_system_prompt( $prompt ),
				'tools'        => $tool_definitions,
				'store'        => true, // Store responses for previous_response_id to work
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
						$tool      = array_filter(
							$tools,
							function ( $tool ) use ( $output_item ) {
								return $tool->name === $output_item->name;
							}
						);
						$tool      = reset( $tool );
						if ( ! $tool ) {
							return new WP_Error( 'tool-not-found', 'Tool not found ' . $output_item->name );
						}

						try {
							$result = $tool->invoke( $arguments );
						} catch ( \Exception $e ) {
							$this->log( 'Tool invocation error for ' . $output_item->name . ': ' . $e->getMessage() );
							$result = new WP_Error(
								'tool-invocation-error',
								sprintf(
									'Error invoking tool %s: %s',
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

	public function complete_backscroll( array $backscroll, callable $callback = null ) {
		$tool_definitions = array_map(
			function ( $tool ) {
				return $tool->get_function_signature();
			},
			array_values( OpenAI_Tool::get_tools() ),
		);
		$max_loops = 10;
		do {
			--$max_loops;
			$completion = $this->api_call(
				'https://api.openai.com/v1/chat/completions',
				array(
					'model'    => 'gpt-5',
					'messages' => array_merge(
						array(
							array(
								'role'    => 'assistant',
								'content' => $this->create_system_prompt(),
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
					$tool      = array_filter(
						OpenAI_Tool::get_tools(),
						function ( $tool ) use ( $tool_call ) {
							return $tool->name === $tool_call->function->name;
						}
					);
					$tool      = reset( $tool );
					if ( ! $tool ) {
						return new WP_Error( 'tool-not-found', 'Tool not found ' . $tool_call->function->name );
					}

					$result = $tool->invoke( $arguments );

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
		} else {
			// No ID and no name provided - always create a new post
			// This is the case when bootstrapping a new conversation
			$existing_posts = array();
		}

		// Generate title if not provided and OpenAI is configured
		$post_title = $search_args['post_title'] ?? null;
		if ( ! $post_title && empty( $existing_posts ) && ! empty( $backscroll ) && $this->is_configured() ) {
			$post_title = $this->generate_conversation_title( $backscroll );
		}

		// Fall back to default title if generation failed or not configured
		if ( ! $post_title && empty( $existing_posts ) ) {
			$post_title = 'Chat ' . gmdate( 'Y-m-d H:i:s' );
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
				$role = 'tool'; // Or 'function'
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

				// Create message block
				$content_blocks[] = get_comment_delimited_block_content(
					'pos/ai-message',
					array(
						'role'    => $role,
						'content' => $content,
						'id'      => $message_id,
					),
					''
				);
			}
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => $notes_module->id,
			'post_status'  => 'private',
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
			
			// Only update if there is content to update or we are not just appending empty content
			// But here we might want to update just to ensure touch?
			if ( isset( $post_data['post_content'] ) || ! $append ) {
				$post_id = wp_update_post( $post_data );
			} else {
				$post_id = $post_data['ID'];
			}
		} else {
			// Create new
			$post_data['post_content'] = implode( "\n\n", $content_blocks );
			// Ensure defaults for new post
			if ( empty( $post_data['post_title'] ) ) {
				$post_data['post_title'] = 'Chat ' . gmdate( 'Y-m-d H:i:s' );
			}
			if ( empty( $post_data['post_name'] ) ) {
				$post_data['post_name'] = 'chat-' . gmdate( 'Y-m-d-H-i-s' );
			}
			
			$post_id = wp_insert_post( $post_data );

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
				$conversation_content .= $message['role'] . ': ' . $message['content'] . "\n";
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
		$prompt = null;
		if ( ! empty( $params['selectedChatModel'] ) ) {
			$this->log( '[vercel_chat] Looking for prompt with slug: ' . $params['selectedChatModel'] );
			$notes_module = POS::get_module_by_id( 'notes' );
			$prompts = $notes_module->list( array(), 'prompts-chat' );
			foreach ( $prompts as $prompt_post ) {
				if ( $prompt_post->post_name === $params['selectedChatModel'] ) {
					$prompt = $prompt_post;
					$pos_model = get_post_meta( $prompt->ID, 'pos_model', true );
					$this->log( '[vercel_chat] Found prompt: ' . $prompt->post_title . ' (ID: ' . $prompt->ID . ', slug: ' . $prompt->post_name . ', pos_model: ' . ( $pos_model ? $pos_model : 'none' ) . ')' );
					break;
				}
			}
			if ( ! $prompt ) {
				$this->log( '[vercel_chat] WARNING: Prompt not found for slug: ' . $params['selectedChatModel'] );
			} else {
				// Save prompt ID to meta if not already set or changed?
				// Maybe we just use the one from params for this turn.
				// But plan says "Read pos_chat_prompt_id meta".
				// Let's check if one is stored, if not store it.
				$stored_prompt_id = get_post_meta( $post_id, 'pos_chat_prompt_id', true );
				if ( ! $stored_prompt_id ) {
					update_post_meta( $post_id, 'pos_chat_prompt_id', $prompt->ID );
				}
			}
		} else {
			$this->log( '[vercel_chat] No selectedChatModel provided in params' );
			// Try to load from meta
			$stored_prompt_id = get_post_meta( $post_id, 'pos_chat_prompt_id', true );
			if ( $stored_prompt_id ) {
				$prompt = get_post( $stored_prompt_id );
			}
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

		// Persist User Message Immediately
		$this->save_backscroll( array( $user_message ), array( 'ID' => $post_id ), true );

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
						// Save Assistant Message
						$assistant_msg = array(
							'role'    => 'assistant',
							'content' => $full_text,
						);
						$module_instance->save_backscroll( array( $assistant_msg ), array( 'ID' => $conversation_id ), true );
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
					// Store response ID for next request
					$module_instance->log( '[vercel_chat] Storing response ID: ' . $data );
					update_post_meta( $conversation_id, 'pos_last_response_id', $data );
				}
			},
			$previous_response_id,
			$prompt
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
