<?php

class OpenAIModuleAIToolsTest extends WP_UnitTestCase {
	private $module = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'openai' );
		wp_set_current_user( 1 );

		// Suppress duplicate registration warnings in tests (abilities may already be registered)
		$this->setExpectedIncorrectUsage( 'WP_Ability_Categories_Registry::register' );
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::register' );

		// Ensure ability categories are registered
		if ( class_exists( 'WP_Ability' ) && function_exists( 'wp_register_ability_category' ) && ! did_action( 'wp_abilities_api_categories_init' ) ) {
			do_action( 'wp_abilities_api_categories_init' );
		}

		// Ensure abilities are registered
		if ( class_exists( 'WP_Ability' ) && ! did_action( 'wp_abilities_api_init' ) ) {
			do_action( 'wp_abilities_api_init' );
		}

		// Create default notebook term if it doesn't exist
		if ( ! term_exists( 'ai-memory', 'notebook' ) ) {
			wp_insert_term( 'AI Memory', 'notebook', array( 'slug' => 'ai-memory' ) );
		}
	}

	public function test_list_posts_ability_returns_posts() {
		// Create some test posts
		$post_id_1 = wp_insert_post(
			array(
				'post_title'   => 'Test Post 1',
				'post_content' => 'Test content 1',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_excerpt' => 'Test excerpt 1',
			)
		);
		$post_id_2 = wp_insert_post(
			array(
				'post_title'   => 'Test Post 2',
				'post_content' => 'Test content 2',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_excerpt' => 'Test excerpt 2',
			)
		);

		// Call the method
		$result = $this->module->list_posts_ability(
			array(
				'posts_per_page' => 10,
				'post_type'      => 'post',
				'post_status'    => 'publish',
			)
		);

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertGreaterThanOrEqual( 2, count( $result ), 'Should return at least 2 posts' );

		// Check structure of returned items
		$found_post_1 = false;
		foreach ( $result as $item ) {
			$this->assertArrayHasKey( 'id', $item );
			$this->assertArrayHasKey( 'title', $item );
			$this->assertArrayHasKey( 'date', $item );
			$this->assertArrayHasKey( 'excerpt', $item );
			$this->assertArrayHasKey( 'url', $item );

			if ( $item['id'] === $post_id_1 ) {
				$found_post_1 = true;
				$this->assertEquals( 'Test Post 1', $item['title'] );
				$this->assertEquals( 'Test excerpt 1', $item['excerpt'] );
			}
		}

		$this->assertTrue( $found_post_1, 'Should find test post 1 in results' );
	}

	public function test_list_posts_ability_filters_by_post_type() {
		// Create a page
		$page_id = wp_insert_post(
			array(
				'post_title'  => 'Test Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);

		// Request only pages
		$result = $this->module->list_posts_ability(
			array(
				'posts_per_page' => 10,
				'post_type'      => 'page',
				'post_status'    => 'publish',
			)
		);

		$this->assertIsArray( $result );
		// Verify all returned items are pages
		$page_found = false;
		foreach ( $result as $item ) {
			if ( $item['id'] === $page_id ) {
				$page_found = true;
				$this->assertEquals( 'Test Page', $item['title'] );
			}
		}
		$this->assertTrue( $page_found, 'Should find the test page' );
	}

	public function test_create_ai_memory_creates_note() {
		$args = array(
			'ID'           => 0,
			'post_title'   => 'Test Memory',
			'post_content' => 'This is a test memory content',
		);

		$result = $this->module->create_ai_memory_ability( $args );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'url', $result, 'Result should have url key' );
		$this->assertNotEmpty( $result['url'], 'URL should not be empty' );

		// Extract post ID from URL
		preg_match( '/[?&]p=(\d+)/', $result['url'], $matches );
		if ( ! empty( $matches[1] ) ) {
			$post_id = $matches[1];
		} else {
			// Try to find post by title
			$posts = get_posts(
				array(
					'title'       => 'Test Memory',
					'post_type'   => 'notes',
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);
			$this->assertNotEmpty( $posts, 'Should find created memory post' );
			$post_id = $posts[0]->ID;
		}

		// Verify the post was created
		$post = get_post( $post_id );
		$this->assertNotNull( $post, 'Post should be created' );
		$this->assertEquals( 'Test Memory', $post->post_title );
		$this->assertEquals( 'This is a test memory content', $post->post_content );
		$this->assertEquals( 'notes', $post->post_type );
		$this->assertEquals( 'publish', $post->post_status );

		// Verify it was tagged with ai-memory notebook
		$notebooks = wp_get_object_terms( $post_id, 'notebook' );
		$notebook_slugs = wp_list_pluck( $notebooks, 'slug' );
		$this->assertContains( 'ai-memory', $notebook_slugs, 'Post should be in ai-memory notebook' );
	}

	public function test_create_ai_memory_updates_existing() {
		// Create initial memory
		$initial_id = wp_insert_post(
			array(
				'post_title'   => 'Initial Memory',
				'post_content' => 'Initial content',
				'post_type'    => 'notes',
				'post_status'  => 'publish',
			)
		);
		wp_set_object_terms( $initial_id, array( 'ai-memory' ), 'notebook' );

		// Update it
		$args = array(
			'ID'           => $initial_id,
			'post_title'   => 'Updated Memory',
			'post_content' => 'Updated content',
		);

		$result = $this->module->create_ai_memory_ability( $args );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'url', $result );

		// Verify the post was updated
		$post = get_post( $initial_id );
		$this->assertEquals( 'Updated Memory', $post->post_title );
		$this->assertEquals( 'Updated content', $post->post_content );
	}

	public function test_ability_list_posts() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the list_posts ability
		$ability = wp_get_ability( 'pos/list-posts' );
		$this->assertNotNull( $ability, 'pos/list-posts ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/list-posts should be a WP_Ability' );

		// Create a test post
		wp_insert_post(
			array(
				'post_title'  => 'Ability Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Execute the ability
		$result = $ability->execute(
			array(
				'posts_per_page' => 10,
				'post_type'      => 'post',
				'post_status'    => 'publish',
			)
		);

		$this->assertIsArray( $result, 'Ability should return an array' );
		$titles = array_column( $result, 'title' );
		$this->assertContains( 'Ability Test Post', $titles, 'Should find test post in results' );
	}

	public function test_ability_create_memory() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the ai_memory ability
		$ability = wp_get_ability( 'pos/ai-memory' );
		$this->assertNotNull( $ability, 'pos/ai-memory ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/ai-memory should be a WP_Ability' );

		// Execute the ability
		$result = $ability->execute(
			array(
				'ID'           => 0,
				'post_title'   => 'Ability Created Memory',
				'post_content' => 'This memory was created via ability',
			)
		);

		$this->assertIsArray( $result, 'Ability should return an array' );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertNotEmpty( $result['url'] );

		// Verify memory was created
		$memories = get_posts(
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
				'title'       => 'Ability Created Memory',
			)
		);

		$this->assertNotEmpty( $memories, 'Memory should be created' );
		$this->assertEquals( 'Ability Created Memory', $memories[0]->post_title );
	}

	public function test_ability_system_state() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the system_state ability
		$ability = wp_get_ability( 'pos/system-state' );
		$this->assertNotNull( $ability, 'pos/system-state ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/system-state should be a WP_Ability' );

		// Get current user to verify against
		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;
		
		// Update user description for testing
		$test_description = 'Test user description for system state';
		update_user_meta( $user_id, 'description', $test_description );
		
		// Clear user cache to ensure fresh data
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'users' );

		// Execute the ability
		$result = $ability->execute( array() );

		$this->assertIsArray( $result, 'Ability should return an array' );
		$this->assertArrayHasKey( 'user_display_name', $result, 'Result should have user_display_name key' );
		$this->assertArrayHasKey( 'user_description', $result, 'Result should have user_description key' );
		$this->assertArrayHasKey( 'system_time', $result, 'Result should have system_time key' );

		// Verify the structure and that values are set
		$this->assertIsString( $result['user_display_name'], 'User display name should be a string' );
		$this->assertNotEmpty( $result['user_display_name'], 'User display name should not be empty' );
		$this->assertIsString( $result['user_description'], 'User description should be a string' );
		$this->assertEquals( $test_description, $result['user_description'], 'User description should match what we set' );
		$this->assertNotEmpty( $result['system_time'], 'System time should not be empty' );
		$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['system_time'], 'System time should be in expected format' );
	}
}
