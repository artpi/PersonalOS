<?php

class EvernoteModuleSyncTest extends WP_UnitTestCase {

	public function test_sync_runs_per_user() {
		$notes_module = POS::get_module_by_id( 'notes' );

		$module = $this->getMockBuilder( Evernote_Module::class )
			->setConstructorArgs( array( $notes_module ) )
			->onlyMethods(
				array(
					'register_sync',
					'register_meta',
					'connect',
					'get_user_ids_with_setting',
					'get_setting',
					'sync_user',
				)
			)
			->getMock();

		$module->method( 'register_sync' );
		$module->method( 'register_meta' );
		$module->method( 'connect' )->willReturn( true );

		$user_one = self::factory()->user->create( array( 'role' => 'editor' ) );
		$user_two = self::factory()->user->create( array( 'role' => 'editor' ) );

		$module->method( 'get_user_ids_with_setting' )
			->with( 'token' )
			->willReturn( array( $user_one, $user_two ) );

		$module->method( 'get_setting' )->willReturnMap(
			array(
				array( 'token', $user_one, 'token-one' ),
				array( 'active', $user_one, true ),
				array( 'synced_notebooks', $user_one, array( 'nb-one' ) ),
				array( 'token', $user_two, 'token-two' ),
				array( 'active', $user_two, true ),
				array( 'synced_notebooks', $user_two, array( 'nb-two' ) ),
				array( 'token', null, null ),
				array( 'active', null, false ),
				array( 'synced_notebooks', null, array() ),
			)
		);

		$module_ref = $module;
		$seen       = array();

		$module->expects( $this->exactly( 2 ) )
			->method( 'sync_user' )
			->willReturnCallback(
				function( $user_id ) use ( &$seen, $module_ref, $user_one, $user_two ) {
					$seen[]   = $user_id;
					$expected = ( $user_id === $user_one ) ? array( 'nb-one' ) : array( 'nb-two' );
					$this->assertSame( $expected, $module_ref->synced_notebooks );
					$this->assertSame( $user_id, get_current_user_id(), 'run_for_user should switch current user' );
				}
			);

		$module->sync();

		$this->assertSame( array( $user_one, $user_two ), $seen );
	}
}

