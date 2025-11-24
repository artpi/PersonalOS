<?php

class OpenAIModuleTest extends WP_UnitTestCase {
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
	 * Helper method to create a sample backscroll for testing
	 *
	 * @return array Sample backscroll data
	 */
	private function get_sample_backscroll() {
		return array(
			array(
				'role'    => 'user',
				'content' => 'Hello, how are you?',
				'id'      => 'user-1',
			),
			array(
				'role'    => 'assistant',
				'content' => 'I am doing well, thank you for asking!',
				'id'      => 'assistant-1',
			),
			array(
				'role'    => 'user',
				'content' => 'Can you help me with a task?',
				'id'      => 'user-2',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Of course! I would be happy to help you.',
				'id'      => 'assistant-2',
			),
		);
	}

	/**
	 * Test save_backscroll creates a new post when one doesn't exist
	 */
	public function test_save_backscroll_creates_new_post() {
		$backscroll = $this->get_sample_backscroll();
		$config = array(
			'name'       => 'test-chat-1',
			'post_title' => 'Test Chat Session',
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( 'Test Chat Session', $post->post_title );
		$this->assertEquals( 'test-chat-1', $post->post_name );
		$this->assertEquals( 'notes', $post->post_type );
		$this->assertEquals( 'private', $post->post_status );

		// Check content contains message blocks
		$this->assertStringContainsString( 'wp:pos/ai-message', $post->post_content );
		$this->assertStringContainsString( 'Hello, how are you?', $post->post_content );
		$this->assertStringContainsString( 'I am doing well, thank you for asking!', $post->post_content );

		// Check notebook assignment
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$this->assertCount( 1, $notebooks );
		$this->assertEquals( 'ai-chats', $notebooks[0]->slug );
	}

	/**
	 * Test save_backscroll updates existing post when found
	 */
	public function test_save_backscroll_updates_existing_post() {
		$backscroll = $this->get_sample_backscroll();
		$config = array(
			'name'       => 'test-chat-update',
			'post_title' => 'Original Title',
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		// Create initial post
		$post_id_1 = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Update with new backscroll and title
		$updated_backscroll = array_merge( $backscroll, array(
			array(
				'role'    => 'user',
				'content' => 'This is an updated message',
				'id'      => 'user-3',
			),
		) );
		$updated_config = array(
			'name'       => 'test-chat-update',
			'post_title' => 'Updated Title', // This won't be applied to existing posts
		);

		$post_id_2 = $method->invokeArgs( $this->module, array( $updated_backscroll, $updated_config ) );

		// Should return the same post ID
		$this->assertEquals( $post_id_1, $post_id_2 );

		$post = get_post( $post_id_2 );
		// Title should remain the same since save_backscroll only updates content for existing posts
		$this->assertEquals( 'Original Title', $post->post_title );
		$this->assertStringContainsString( 'This is an updated message', $post->post_content );
	}

	/**
	 * Test save_backscroll with custom notebook
	 */
	public function test_save_backscroll_custom_notebook() {
		// Create custom notebook
		$custom_notebook = wp_insert_term( 'Custom Notebook', 'notebook', array( 'slug' => 'custom-notebook' ) );

		$backscroll = $this->get_sample_backscroll();
		$config = array(
			'name'     => 'test-chat-custom',
			'notebook' => 'custom-notebook',
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Check notebook assignment
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$this->assertCount( 1, $notebooks );
		$this->assertEquals( 'custom-notebook', $notebooks[0]->slug );
	}

	/**
	 * Test save_backscroll creates notebook if it doesn't exist
	 */
	public function test_save_backscroll_creates_notebook() {
		$backscroll = $this->get_sample_backscroll();
		$config = array(
			'name'     => 'test-chat-new-notebook',
			'notebook' => 'new-test-notebook',
		);

		// Ensure notebook doesn't exist - check both term_exists and get_term_by
		$existing_term = get_term_by( 'slug', 'new-test-notebook', 'notebook' );
		$this->assertFalse( $existing_term, 'Notebook should not exist before test' );

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Check notebook was created
		$created_term = get_term_by( 'slug', 'new-test-notebook', 'notebook' );
		$this->assertNotFalse( $created_term, 'Notebook should be created' );

		// Check notebook assignment
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$this->assertCount( 1, $notebooks );
		$this->assertEquals( 'new-test-notebook', $notebooks[0]->slug );
		$this->assertEquals( 'New Test Notebook', $notebooks[0]->name );
	}

	/**
	 * Test save_backscroll error handling for missing name
	 */
	public function test_save_backscroll_missing_post_name() {
		$backscroll = $this->get_sample_backscroll();
		$config = array(); // Missing name

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Should succeed since name is now optional with a default
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertStringContainsString( 'chat-', $post->post_name ); // Default name format
	}

	/**
	 * Test save_backscroll error handling when notes module is not available
	 */
	public function test_save_backscroll_notes_module_unavailable() {
		$backscroll = $this->get_sample_backscroll();
		$config = array( 'name' => 'test-chat' );

		// Mock POS class to return null for notes module
		$original_notes_module = $this->notes_module;
		
		// Use a temporary workaround - we'll test this indirectly by checking the method behavior
		// when the notes module would be unavailable in a real scenario
		$this->markTestSkipped( 'Testing notes module unavailability requires mocking POS::get_module_by_id which is complex in this context' );
	}

	/**
	 * Test save_backscroll uses notes module's list method
	 */
	public function test_save_backscroll_uses_notes_module_list() {
		// Create an existing post first
		$existing_post_id = wp_insert_post( array(
			'post_type'   => 'notes',
			'post_name'   => 'existing-chat',
			'post_title'  => 'Existing Chat',
			'post_status' => 'private',
		) );

		$backscroll = $this->get_sample_backscroll();
		$config = array(
			'name'       => 'existing-chat',
			'post_title' => 'Updated Chat', // This won't be applied to existing posts
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		// Should return the same post ID as the existing one
		$this->assertEquals( $existing_post_id, $post_id );

		$post = get_post( $post_id );
		// Title should remain the same since save_backscroll only updates content for existing posts
		$this->assertEquals( 'Existing Chat', $post->post_title );
		// But content should be updated
		$this->assertStringContainsString( 'wp:pos/ai-message', $post->post_content );
	}

	/**
	 * Test that only user and assistant messages are saved to content
	 */
	public function test_save_backscroll_filters_message_types() {
		$backscroll = array(
			array(
				'role'    => 'user',
				'content' => 'User message',
				'id'      => 'user-1',
			),
			array(
				'role'    => 'assistant',
				'content' => 'Assistant message',
				'id'      => 'assistant-1',
			),
			array(
				'role'    => 'system',
				'content' => 'System message - should be ignored',
				'id'      => 'system-1',
			),
			array(
				'role'    => 'tool',
				'content' => 'Tool message - should be ignored',
				'id'      => 'tool-1',
			),
		);

		$config = array(
			'name' => 'test-chat-filtered',
		);

		// Use reflection to access the private method
		$reflection = new ReflectionClass( $this->module );
		$method = $reflection->getMethod( 'save_backscroll' );
		$method->setAccessible( true );

		$post_id = $method->invokeArgs( $this->module, array( $backscroll, $config ) );

		$post = get_post( $post_id );
		
		// Should contain user and assistant messages
		$this->assertStringContainsString( 'User message', $post->post_content );
		$this->assertStringContainsString( 'Assistant message', $post->post_content );
		
		// Should not contain system or tool messages
		$this->assertStringNotContainsString( 'System message', $post->post_content );
		$this->assertStringNotContainsString( 'Tool message', $post->post_content );
	}

	/**
	 * Test get_chat_prompts returns all prompts when no args provided
	 */
	public function test_get_chat_prompts_returns_all_prompts() {
		// Create prompts-chat notebook if it doesn't exist
		if ( ! term_exists( 'prompts-chat', 'notebook' ) ) {
			wp_insert_term( 'Prompts: Chat', 'notebook', array( 'slug' => 'prompts-chat' ) );
		}

		// Create test prompts similar to starter-content.php
		$prompt1_id = $this->notes_module->create(
			'Helpful Assistant - GPT-4.1',
			'<!-- wp:paragraph --><p>You are a helpful assistant. Keep your responses concise, clear, and actionable.</p><!-- /wp:paragraph -->',
			array( 'prompts-chat' )
		);
		update_post_meta( $prompt1_id, 'pos_model', 'gpt-4.1' );

		$prompt2_id = $this->notes_module->create(
			'Helpful Assistant - GPT-5',
			'<!-- wp:paragraph --><p>You are a helpful assistant. Keep your responses concise, clear, and actionable.</p><!-- /wp:paragraph -->',
			array( 'prompts-chat' )
		);
		update_post_meta( $prompt2_id, 'pos_model', 'gpt-5' );

		// Get all prompts
		$prompts = $this->module->get_chat_prompts();

		// Should return an array
		$this->assertIsArray( $prompts );
		$this->assertGreaterThanOrEqual( 2, count( $prompts ) );

		// Verify structure of returned prompts
		foreach ( $prompts as $slug => $config ) {
			$this->assertIsString( $slug );
			$this->assertIsArray( $config );
			$this->assertArrayHasKey( 'id', $config );
			$this->assertArrayHasKey( 'post_id', $config );
			$this->assertArrayHasKey( 'name', $config );
			$this->assertArrayHasKey( 'description', $config );
			$this->assertArrayHasKey( 'model', $config );
			$this->assertIsString( $config['id'] );
			$this->assertIsInt( $config['post_id'] );
			$this->assertIsString( $config['name'] );
			$this->assertIsString( $config['description'] );
			$this->assertIsString( $config['model'] );
		}

		// Verify our created prompts are in the results
		$prompt1_post = get_post( $prompt1_id );
		$prompt2_post = get_post( $prompt2_id );
		$this->assertArrayHasKey( $prompt1_post->post_name, $prompts );
		$this->assertArrayHasKey( $prompt2_post->post_name, $prompts );
		$this->assertEquals( 'gpt-4.1', $prompts[ $prompt1_post->post_name ]['model'] );
		$this->assertEquals( 'gpt-5', $prompts[ $prompt2_post->post_name ]['model'] );
	}

	/**
	 * Test get_chat_prompts filters by slug when name arg provided
	 */
	public function test_get_chat_prompts_filters_by_slug() {
		// Create prompts-chat notebook if it doesn't exist
		if ( ! term_exists( 'prompts-chat', 'notebook' ) ) {
			wp_insert_term( 'Prompts: Chat', 'notebook', array( 'slug' => 'prompts-chat' ) );
		}

		// Create a test prompt with a specific slug
		$prompt_id = $this->notes_module->create(
			'Test Prompt',
			'<!-- wp:paragraph --><p>This is a test prompt.</p><!-- /wp:paragraph -->',
			array( 'prompts-chat' )
		);
		update_post_meta( $prompt_id, 'pos_model', 'gpt-4o' );

		$prompt_post = get_post( $prompt_id );
		$prompt_slug = $prompt_post->post_name;

		// Get prompt by slug
		$prompts = $this->module->get_chat_prompts( array( 'name' => $prompt_slug ) );

		// Should return array with one element
		$this->assertIsArray( $prompts );
		$this->assertCount( 1, $prompts );
		$this->assertArrayHasKey( $prompt_slug, $prompts );

		// Verify the prompt data
		$prompt_config = $prompts[ $prompt_slug ];
		$this->assertEquals( $prompt_slug, $prompt_config['id'] );
		$this->assertEquals( $prompt_id, $prompt_config['post_id'] );
		$this->assertEquals( 'Test Prompt', $prompt_config['name'] );
		$this->assertEquals( 'gpt-4o', $prompt_config['model'] );
		$this->assertStringContainsString( 'test prompt', strtolower( $prompt_config['description'] ) );
	}

	/**
	 * Test get_chat_prompts filters by ID when p arg provided
	 */
	public function test_get_chat_prompts_filters_by_id() {
		// Create prompts-chat notebook if it doesn't exist
		if ( ! term_exists( 'prompts-chat', 'notebook' ) ) {
			wp_insert_term( 'Prompts: Chat', 'notebook', array( 'slug' => 'prompts-chat' ) );
		}

		// Create a test prompt
		$prompt_id = $this->notes_module->create(
			'Test Prompt by ID',
			'<!-- wp:paragraph --><p>This is a test prompt for ID filtering.</p><!-- /wp:paragraph -->',
			array( 'prompts-chat' )
		);
		update_post_meta( $prompt_id, 'pos_model', 'gpt-4o' );

		$prompt_post = get_post( $prompt_id );

		// Get prompt by ID
		$prompts = $this->module->get_chat_prompts( array( 'p' => $prompt_id ) );

		// Should return array with one element
		$this->assertIsArray( $prompts );
		$this->assertCount( 1, $prompts );
		$this->assertArrayHasKey( $prompt_post->post_name, $prompts );

		// Verify the prompt data
		$prompt_config = $prompts[ $prompt_post->post_name ];
		$this->assertEquals( $prompt_post->post_name, $prompt_config['id'] );
		$this->assertEquals( $prompt_id, $prompt_config['post_id'] );
		$this->assertEquals( 'Test Prompt by ID', $prompt_config['name'] );
		$this->assertEquals( 'gpt-4o', $prompt_config['model'] );
	}

	/**
	 * Test get_chat_prompts returns empty array when no prompts match
	 */
	public function test_get_chat_prompts_returns_empty_when_no_match() {
		// Create prompts-chat notebook if it doesn't exist
		if ( ! term_exists( 'prompts-chat', 'notebook' ) ) {
			wp_insert_term( 'Prompts: Chat', 'notebook', array( 'slug' => 'prompts-chat' ) );
		}

		// Try to get a non-existent prompt by slug
		$prompts = $this->module->get_chat_prompts( array( 'name' => 'non-existent-slug-12345' ) );

		// Should return empty array
		$this->assertIsArray( $prompts );
		$this->assertEmpty( $prompts );

		// Try to get a non-existent prompt by ID
		$prompts = $this->module->get_chat_prompts( array( 'p' => 999999 ) );

		// Should return empty array
		$this->assertIsArray( $prompts );
		$this->assertEmpty( $prompts );
	}

	/**
	 * Test get_chat_prompts only returns prompts from prompts-chat notebook
	 */
	public function test_get_chat_prompts_only_returns_prompts_chat() {
		// Create prompts-chat notebook if it doesn't exist
		if ( ! term_exists( 'prompts-chat', 'notebook' ) ) {
			wp_insert_term( 'Prompts: Chat', 'notebook', array( 'slug' => 'prompts-chat' ) );
		}

		// Create a prompt in prompts-chat
		$chat_prompt_id = $this->notes_module->create(
			'Chat Prompt',
			'<!-- wp:paragraph --><p>This is a chat prompt.</p><!-- /wp:paragraph -->',
			array( 'prompts-chat' )
		);

		// Create a prompt in a different notebook (if inbox doesn't exist, create it)
		if ( ! term_exists( 'inbox', 'notebook' ) ) {
			wp_insert_term( 'Inbox', 'notebook', array( 'slug' => 'inbox' ) );
		}
		$other_prompt_id = $this->notes_module->create(
			'Other Prompt',
			'<!-- wp:paragraph --><p>This is not a chat prompt.</p><!-- /wp:paragraph -->',
			array( 'inbox' )
		);

		// Get all chat prompts
		$prompts = $this->module->get_chat_prompts();

		// Should only contain the chat prompt, not the other one
		$chat_prompt_post = get_post( $chat_prompt_id );
		$other_prompt_post = get_post( $other_prompt_id );

		$this->assertArrayHasKey( $chat_prompt_post->post_name, $prompts );
		$this->assertArrayNotHasKey( $other_prompt_post->post_name, $prompts );
	}
} 