<?php

class NotesRestPermissionsTest extends WP_UnitTestCase {

	private int $editor_one;
	private int $editor_two;
	private int $admin_id;
	private int $note_id;

	public function set_up(): void {
		parent::set_up();
		rest_get_server();

		$this->editor_one = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->editor_two = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->admin_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->note_id = wp_insert_post(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'post_author' => $this->editor_one,
				'post_title'  => 'Private Note',
				'post_content'=> 'Secret',
			)
		);
	}

	public function test_editor_can_create_note_via_rest() {
		wp_set_current_user( $this->editor_one );
		$request = new WP_REST_Request( 'POST', '/pos/v1/notes' );
		$request->set_body_params(
			array(
				'title'   => 'API Note',
				'content' => 'Body',
				'status'  => 'private',
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_subscriber_cannot_create_note_via_rest() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$request = new WP_REST_Request( 'POST', '/pos/v1/notes' );
		$request->set_body_params(
			array(
				'title'   => 'Blocked',
				'content' => 'Nope',
				'status'  => 'private',
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_rest_read_permissions_follow_capabilities() {
		wp_set_current_user( $this->editor_one );
		$request  = new WP_REST_Request( 'GET', '/pos/v1/notes/' . $this->note_id );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'Author should read own note' );

		wp_set_current_user( $this->editor_two );
		$request  = new WP_REST_Request( 'GET', '/pos/v1/notes/' . $this->note_id );
		$response = rest_do_request( $request );
		$this->assertEquals( 403, $response->get_status(), 'Other editors should not read private note' );

		wp_set_current_user( $this->admin_id );
		$request  = new WP_REST_Request( 'GET', '/pos/v1/notes/' . $this->note_id );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'Admins should read all notes' );
	}
}

