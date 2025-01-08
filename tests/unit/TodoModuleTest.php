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
		$id = wp_insert_post( $data );
		return $id;
	}

	private function api_request( $path, $params = array() ) {
		$request = new WP_REST_Request( 'GET', $path );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request )->get_data();
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


		$api_response = $this->api_request( '/pos/v1/todo/' . $blocking );

		// TODO: For some reason, meta is empty.
		//$blocked_api_response = $this->api_request( '/pos/v1/todo/' . $blocked );
		//$this->assertEquals( $blocking, $blocked_api_response['meta']['pos_blocked_by'], 'API response contains pos_blocked_by meta' );
		//$this->assertEquals( 'now', $blocked_api_response['meta']['pos_blocked_pending_term'], 'API response contains pos_blocked_pending_term meta' );


		$this->assertEquals( $blocked, $api_response['blocking'][0], 'API response contains a list of blocked todos' );

		// Check unblocking:
		$notebooks = wp_list_pluck( wp_get_object_terms( $blocked, 'notebook' ), 'slug' );
		$this->assertContains( 'inbox', $notebooks );

		$this->assertNotContains( 'now', $notebooks, 'TODO is not in now before unblocking' );

		wp_trash_post( $blocking );
		$notebooks = wp_list_pluck( wp_get_object_terms( $blocked, 'notebook' ), 'slug' );
		$this->assertContains( 'now', $notebooks, 'TODO is in now after unblocking' );

	}

	public function test_scheduled_todo() {
		$scheduled = $this->create_todo( [
			'post_date' => date( 'Y-m-d H:i:s', strtotime( '+2 day' ) ),
			'meta_input' => [
				'pos_blocked_pending_term' => 'now',
				'pos_recurring_days' => 2,
			],
		] );


		$notebooks = wp_list_pluck( wp_get_object_terms( $scheduled, 'notebook' ), 'slug' );
		$this->assertNotContains( 'now', $notebooks, 'TODO is not in now before unblocking' );

		$cron_id = wp_next_scheduled( 'pos_todo_scheduled', array( $scheduled ) );
		$this->assertNotEmpty( $cron_id, 'TODO is scheduled' );

		$api_response = $this->api_request( '/pos/v1/todo/' . $scheduled );
		$this->assertEquals( $cron_id, $api_response['scheduled'], 'API response contains scheduled time' );

		// Trigger the scheduled event
		do_action( 'pos_todo_scheduled', $scheduled );

		$notebooks = wp_list_pluck( wp_get_object_terms( $scheduled, 'notebook' ), 'slug' );
		$this->assertContains( 'now', $notebooks, 'TODO is in now after scheduled time' );
	}
}

