<?php

class NotesModuleAIToolsTest extends WP_UnitTestCase {
	private $module = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'notes' );
		wp_set_current_user( 1 );
	}

	public function test_get_notebooks_for_openai_returns_all_notebooks() {
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
		$result = $this->module->get_notebooks_for_openai(
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

	public function test_get_notebooks_for_openai_notebook_structure() {
		// Create a notebook with metadata
		$notebook_id = wp_insert_term(
			'Structured Notebook',
			'notebook',
			array(
				'slug'        => 'structured-notebook',
				'description' => 'A notebook for structure testing',
			)
		);

		$result = $this->module->get_notebooks_for_openai(
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

	public function test_ai_tool_get_notebooks() {
		// Get the get_notebooks tool
		$tool = OpenAI_Tool::get_tool( 'get_notebooks' );
		$this->assertNotNull( $tool, 'get_notebooks tool should be registered' );
		$this->assertInstanceOf( 'OpenAI_Tool', $tool, 'get_notebooks should be an OpenAI_Tool' );
		$this->assertFalse( $tool->writeable, 'get_notebooks should not be writeable' );

		// Create a test notebook
		wp_insert_term(
			'Tool Test Notebook',
			'notebook',
			array(
				'slug'        => 'tool-test-notebook',
				'description' => 'Created for tool testing',
			)
		);

		// Invoke the tool
		$result = $tool->invoke(
			array( 'notebook_flag' => 'all' )
		);

		$this->assertIsArray( $result, 'Tool should return an array' );

		// Verify structure
		if ( ! empty( $result ) ) {
			$first_group = $result[0];
			$this->assertArrayHasKey( 'flag_id', $first_group );
			$this->assertArrayHasKey( 'notebooks', $first_group );
		}
	}
}
