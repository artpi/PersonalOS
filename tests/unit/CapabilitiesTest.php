<?php

/**
 * Tests for PersonalOS custom capabilities.
 */
class CapabilitiesTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// Reset capabilities to ensure test determinism.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->remove_cap( 'use_personalos' );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( 'use_personalos' );
			$admin->remove_cap( 'admin_personalos' );
		}
	}

	public function test_editor_gets_use_capability() {
		POS::ensure_capabilities();
		$editor = get_role( 'editor' );

		$this->assertNotEmpty( $editor );
		$this->assertTrue( $editor->has_cap( 'use_personalos' ) );
		$this->assertFalse( $editor->has_cap( 'admin_personalos' ) );
	}

	public function test_admin_gets_admin_capability() {
		POS::ensure_capabilities();
		$admin = get_role( 'administrator' );

		$this->assertNotEmpty( $admin );
		$this->assertTrue( $admin->has_cap( 'use_personalos' ) );
		$this->assertTrue( $admin->has_cap( 'admin_personalos' ) );
	}
}

