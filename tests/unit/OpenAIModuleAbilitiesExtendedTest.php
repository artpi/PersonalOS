<?php

/**
 * Extended tests for OpenAI Module abilities and helper functions.
 */
class OpenAIModuleAbilitiesExtendedTest extends WP_UnitTestCase {
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

		// Create ai-memory notebook term if it doesn't exist
		if ( ! term_exists( 'ai-memory', 'notebook' ) ) {
			wp_insert_term( 'AI Memory', 'notebook', array( 'slug' => 'ai-memory' ) );
		}
	}

	/**
	 * Test get_ai_memories_ability returns empty array when no memories exist.
	 */
	public function test_get_ai_memories_ability_returns_empty_array() {
		// Delete any existing memories
		$existing = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'any',
				'tax_query'   => array(
					array(
						'taxonomy' => 'notebook',
						'field'    => 'slug',
						'terms'    => 'ai-memory',
					),
				),
				'numberposts' => -1,
			)
		);
		foreach ( $existing as $post ) {
			wp_delete_post( $post->ID, true );
		}

		$result = $this->module->get_ai_memories_ability( array() );
		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEmpty( $result, 'Should return empty array when no memories exist' );
	}

	/**
	 * Test get_ai_memories_ability returns memories with correct structure.
	 */
	public function test_get_ai_memories_ability_returns_memories() {
		// Create some memories
		$memory1_id = wp_insert_post(
			array(
				'post_title'   => 'Memory 1',
				'post_content' => 'Content of memory 1',
				'post_type'    => 'notes',
				'post_status'  => 'publish',
			)
		);
		wp_set_object_terms( $memory1_id, array( 'ai-memory' ), 'notebook' );

		$memory2_id = wp_insert_post(
			array(
				'post_title'   => 'Memory 2',
				'post_content' => 'Content of memory 2',
				'post_type'    => 'notes',
				'post_status'  => 'publish',
			)
		);
		wp_set_object_terms( $memory2_id, array( 'ai-memory' ), 'notebook' );

		$result = $this->module->get_ai_memories_ability( array() );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertGreaterThanOrEqual( 2, count( $result ), 'Should return at least 2 memories' );

		// Check structure of returned memories
		foreach ( $result as $memory ) {
			$this->assertArrayHasKey( 'title', $memory, 'Memory should have title' );
			$this->assertArrayHasKey( 'content', $memory, 'Memory should have content' );
			$this->assertArrayHasKey( 'date', $memory, 'Memory should have date' );
		}

		// Verify our memories are in the result
		$titles = array_column( $result, 'title' );
		$this->assertContains( 'Memory 1', $titles, 'Should contain Memory 1' );
		$this->assertContains( 'Memory 2', $titles, 'Should contain Memory 2' );

		// Clean up
		wp_delete_post( $memory1_id, true );
		wp_delete_post( $memory2_id, true );
	}

	/**
	 * Test get_ai_memories ability execution via Abilities API.
	 */
	public function test_ability_get_ai_memories() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/get-ai-memories' );
		$this->assertNotNull( $ability, 'pos/get-ai-memories ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/get-ai-memories should be a WP_Ability' );

		// Create a memory
		$memory_id = wp_insert_post(
			array(
				'post_title'   => 'Test Ability Memory',
				'post_content' => 'Created for ability testing',
				'post_type'    => 'notes',
				'post_status'  => 'publish',
			)
		);
		wp_set_object_terms( $memory_id, array( 'ai-memory' ), 'notebook' );

		// Execute the ability
		$result = $ability->execute( array() );

		$this->assertIsArray( $result, 'Ability should return an array' );
		$titles = array_column( $result, 'title' );
		$this->assertContains( 'Test Ability Memory', $titles, 'Should find test memory in results' );

		// Clean up
		wp_delete_post( $memory_id, true );
	}

	/**
	 * Test list_posts_ability with default parameters.
	 */
	public function test_list_posts_ability_with_defaults() {
		// Create a test post
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Default Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Call with minimal arguments
		$result = $this->module->list_posts_ability( array() );

		$this->assertIsArray( $result, 'Result should be an array' );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test list_posts_ability respects posts_per_page limit.
	 */
	public function test_list_posts_ability_respects_limit() {
		// Create multiple posts
		$post_ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$post_ids[] = wp_insert_post(
				array(
					'post_title'  => "Limit Test Post $i",
					'post_status' => 'publish',
					'post_type'   => 'post',
				)
			);
		}

		// Request only 3 posts
		$result = $this->module->list_posts_ability(
			array(
				'posts_per_page' => 3,
				'post_type'      => 'post',
				'post_status'    => 'publish',
			)
		);

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertLessThanOrEqual( 3, count( $result ), 'Should return at most 3 posts' );

		// Clean up
		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Test block rendering with array output that has nested objects (array_to_xml edge case).
	 */
	public function test_block_renders_xml_with_nested_arrays() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$notes_module = \POS::get_module_by_id( 'notes' );

		// Create test notebooks with flags
		$term_project = $notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$notes_module->create_term_if_not_exists( 'XML Test Project', 'xml-test', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		$attributes = array(
			'tool'         => 'pos/get-notebooks',
			'parameters'   => array( 'notebook_flag' => 'project' ),
			'outputFormat' => 'xml',
		);

		$output = $this->module->render_tool_block( $attributes );

		$this->assertNotEmpty( $output, 'Block should render output' );
		$this->assertStringContainsString( '<root>', $output, 'XML should contain root element' );
		$this->assertStringContainsString( '</root>', $output, 'XML should have closing root element' );
		$this->assertStringContainsString( '<item>', $output, 'XML should contain item elements for arrays' );
	}

	/**
	 * Test block rendering with empty output fields (should return full result).
	 */
	public function test_block_renders_with_empty_output_fields() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$attributes = array(
			'tool'         => 'pos/system-state',
			'parameters'   => array(),
			'outputFields' => array(), // Empty array
		);

		$output = $this->module->render_tool_block( $attributes );

		$this->assertNotEmpty( $output, 'Block should render output' );
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		// With empty outputFields, all fields should be present
		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertArrayHasKey( 'user_display_name', $decoded, 'Should have user_display_name field' );
		$this->assertArrayHasKey( 'user_description', $decoded, 'Should have user_description field' );
		$this->assertArrayHasKey( 'system_time', $decoded, 'Should have system_time field' );
	}

	/**
	 * Test create_ai_memory creates in correct notebook.
	 */
	public function test_create_ai_memory_sets_notebook() {
		$args = array(
			'ID'           => 0,
			'post_title'   => 'Notebook Test Memory',
			'post_content' => 'Testing notebook assignment',
		);

		$result = $this->module->create_ai_memory_ability( $args );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'url', $result );

		// Find the created memory
		$memories = get_posts(
			array(
				'post_type'   => 'notes',
				'post_status' => 'publish',
				'title'       => 'Notebook Test Memory',
				'numberposts' => 1,
			)
		);

		$this->assertNotEmpty( $memories, 'Memory should be created' );
		$memory = $memories[0];

		// Verify notebook assignment
		$notebooks = wp_get_object_terms( $memory->ID, 'notebook' );
		$slugs = wp_list_pluck( $notebooks, 'slug' );
		$this->assertContains( 'ai-memory', $slugs, 'Memory should be in ai-memory notebook' );

		// Clean up
		wp_delete_post( $memory->ID, true );
	}

	/**
	 * Test get_system_state returns all required fields.
	 */
	public function test_get_system_state_ability_full_structure() {
		$result = $this->module->get_system_state_ability( array() );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'user_display_name', $result, 'Should have user_display_name' );
		$this->assertArrayHasKey( 'user_description', $result, 'Should have user_description' );
		$this->assertArrayHasKey( 'system_time', $result, 'Should have system_time' );

		// Verify system_time format
		$this->assertMatchesRegularExpression(
			'/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
			$result['system_time'],
			'System time should be in Y-m-d H:i:s format'
		);
	}
}

