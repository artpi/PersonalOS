<?php

/**
 * Helper module used to test token-to-user mapping.
 */
class POS_Token_Helper_Test_Module extends POS_Module {
	public $id   = 'token-helper';
	public $name = 'Token Helper';

	public function __construct() {
		// Skip parent constructor to avoid automatic registration.
	}
}

class ModuleTokenMappingTest extends WP_UnitTestCase {

	public function test_find_user_for_setting_token_matches_editor() {
		$module = new POS_Token_Helper_Test_Module();
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'token-editor@example.com',
			)
		);
		update_user_meta( $user_id, 'pos_token-helper_secret', 'shared-secret' );

		$user = $module->find_user_for_setting_token( 'secret', 'shared-secret' );

		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( $user_id, $user->ID );
	}

	public function test_find_user_for_setting_token_rejects_missing_or_invalid() {
		$module = new POS_Token_Helper_Test_Module();

		$this->assertNull( $module->find_user_for_setting_token( 'secret', 'aa' ), 'Tokens shorter than three chars should be rejected.' );

		$user_id = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'token-subscriber@example.com',
			)
		);
		update_user_meta( $user_id, 'pos_token-helper_secret', 'subscriber-secret' );

		$this->assertNull( $module->find_user_for_setting_token( 'secret', 'subscriber-secret' ), 'Subscriber lacks use_personalos capability.' );
		$this->assertNull( $module->find_user_for_setting_token( 'secret', 'non-existent' ), 'Unknown tokens return null.' );
	}
}

