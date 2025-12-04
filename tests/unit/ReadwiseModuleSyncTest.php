<?php

class ReadwiseModuleSyncTest extends WP_UnitTestCase {

	public function test_sync_runs_per_user() {
		$notes_module = POS::get_module_by_id( 'notes' );

		$module = $this->getMockBuilder( Readwise::class )
			->setConstructorArgs( array( $notes_module ) )
			->onlyMethods(
				array(
					'register_sync',
					'register_block',
					'setup_default_notebook',
					'get_user_ids_with_setting',
					'get_setting',
					'sync_user',
				)
			)
			->getMock();

		$module->method( 'register_sync' );
		$module->method( 'register_block' );
		$module->method( 'setup_default_notebook' );

		$user_one = self::factory()->user->create( array( 'role' => 'editor' ) );
		$user_two = self::factory()->user->create( array( 'role' => 'editor' ) );

		$module->method( 'get_user_ids_with_setting' )
			->with( 'token' )
			->willReturn( array( $user_one, $user_two ) );

		$module->method( 'get_setting' )->willReturnMap(
			array(
				array( 'token', $user_one, 'token-one' ),
				array( 'token', $user_two, 'token-two' ),
				array( 'token', null, null ),
			)
		);

		$module->expects( $this->exactly( 2 ) )
			->method( 'sync_user' )
			->willReturnCallback(
				function( $token, $user_id ) use ( $user_one, $user_two ) {
					$expected_token = ( $user_id === $user_one ) ? 'token-one' : 'token-two';
					$this->assertSame( $expected_token, $token );
					$this->assertSame( $user_id, get_current_user_id(), 'run_for_user should switch context' );
				}
			);

		$module->sync();
	}

	public function test_sync_book_creates_private_note_for_current_user() {
		$notes_module = POS::get_module_by_id( 'notes' );

		$module = $this->getMockBuilder( Readwise::class )
			->setConstructorArgs( array( $notes_module ) )
			->onlyMethods( array( 'register_sync', 'register_block' ) )
			->getMock();
		$module->method( 'register_sync' );
		$module->method( 'register_block' );

		$module->setup_default_notebook();

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$book = (object) array(
			'title'       => 'Test Book',
			'user_book_id'=> 123,
			'category'    => 'books',
			'author'      => 'Tester',
			'source_url'  => 'https://example.com',
			'book_tags'   => array(),
			'summary'     => 'Summary',
			'highlights'  => array(
				(object) array(
					'readwise_url' => 'https://example.com/highlight',
					'text'         => 'Highlight text',
					'created_at'   => '2025-01-01T00:00:00Z',
				),
			),
		);

		$module->sync_book( $book );

		$posts = get_posts(
			array(
				'post_type'   => 'notes',
				'meta_key'    => 'readwise_id',
				'meta_value'  => 123,
				'post_status' => 'private',
			)
		);

		$this->assertNotEmpty( $posts );
		$post = $posts[0];
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( $user_id, (int) $post->post_author );
	}
}

