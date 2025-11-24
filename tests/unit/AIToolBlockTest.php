<?php

/**
 * Tests for the pos/ai-tool block rendering.
 */
class AIToolBlockTest extends WP_UnitTestCase {
	private $openai_module = null;
	private $notes_module = null;

	public function set_up() {
		parent::set_up();
		wp_set_current_user( 1 );

		$this->openai_module = \POS::get_module_by_id( 'openai' );
		$this->notes_module = \POS::get_module_by_id( 'notes' );

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
	}

	/**
	 * Test that the block renders empty string when ability API is not available.
	 */
	public function test_block_renders_empty_when_ability_api_unavailable() {
		if ( class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available' );
		}

		$attributes = array(
			'tool' => 'pos/get-notebooks',
			'parameters' => array(),
		);

		$output = $this->openai_module->render_tool_block( $attributes );
		$this->assertEquals( '', $output, 'Block should return empty string when Abilities API is not available' );
	}

	/**
	 * Test that the block renders empty string when tool attribute is empty.
	 */
	public function test_block_renders_empty_when_tool_empty() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$attributes = array(
			'tool' => '',
			'parameters' => array(),
		);

		$output = $this->openai_module->render_tool_block( $attributes );
		$this->assertEquals( '', $output, 'Block should return empty string when tool is empty' );
	}

	/**
	 * Test that the block renders empty string when ability doesn't exist.
	 */
	public function test_block_renders_empty_when_ability_not_found() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Suppress incorrect usage notice for non-existent ability
		$this->setExpectedIncorrectUsage( 'WP_Abilities_Registry::get_registered' );

		$attributes = array(
			'tool' => 'pos/non-existent-ability',
			'parameters' => array(),
		);

		$output = $this->openai_module->render_tool_block( $attributes );
		$this->assertEquals( '', $output, 'Block should return empty string when ability does not exist' );
	}

	/**
	 * Test block rendering with pos/get-notebooks ability.
	 */
	public function test_block_renders_get_notebooks_ability() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks similar to starter content
		$term_status = $this->notes_module->create_term_if_not_exists( '1-Status', 'status', array() );
		$this->notes_module->create_term_if_not_exists( 'Inbox', 'inbox', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );
		$this->notes_module->create_term_if_not_exists( 'NOW', 'now', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );

		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'My main project now', 'project1', array( array( 'flag', 'project' ), array( 'flag', 'star' ) ), array( 'parent' => $term_project ) );
		$this->notes_module->create_term_if_not_exists( 'World Domination', 'project2', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		$attributes = array(
			'tool' => 'pos/get-notebooks',
			'parameters' => array(
				'notebook_flag' => 'project',
			),
		);

		$output = $this->openai_module->render_tool_block( $attributes );

		// Verify output structure
		$this->assertNotEmpty( $output, 'Block should render output for pos/get-notebooks ability' );
		$this->assertStringStartsWith( '<pre>', $output, 'Output should start with <pre> tag' );
		$this->assertStringEndsWith( '</pre>', $output, 'Output should end with </pre> tag' );

		// Extract JSON from output
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

		// Verify structure matches get_notebooks ability output
		if ( ! empty( $decoded ) ) {
			$first_group = $decoded[0];
			$this->assertArrayHasKey( 'flag_id', $first_group, 'Output should have flag_id in each group' );
			$this->assertArrayHasKey( 'flag_name', $first_group, 'Output should have flag_name in each group' );
			$this->assertArrayHasKey( 'flag_label', $first_group, 'Output should have flag_label in each group' );
			$this->assertArrayHasKey( 'notebooks', $first_group, 'Output should have notebooks array in each group' );
			$this->assertIsArray( $first_group['notebooks'], 'notebooks should be an array' );
		}
	}

	/**
	 * Test block rendering with pos/get-notebooks ability using 'all' flag (like starter content).
	 */
	public function test_block_renders_get_notebooks_all_flag() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks similar to starter content with different flags
		$term_status = $this->notes_module->create_term_if_not_exists( '1-Status', 'status', array() );
		$this->notes_module->create_term_if_not_exists( 'Inbox', 'inbox', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );
		$this->notes_module->create_term_if_not_exists( 'NOW', 'now', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );

		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'My main project now', 'project1', array( array( 'flag', 'project' ), array( 'flag', 'star' ) ), array( 'parent' => $term_project ) );
		$this->notes_module->create_term_if_not_exists( 'World Domination', 'project2', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		$term_resources = $this->notes_module->create_term_if_not_exists( '4-Resources', 'resources', array() );
		$this->notes_module->create_term_if_not_exists( 'Starter Content', 'starter-content', array( array( 'flag', 'star' ) ), array( 'parent' => $term_resources ) );

		// Create a notebook without any flags
		$this->notes_module->create_term_if_not_exists( 'Unflagged Notebook', 'unflagged', array(), array( 'parent' => $term_resources ) );

		$attributes = array(
			'tool' => 'pos/get-notebooks',
			'parameters' => array(
				'notebook_flag' => 'all',
			),
		);

		$output = $this->openai_module->render_tool_block( $attributes );

		// Verify output structure
		$this->assertNotEmpty( $output, 'Block should render output for pos/get-notebooks with all flag' );
		$this->assertStringStartsWith( '<pre>', $output, 'Output should start with <pre> tag' );
		$this->assertStringEndsWith( '</pre>', $output, 'Output should end with </pre> tag' );

		// Extract JSON from output
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

		// Verify we have multiple flag groups when using 'all'
		$this->assertGreaterThan( 0, count( $decoded ), 'Should have at least one flag group when using all flag' );

		// Verify structure of each group
		foreach ( $decoded as $group ) {
			$this->assertArrayHasKey( 'flag_id', $group, 'Each group should have flag_id' );
			$this->assertArrayHasKey( 'flag_name', $group, 'Each group should have flag_name' );
			$this->assertArrayHasKey( 'flag_label', $group, 'Each group should have flag_label' );
			$this->assertArrayHasKey( 'notebooks', $group, 'Each group should have notebooks array' );
			$this->assertIsArray( $group['notebooks'], 'notebooks should be an array' );

			// Verify notebook structure within groups
			foreach ( $group['notebooks'] as $notebook ) {
				$this->assertArrayHasKey( 'notebook_name', $notebook, 'Each notebook should have notebook_name' );
				$this->assertArrayHasKey( 'notebook_id', $notebook, 'Each notebook should have notebook_id' );
				$this->assertArrayHasKey( 'notebook_slug', $notebook, 'Each notebook should have notebook_slug' );
				$this->assertArrayHasKey( 'notebook_description', $notebook, 'Each notebook should have notebook_description' );
			}
		}

		// Verify we have notebooks from different flags (star and project)
		$flag_ids = array_column( $decoded, 'flag_id' );
		$this->assertContains( 'star', $flag_ids, 'Should include notebooks with star flag' );
		$this->assertContains( 'project', $flag_ids, 'Should include notebooks with project flag' );

		// Verify we have a group for unflagged notebooks (flag_id should be "null" as string)
		$has_unflagged = false;
		foreach ( $decoded as $group ) {
			if ( $group['flag_id'] === 'null' || $group['flag_id'] === null ) {
				$has_unflagged = true;
				break;
			}
		}
		$this->assertTrue( $has_unflagged, 'Should include a group for notebooks without flags when using all flag' );
	}

	/**
	 * Test block rendering with pos/get-notebooks ability using default parameters (should default to 'all').
	 */
	public function test_block_renders_get_notebooks_default_parameters() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks with different flags
		$term_status = $this->notes_module->create_term_if_not_exists( '1-Status', 'status', array() );
		$this->notes_module->create_term_if_not_exists( 'Inbox', 'inbox', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );

		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'Test Project', 'test-project', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		// Test with empty parameters - should default to 'all'
		$attributes = array(
			'tool' => 'pos/get-notebooks',
			'parameters' => array(),
		);

		$output = $this->openai_module->render_tool_block( $attributes );

		// Verify output structure
		$this->assertNotEmpty( $output, 'Block should render output for pos/get-notebooks with default parameters' );
		$this->assertStringStartsWith( '<pre>', $output, 'Output should start with <pre> tag' );
		$this->assertStringEndsWith( '</pre>', $output, 'Output should end with </pre> tag' );

		// Extract JSON from output
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );
		$this->assertGreaterThan( 0, count( $decoded ), 'Should have at least one flag group with default parameters' );
	}

	/**
	 * Test block rendering with pos/todo-get-items ability.
	 */
	public function test_block_renders_todo_get_items_ability() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$todo_module = \POS::get_module_by_id( 'todo' );

		// Create inbox and now notebooks if they don't exist
		if ( ! term_exists( 'inbox', 'notebook' ) ) {
			wp_insert_term( 'inbox', 'notebook' );
		}
		if ( ! term_exists( 'now', 'notebook' ) ) {
			wp_insert_term( 'now', 'notebook' );
		}

		// Create a test todo
		$todo_module->create(
			array(
				'post_title' => 'Test TODO for block rendering',
				'post_excerpt' => 'This is a test TODO',
			),
			array( 'now', 'inbox' )
		);

		$attributes = array(
			'tool' => 'pos/todo-get-items',
			'parameters' => array(),
		);

		$output = $this->openai_module->render_tool_block( $attributes );

		// Verify output structure
		$this->assertNotEmpty( $output, 'Block should render output for pos/todo-get-items ability' );
		$this->assertStringStartsWith( '<pre>', $output, 'Output should start with <pre> tag' );
		$this->assertStringEndsWith( '</pre>', $output, 'Output should end with </pre> tag' );

		// Extract JSON from output
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

		// Verify structure matches todo-get-items ability output
		if ( ! empty( $decoded ) ) {
			$first_todo = $decoded[0];
			$this->assertArrayHasKey( 'title', $first_todo, 'Output should have title in each todo' );
			$this->assertArrayHasKey( 'ID', $first_todo, 'Output should have ID in each todo' );
		}
	}

	/**
	 * Test block rendering with parameters passed correctly.
	 */
	public function test_block_passes_parameters_correctly() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks
		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'Test Project', 'test-project', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		$attributes = array(
			'tool' => 'pos/get-notebooks',
			'parameters' => array(
				'notebook_flag' => 'project',
			),
		);

		$output = $this->openai_module->render_tool_block( $attributes );

		// Extract JSON and verify it contains project notebooks
		$json_string = str_replace( array( '<pre>', '</pre>' ), '', $output );
		$decoded = json_decode( $json_string, true );

		$this->assertNotNull( $decoded, 'Output should contain valid JSON' );
		$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

		// Find project flag group
		$project_group = null;
		foreach ( $decoded as $group ) {
			if ( isset( $group['flag_id'] ) && $group['flag_id'] === 'project' ) {
				$project_group = $group;
				break;
			}
		}

		$this->assertNotNull( $project_group, 'Should have a project flag group when filtering by project flag' );
		$this->assertArrayHasKey( 'notebooks', $project_group, 'Project group should have notebooks array' );
	}

	/**
	 * Test that the block renders correctly when embedded in a post (like starter content).
	 */
	public function test_block_renders_in_post_content() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks similar to starter content
		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'My main project now', 'project1', array( array( 'flag', 'project' ), array( 'flag', 'star' ) ), array( 'parent' => $term_project ) );
		$this->notes_module->create_term_if_not_exists( 'World Domination', 'project2', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		// Create a post with the block markup (like starter-content.php)
		$post_content = '<!-- wp:heading -->
<h2 class="wp-block-heading">Projects I want to focus on right now:</h2>
<!-- /wp:heading -->

<!-- wp:pos/ai-tool {"tool":"pos/get-notebooks","parameters":{"notebook_flag":"project"}} -->
<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
<!-- /wp:pos/ai-tool -->';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post with AI Tool Block',
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_type'    => 'notes',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Render the post content through WordPress block rendering
		$post = get_post( $post_id );
		$rendered_content = do_blocks( $post->post_content );

		// Verify the block output is present in rendered content
		$this->assertNotEmpty( $rendered_content, 'Rendered content should not be empty' );
		$this->assertStringContainsString( '<pre>', $rendered_content, 'Rendered content should contain <pre> tag from block output' );
		$this->assertStringContainsString( '</pre>', $rendered_content, 'Rendered content should contain </pre> tag from block output' );

		// Extract JSON from rendered content
		// The block output should be wrapped in <pre> tags
		preg_match( '/<pre>(.*?)<\/pre>/s', $rendered_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			$decoded = json_decode( $matches[1], true );
			$this->assertNotNull( $decoded, 'Rendered content should contain valid JSON from block' );
			$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );

			// Verify structure matches get_notebooks ability output
			if ( ! empty( $decoded ) ) {
				$first_group = $decoded[0];
				$this->assertArrayHasKey( 'flag_id', $first_group, 'Output should have flag_id in each group' );
				$this->assertArrayHasKey( 'notebooks', $first_group, 'Output should have notebooks array in each group' );

				// Verify we have project notebooks
				$project_group = null;
				foreach ( $decoded as $group ) {
					if ( isset( $group['flag_id'] ) && $group['flag_id'] === 'project' ) {
						$project_group = $group;
						break;
					}
				}
				$this->assertNotNull( $project_group, 'Should have a project flag group in rendered output' );
				$this->assertGreaterThan( 0, count( $project_group['notebooks'] ), 'Project group should have notebooks' );
			}
		}

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that the block renders correctly when using apply_filters( 'the_content' ).
	 */
	public function test_block_renders_via_the_content_filter() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create test notebooks
		$term_project = $this->notes_module->create_term_if_not_exists( '2-Projects', 'projects', array() );
		$this->notes_module->create_term_if_not_exists( 'Test Project', 'test-project', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

		// Create a post with the block markup
		$post_content = '<!-- wp:pos/ai-tool {"tool":"pos/get-notebooks","parameters":{"notebook_flag":"project"}} -->
<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
<!-- /wp:pos/ai-tool -->';

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post with AI Tool Block via the_content',
				'post_content' => $post_content,
				'post_status'  => 'publish',
				'post_type'    => 'notes',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Render the post content through the_content filter (more realistic)
		$post = get_post( $post_id );
		$rendered_content = apply_filters( 'the_content', $post->post_content );

		// Verify the block output is present in rendered content
		$this->assertNotEmpty( $rendered_content, 'Rendered content should not be empty' );
		$this->assertStringContainsString( '<pre>', $rendered_content, 'Rendered content should contain <pre> tag from block output' );

		// Extract JSON from rendered content
		preg_match( '/<pre>(.*?)<\/pre>/s', $rendered_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			$decoded = json_decode( $matches[1], true );
			$this->assertNotNull( $decoded, 'Rendered content should contain valid JSON from block' );
			$this->assertIsArray( $decoded, 'Decoded JSON should be an array' );
		}

		// Clean up
		wp_delete_post( $post_id, true );
	}
}

