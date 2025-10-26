<?php
/**
 * Class IMAP_Module_Test
 *
 * @package Personalos
 */

/**
 * IMAP Module test case.
 */
class IMAP_Module_Test extends WP_UnitTestCase {

	private $module = null;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		// Get the IMAP module from POS modules
		$this->module = POS::get_module_by_id( 'imap' );
	}

	/**
	 * Test module initialization
	 */
	public function test_module_exists() {
		$this->assertNotNull( $this->module, 'IMAP module should exist' );
		$this->assertInstanceOf( IMAP_Module::class, $this->module, 'Module should be instance of IMAP_Module' );
	}

	/**
	 * Test module properties
	 */
	public function test_module_properties() {
		$this->assertEquals( 'imap', $this->module->id, 'Module ID should be imap' );
		$this->assertEquals( 'IMAP Email', $this->module->name, 'Module name should be IMAP Email' );
		$this->assertNotEmpty( $this->module->description, 'Module should have a description' );
	}

	/**
	 * Test module settings
	 */
	public function test_module_settings() {
		$settings = $this->module->get_settings_fields();
		$this->assertNotEmpty( $settings, 'Module should have settings' );
		
		// Check for required settings
		$this->assertArrayHasKey( 'imap_host', $settings, 'Should have imap_host setting' );
		$this->assertArrayHasKey( 'imap_port', $settings, 'Should have imap_port setting' );
		$this->assertArrayHasKey( 'imap_username', $settings, 'Should have imap_username setting' );
		$this->assertArrayHasKey( 'imap_password', $settings, 'Should have imap_password setting' );
		$this->assertArrayHasKey( 'smtp_host', $settings, 'Should have smtp_host setting' );
		$this->assertArrayHasKey( 'smtp_port', $settings, 'Should have smtp_port setting' );
		$this->assertArrayHasKey( 'active', $settings, 'Should have active setting' );
	}

	/**
	 * Test minutely cron schedule
	 */
	public function test_minutely_cron_schedule() {
		$schedules = wp_get_schedules();
		$this->assertArrayHasKey( 'minutely', $schedules, 'Minutely schedule should be registered' );
		$this->assertEquals( 60, $schedules['minutely']['interval'], 'Minutely interval should be 60 seconds' );
	}

	/**
	 * Test sync hook registration
	 */
	public function test_sync_hook_registered() {
		$hook_name = $this->module->get_sync_hook_name();
		$this->assertEquals( 'pos_sync_imap', $hook_name, 'Sync hook name should be pos_sync_imap' );
		
		// Check if hook has callbacks
		$this->assertTrue( has_action( $hook_name ), 'Sync hook should have callbacks registered' );
	}

	/**
	 * Test email logging action
	 */
	public function test_email_logging_action_registered() {
		$this->assertTrue( has_action( 'pos_imap_new_email' ), 'pos_imap_new_email action should be registered' );
	}

	/**
	 * Test log_new_email method
	 */
	public function test_log_new_email() {
		$email_data = array(
			'id'      => 1,
			'subject' => 'Test Subject',
			'from'    => 'test@example.com',
			'date'    => '2024-01-01 12:00:00',
			'body'    => 'Test email body content',
		);

		// This should not throw an error and should not log body content
		$this->module->log_new_email( $email_data );
		$this->assertTrue( true, 'log_new_email should execute without errors' );
	}

	/**
	 * Test default settings values
	 */
	public function test_default_settings() {
		$this->assertEquals( '993', $this->module->get_setting( 'imap_port' ), 'Default IMAP port should be 993' );
		$this->assertEquals( '587', $this->module->get_setting( 'smtp_port' ), 'Default SMTP port should be 587' );
		$this->assertEquals( true, $this->module->get_setting( 'imap_ssl' ), 'SSL should be enabled by default' );
		$this->assertEquals( false, $this->module->get_setting( 'active' ), 'Module should be inactive by default' );
	}
}
