<?php

class TodoAIToolsTest extends WP_UnitTestCase {
	private $module = null;
	private $now_term_id = null;
	private $inbox_term_id = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'todo' );
		wp_set_current_user( 1 );

		if ( ! term_exists( 'now', 'notebook' ) ) {
			$this->now_term_id = wp_insert_term( 'now', 'notebook' )['term_id'];
		} else {
			$this->now_term_id = get_term_by( 'slug', 'now', 'notebook' )->term_id;
		}
		if ( ! term_exists( 'inbox', 'notebook' ) ) {
			$this->inbox_term_id = wp_insert_term( 'inbox', 'notebook' )['term_id'];
		} else {
			$this->inbox_term_id = get_term_by( 'slug', 'inbox', 'notebook' )->term_id;
		}
	}

	public function test_ai_tool_create_todo() {
		// Get the todo_create_item tool
		$tool = OpenAI_Tool::get_tool( 'todo_create_item' );
		$this->assertNotNull( $tool, 'todo_create_item tool should be registered' );
		$this->assertInstanceOf( 'OpenAI_Tool_Writeable', $tool, 'todo_create_item should be writeable' );

		// Create a TODO using the AI tool
		$result = $tool->invoke( array(
			'post_title'   => 'Test AI TODO',
			'post_excerpt' => 'This TODO was created by AI tool',
		) );

		$this->assertInstanceOf( 'WP_Post', $result, 'Tool should return a WP_Post object' );
		$this->assertEquals( 'Test AI TODO', $result->post_title );
		$this->assertEquals( 'This TODO was created by AI tool', $result->post_excerpt );
		$this->assertEquals( 'private', $result->post_status );

		// Verify the TODO is in the inbox notebook
		$notebooks = wp_list_pluck( wp_get_object_terms( $result->ID, 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks, 'Created TODO should be in inbox notebook' );
	}

	public function test_ai_tool_create_todo_with_notebook() {
		// Get the todo_create_item tool
		$tool = OpenAI_Tool::get_tool( 'todo_create_item' );
		$this->assertNotNull( $tool, 'todo_create_item tool should be registered' );

		// Create a TODO with a specific notebook
		$result = $tool->invoke( array(
			'post_title'   => 'Test AI TODO with notebook',
			'post_excerpt' => 'This TODO was created by AI tool with notebook',
			'notebook'     => 'now',
		) );

		$this->assertInstanceOf( 'WP_Post', $result, 'Tool should return a WP_Post object' );

		// Verify the TODO is in both inbox and now notebooks
		$notebooks = wp_list_pluck( wp_get_object_terms( $result->ID, 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks, 'Created TODO should be in inbox notebook' );
		$this->assertContains( 'now', $notebooks, 'Created TODO should be in now notebook when specified' );
	}

	public function test_ai_tool_list_todos() {
		// Get the todo_get_items tool
		$tool = OpenAI_Tool::get_tool( 'todo_get_items' );
		$this->assertNotNull( $tool, 'todo_get_items tool should be registered' );
		$this->assertInstanceOf( 'OpenAI_Tool', $tool, 'todo_get_items should be an OpenAI_Tool' );

		// Create a TODO in the 'now' notebook
		$todo_in_now = $this->module->create(
			array(
				'post_title'   => 'TODO in now',
				'post_excerpt' => 'This should be visible in default list',
			),
			array( 'now', 'inbox' )
		);

		// Create a TODO only in the 'inbox' notebook
		$todo_in_inbox = $this->module->create(
			array(
				'post_title'   => 'TODO in inbox only',
				'post_excerpt' => 'This should NOT be visible in default list',
			),
			array( 'inbox' )
		);

		// Test 1: Default behavior (should list from 'now' notebook)
		$result = $tool->invoke( array() );
		$this->assertIsArray( $result, 'Tool should return an array' );
		$titles = array_column( $result, 'title' );
		$this->assertContains( 'TODO in now', $titles, 'TODO in now notebook should be visible by default' );
		$this->assertNotContains( 'TODO in inbox only', $titles, 'TODO only in inbox should NOT be visible by default' );
		
		// Test 2: Explicitly list from 'inbox' notebook
		$result_inbox = $tool->invoke( array( 'notebook' => 'inbox' ) );
		$titles_inbox = array_column( $result_inbox, 'title' );
		$this->assertContains( 'TODO in inbox only', $titles_inbox, 'TODO in inbox should be visible when explicitly requesting inbox' );
		$this->assertContains( 'TODO in now', $titles_inbox, 'TODO in now should also be visible in inbox since it has both notebooks' );
		
		// Test 3: List from all notebooks
		$result_all = $tool->invoke( array( 'notebook' => 'all' ) );
		$titles_all = array_column( $result_all, 'title' );
		$this->assertContains( 'TODO in now', $titles_all, 'TODO in now should be visible when listing all' );
		$this->assertContains( 'TODO in inbox only', $titles_all, 'TODO in inbox should be visible when listing all' );
	}

	public function test_ai_tool_create_and_list_integration() {
		// This test demonstrates the full workflow:
		// 1. Create a TODO using AI tool (it goes to inbox)
		// 2. By default, listing shows 'now' notebook, so created TODO won't appear
		// 3. But when listing with 'all' or 'inbox', it will appear
		
		$create_tool = OpenAI_Tool::get_tool( 'todo_create_item' );
		$list_tool = OpenAI_Tool::get_tool( 'todo_get_items' );

		// Create a TODO using AI (without specifying notebook, so it goes to inbox)
		$created_todo = $create_tool->invoke( array(
			'post_title'   => 'AI Created TODO',
			'post_excerpt' => 'Created via AI, should go to inbox',
		) );

		$this->assertInstanceOf( 'WP_Post', $created_todo );

		// List with default (now) - should NOT appear
		$todos_default = $list_tool->invoke( array() );
		$titles_default = array_column( $todos_default, 'title' );
		$this->assertNotContains( 
			'AI Created TODO', 
			$titles_default, 
			'Default listing shows "now" notebook, so inbox-only TODO should not appear'
		);

		// List with 'all' - should appear
		$todos_all = $list_tool->invoke( array( 'notebook' => 'all' ) );
		$titles_all = array_column( $todos_all, 'title' );
		$this->assertContains( 
			'AI Created TODO', 
			$titles_all, 
			'When listing all notebooks, AI-created TODO should be visible'
		);

		// List with 'inbox' - should appear
		$todos_inbox = $list_tool->invoke( array( 'notebook' => 'inbox' ) );
		$titles_inbox = array_column( $todos_inbox, 'title' );
		$this->assertContains( 
			'AI Created TODO', 
			$titles_inbox, 
			'When listing inbox notebook, AI-created TODO should be visible'
		);
	}

	public function test_ai_tool_create_with_now_and_list() {
		// This test shows that when creating with 'now' notebook, it appears in default listing
		$create_tool = OpenAI_Tool::get_tool( 'todo_create_item' );
		$list_tool = OpenAI_Tool::get_tool( 'todo_get_items' );

		// Create a TODO with 'now' notebook
		$created_todo = $create_tool->invoke( array(
			'post_title'   => 'AI Created TODO for now',
			'post_excerpt' => 'Created via AI with now notebook',
			'notebook'     => 'now',
		) );

		$this->assertInstanceOf( 'WP_Post', $created_todo );

		// List with default (now) - should appear
		$todos_default = $list_tool->invoke( array() );
		$titles_default = array_column( $todos_default, 'title' );
		$this->assertContains( 
			'AI Created TODO for now', 
			$titles_default, 
			'When TODO is created with "now" notebook, it should appear in default listing'
		);
	}
}
