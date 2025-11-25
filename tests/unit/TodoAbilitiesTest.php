<?php

class TodoAIToolsTest extends WP_UnitTestCase {
	private $module = null;
	private $now_term_id = null;
	private $inbox_term_id = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'todo' );
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

	public function test_ability_create_todo() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the todo_create_item ability
		$ability = wp_get_ability( 'pos/todo-create-item' );
		$this->assertNotNull( $ability, 'pos/todo-create-item ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/todo-create-item should be a WP_Ability' );

		// Create a TODO using the ability
		$result = $ability->execute( array(
			'post_title'   => 'Test AI TODO',
			'post_excerpt' => 'This TODO was created by AI tool',
		) );

		$this->assertIsArray( $result, 'Tool should return an array' );
		$this->assertEquals( 'Test AI TODO', $result['title'] );
		$this->assertEquals( 'This TODO was created by AI tool', $result['excerpt'] );
		$this->assertEquals( 'private', $result['post_status'] );

		// Verify the TODO is in the inbox notebook
		$notebooks = wp_list_pluck( wp_get_object_terms( $result['ID'], 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks, 'Created TODO should be in inbox notebook' );
	}

	public function test_ability_create_todo_with_notebook() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the todo_create_item ability
		$ability = wp_get_ability( 'pos/todo-create-item' );
		$this->assertNotNull( $ability, 'pos/todo-create-item ability should be registered' );

		// Create a TODO with a specific notebook
		$result = $ability->execute( array(
			'post_title'   => 'Test AI TODO with notebook',
			'post_excerpt' => 'This TODO was created by AI tool with notebook',
			'notebook'     => 'now',
		) );

		$this->assertIsArray( $result, 'Tool should return an array' );

		// Verify the TODO is in both inbox and now notebooks
		$notebooks = wp_list_pluck( wp_get_object_terms( $result['ID'], 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks, 'Created TODO should be in inbox notebook' );
		$this->assertContains( 'now', $notebooks, 'Created TODO should be in now notebook when specified' );
	}

	public function test_ability_list_todos() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Get the todo_get_items ability
		$ability = wp_get_ability( 'pos/todo-get-items' );
		$this->assertNotNull( $ability, 'pos/todo-get-items ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/todo-get-items should be a WP_Ability' );

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
		$result = $ability->execute( array() );
		$this->assertIsArray( $result, 'Ability should return an array' );
		$titles = array_column( $result, 'title' );
		$this->assertContains( 'TODO in now', $titles, 'TODO in now notebook should be visible by default' );
		$this->assertNotContains( 'TODO in inbox only', $titles, 'TODO only in inbox should NOT be visible by default' );
		
		// Test 2: Explicitly list from 'inbox' notebook
		$result_inbox = $ability->execute( array( 'notebook' => 'inbox' ) );
		$titles_inbox = array_column( $result_inbox, 'title' );
		$this->assertContains( 'TODO in inbox only', $titles_inbox, 'TODO in inbox should be visible when explicitly requesting inbox' );
		$this->assertContains( 'TODO in now', $titles_inbox, 'TODO in now should also be visible in inbox since it has both notebooks' );
		
		// Test 3: List from all notebooks
		$result_all = $ability->execute( array( 'notebook' => 'all' ) );
		$titles_all = array_column( $result_all, 'title' );
		$this->assertContains( 'TODO in now', $titles_all, 'TODO in now should be visible when listing all' );
		$this->assertContains( 'TODO in inbox only', $titles_all, 'TODO in inbox should be visible when listing all' );
	}

	public function test_ability_create_and_list_integration() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// This test demonstrates the full workflow:
		// 1. Create a TODO using ability (it goes to inbox)
		// 2. By default, listing shows 'now' notebook, so created TODO won't appear
		// 3. But when listing with 'all' or 'inbox', it will appear
		
		$create_ability = wp_get_ability( 'pos/todo-create-item' );
		$list_ability = wp_get_ability( 'pos/todo-get-items' );
		$this->assertNotNull( $create_ability, 'pos/todo-create-item ability should be registered' );
		$this->assertNotNull( $list_ability, 'pos/todo-get-items ability should be registered' );

		// Create a TODO using ability (without specifying notebook, so it goes to inbox)
		$created_todo = $create_ability->execute( array(
			'post_title'   => 'AI Created TODO',
			'post_excerpt' => 'Created via ability, should go to inbox',
		) );

		$this->assertIsArray( $created_todo );

		// List with default (now) - should NOT appear
		$todos_default = $list_ability->execute( array() );
		$titles_default = array_column( $todos_default, 'title' );
		$this->assertNotContains( 
			'AI Created TODO', 
			$titles_default, 
			'Default listing shows "now" notebook, so inbox-only TODO should not appear'
		);

		// List with 'all' - should appear
		$todos_all = $list_ability->execute( array( 'notebook' => 'all' ) );
		$titles_all = array_column( $todos_all, 'title' );
		$this->assertContains( 
			'AI Created TODO', 
			$titles_all, 
			'When listing all notebooks, ability-created TODO should be visible'
		);

		// List with 'inbox' - should appear
		$todos_inbox = $list_ability->execute( array( 'notebook' => 'inbox' ) );
		$titles_inbox = array_column( $todos_inbox, 'title' );
		$this->assertContains( 
			'AI Created TODO', 
			$titles_inbox, 
			'When listing inbox notebook, AI-created TODO should be visible'
		);
	}

	public function test_ability_create_with_now_and_list() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// This test shows that when creating with 'now' notebook, it appears in default listing
		$create_ability = wp_get_ability( 'pos/todo-create-item' );
		$list_ability = wp_get_ability( 'pos/todo-get-items' );
		$this->assertNotNull( $create_ability, 'pos/todo-create-item ability should be registered' );
		$this->assertNotNull( $list_ability, 'pos/todo-get-items ability should be registered' );

		// Create a TODO with 'now' notebook
		$created_todo = $create_ability->execute( array(
			'post_title'   => 'AI Created TODO for now',
			'post_excerpt' => 'Created via ability with now notebook',
			'notebook'     => 'now',
		) );

		$this->assertIsArray( $created_todo );

		// List with default (now) - should appear
		$todos_default = $list_ability->execute( array() );
		$titles_default = array_column( $todos_default, 'title' );
		$this->assertContains( 
			'AI Created TODO for now', 
			$titles_default, 
			'When TODO is created with "now" notebook, it should appear in default listing'
		);
	}
}
