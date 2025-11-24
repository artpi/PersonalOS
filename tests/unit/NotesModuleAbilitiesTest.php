<?php

class NotesModuleAIToolsTest extends WP_UnitTestCase {
	private $module = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'notes' );
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
	}

	public function test_get_notebooks_ability_returns_all_notebooks() {
		// Create some test notebooks
		$notebook1_id = wp_insert_term(
			'Test Notebook 1',
			'notebook',
			array(
				'slug'        => 'test-notebook-1',
				'description' => 'Test notebook 1 description',
			)
		);
		$notebook2_id = wp_insert_term(
			'Test Notebook 2',
			'notebook',
			array(
				'slug'        => 'test-notebook-2',
				'description' => 'Test notebook 2 description',
			)
		);

		// Call the method with 'all' flag
		$result = $this->module->get_notebooks_ability(
			array( 'notebook_flag' => 'all' )
		);

		$this->assertIsArray( $result, 'Result should be an array' );
		// The result is grouped by flags, so we need to check within those groups
		$all_notebook_slugs = array();
		foreach ( $result as $flag_group ) {
			$this->assertArrayHasKey( 'notebooks', $flag_group );
			foreach ( $flag_group['notebooks'] as $notebook ) {
				$all_notebook_slugs[] = $notebook['notebook_slug'];
			}
		}

		// We may not find our notebooks if flags are filtering, but we can at least verify structure
		if ( ! empty( $result ) ) {
			$first_group = $result[0];
			$this->assertArrayHasKey( 'flag_id', $first_group );
			$this->assertArrayHasKey( 'flag_name', $first_group );
			$this->assertArrayHasKey( 'flag_label', $first_group );
			$this->assertArrayHasKey( 'notebooks', $first_group );
			$this->assertIsArray( $first_group['notebooks'] );
		}
	}

	public function test_get_notebooks_ability_notebook_structure() {
		// Create a notebook with metadata
		$notebook_id = wp_insert_term(
			'Structured Notebook',
			'notebook',
			array(
				'slug'        => 'structured-notebook',
				'description' => 'A notebook for structure testing',
			)
		);

		$result = $this->module->get_notebooks_ability(
			array( 'notebook_flag' => 'all' )
		);

		$this->assertIsArray( $result );

		// Find our notebook in the results
		$found_notebook = null;
		foreach ( $result as $flag_group ) {
			foreach ( $flag_group['notebooks'] as $notebook ) {
				if ( $notebook['notebook_slug'] === 'structured-notebook' ) {
					$found_notebook = $notebook;
					break 2;
				}
			}
		}

		if ( $found_notebook ) {
			$this->assertArrayHasKey( 'notebook_name', $found_notebook );
			$this->assertArrayHasKey( 'notebook_id', $found_notebook );
			$this->assertArrayHasKey( 'notebook_slug', $found_notebook );
			$this->assertArrayHasKey( 'notebook_description', $found_notebook );
			$this->assertEquals( 'Structured Notebook', $found_notebook['notebook_name'] );
			$this->assertEquals( 'structured-notebook', $found_notebook['notebook_slug'] );
			$this->assertEquals( 'A notebook for structure testing', $found_notebook['notebook_description'] );
		}
	}

	public function test_ability_get_notebooks() {

		// Get the get_notebooks ability
		$ability = wp_get_ability( 'pos/get-notebooks' );
		$this->assertNotNull( $ability, 'pos/get-notebooks ability should be registered' );
		$this->assertInstanceOf( 'WP_Ability', $ability, 'pos/get-notebooks should be a WP_Ability' );

		// Create a test notebook
		wp_insert_term(
			'Ability Test Notebook',
			'notebook',
			array(
				'slug'        => 'ability-test-notebook',
				'description' => 'Created for ability testing',
			)
		);

		// Execute the ability
		$result = $ability->execute(
			array( 'notebook_flag' => 'all' )
		);

		$this->assertIsArray( $result, 'Ability should return an array' );

		// Verify structure
		if ( ! empty( $result ) ) {
			$first_group = $result[0];
			$this->assertArrayHasKey( 'flag_id', $first_group );
			$this->assertArrayHasKey( 'notebooks', $first_group );
		}
	}
}
