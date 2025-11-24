<?php

class AbilitiesAPIIntegrationTest extends WP_UnitTestCase {
	
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 1 );
	}

	public function test_abilities_api_available() {
		$this->assertTrue( class_exists( 'WP_Ability' ), 'Abilities API should be available' );
		$this->assertTrue( function_exists( 'wp_register_ability' ), 'wp_register_ability function should exist' );
		$this->assertTrue( function_exists( 'wp_get_ability' ), 'wp_get_ability function should exist' );
	}

	public function test_pos_abilities_class_exists() {
		$this->assertTrue( class_exists( 'POS_Abilities' ), 'POS_Abilities class should exist' );
	}

	public function test_todo_get_items_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/todo-get-items' );
		$this->assertNotNull( $ability, 'pos/todo-get-items ability should be registered' );
		$this->assertEquals( 'pos/todo-get-items', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
		
		// Verify input schema
		$input_schema = $ability->get_input_schema();
		$this->assertArrayHasKey( 'properties', $input_schema );
		$this->assertArrayHasKey( 'notebook', $input_schema['properties'] );
	}

	public function test_todo_create_item_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/todo-create-item' );
		$this->assertNotNull( $ability, 'pos/todo-create-item ability should be registered' );
		$this->assertEquals( 'pos/todo-create-item', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
		
		// Verify it's marked as destructive
		$annotations = $ability->get_meta( 'annotations' );
		$this->assertArrayHasKey( 'destructive', $annotations );
		$this->assertTrue( $annotations['destructive'] );
	}

	public function test_list_posts_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/list-posts' );
		$this->assertNotNull( $ability, 'pos/list-posts ability should be registered' );
		$this->assertEquals( 'pos/list-posts', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
		
		// Verify it's marked as readonly
		$annotations = $ability->get_meta( 'annotations' );
		$this->assertArrayHasKey( 'readonly', $annotations );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	public function test_ai_memory_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/ai-memory' );
		$this->assertNotNull( $ability, 'pos/ai-memory ability should be registered' );
		$this->assertEquals( 'pos/ai-memory', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
	}

	public function test_get_notebooks_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/get-notebooks' );
		$this->assertNotNull( $ability, 'pos/get-notebooks ability should be registered' );
		$this->assertEquals( 'pos/get-notebooks', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
	}

	public function test_perplexity_search_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/perplexity-search' );
		if ( ! POS::get_module_by_id( 'perplexity' ) ) {
			$this->markTestSkipped( 'Perplexity module not available' );
		}
		$this->assertNotNull( $ability, 'pos/perplexity-search ability should be registered' );
		$this->assertEquals( 'pos/perplexity-search', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
	}

	public function test_evernote_search_notes_ability_registered() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$ability = wp_get_ability( 'pos/evernote-search-notes' );
		if ( ! POS::get_module_by_id( 'evernote' ) ) {
			$this->markTestSkipped( 'Evernote module not available' );
		}
		$this->assertNotNull( $ability, 'pos/evernote-search-notes ability should be registered' );
		$this->assertEquals( 'pos/evernote-search-notes', $ability->get_name() );
		$this->assertEquals( 'personalos', $ability->get_meta( 'category' ) );
	}

	public function test_all_abilities_have_personalos_category() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		$abilities = wp_get_abilities();
		$pos_abilities = array_filter(
			$abilities,
			function( $ability ) {
				return strpos( $ability->get_name(), 'pos/' ) === 0;
			}
		);

		$this->assertGreaterThan( 0, count( $pos_abilities ), 'Should have at least one pos/ ability registered' );

		foreach ( $pos_abilities as $ability ) {
			$this->assertEquals( 
				'personalos', 
				$ability->get_meta( 'category' ),
				'Ability ' . $ability->get_name() . ' should have personalos category' 
			);
		}
	}

	public function test_ability_direct_execution() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available' );
		}

		// Create inbox notebook if it doesn't exist
		if ( ! term_exists( 'inbox', 'notebook' ) ) {
			wp_insert_term( 'inbox', 'notebook' );
		}

		// Get the ability directly
		$ability = wp_get_ability( 'pos/todo-get-items' );
		$this->assertNotNull( $ability, 'Should find pos/todo-get-items ability' );

		// Execute the ability directly
		$result = $ability->execute( array( 'notebook' => 'inbox' ) );
		$this->assertIsArray( $result, 'Should return array result' );
	}
}
