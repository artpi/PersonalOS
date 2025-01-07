<?php

class TodoModuleTest extends WP_UnitTestCase {
	private $module = null;

	public function set_up() {
		parent::set_up();
		$this->module = \POS::get_module_by_id( 'todo' );
		wp_set_current_user( 1 );
		wp_insert_term( 'now', 'notebook' );
	}


	private function create_todo( $data = array() ) {
		$default_data = array(
			'post_type' => $this->module->id,
			'post_title' => 'Test Todo',
			'post_status' => 'private',
			'post_excerpt' => 'Test Todo Excerpt',
			'post_date' => date( 'Y-m-d H:i:s' ),
			'meta_input' => array(
				'url' => '',
				'pos_recurring_days' => 0,
				'pos_blocked_by' => 0,
				'pos_blocked_pending_term' => '',
			),
			'tax_input' => array(
				'notebook' => array( get_term_by( 'slug', 'inbox', 'notebook' )->term_id ),
			),
		);
		$data = wp_parse_args( $data, $default_data );
		return wp_insert_post( $data );
	}

	public function test_todo_creation() {
		$new_todo = $this->create_todo();
		$this->assertNotEmpty( $new_todo );
		$this->assertEquals( 'inbox', wp_get_object_terms( $new_todo, 'notebook' )[0]->slug );
		$this->assertEquals( '', get_post_meta( $new_todo, 'url', true ) );
		$this->assertEquals( 0, get_post_meta( $new_todo, 'pos_recurring_days', true ) );
		$this->assertEquals( 0, get_post_meta( $new_todo, 'pos_blocked_by', true ) );
		$this->assertEquals( '', get_post_meta( $new_todo, 'pos_blocked_pending_term', true ) );
	}

	public function test_blocked_by() {
		$blocking = $this->create_todo( [
			'post_title' => 'Test Todo Blocking',
			'tax_input' => [
				'notebook' => [ get_term_by( 'slug', 'now', 'notebook' )->term_id ],
			],
		] );

		$blocked = $this->create_todo( [
			'post_title' => 'Test Todo Blocked',
			'tax_input' => [
				'notebook' => [ get_term_by( 'slug', 'inbox', 'notebook' )->term_id ],
			],
			'meta_input' => [
				'pos_blocked_by' => $blocking,
				'pos_blocked_pending_term' => 'now',
			],
		] );

		$notebooks = wp_list_pluck( wp_get_object_terms( $blocked, 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks );

		$this->assertNotContains( 'now', $notebooks );

		wp_trash_post( $blocking );
		$notebooks = wp_list_pluck( wp_get_object_terms( $blocked, 'notebook' ), 'slug' );
		$this->assertContains( 'now', $notebooks );

	}
}

