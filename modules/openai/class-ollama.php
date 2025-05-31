<?php
/**
 * Ollama Mock Server Class
 *
 * A WordPress REST API implementation that mimics Ollama server functionality.
 * Implements various Ollama API endpoints for testing and development.
 *
 * Available endpoints under /wp-json/ollama/v1/:
 * - GET  /api/tags     - List available models
 * - GET  /api/version  - Get version info
 * - POST /api/chat     - Chat with model
 * - POST /api/generate - Generate text
 * - POST /api/pull     - Pull model
 * - POST /api/show     - Show model details
 * - POST /api/create   - Create model
 * - DELETE /api/delete - Delete model
 * - POST /api/copy     - Copy model
 * - POST /api/push     - Push model
 * - GET  /api/ps       - List running models
 *
 * All endpoints work with the single model: personalos:gpt5
 *
 * @package PersonalOS
 */

/**
 * Class POS_Ollama_Server
 *
 * Mock Ollama server implementation using WordPress REST API.
 */
class POS_Ollama_Server {

	public $module;
	public $rest_namespace = 'ollama/v1';
	/**
	 * Array of available models in this mock server.
	 *
	 * @var array
	 */
	private $models;

	/**
	 * Constructor.
	 */
	public function __construct( $module ) {
		$this->module = $module;
		$token = $this->module->get_setting( 'ollama_auth_token' );
		$this->module->settings['ollama_auth_token'] = array(
			'type'    => 'text',
			'name'    => 'Token for authorizing OLLAMA mock API.',
			'label'   => strlen( $token ) < 3 ? 'Set a token to enable Ollama-compatible API for external clients' : 'OLLAMA Api accessible at <a href="' . add_query_arg( 'token', $token, get_rest_url( null, $this->rest_namespace ) ) . '" target="_blank">here</a>',
			'default' => '0',
		);
		if ( strlen( $token ) >= 3 ) {
			$this->init_models();
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}
	}

	/**
	 * Initialize the models array.
	 */
	private function init_models(): void {
		$this->models = array(
			'personalos:gpt5' => array(
				'name'        => 'personalos:gpt5',
				'model'       => 'personalos:gpt5',
				'modified_at' => gmdate( 'c' ),
				'size'        => 4299915632,
				'digest'      => 'sha256:a2af6cc3eb7fa8be8504abaf9b04e88f17a119ec3f04a3addf55f92841195f5a',
				'details'     => array(
					'parent_model'       => '',
					'format'             => 'gguf',
					'family'             => 'personalos',
					'families'           => array( 'personalos' ),
					'parameter_size'     => '4.0B',
					'quantization_level' => 'Q4_K_M',
				),
			),
		);
	}

	/**
	 * Get a specific model by name.
	 *
	 * @param string $name Model name.
	 * @return array|null Model data or null if not found.
	 */
	private function get_model( string $name ): ?array {
		return $this->models[ $name ] ?? null;
	}

	/**
	 * Get all models as indexed array.
	 *
	 * @return array Array of models.
	 */
	private function get_all_models(): array {
		return array_values( $this->models );
	}

	/**
	 * Check if model exists.
	 *
	 * @param string $name Model name.
	 * @return bool True if model exists.
	 */
	private function model_exists( string $name ): bool {
		return isset( $this->models[ $name ] );
	}

	public function check_permission( WP_REST_Request $request ) {
		$token = $request->get_param( 'token' );
		if ( $token === $this->module->get_setting( 'ollama_auth_token' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Register all REST API routes.
	 */
	public function register_routes(): void {
		// GET /api/tags - list models
		register_rest_route(
			$this->rest_namespace,
			'/api/tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tags' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// GET /api/version - version info
		register_rest_route(
			$this->rest_namespace,
			'/api/version',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_version' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/chat - chat endpoint
		register_rest_route(
			$this->rest_namespace,
			'/api/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_chat' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/generate - text generation
		register_rest_route(
			$this->rest_namespace,
			'/api/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_generate' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/pull - pull model
		register_rest_route(
			$this->rest_namespace,
			'/api/pull',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_pull' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/show - show model info
		register_rest_route(
			$this->rest_namespace,
			'/api/show',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_show' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/create - create model
		register_rest_route(
			$this->rest_namespace,
			'/api/create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_create' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// DELETE /api/delete - delete model
		register_rest_route(
			$this->rest_namespace,
			'/api/delete',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_model' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/copy - copy model
		register_rest_route(
			$this->rest_namespace,
			'/api/copy',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_copy' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /api/push - push model
		register_rest_route(
			$this->rest_namespace,
			'/api/push',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_push' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// GET /api/ps - list running models
		register_rest_route(
			$this->rest_namespace,
			'/api/ps',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ps' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Get model license text.
	 *
	 * @param string $family Model family.
	 * @return string License text.
	 */
	private function get_model_license( string $family ): string {
		switch ( $family ) {
			case 'personalos':
				return 'PersonalOS Mock License

This is a mock license for the PersonalOS model family.
Used for testing and development purposes only.

[Truncated for brevity - full PersonalOS license text would be here]';

			default:
				return 'Mock license for ' . $family . ' model family.';
		}
	}

	/**
	 * Get model template.
	 *
	 * @param string $family Model family.
	 * @return string Template text.
	 */
	private function get_model_template( string $family ): string {
		switch ( $family ) {
			case 'personalos':
				return '{{ if .System }}<|start_header_id|>system<|end_header_id|>

{{ .System }}<|eot_id|>{{ end }}{{ if .Prompt }}<|start_header_id|>user<|end_header_id|>

{{ .Prompt }}<|eot_id|>{{ end }}<|start_header_id|>assistant<|end_header_id|>

{{ .Response }}<|eot_id|>';

			default:
				return 'Default template for {{ .Prompt }}';
		}
	}

	/**
	 * GET /api/tags - List models.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_tags( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array( 'models' => $this->get_all_models() ),
			200
		);
	}

	/**
	 * GET /api/version - Get version info.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_version( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array( 'version' => '0.7.1' ),
			200
		);
	}

	private function calculate_rolling_hash( $messages ) {
		$hash = '';
		$last_assistant_index = -1;
		foreach ( $messages as $index => $message ) {
			$message = (array) $message;
			if ( in_array( $message['role'], array( 'assistant', 'system' ), true ) ) {
				$last_assistant_index = $index;
			}
		}
		foreach ( $messages as $index => $message ) {
			$message = (array) $message;
			if ( ( $index <= $last_assistant_index || $last_assistant_index === -1 ) && in_array( $message['role'], array( 'user', 'assistant' ), true ) ) {
				$hash .= "\n\n" . trim( $message['content'] );
			}
		}
		return hash( 'sha256', trim( $hash ) );
	}

	/**
	 * POST /api/chat - Chat endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_chat( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid JSON' ),
				400
			);
		}

		$model    = $data['model'] ?? 'personalos:gpt5';
		$messages = $data['messages'] ?? array();
		$stream   = $data['stream'] ?? false;

		// Validate model exists
		if ( ! $this->model_exists( $model ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Model not found' ),
				404
			);
		}

		$non_system_messages = array_filter(
			$messages,
			function( $message ) {
				return $message['role'] !== 'system';
			}
		);

		$hash = $this->calculate_rolling_hash( $messages );

		// Get post with ollama-hash meta equal to $hash, or create one if it doesn't exist
		$existing_posts = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'   => 'ollama-hash',
						'value' => $hash,
					),
				),
			)
		);
		error_log( 'existing_posts: ' . print_r( $existing_posts, true ) . ' hash: [' . $hash . ']' );

		if ( ! empty( $existing_posts ) ) {
			$post_id = $existing_posts[0]->ID;
		} else {
			// Create new post with ollama-hash meta
			$post_id = wp_insert_post(
				array(
					'post_title'   => 'Ollama Chat ' . gmdate( 'Y-m-d H:i:s' ),
					'post_status'  => 'private',
					'post_type'    => 'notes',
				)
			);
		}

		$result = $this->module->complete_backscroll( $non_system_messages );

		$content_blocks = array();
		foreach ( $result as $message ) {
			$message = (array) $message;
			if ( in_array( $message['role'], array( 'user', 'assistant' ), true ) ) {
				// Create message block
				$content_blocks[] = get_comment_delimited_block_content(
					'pos/ai-message',
					array(
						'role' => $message['role'],
						'content' => $message['content'],
						'id' => $message['id'] ?? '',
					),
					''
				);
			}
		}

		wp_update_post(
			array(
				'ID' => $post_id,
				'post_content' => implode( "\n\n", $content_blocks ),
				'meta_input'   => array(
					'ollama-hash' => $this->calculate_rolling_hash( $result ),
				),
			)
		);

		$last_message = (array) end( $result );
		$answer      = $last_message['content'] ?? 'Hello from PersonalOS Mock Ollama!';
		// $answer       = 'Echo: ' . json_encode( $data  ); //$content;

		if ( $stream ) {
			// For streaming, we'll return a simple response since WordPress doesn't handle streaming well
			return new WP_REST_Response(
				array(
					'model'                => $model,
					'created_at'           => gmdate( 'c' ),
					'message'              => array(
						'role'    => 'assistant',
						'content' => $answer,
					),
					'done'                 => true,
					'total_duration'       => 1000000000,
					'load_duration'        => 100000000,
					'prompt_eval_count'    => 10,
					'prompt_eval_duration' => 200000000,
					'eval_count'           => str_word_count( $answer ),
					'eval_duration'        => 700000000,
				),
				200
			);
		} else {
			return new WP_REST_Response(
				array(
					'model'                => $model,
					'created_at'           => gmdate( 'c' ),
					'message'              => array(
						'role'    => 'assistant',
						'content' => $answer,
					),
					'done'                 => true,
					'total_duration'       => 1000000000,
					'load_duration'        => 100000000,
					'prompt_eval_count'    => 10,
					'prompt_eval_duration' => 200000000,
					'eval_count'           => str_word_count( $answer ),
					'eval_duration'        => 700000000,
				),
				200
			);
		}
	}

	/**
	 * POST /api/generate - Text generation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_generate( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid JSON' ),
				400
			);
		}

		$model    = $data['model'] ?? 'personalos:gpt5';
		$prompt   = $data['prompt'] ?? 'Hello!';
		$stream   = $data['stream'] ?? false;
		$response = 'Generated response to: ' . $prompt;

		// Validate model exists
		if ( ! $this->model_exists( $model ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Model not found' ),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'model'                => $model,
				'created_at'           => gmdate( 'c' ),
				'response'             => $response,
				'done'                 => true,
				'total_duration'       => 1000000000,
				'load_duration'        => 100000000,
				'prompt_eval_count'    => str_word_count( $prompt ),
				'prompt_eval_duration' => 200000000,
				'eval_count'           => str_word_count( $response ),
				'eval_duration'        => 700000000,
			),
			200
		);
	}

	/**
	 * POST /api/pull - Pull model.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_pull( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || empty( $data['name'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"name": "model"}' ),
				400
			);
		}

		$name = $data['name'];

		if ( ! $this->model_exists( $name ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Model not available. Only personalos:gpt5 is supported.' ),
				404
			);
		}

		return new WP_REST_Response(
			array( 'status' => 'success' ),
			200
		);
	}

	/**
	 * POST /api/show - Show model info.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_show( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || ( empty( $data['name'] ) && empty( $data['model'] ) ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"name": "model"} or {"model": "model"}' ),
				400
			);
		}

		$name = $data['name'] ?? $data['model'];
		$model_data = $this->get_model( $name );

		if ( ! $model_data ) {
			return new WP_REST_Response(
				array( 'error' => 'Model not found' ),
				404
			);
		}

		$family = $model_data['details']['family'] ?? 'personalos';

		$modelfile  = "# Modelfile generated by \"ollama show\"\n";
		$modelfile .= "# To build a new Modelfile based on this, replace FROM with:\n";
		$modelfile .= '# FROM ' . $model_data['name'] . "\n\n";
		$modelfile .= "FROM /fake/path/to/model/blob\n";
		$modelfile .= 'TEMPLATE """' . $this->get_model_template( $family ) . '"""' . "\n";
		$modelfile .= "PARAMETER num_keep 24\n";
		$modelfile .= 'PARAMETER stop "<|start_header_id|>"' . "\n";
		$modelfile .= 'PARAMETER stop "<|end_header_id|>"' . "\n";
		$modelfile .= 'PARAMETER stop "<|eot_id|>"' . "\n";
		$modelfile .= 'LICENSE """' . $this->get_model_license( $family ) . '"""' . "\n";

		$model_info = array(
			'personalos.attention.head_count'             => 32,
			'personalos.attention.head_count_kv'          => 8,
			'personalos.attention.layer_norm_rms_epsilon' => 0.00001,
			'personalos.block_count'                      => 32,
			'personalos.context_length'                   => 8192,
			'personalos.embedding_length'                 => 4096,
			'personalos.feed_forward_length'              => 14336,
			'general.architecture'                        => 'personalos',
			'general.parameter_count'                     => 4000000000,
			'tokenizer.ggml.model'                        => 'personalos',
		);

		$tensors = array(
			array(
				'name'  => 'token_embd.weight',
				'type'  => 'F32',
				'shape' => array( 2560, 2560 ),
			),
			array(
				'name'  => 'output_norm.weight',
				'type'  => 'Q4_K',
				'shape' => array( 2560, 2560 ),
			),
			array(
				'name'  => 'output.weight',
				'type'  => 'Q6_K',
				'shape' => array( 2560, 2560 ),
			),
		);

		$capabilities = array( 'completion' );

		return new WP_REST_Response(
			array(
				'license'      => $this->get_model_license( $family ),
				'modelfile'    => $modelfile,
				'parameters'   => "num_keep                       24\nstop                           \"<|start_header_id|>\"\nstop                           \"<|end_header_id|>\"\nstop                           \"<|eot_id|>\"",
				'template'     => $this->get_model_template( $family ),
				'details'      => $model_data['details'],
				'model_info'   => $model_info,
				'tensors'      => $tensors,
				'capabilities' => $capabilities,
				'modified_at'  => $model_data['modified_at'],
			),
			200
		);
	}

	/**
	 * POST /api/create - Create model.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_create( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || empty( $data['name'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"name": "model"}' ),
				400
			);
		}

		return new WP_REST_Response(
			array( 'status' => 'success' ),
			200
		);
	}

	/**
	 * DELETE /api/delete - Delete model.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_model( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || empty( $data['name'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"name": "model"}' ),
				400
			);
		}

		$name = $data['name'];
		if ( ! $this->model_exists( $name ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Model not found' ),
				404
			);
		}

		return new WP_REST_Response(
			array( 'error' => 'Cannot delete the only available model' ),
			400
		);
	}

	/**
	 * POST /api/copy - Copy model.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_copy( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || empty( $data['source'] ) || empty( $data['destination'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"source": "model", "destination": "model"}' ),
				400
			);
		}

		$source = $data['source'];
		if ( ! $this->model_exists( $source ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Source model not found' ),
				404
			);
		}

		return new WP_REST_Response(
			array( 'status' => 'success' ),
			200
		);
	}

	/**
	 * POST /api/push - Push model.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function post_push( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! $data || empty( $data['name'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Body must contain {"name": "model"}' ),
				400
			);
		}

		return new WP_REST_Response(
			array( 'status' => 'success' ),
			200
		);
	}

	/**
	 * GET /api/ps - List running models.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_ps( WP_REST_Request $request ): WP_REST_Response {
		$running_models = array();

		foreach ( $this->models as $model_data ) {
			$running_models[] = array(
				'name'       => $model_data['name'],
				'model'      => $model_data['model'],
				'size'       => $model_data['size'],
				'digest'     => $model_data['digest'],
				'details'    => $model_data['details'],
				'expires_at' => gmdate( 'c', time() + 300 ),
				'size_vram'  => $model_data['size'],
			);
		}

		return new WP_REST_Response(
			array( 'models' => $running_models ),
			200
		);
	}
}
