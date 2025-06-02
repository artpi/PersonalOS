<?php

require_once __DIR__ . '/../../modules/openai/class-pos-ollama-server.php';

/**
 * Unit tests for POS_Ollama_Server class.
 *
 * Tests the Ollama-compatible API endpoints including chat, show, and tags routes.
 * Also tests the backscroll functionality where the API uses previous conversation
 * history to determine the correct post to backscroll.
 */
class OllamaServerTest extends WP_UnitTestCase {

	private $ollama_server;
	private $mock_openai_module;

	public function set_up(): void {
		parent::set_up();

		// Create mock OpenAI module
		$this->mock_openai_module = $this->createMock( OpenAI_Module::class );
		$this->mock_openai_module->settings = array();

		// Mock get_setting method to return test token
		$this->mock_openai_module->method( 'get_setting' )
			->with( 'ollama_auth_token' )
			->willReturn( 'test-token-123' );

		// Create Ollama server instance
		$this->ollama_server = new POS_Ollama_Server( $this->mock_openai_module );
	}

	/**
	 * Test GET /api/tags endpoint.
	 *
	 * @covers POS_Ollama_Server::get_tags
	 */
	public function test_get_tags() {
		// Create request with valid token
		$request = new WP_REST_Request( 'GET', '/ollama/v1/api/tags' );
		$request->set_param( 'token', 'test-token-123' );

		$response = $this->ollama_server->get_tags( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'models', $data );
		$this->assertIsArray( $data['models'] );
		$this->assertCount( 1, $data['models'] );

		$model = $data['models'][0];
		$this->assertEquals( 'personalos:4o', $model['name'] );
		$this->assertEquals( 'personalos:4o', $model['model'] );
		$this->assertArrayHasKey( 'modified_at', $model );
		$this->assertArrayHasKey( 'size', $model );
		$this->assertArrayHasKey( 'digest', $model );
		$this->assertArrayHasKey( 'details', $model );

		// Test model details
		$details = $model['details'];
		$this->assertEquals( 'personalos', $details['family'] );
		$this->assertEquals( '4.0B', $details['parameter_size'] );
		$this->assertEquals( 'Q4_K_M', $details['quantization_level'] );
	}

	/**
	 * Test GET /api/tags endpoint with invalid token.
	 *
	 * @covers POS_Ollama_Server::check_permission
	 */
	public function test_get_tags_invalid_token() {
		$request = new WP_REST_Request( 'GET', '/ollama/v1/api/tags' );
		$request->set_param( 'token', 'invalid-token' );

		$result = $this->ollama_server->check_permission( $request );

		$this->assertFalse( $result );
	}

	/**
	 * Test POST /api/show endpoint with valid model.
	 *
	 * @covers POS_Ollama_Server::post_show
	 */
	public function test_post_show_valid_model() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/show' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'name' => 'personalos:4o' ) ) );

		$response = $this->ollama_server->post_show( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'license', $data );
		$this->assertArrayHasKey( 'modelfile', $data );
		$this->assertArrayHasKey( 'parameters', $data );
		$this->assertArrayHasKey( 'template', $data );
		$this->assertArrayHasKey( 'details', $data );
		$this->assertArrayHasKey( 'model_info', $data );
		$this->assertArrayHasKey( 'tensors', $data );
		$this->assertArrayHasKey( 'capabilities', $data );
		$this->assertArrayHasKey( 'modified_at', $data );

		// Test license contains PersonalOS text
		$this->assertStringContainsString( 'PersonalOS Mock License', $data['license'] );

		// Test template contains PersonalOS format
		$this->assertStringContainsString( '|start_header_id|', $data['template'] );

		// Test capabilities
		$this->assertContains( 'completion', $data['capabilities'] );
	}

	/**
	 * Test POST /api/show endpoint with model parameter instead of name.
	 *
	 * @covers POS_Ollama_Server::post_show
	 */
	public function test_post_show_with_model_param() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/show' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'model' => 'personalos:4o' ) ) );

		$response = $this->ollama_server->post_show( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test POST /api/show endpoint with invalid model.
	 *
	 * @covers POS_Ollama_Server::post_show
	 */
	public function test_post_show_invalid_model() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/show' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'name' => 'nonexistent:model' ) ) );

		$response = $this->ollama_server->post_show( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Model not found', $data['error'] );
	}

	/**
	 * Test POST /api/show endpoint with missing model name.
	 *
	 * @covers POS_Ollama_Server::post_show
	 */
	public function test_post_show_missing_model() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/show' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array() ) );

		$response = $this->ollama_server->post_show( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'Body must contain', $data['error'] );
	}

	/**
	 * Test POST /api/chat endpoint with simple message.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_simple() {
		// Mock the complete_backscroll method
		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! How can I help you today?',
			),
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		// Mock save_backscroll to return a post ID
		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 123 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => 'Hello',
						),
					),
					'stream'   => false,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'model', $data );
		$this->assertArrayHasKey( 'created_at', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'done', $data );
		$this->assertTrue( $data['done'] );

		$message = $data['message'];
		$this->assertEquals( 'assistant', $message['role'] );
		$this->assertEquals( 'Hello! How can I help you today?', $message['content'] );
	}

	/**
	 * Test POST /api/chat endpoint with backscroll functionality.
	 *
	 * This test simulates the scenario where a client sends only the current
	 * backscroll and the server needs to use the hash to find the correct
	 * post to continue the conversation.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_post_chat_backscroll_functionality() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'What is the weather like?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'I don\'t have access to current weather data.',
			),
			array(
				'role'    => 'user',
				'content' => 'Can you help me with something else?',
			),
		);

		// Mock the complete_backscroll method to return the messages plus a new response
		$mock_response = array_merge(
			$messages,
			array(
				array(
					'role'    => 'assistant',
					'content' => 'Of course! I\'d be happy to help you with something else.',
				),
			)
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		// Mock save_backscroll to return a post ID and verify the hash is passed
		$this->mock_openai_module->expects( $this->once() )
			->method( 'save_backscroll' )
			->with(
				$this->equalTo( $mock_response ),
				$this->callback(
					function( $args ) {
						// Verify that meta_input contains ollama-hash
						return isset( $args['meta_input']['ollama-hash'] ) &&
							is_string( $args['meta_input']['ollama-hash'] ) &&
							strlen( $args['meta_input']['ollama-hash'] ) === 64; // SHA256 hash
					}
				)
			)
			->willReturn( 456 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $messages,
					'stream'   => false,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'personalos:4o', $data['model'] );
		$this->assertEquals( 'assistant', $data['message']['role'] );
		$this->assertEquals( 'Of course! I\'d be happy to help you with something else.', $data['message']['content'] );
	}

	/**
	 * Test POST /api/chat endpoint with system messages.
	 *
	 * System messages should be filtered out when calculating the hash and
	 * passed to complete_backscroll, but only non-system messages should be
	 * used for hash calculation.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_post_chat_with_system_messages() {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful assistant.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
		);

		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! How can I help you?',
			),
		);

		// complete_backscroll should receive only non-system messages
		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 789 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $messages,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertEquals( 200, $response->get_status() );
		
		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertEquals( 'assistant', $data['message']['role'] );
		$this->assertEquals( 'Hello! How can I help you?', $data['message']['content'] );
	}

	/**
	 * Test backscroll hash calculation with system messages excluded.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_calculate_rolling_hash_excludes_system_messages() {
		// Messages with system messages
		$messages_with_system = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful assistant.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hi there!',
			),
		);

		// Same messages without system message
		$messages_without_system = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hi there!',
			),
		);

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->ollama_server );
		$method = $reflection->getMethod( 'calculate_rolling_hash' );
		$method->setAccessible( true );

		$hash_with_system = $method->invoke( $this->ollama_server, $messages_with_system );
		$hash_without_system = $method->invoke( $this->ollama_server, $messages_without_system );

		// Hashes should be the same since system messages are excluded
		$this->assertEquals( $hash_with_system, $hash_without_system );
	}

	/**
	 * Test backscroll hash calculation with messages after last assistant message.
	 * 
	 * The hash should only include messages up to and including the last assistant message.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_calculate_rolling_hash_last_assistant_cutoff() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'First user message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'First assistant response',
			),
			array(
				'role'    => 'user',
				'content' => 'Second user message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Second assistant response',
			),
			array(
				'role'    => 'user',
				'content' => 'Third user message that should be excluded from hash',
			),
		);

		// Messages that should be included in hash (up to last assistant message)
		$expected_messages = array(
			array(
				'role'    => 'user',
				'content' => 'First user message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'First assistant response',
			),
			array(
				'role'    => 'user',
				'content' => 'Second user message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Second assistant response',
			),
		);

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->ollama_server );
		$method = $reflection->getMethod( 'calculate_rolling_hash' );
		$method->setAccessible( true );

		$hash_full = $method->invoke( $this->ollama_server, $messages );
		$hash_expected = $method->invoke( $this->ollama_server, $expected_messages );

		// Hashes should be the same since messages after last assistant are excluded
		$this->assertEquals( $hash_full, $hash_expected );
	}

	/**
	 * Test backscroll functionality with conversation continuation.
	 * 
	 * This test simulates a realistic scenario where a client sends conversation
	 * history and the server needs to continue the conversation and update the hash.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_post_chat_conversation_continuation() {
		$conversation_history = array(
			array(
				'role'    => 'user',
				'content' => 'Tell me about cats',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Cats are wonderful pets. They are independent and affectionate.',
			),
			array(
				'role'    => 'user',
				'content' => 'What about their behavior?',
			),
		);

		$expected_new_conversation = array_merge(
			$conversation_history,
			array(
				array(
					'role'    => 'assistant',
					'content' => 'Cats exhibit many interesting behaviors like hunting, grooming, and purring.',
				),
			)
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $expected_new_conversation );

		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 555 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $conversation_history,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'personalos:4o', $data['model'] );
		$this->assertEquals( 'assistant', $data['message']['role'] );
		$this->assertEquals( 'Cats exhibit many interesting behaviors like hunting, grooming, and purring.', $data['message']['content'] );
		$this->assertTrue( $data['done'] );
		$this->assertArrayHasKey( 'total_duration', $data );
		$this->assertArrayHasKey( 'eval_count', $data );
	}

	/**
	 * Test error handling when save_backscroll fails.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_save_backscroll_error() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Test message',
			),
		);

		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Test response',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Test response',
			),
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		// Mock save_backscroll to return a WP_Error
		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( new WP_Error( 'save_error', 'Failed to save conversation' ) );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $messages,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'Failed to save conversation', $data['error'] );
	}

	/**
	 * Test rolling hash calculation with only user messages.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_calculate_rolling_hash_user_only() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'First message',
			),
			array(
				'role'    => 'user',
				'content' => 'Second message',
			),
		);

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->ollama_server );
		$method = $reflection->getMethod( 'calculate_rolling_hash' );
		$method->setAccessible( true );

		$hash = $method->invoke( $this->ollama_server, $messages );

		// Should produce a valid hash even with only user messages
		$this->assertIsString( $hash );
		$this->assertEquals( 64, strlen( $hash ) );
	}

	/**
	 * Test rolling hash calculation with empty messages array.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_calculate_rolling_hash_empty_messages() {
		$messages = array();

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->ollama_server );
		$method = $reflection->getMethod( 'calculate_rolling_hash' );
		$method->setAccessible( true );

		$hash = $method->invoke( $this->ollama_server, $messages );

		// Should handle empty array gracefully
		$this->assertIsString( $hash );
		$this->assertEquals( 64, strlen( $hash ) );
	}

	/**
	 * Test POST /api/chat endpoint with invalid model.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_invalid_model() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'invalid:model',
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => 'Hello',
						),
					),
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Model not found', $data['error'] );
	}

	/**
	 * Test POST /api/chat endpoint with invalid JSON.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_invalid_json() {
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( 'invalid json' );

		$response = $this->ollama_server->post_chat( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Invalid JSON', $data['error'] );
	}

	/**
	 * Test POST /api/chat endpoint with streaming enabled.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_streaming() {
		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Hello',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! Streaming response.',
			),
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 321 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => 'Hello',
						),
					),
					'stream'   => true,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['done'] );
		$this->assertEquals( 'Hello! Streaming response.', $data['message']['content'] );
	}

	/**
	 * Test rolling hash calculation.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_calculate_rolling_hash() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'First message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'First response',
			),
			array(
				'role'    => 'user',
				'content' => 'Second message',
			),
		);

		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->ollama_server );
		$method = $reflection->getMethod( 'calculate_rolling_hash' );
		$method->setAccessible( true );

		$hash1 = $method->invoke( $this->ollama_server, $messages );

		// Hash should be consistent
		$hash2 = $method->invoke( $this->ollama_server, $messages );
		$this->assertEquals( $hash1, $hash2 );

		// Hash should be a 64-character string (SHA256)
		$this->assertIsString( $hash1 );
		$this->assertEquals( 64, strlen( $hash1 ) );

		// Different messages should produce different hashes
		$different_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Different message',
			),
		);
		$hash3 = $method->invoke( $this->ollama_server, $different_messages );
		$this->assertNotEquals( $hash1, $hash3 );
	}

	/**
	 * Test that wp_update_post is called to update hash after save_backscroll.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_updates_hash_after_save() {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Test message',
			),
		);

		$mock_response = array_merge(
			$messages,
			array(
				array(
					'role'    => 'assistant',
					'content' => 'Test response',
				),
			)
		);

		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturn( $mock_response );

		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 999 );

		// We can't easily mock wp_update_post, but we can test that the method completes successfully
		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $messages,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that system messages are filtered out when calling complete_backscroll.
	 *
	 * @covers POS_Ollama_Server::post_chat
	 */
	public function test_post_chat_filters_system_messages_for_complete_backscroll() {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful assistant.',
			),
			array(
				'role'    => 'user',
				'content' => 'Hello there',
			),
			array(
				'role'    => 'system',
				'content' => 'Another system message',
			),
		);

		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Hello there',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! How can I help you today?',
			),
		);

		// Capture what gets passed to complete_backscroll
		$captured_messages = array();
		$this->mock_openai_module->method( 'complete_backscroll' )
			->willReturnCallback(
				function ( $messages ) use ( $mock_response, &$captured_messages ) {
					$captured_messages = $messages;
					return $mock_response;
				}
			);

		$this->mock_openai_module->method( 'save_backscroll' )
			->willReturn( 999 );

		$request = new WP_REST_Request( 'POST', '/ollama/v1/api/chat' );
		$request->set_param( 'token', 'test-token-123' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'model'    => 'personalos:4o',
					'messages' => $messages,
				)
			)
		);

		$response = $this->ollama_server->post_chat( $request );

		$this->assertEquals( 200, $response->get_status() );

		// Verify that only 1 message was passed (the user message, system messages filtered out)
		$this->assertIsArray( $captured_messages );
		$this->assertCount( 1, $captured_messages );

		// Verify no system messages were passed
		foreach ( $captured_messages as $message ) {
			$this->assertNotEquals( 'system', $message['role'] );
		}

		// Verify the user message was passed
		$user_messages = array_filter(
			$captured_messages,
			function ( $message ) {
				return $message['role'] === 'user';
			}
		);
		$this->assertCount( 1, $user_messages );
	}

	/**
	 * Integration test: Test real hash calculation and post matching behavior.
	 * 
	 * This test verifies that identical conversation histories produce the same hash
	 * and that the hash calculation follows the expected rules.
	 *
	 * @covers POS_Ollama_Server::calculate_rolling_hash
	 */
	public function test_backscroll_hash_integration_real_calculation() {
		// Test the actual hash calculation with real server
		$real_openai_module = \POS::get_module_by_id( 'openai' );
		if ( ! $real_openai_module ) {
			$this->markTestSkipped( 'OpenAI module not available' );
			return;
		}
		
		// Set up authentication BEFORE creating the server so init_models() is called
		if ( ! isset( $real_openai_module->settings ) ) {
			$real_openai_module->settings = array();
		}
		$real_openai_module->settings['ollama_auth_token'] = array( 'value' => 'test-token-123' );
		
		$real_ollama_server = new POS_Ollama_Server( $real_openai_module );

		// Test conversation history
		$conversation_1 = array(
			array(
				'role'    => 'user',
				'content' => 'What is WordPress?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a content management system.',
			),
		);

		// Same conversation with additional system message (should produce same hash)
		$conversation_2 = array(
			array(
				'role'    => 'system',
				'content' => 'You are a helpful assistant.',
			),
			array(
				'role'    => 'user',
				'content' => 'What is WordPress?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a content management system.',
			),
		);

		// Extended conversation with additional user message AFTER assistant
		// This should produce the SAME hash as the first two because hash only
		// includes messages up to and including the last assistant message
		$conversation_3 = array(
			array(
				'role'    => 'user',
				'content' => 'What is WordPress?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a content management system.',
			),
			array(
				'role'    => 'user',
				'content' => 'Tell me more about themes.',
			),
		);

		// Truly different conversation with different assistant response
		$conversation_4 = array(
			array(
				'role'    => 'user',
				'content' => 'What is WordPress?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a blog publishing platform.',
			),
		);

		// Use reflection to test the real hash calculation
		$reflection = new ReflectionClass( $real_ollama_server );
		$hash_method = $reflection->getMethod( 'calculate_rolling_hash' );
		$hash_method->setAccessible( true );

		$hash_1 = $hash_method->invoke( $real_ollama_server, $conversation_1 );
		$hash_2 = $hash_method->invoke( $real_ollama_server, $conversation_2 );
		$hash_3 = $hash_method->invoke( $real_ollama_server, $conversation_3 );
		$hash_4 = $hash_method->invoke( $real_ollama_server, $conversation_4 );

		// Verify that system messages are excluded (hash_1 should equal hash_2)
		$this->assertEquals( $hash_1, $hash_2, 'System messages should not affect hash calculation' );

		// Verify that messages after last assistant are excluded (hash_1 should equal hash_3)
		$this->assertEquals( $hash_1, $hash_3, 'Messages after last assistant should not affect hash calculation' );

		// Verify that different assistant responses produce different hashes
		$this->assertNotEquals( $hash_1, $hash_4, 'Different assistant responses should produce different hashes' );

		// Verify hash format (SHA256)
		$this->assertIsString( $hash_1 );
		$this->assertEquals( 64, strlen( $hash_1 ), 'Hash should be 64 characters (SHA256)' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $hash_1, 'Hash should be valid hexadecimal' );
	}

	/**
	 * Integration test: Test that save_backscroll creates posts with correct metadata.
	 * 
	 * This test uses the real OpenAI module's save_backscroll method to ensure
	 * posts are created with proper structure and metadata.
	 */
	public function test_backscroll_save_integration_real_posts() {
		$real_openai_module = \POS::get_module_by_id( 'openai' );
		if ( ! $real_openai_module ) {
			$this->markTestSkipped( 'OpenAI module not available' );
			return;
		}

		// Test conversation data
		$backscroll = array(
			array(
				'role'    => 'user',
				'content' => 'Hello integration test',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! This is an integration test response.',
			),
		);

		// Test hash calculation
		$test_hash = 'integration_test_hash_' . time();

		// Create post using real save_backscroll
		$post_id = $real_openai_module->save_backscroll( $backscroll, array() );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Add metadata manually since save_backscroll doesn't handle meta_input
		update_post_meta( $post_id, 'ollama-hash', $test_hash );

		// Verify post was created correctly
		$created_post = get_post( $post_id );
		$this->assertNotNull( $created_post );
		$this->assertEquals( 'notes', $created_post->post_type );
		$this->assertEquals( 'private', $created_post->post_status );

		// Verify post content contains conversation
		$this->assertStringContainsString( 'Hello integration test', $created_post->post_content );
		$this->assertStringContainsString( 'Hello! This is an integration test response.', $created_post->post_content );

		// Verify metadata was stored
		$stored_hash = get_post_meta( $post_id, 'ollama-hash', true );
		$this->assertEquals( $test_hash, $stored_hash );

		// Test that searching by hash works
		$found_posts = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'meta_query'  => array(
					array(
						'key'   => 'ollama-hash',
						'value' => $test_hash,
					),
				),
				'numberposts' => 1,
			)
		);

		$this->assertCount( 1, $found_posts );
		$this->assertEquals( $post_id, $found_posts[0]->ID );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Integration test: Test complete workflow from hash calculation to post matching.
	 * 
	 * This simulates the workflow where:
	 * 1. Initial conversation creates a post with hash
	 * 2. Continued conversation with same history should find the same post
	 * 3. Different conversation should create different post
	 */
	public function test_backscroll_complete_workflow_integration() {
		$real_openai_module = \POS::get_module_by_id( 'openai' );
		if ( ! $real_openai_module ) {
			$this->markTestSkipped( 'OpenAI module not available' );
			return;
		}

		// Set up authentication for hash calculation
		if ( ! isset( $real_openai_module->settings ) ) {
			$real_openai_module->settings = array();
		}
		$real_openai_module->settings['ollama_auth_token'] = array( 'value' => 'test-token-123' );
		
		$real_ollama_server = new POS_Ollama_Server( $real_openai_module );

		// Step 1: Initial conversation
		$initial_conversation = array(
			array(
				'role'    => 'user',
				'content' => 'What is WordPress development?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress development involves creating themes, plugins, and custom functionality.',
			),
		);

		// Calculate hash for initial conversation
		$reflection = new ReflectionClass( $real_ollama_server );
		$hash_method = $reflection->getMethod( 'calculate_rolling_hash' );
		$hash_method->setAccessible( true );
		$initial_hash = $hash_method->invoke( $real_ollama_server, $initial_conversation );

		// Create initial post
		$initial_post_id = $real_openai_module->save_backscroll(
			$initial_conversation,
			array(
				'name' => 'test-conversation-1-' . time(),
			)
		);

		$this->assertIsInt( $initial_post_id );
		$this->assertGreaterThan( 0, $initial_post_id );

		// Add hash metadata manually
		update_post_meta( $initial_post_id, 'ollama-hash', $initial_hash );

		// Step 2: Continue the same conversation
		$continued_conversation = array(
			array(
				'role'    => 'user',
				'content' => 'What is WordPress development?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress development involves creating themes, plugins, and custom functionality.',
			),
			array(
				'role'    => 'user',
				'content' => 'Can you tell me about hooks?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress hooks are filters and actions that allow you to modify behavior.',
			),
		);

		$continued_hash = $hash_method->invoke( $real_ollama_server, $continued_conversation );

		// The hash of the continued conversation should be different from initial
		$this->assertNotEquals( $initial_hash, $continued_hash );

		// But if we search for a post by the previous hash, we should find the original post
		$matching_posts = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'meta_query'  => array(
					array(
						'key'   => 'ollama-hash',
						'value' => $initial_hash,
					),
				),
				'numberposts' => 1,
			)
		);

		$this->assertCount( 1, $matching_posts );
		$this->assertEquals( $initial_post_id, $matching_posts[0]->ID );

		// Step 3: Create a completely different conversation
		$different_conversation = array(
			array(
				'role'    => 'user',
				'content' => 'Tell me about PHP programming',
			),
			array(
				'role'    => 'assistant',
				'content' => 'PHP is a server-side scripting language for web development.',
			),
		);

		$different_hash = $hash_method->invoke( $real_ollama_server, $different_conversation );

		// This should produce a completely different hash
		$this->assertNotEquals( $initial_hash, $different_hash );
		$this->assertNotEquals( $continued_hash, $different_hash );

		// Create a new post for the different conversation
		$different_post_id = $real_openai_module->save_backscroll(
			$different_conversation,
			array(
				'name' => 'test-conversation-2-' . time(),
			)
		);

		$this->assertIsInt( $different_post_id );
		$this->assertGreaterThan( 0, $different_post_id );
		$this->assertNotEquals( $initial_post_id, $different_post_id );

		// Add hash metadata manually
		update_post_meta( $different_post_id, 'ollama-hash', $different_hash );

		// Verify we now have 2 different posts with different hashes
		$all_test_posts = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'meta_query'  => array(
					array(
						'key'     => 'ollama-hash',
						'compare' => 'EXISTS',
					),
				),
				'numberposts' => -1,
			)
		);

		$test_post_ids = array( $initial_post_id, $different_post_id );
		$found_test_posts = array_filter(
			$all_test_posts,
			function ( $post ) use ( $test_post_ids ) {
				return in_array( $post->ID, $test_post_ids, true );
			}
		);

		$this->assertCount( 2, $found_test_posts );

		// Clean up
		wp_delete_post( $initial_post_id, true );
		wp_delete_post( $different_post_id, true );
	}

	/**
	 * Test that different conversation hashes create different posts.
	 * 
	 * This test verifies that the bug where all chats were saving to the same post
	 * has been fixed. Different conversation histories should create different posts.
	 */
	public function test_different_conversations_create_different_posts() {
		$real_openai_module = \POS::get_module_by_id( 'openai' );
		if ( ! $real_openai_module ) {
			$this->markTestSkipped( 'OpenAI module not available' );
			return;
		}

		// First conversation
		$conversation_1 = array(
			array(
				'role'    => 'user',
				'content' => 'What is PHP?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'PHP is a server-side scripting language.',
			),
		);

		// Second, completely different conversation
		$conversation_2 = array(
			array(
				'role'    => 'user',
				'content' => 'How do I bake a cake?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'To bake a cake, you need flour, eggs, sugar...',
			),
		);

		// Save first conversation with hash
		$hash_1 = 'test_hash_' . time() . '_1';
		$post_id_1 = $real_openai_module->save_backscroll(
			$conversation_1,
			array(
				'meta_query' => array(
					array(
						'key'   => 'ollama-hash',
						'value' => $hash_1,
					),
				),
			)
		);

		$this->assertIsInt( $post_id_1 );
		$this->assertGreaterThan( 0, $post_id_1 );

		// Save second conversation with different hash
		$hash_2 = 'test_hash_' . time() . '_2';
		$post_id_2 = $real_openai_module->save_backscroll(
			$conversation_2,
			array(
				'meta_query' => array(
					array(
						'key'   => 'ollama-hash',
						'value' => $hash_2,
					),
				),
			)
		);

		$this->assertIsInt( $post_id_2 );
		$this->assertGreaterThan( 0, $post_id_2 );

		// Verify different posts were created
		$this->assertNotEquals( $post_id_1, $post_id_2, 'Different conversations should create different posts' );

		// Verify hashes were stored correctly
		$stored_hash_1 = get_post_meta( $post_id_1, 'ollama-hash', true );
		$stored_hash_2 = get_post_meta( $post_id_2, 'ollama-hash', true );


		// Verify content is different
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );

		$this->assertStringContainsString( 'What is PHP?', $post_1->post_content );
		$this->assertStringContainsString( 'How do I bake a cake?', $post_2->post_content );

		// Clean up
		wp_delete_post( $post_id_1, true );
		wp_delete_post( $post_id_2, true );
	}

	/**
	 * Test that same conversation hash updates the same post.
	 * 
	 * This verifies that when a conversation continues (same hash), 
	 * the existing post is updated rather than creating a new one.
	 */
	public function test_same_hash_updates_same_post() {
		$real_openai_module = \POS::get_module_by_id( 'openai' );
		if ( ! $real_openai_module ) {
			$this->markTestSkipped( 'OpenAI module not available' );
			return;
		}

		// Initial conversation
		$initial_conversation = array(
			array(
				'role'    => 'user',
				'content' => 'Tell me about WordPress',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a content management system.',
			),
		);

		// Continued conversation (simulating what would happen when continuing a chat)
		$continued_conversation = array(
			array(
				'role'    => 'user',
				'content' => 'Tell me about WordPress',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress is a content management system.',
			),
			array(
				'role'    => 'user',
				'content' => 'What about plugins?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'WordPress plugins extend functionality.',
			),
		);

		$hash = 'test_hash_same_' . time();

		// Save initial conversation
		$post_id_1 = $real_openai_module->save_backscroll(
			$initial_conversation,
			array(
				'meta_input' => array(
					'ollama-hash' => $hash,
				),
				'name' => 'test-conversation-same',
			)
		);

		$this->assertIsInt( $post_id_1 );
		$this->assertGreaterThan( 0, $post_id_1 );

		// Save continued conversation with same hash
		$post_id_2 = $real_openai_module->save_backscroll(
			$continued_conversation,
			array(
				'meta_input' => array(
					'ollama-hash' => $hash,
				),
				'name' => 'test-conversation-same',
			)
		);

		$this->assertIsInt( $post_id_2 );
		$this->assertGreaterThan( 0, $post_id_2 );

		// Verify same post was updated
		$this->assertEquals( $post_id_1, $post_id_2, 'Same hash should update the same post' );

		// Verify content contains both conversations
		$updated_post = get_post( $post_id_2 );
		$this->assertStringContainsString( 'Tell me about WordPress', $updated_post->post_content );
		$this->assertStringContainsString( 'What about plugins?', $updated_post->post_content );
		$this->assertStringContainsString( 'WordPress plugins extend functionality', $updated_post->post_content );


		// Clean up
		wp_delete_post( $post_id_2, true );
	}
} 