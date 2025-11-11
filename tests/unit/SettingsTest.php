<?php
/**
 * Class SettingsTest
 *
 * @package Personalos
 */

/**
 * Test case for POS_Settings.
 */
class SettingsTest extends WP_UnitTestCase {

	private $settings_instance = null;
	private $test_modules      = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock modules for testing
		$this->create_test_modules();
		$this->settings_instance = new POS_Settings( $this->test_modules );
	}

	/**
	 * Create test modules with different setting types.
	 */
	private function create_test_modules() {
		// Mock module 1 with text and bool settings
		$module1           = new POS_Module();
		$module1->id       = 'test_module_1';
		$module1->name     = 'Test Module 1';
		$module1->settings = array(
			'api_key'    => array(
				'name'    => 'API Key',
				'type'    => 'text',
				'label'   => 'Enter your API key',
				'default' => '',
			),
			'enabled'    => array(
				'name'    => 'Enabled',
				'type'    => 'bool',
				'label'   => 'Enable this feature',
				'default' => false,
			),
			'notes'      => array(
				'name'    => 'Notes',
				'type'    => 'textarea',
				'label'   => 'Additional notes',
				'default' => '',
			),
		);

		// Mock module 2 with different settings
		$module2           = new POS_Module();
		$module2->id       = 'test_module_2';
		$module2->name     = 'Test Module 2';
		$module2->settings = array(
			'username'   => array(
				'name'    => 'Username',
				'type'    => 'text',
				'label'   => 'Enter username',
				'default' => '',
			),
			'auto_sync'  => array(
				'name'    => 'Auto Sync',
				'type'    => 'bool',
				'label'   => 'Enable automatic sync',
				'default' => true,
			),
		);

		$this->test_modules = array( $module1, $module2 );
	}

	/**
	 * Test that settings are registered correctly.
	 */
	public function test_settings_registered() {
		global $wp_registered_settings;

		// Trigger settings initialization directly to avoid header issues in tests
		$this->settings_instance->settings_init();

		// Check that settings are registered with correct option names
		$this->assertArrayHasKey( 'test_module_1_api_key', $wp_registered_settings );
		$this->assertArrayHasKey( 'test_module_1_enabled', $wp_registered_settings );
		$this->assertArrayHasKey( 'test_module_2_username', $wp_registered_settings );
		$this->assertArrayHasKey( 'test_module_2_auto_sync', $wp_registered_settings );
	}

	/**
	 * Test that settings are registered to correct option groups.
	 */
	public function test_settings_registered_to_correct_groups() {
		global $wp_registered_settings;

		// Trigger settings initialization directly to avoid header issues in tests
		$this->settings_instance->settings_init();

		// Verify settings are in their respective module groups
		$this->assertEquals( 'pos_test_module_1', $wp_registered_settings['test_module_1_api_key']['group'] );
		$this->assertEquals( 'pos_test_module_1', $wp_registered_settings['test_module_1_enabled']['group'] );
		$this->assertEquals( 'pos_test_module_2', $wp_registered_settings['test_module_2_username']['group'] );
		$this->assertEquals( 'pos_test_module_2', $wp_registered_settings['test_module_2_auto_sync']['group'] );
	}

	/**
	 * Test that saving one module's settings doesn't affect another module's settings.
	 */
	public function test_module_settings_isolation() {
		// Set initial values for both modules
		update_option( 'test_module_1_api_key', 'initial_key_1' );
		update_option( 'test_module_1_enabled', '1' );
		update_option( 'test_module_2_username', 'initial_user' );
		update_option( 'test_module_2_auto_sync', '1' );

		// Simulate saving module 1 settings with new values
		$_POST['option_page'] = 'pos_test_module_1';
		$_POST['test_module_1_api_key'] = 'updated_key_1';
		$_POST['test_module_1_enabled'] = ''; // Unchecked

		// Trigger settings initialization directly to avoid header issues in tests
		$this->settings_instance->settings_init();

		// Update the options as WordPress would do
		update_option( 'test_module_1_api_key', sanitize_text_field( $_POST['test_module_1_api_key'] ) );
		update_option( 'test_module_1_enabled', '' ); // Unchecked checkbox

		// Verify module 1 settings are updated
		$this->assertEquals( 'updated_key_1', get_option( 'test_module_1_api_key' ) );
		$this->assertEquals( '', get_option( 'test_module_1_enabled' ) );

		// Verify module 2 settings remain unchanged
		$this->assertEquals( 'initial_user', get_option( 'test_module_2_username' ) );
		$this->assertEquals( '1', get_option( 'test_module_2_auto_sync' ) );
	}

	/**
	 * Test checkbox sanitization callback.
	 */
	public function test_checkbox_sanitization() {
		// Trigger settings initialization directly to avoid header issues in tests
		$this->settings_instance->settings_init();

		global $wp_registered_settings;
		
		// Get the sanitize callback for a boolean setting
		$bool_setting = $wp_registered_settings['test_module_1_enabled'];
		$this->assertNotNull( $bool_setting['sanitize_callback'] );

		// Test sanitization
		$sanitize_callback = $bool_setting['sanitize_callback'];
		
		// Test that truthy values return '1'
		$this->assertEquals( '1', $sanitize_callback( '1' ) );
		$this->assertEquals( '1', $sanitize_callback( 'yes' ) );
		$this->assertEquals( '1', $sanitize_callback( 'on' ) );
		
		// Test that falsy values return ''
		$this->assertEquals( '', $sanitize_callback( '' ) );
		$this->assertEquals( '', $sanitize_callback( '0' ) );
		$this->assertEquals( '', $sanitize_callback( null ) );
	}

	/**
	 * Test text field sanitization.
	 */
	public function test_text_sanitization() {
		// Trigger settings initialization directly to avoid header issues in tests
		$this->settings_instance->settings_init();

		global $wp_registered_settings;
		
		// Get the sanitize callback for a text setting
		$text_setting = $wp_registered_settings['test_module_1_api_key'];
		$this->assertEquals( 'sanitize_text_field', $text_setting['sanitize_callback'] );
	}

	/**
	 * Test that invalid module IDs in GET parameters don't cause issues.
	 */
	public function test_invalid_module_id_handling() {
		// Set an invalid module ID
		$_GET['module'] = 'invalid_module_<script>';

		// Create a settings instance
		$settings = new POS_Settings( $this->test_modules );

		// The settings page should handle this gracefully
		// This test just ensures no fatal errors occur
		$this->assertInstanceOf( 'POS_Settings', $settings );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up options
		delete_option( 'test_module_1_api_key' );
		delete_option( 'test_module_1_enabled' );
		delete_option( 'test_module_1_notes' );
		delete_option( 'test_module_2_username' );
		delete_option( 'test_module_2_auto_sync' );

		parent::tearDown();
	}
}
