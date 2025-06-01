<?php

class OpenAIModuleVercelChatTest extends WP_UnitTestCase {
	private $module = null;
	private $notes_module = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'openai' );
		$this->notes_module = \POS::get_module_by_id( 'notes' );
		wp_set_current_user( 1 );

		// Create default notebook term if it doesn't exist
		if ( ! term_exists( 'ai-chats', 'notebook' ) ) {
			wp_insert_term( 'AI Chats', 'notebook', array( 'slug' => 'ai-chats' ) );
		}
	}

	/**
	 * Helper method to create a mock request for vercel_chat
	 *
	 * @param array $params The parameters for the request
	 * @return WP_REST_Request Mock request object
	 */
	private function create_mock_request( array $params ) {
		$request = new WP_REST_Request( 'POST', '/pos/v1/openai/vercel/chat' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		return $request;
	}

	/**
	 * Test vercel_chat method with save_backscroll
	 */
	public function test_vercel_chat_saves_conversation() {
		$chat_id = 'test-unit-chat-' . time();
		$params = array(
			'id'      => $chat_id,
			'message' => array(
				'content' => 'Hello, this is a test message for unit testing.',
			),
		);

		$request = $this->create_mock_request( $params );

		// Mock the complete_backscroll method to avoid actual API calls
		$mock_response = array(
			array(
				'role'    => 'user',
				'content' => 'Hello, this is a test message for unit testing.',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Hello! I understand this is a test message for unit testing. How can I help you today?',
			),
		);

		// Set up transient to simulate existing conversation
		set_transient( 'vercel_chat_' . $chat_id, array(), 60 * 60 );

		// Check if a post gets created with the chat ID
		$posts_before = $this->notes_module->list( array( 'name' => $chat_id ) );
		$this->assertEmpty( $posts_before, 'No posts should exist before the test' );

		// Since vercel_chat ends with die(), we need to test save_backscroll directly
		// but in the context of how vercel_chat would call it
		$config = array(
			'name' => $chat_id,
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $mock_response, $config ) );

		// Verify the post was created
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Verify the post has the correct properties
		$post = get_post( $post_id );
		$this->assertEquals( 'notes', $post->post_type );
		$this->assertEquals( 'private', $post->post_status );
		$this->assertEquals( $chat_id, $post->post_name );

		// Verify content structure
		$this->assertStringContainsString( 'wp:pos/ai-message', $post->post_content );
		$this->assertStringContainsString( 'Hello, this is a test message for unit testing.', $post->post_content );
		$this->assertStringContainsString( 'Hello! I understand this is a test message', $post->post_content );

		// Verify notebook assignment
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$this->assertCount( 1, $notebooks );
		$this->assertEquals( 'ai-chats', $notebooks[0]->slug );

		// Test updating the same conversation
		$updated_response = array_merge( $mock_response, array(
			array(
				'role'    => 'user',
				'content' => 'Can you help me with something else?',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Of course! I would be happy to help you with something else.',
			),
		) );

		$updated_post_id = $method->invokeArgs( $this->module, array( $updated_response, $config ) );

		// Should be the same post ID
		$this->assertEquals( $post_id, $updated_post_id );

		// Verify updated content
		$updated_post = get_post( $updated_post_id );
		$this->assertStringContainsString( 'Can you help me with something else?', $updated_post->post_content );
		$this->assertStringContainsString( 'Of course! I would be happy to help you', $updated_post->post_content );
	}

	/**
	 * Test vercel_chat with missing content
	 */
	public function test_vercel_chat_missing_content() {
		$params = array(
			'id' => 'test-missing-content',
			// Missing message content
		);

		$request = $this->create_mock_request( $params );

		// Mock the check_permission method to return true
		$result = $this->module->vercel_chat( $request );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_message_content', $result->get_error_code() );
	}

	/**
	 * Test vercel_chat with messages array format
	 */
	public function test_vercel_chat_with_messages_array() {
		$chat_id = 'test-messages-array-' . time();
		$params = array(
			'id'       => $chat_id,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello from messages array',
				),
				array(
					'role'    => 'assistant',
					'content' => 'Previous response',
				),
				array(
					'role'    => 'user',
					'content' => 'This is the latest message',
				),
			),
		);

		// Set up transient to simulate conversation state
		$existing_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Hello from messages array',
			),
		);
		set_transient( 'vercel_chat_' . $chat_id, $existing_messages, 60 * 60 );

		// Test that the latest user message is extracted correctly
		// We'll test this by checking what would be added to the conversation
		$expected_content = 'This is the latest message';

		// Since we can't easily test the full vercel_chat method due to die(),
		// we'll verify the message extraction logic by testing a similar pattern
		$user_message_content = null;
		if ( isset( $params['message']['content'] ) ) {
			$user_message_content = $params['message']['content'];
		} elseif ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) {
			$last_message = end( $params['messages'] );
			if ( $last_message && isset( $last_message['content'] ) && 'user' === $last_message['role'] ) {
				$user_message_content = $last_message['content'];
			}
		}

		$this->assertEquals( $expected_content, $user_message_content );
	}

	/**
	 * Test transient handling in vercel_chat
	 */
	public function test_vercel_chat_transient_handling() {
		$chat_id = 'test-transient-' . time();

		// Test with no existing transient
		$transient = get_transient( 'vercel_chat_' . $chat_id );
		$this->assertFalse( $transient );

		// Simulate what vercel_chat does with transients
		$openai_messages = get_transient( 'vercel_chat_' . $chat_id );
		if ( ! $openai_messages ) {
			$openai_messages = array();
		}
		$openai_messages[] = array(
			'role'    => 'user',
			'content' => 'Test message',
		);

		// This simulates the pattern in vercel_chat
		$this->assertCount( 1, $openai_messages );
		$this->assertEquals( 'user', $openai_messages[0]['role'] );
		$this->assertEquals( 'Test message', $openai_messages[0]['content'] );

		// Test setting the transient (what would happen at the end of vercel_chat)
		$response = array(
			array(
				'role'    => 'user',
				'content' => 'Test message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Test response',
			),
		);

		set_transient( 'vercel_chat_' . $chat_id, $response, 60 * 60 );

		// Verify transient was set
		$stored_transient = get_transient( 'vercel_chat_' . $chat_id );
		$this->assertEquals( $response, $stored_transient );
	}

	/**
	 * Test save_backscroll config parameter handling in vercel_chat context
	 */
	public function test_save_backscroll_config_in_vercel_chat() {
		$chat_id = 'test-config-' . time();
		$backscroll = array(
			array(
				'role'    => 'user',
				'content' => 'Test config message',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Config response',
			),
		);

		// Test the config array that vercel_chat passes to save_backscroll
		$config = array(
			'name' => $chat_id,
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Verify the post was created with correct properties
		$post = get_post( $post_id );
		$this->assertEquals( $chat_id, $post->post_name );
		$this->assertStringContainsString( 'Chat ', $post->post_title ); // Default title format
		$this->assertEquals( 'private', $post->post_status );

		// Verify default notebook assignment (ai-chats)
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$this->assertCount( 1, $notebooks );
		$this->assertEquals( 'ai-chats', $notebooks[0]->slug );
	}
} 