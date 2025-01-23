<?php

require_once __DIR__ . '/class-openai-tool.php';
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

	public function is_configured() {
		return ! empty( $this->settings['api_key'] );
	}

	public function register() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
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
		add_action( 'admin_head', function() {
			if ( get_current_screen()->id !== 'personal-os_page_pos-voice-chat' ) {
				return;
			}
			?>
				<meta name="apple-mobile-web-app-capable" content="yes">
				<meta name="apple-mobile-web-app-status-bar-style" content="default">

				<!-- Set the app title -->
				<meta name="apple-mobile-web-app-title" content="PersonalOS Voice Mode">
			<?php
		} );
	}

	public function voice_chat_page() {
		echo <<<EOF
		<div id="chat-container">
			<div id="messages">
				<!-- Chat messages will appear here -->
				<div class="message bot">
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
			#chat-container.session_active #input-container {
				display: none;
			}
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
			.message.bot {
				align-self: flex-start;
				background-color: #e4e6eb;
				color: black;
				border-bottom-left-radius: 4px;
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
				'args' => array(
					'name' => array(
						'required' => true,
						'type' => 'string',
					),
					'arguments' => array(
						'required' => false,
						'type' => 'string',
					),
				),
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
			'/openai/media/describe/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'media_describe' ),
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
				'model' => 'gpt-4o-realtime-preview-2024-12-17',
				'instructions' => 'You are an assistant with access to my database of notes and todos. You will help me complete tasks and schedule my work. Use very basic markdown.',
				'voice' => 'ballad',
				'input_audio_transcription' => array(
					'model' => 'whisper-1',
				),
				'tools' => array_map( function( $tool ) {
					return $tool->get_function_signature_for_realtime_api();
				}, OpenAI_Tool::get_tools() ),
			)
		);
		return $result;
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

	public function api_call( $url, $data ) {
		$api_key = $this->get_setting( 'api_key' );

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
		$body     = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return json_decode( $body );
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

	public function tts( $text, $voice = 'shimmer', $data = array() ) {
		$api_key = $this->get_setting( 'api_key' );
		$file_name = 'speech-' . uniqid() . '.mp3';

		$response = wp_remote_post(
			'https://api.openai.com/v1/audio/speech',
			array(
				'timeout'  => 360,
				'headers'  => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'     => wp_json_encode(
					array(
						'model' => 'tts-1',
						'input' => $text,
						'voice' => $voice,
					)
				),
				'filename' => $file_name,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tempfile = wp_tempnam();
		global $wp_filesystem;
		WP_Filesystem();
		$wp_filesystem->put_contents( $tempfile, wp_remote_retrieve_body( $response ) );

		$file = array(
			'name'     => wp_hash( time() ) . '-' . $file_name, // This hash is used to obfuscate the file names which should NEVER be exposed.
			'type'     => 'audio/mpeg',
			'tmp_name' => $tempfile,
			'error'    => 0,
			'size'     => filesize( $tempfile ),
		);

		$data['post_content'] = $text;
		$data['post_status'] = 'private';

		$media_id = media_handle_sideload( $file, 0, null, $data );

		return $media_id;
	}
}
