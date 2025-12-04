<?php

class NotesModuleTest extends WP_UnitTestCase {
	private ?Notes_Module $module = null;

	public function set_up(): void {
		parent::set_up();
		$this->module = POS::get_module_by_id( 'notes' );
		wp_set_current_user( 1 );
	}

	public function test_create_defaults_to_private_status() {
		$post_id = $this->module->create( 'Test Note', 'Test content' );

		$this->assertNotEmpty( $post_id );
		$this->assertSame( 'private', get_post_status( $post_id ) );
	}

	public function test_autopublish_converts_draft_to_private() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'notes',
				'post_status' => 'draft',
				'post_title'  => 'Draft note',
			)
		);

		$post = get_post( $post_id );
		$this->module->autopublish_drafts( $post_id, $post, true );

		$this->assertSame( 'private', get_post_status( $post_id ) );
	}
}

