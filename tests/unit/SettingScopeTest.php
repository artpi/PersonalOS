<?php

class SettingScopeTest extends WP_UnitTestCase {
	private ?POS_Module $notes_module = null;

	public function set_up(): void {
		parent::set_up();
		$this->notes_module = POS::get_module_by_id( 'notes' );
	}

	public function test_user_scoped_setting_stores_in_user_meta() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$this->notes_module->settings['test_user_setting'] = array(
			'type'  => 'text',
			'name'  => 'Test User Setting',
			'scope' => 'user',
		);

		$this->notes_module->update_setting( 'test_user_setting', 'user-value' );

		$this->assertSame(
			'user-value',
			get_user_meta( $user_id, 'pos_notes_test_user_setting', true )
		);
		$this->assertSame( 'user-value', $this->notes_module->get_setting( 'test_user_setting' ) );
	}

	public function test_global_scoped_setting_stores_in_options() {
		$this->notes_module->settings['test_global_setting'] = array(
			'type'    => 'text',
			'name'    => 'Test Global Setting',
			'scope'   => 'global',
			'default' => 'default-value',
		);

		$this->notes_module->update_setting( 'test_global_setting', 'global-value' );

		$this->assertSame(
			'global-value',
			get_option( $this->notes_module->get_setting_option_name( 'test_global_setting' ) )
		);
		$this->assertSame( 'global-value', $this->notes_module->get_setting( 'test_global_setting' ) );
	}
}

