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

	/**
	 * Ensure Gmail X-Gm-Authentication-Results header is trusted.
	 */
	public function test_gmail_authentication_results_trusted() {
		$headers  = "Return-Path: <artur.piszek@gmail.com>\r\n";
		$headers .= 'X-Gm-Authentication-Results: mx.google.com; dkim=pass header.i=@gmail.com; spf=pass smtp.mailfrom=gmail.com; dmarc=pass header.from=gmail.com';

		$reflection = new ReflectionClass( IMAP_Module::class );
		$method     = $reflection->getMethod( 'evaluate_sender_trust' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->module, $headers, 'gmail.com' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'is_trusted', $result );
		$this->assertTrue( $result['is_trusted'], 'Gmail authentication header should result in trusted sender.' );
		$this->assertSame( 'mx.google.com', $result['authserv'] );
		$this->assertSame( 'pass', $result['dmarc'] );
		$this->assertStringContainsString( 'dmarc=pass', $result['summary'] );
	}

	/**
	 * Ensure Authentication-Results from the configured IMAP host is trusted.
	 */
	public function test_authentication_results_from_imap_host_trusted() {
		update_option( 'imap_imap_host', 's8.cyber-folks.pl' );

		$headers  = "Return-Path: <artur.piszek@gmail.com>\r\n";
		$headers .= 'Authentication-Results: s8.cyber-folks.pl; dmarc=pass header.from=gmail.com; dkim=pass header.d=gmail.com; spf=pass smtp.mailfrom=gmail.com';

		$reflection = new ReflectionClass( IMAP_Module::class );
		$method     = $reflection->getMethod( 'evaluate_sender_trust' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->module, $headers, 'gmail.com' );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['is_trusted'], 'Authentication-Results from configured IMAP host should be trusted.' );
		$this->assertSame( 's8.cyber-folks.pl', $result['authserv'] );
		$this->assertSame( 'pass', $result['dmarc'] );
		$this->assertStringContainsString( 'dmarc=pass', $result['summary'] );

		delete_option( 'imap_imap_host' );
	}

	public function test_match_recipient_user_ids_returns_unique_ids() {
		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'recipient@example.com',
			)
		);

		$addresses  = array( 'recipient@example.com', 'other@example.com', 'recipient@example.com' );
		$reflection = new ReflectionMethod( IMAP_Module::class, 'match_recipient_user_ids' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->module, $addresses, array() );

		$this->assertSame( array( $user_id ), $result );
	}

	public function test_extract_recipient_addresses_from_headers() {
		$header        = new stdClass();
		$header->to    = array(
			(object) array(
				'mailbox' => 'alice',
				'host'    => 'example.com',
			),
		);
		$header->cc    = array(
			(object) array(
				'mailbox' => 'bob',
				'host'    => 'example.com',
			),
		);
		$delivered_raw = "Delivered-To: carol@example.com\r\n";

		$reflection = new ReflectionMethod( IMAP_Module::class, 'extract_recipient_addresses' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->module, $header, $delivered_raw );
		$this->assertEquals( array( 'alice@example.com', 'bob@example.com' ), $result );

		$header->to = null;
		$header->cc = null;
		$result     = $reflection->invoke( $this->module, $header, $delivered_raw );
		$this->assertEquals( array( 'carol@example.com' ), $result );
	}

	public function test_dispatch_email_action_passes_user_ids() {
		$user_one = self::factory()->user->create();
		$user_two = self::factory()->user->create();

		$email_data = array(
			'id'               => 1,
			'subject'          => 'Subject',
			'from'             => 'sender@example.com',
			'body'             => 'Body',
			'matched_user_ids' => array( $user_one, $user_two ),
		);

		wp_set_current_user( 0 );
		$calls = array();
		$callback = function( $passed_email_data, $module, $user_id ) use ( &$calls ) {
			$calls[] = array(
				'user_id'          => $user_id,
				'matched_user_id'  => isset( $passed_email_data['matched_user_id'] ) ? $passed_email_data['matched_user_id'] : null,
				'current_user_id'  => get_current_user_id(),
			);
		};

		add_action( 'pos_imap_new_email', $callback, 5, 3 );

		$reflection = new ReflectionMethod( IMAP_Module::class, 'dispatch_email_action' );
		$reflection->setAccessible( true );

		$reflection->invoke( $this->module, 'pos_imap_new_email', $email_data, array( $user_one, $user_two ) );

		remove_action( 'pos_imap_new_email', $callback, 5 );

		$this->assertCount( 2, $calls );
		$this->assertSame( $user_one, $calls[0]['user_id'] );
		$this->assertSame( $user_one, $calls[0]['matched_user_id'] );
		$this->assertSame( $user_one, $calls[0]['current_user_id'] );
		$this->assertSame( $user_two, $calls[1]['user_id'] );
		$this->assertSame( $user_two, $calls[1]['matched_user_id'] );
		$this->assertSame( $user_two, $calls[1]['current_user_id'] );
		$this->assertSame( 0, get_current_user_id(), 'Current user should be restored after dispatch' );

		$calls = array();
		wp_set_current_user( 0 );
		$callback = function( $passed_email_data, $module, $user_id ) use ( &$calls ) {
			$calls[] = $user_id;
		};
		add_action( 'pos_imap_new_email', $callback, 5, 3 );
		$reflection->invoke( $this->module, 'pos_imap_new_email', $email_data, array() );
		remove_action( 'pos_imap_new_email', $callback, 5 );

		$this->assertEquals( array( 0 ), $calls );
		$this->assertSame( 0, get_current_user_id(), 'Current user should remain unchanged when no match' );
	}
}
