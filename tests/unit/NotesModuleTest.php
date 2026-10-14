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

	public function test_notebook_widget_posts_respect_current_user() {
		$term = wp_insert_term( 'Star Notebook', 'notebook', array( 'slug' => 'star-notebook' ) );
		update_term_meta( $term['term_id'], 'flag', 'star' );

		$editor_one = self::factory()->user->create( array( 'role' => 'editor' ) );
		$editor_two = self::factory()->user->create( array( 'role' => 'editor' ) );

		$note_one = wp_insert_post(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'post_author' => $editor_one,
				'post_title'  => 'User One Note',
			)
		);
		wp_set_post_terms( $note_one, array( $term['term_id'] ), 'notebook' );

		$note_two = wp_insert_post(
			array(
				'post_type'   => 'notes',
				'post_status' => 'private',
				'post_author' => $editor_two,
				'post_title'  => 'User Two Note',
			)
		);
		wp_set_post_terms( $note_two, array( $term['term_id'] ), 'notebook' );

		$method = new ReflectionMethod( Notes_Module::class, 'get_notebook_widget_posts' );
		$method->setAccessible( true );

		wp_set_current_user( $editor_one );
		$posts = $method->invoke( $this->module, 'notes', 'star-notebook' );
		$this->assertCount( 1, $posts, 'Non-admin should only see their own notes' );
		$this->assertSame( $editor_one, (int) $posts[0]->post_author );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$posts = $method->invoke( $this->module, 'notes', 'star-notebook' );
		$this->assertCount( 2, $posts, 'Admins should see all notes' );
	}
}

