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
				'post_excerpt' => 'This should be visible',
			),
			array( 'now', 'inbox' )
		);

		// Create a TODO only in the 'inbox' notebook
		$todo_in_inbox = $this->module->create(
			array(
				'post_title'   => 'TODO in inbox only',
				'post_excerpt' => 'This should now be visible after fix',
			),
			array( 'inbox' )
		);

		// Get TODOs using the AI tool
		$result = $tool->invoke( array() );

		$this->assertIsArray( $result, 'Tool should return an array' );
		
		// Find TODOs in the result
		$titles = array_column( $result, 'title' );
		
		// The TODO in 'now' should be visible
		$this->assertContains( 'TODO in now', $titles, 'TODO in now notebook should be visible' );
		
		// The TODO only in 'inbox' should now be visible after the fix
		$this->assertContains( 'TODO in inbox only', $titles, 'TODO only in inbox should now be visible after fix' );
	}

	public function test_ai_tool_create_and_list_integration() {
		// This test demonstrates the full workflow after fix:
		// 1. Create a TODO using AI tool (it goes to inbox)
		// 2. List it using AI tool (it now appears because list shows all todos)
		
		$create_tool = OpenAI_Tool::get_tool( 'todo_create_item' );
		$list_tool = OpenAI_Tool::get_tool( 'todo_get_items' );

		// Create a TODO using AI (without specifying notebook)
		$created_todo = $create_tool->invoke( array(
			'post_title'   => 'AI Created TODO',
			'post_excerpt' => 'Created via AI, should go to inbox',
		) );

		$this->assertInstanceOf( 'WP_Post', $created_todo );

		// Try to list TODOs
		$todos = $list_tool->invoke( array() );
		$titles = array_column( $todos, 'title' );

		// After the fix, the just-created TODO should be visible
		$this->assertContains( 
			'AI Created TODO', 
			$titles, 
			'After fix: AI-created TODO (in inbox) should be visible when listing'
		);
	}
}
