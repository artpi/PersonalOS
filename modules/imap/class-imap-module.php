<?php
/**
 * IMAP Module for PersonalOS
 *
 * This module provides IMAP email checking and sending functionality.
 * It checks the inbox every minute via wp-cron and triggers actions for new emails.
 *
 * @package PersonalOS
 */

class IMAP_Module extends External_Service_Module {
	public $id          = 'imap';
	public $name        = 'IMAP Email';
	public $description = 'Connect to IMAP email inbox and send emails';

	public $settings = array(
		'imap_host'     => array(
			'type'    => 'text',
			'name'    => 'IMAP Server',
			'label'   => 'IMAP server hostname (e.g., imap.gmail.com)',
			'default' => '',
		),
		'imap_port'     => array(
			'type'    => 'text',
			'name'    => 'IMAP Port',
			'label'   => 'IMAP server port (e.g., 993 for SSL)',
			'default' => '993',
		),
		'imap_username' => array(
			'type'    => 'text',
			'name'    => 'IMAP Username',
			'label'   => 'Your email address or username',
			'default' => '',
		),
		'imap_password' => array(
			'type'    => 'text',
			'name'    => 'IMAP Password',
			'label'   => 'Your email password or app-specific password',
			'default' => '',
		),
		'imap_ssl'      => array(
			'type'    => 'bool',
			'name'    => 'Use SSL',
			'label'   => 'Connect using SSL/TLS',
			'default' => true,
		),
		'smtp_host'     => array(
			'type'    => 'text',
			'name'    => 'SMTP Server',
			'label'   => 'SMTP server hostname for sending emails (e.g., smtp.gmail.com)',
			'default' => '',
		),
		'smtp_port'     => array(
			'type'    => 'text',
			'name'    => 'SMTP Port',
			'label'   => 'SMTP server port (e.g., 587 for TLS, 465 for SSL)',
			'default' => '587',
		),
		'smtp_username' => array(
			'type'    => 'text',
			'name'    => 'SMTP Username',
			'label'   => 'SMTP username (usually same as IMAP username)',
			'default' => '',
		),
		'smtp_password' => array(
			'type'    => 'text',
			'name'    => 'SMTP Password',
			'label'   => 'SMTP password (usually same as IMAP password)',
			'default' => '',
		),
		'active'        => array(
			'type'    => 'bool',
			'name'    => 'IMAP Sync Active',
			'label'   => 'Enable automatic email checking',
			'default' => false,
		),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->register_sync( 'minutely' );

		// Register custom cron interval for checking every minute
		add_filter( 'cron_schedules', array( $this, 'add_minutely_cron_interval' ) );

		// Register action to log emails
		add_action( 'pos_imap_new_email', array( $this, 'log_new_email' ), 10, 1 );
	}

	/**
	 * Add minutely cron interval
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_minutely_cron_interval( $schedules ) {
		if ( ! isset( $schedules['minutely'] ) ) {
			$schedules['minutely'] = array(
				'interval' => 60,
				'display'  => __( 'Once Every Minute', 'personalos' ),
			);
		}
		return $schedules;
	}

	/**
	 * Connect to IMAP server
	 *
	 * @return resource|false IMAP connection resource or false on failure.
	 */
	private function connect_imap() {
		if ( ! function_exists( 'imap_open' ) ) {
			$this->log( 'IMAP extension is not available', E_USER_ERROR );
			return false;
		}

		$host     = $this->get_setting( 'imap_host' );
		$port     = $this->get_setting( 'imap_port' );
		$username = $this->get_setting( 'imap_username' );
		$password = $this->get_setting( 'imap_password' );
		$ssl      = $this->get_setting( 'imap_ssl' );

		if ( empty( $host ) || empty( $username ) || empty( $password ) ) {
			$this->log( 'IMAP credentials not configured', E_USER_WARNING );
			return false;
		}

		// Build IMAP connection string
		$ssl_flag    = $ssl ? '/ssl' : '';
		$mailbox     = '{' . $host . ':' . $port . '/imap' . $ssl_flag . '}INBOX';

		// Attempt to connect, suppressing warnings as we handle errors explicitly
		$imap = imap_open( $mailbox, $username, $password ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $imap ) {
			$error = imap_last_error();
			$this->log( 'Failed to connect to IMAP server: ' . $error, E_USER_ERROR );
			return false;
		}

		return $imap;
	}

	/**
	 * Sync - Check for new emails
	 */
	public function sync() {
		if ( ! $this->get_setting( 'active' ) ) {
			return;
		}

		$this->log( 'Starting IMAP sync' );

		$imap = $this->connect_imap();
		if ( ! $imap ) {
			return;
		}

		// Get all emails from inbox
		// Using 'ALL' for initial implementation - future: use UNSEEN or date-based search
		$emails = imap_search( $imap, 'ALL' );

		if ( ! $emails ) {
			$this->log( 'No emails found in inbox' );
			imap_close( $imap );
			return;
		}

		$this->log( 'Found ' . count( $emails ) . ' email(s) in inbox' );

		// Get last processed email ID
		$last_processed_id = get_option( $this->get_setting_option_name( 'last_email_id' ), 0 );

		// Process each email
		foreach ( $emails as $email_id ) {
			// Skip already processed emails
			if ( $email_id <= $last_processed_id ) {
				continue;
			}

			$this->process_email( $imap, $email_id );

			// Update last processed ID
			update_option( $this->get_setting_option_name( 'last_email_id' ), $email_id );
		}

		imap_close( $imap );
		$this->log( 'IMAP sync completed' );
	}

	/**
	 * Process a single email
	 *
	 * @param resource $imap IMAP connection.
	 * @param int      $email_id Email ID.
	 */
	private function process_email( $imap, $email_id ) {
		// Get email header
		$header = imap_headerinfo( $imap, $email_id );

		if ( ! $header ) {
			$this->log( 'Failed to get header for email ID: ' . $email_id, E_USER_WARNING );
			return;
		}

		// Get email structure
		$structure = imap_fetchstructure( $imap, $email_id );

		// Get email body
		$body = $this->get_email_body( $imap, $email_id, $structure );

		// Prepare email data
		$email_data = array(
			'id'      => $email_id,
			'subject' => isset( $header->subject ) ? sanitize_text_field( $this->decode_header( $header->subject ) ) : '(No Subject)',
			'from'    => isset( $header->from[0] ) ? sanitize_email( $header->from[0]->mailbox . '@' . $header->from[0]->host ) : 'unknown',
			'date'    => isset( $header->date ) ? sanitize_text_field( $header->date ) : '',
			'body'    => $body,
		);

		// Trigger action for new email
		do_action( 'pos_imap_new_email', $email_data );
	}

	/**
	 * Get email body
	 *
	 * @param resource $imap IMAP connection.
	 * @param int      $email_id Email ID.
	 * @param object   $structure Email structure.
	 * @return string Email body.
	 */
	private function get_email_body( $imap, $email_id, $structure ) {
		$body = '';

		// Check if email has parts (multipart)
		if ( isset( $structure->parts ) && count( $structure->parts ) ) {
			// Multipart email
			foreach ( $structure->parts as $part_num => $part ) {
				// Look for text/plain or text/html
				if ( $part->subtype === 'PLAIN' || $part->subtype === 'HTML' ) {
					$body = imap_fetchbody( $imap, $email_id, $part_num + 1 );

					// Decode based on encoding
					if ( isset( $part->encoding ) ) {
						$body = $this->decode_body( $body, $part->encoding );
					}

					// Prefer plain text, but take HTML if plain not available
					if ( $part->subtype === 'PLAIN' && ! empty( $body ) ) {
						break;
					}
				}
			}
		} else {
			// Simple email
			$body = imap_body( $imap, $email_id );

			if ( isset( $structure->encoding ) ) {
				$body = $this->decode_body( $body, $structure->encoding );
			}
		}

		return $body;
	}

	/**
	 * Decode email body based on encoding
	 *
	 * @param string $body Email body.
	 * @param int    $encoding Encoding type.
	 * @return string Decoded body.
	 */
	private function decode_body( $body, $encoding ) {
		switch ( $encoding ) {
			case 3: // BASE64
				return base64_decode( $body, true );
			case 4: // QUOTED-PRINTABLE
				return quoted_printable_decode( $body );
			default:
				return $body;
		}
	}

	/**
	 * Decode email header (handles MIME encoding)
	 *
	 * @param string $header Header string.
	 * @return string Decoded header.
	 */
	private function decode_header( $header ) {
		$decoded = imap_mime_header_decode( $header );
		$result  = '';

		foreach ( $decoded as $part ) {
			$result .= $part->text;
		}

		return $result;
	}

	/**
	 * Log new email (hooked to pos_imap_new_email action)
	 * Note: Logs truncated body content. Avoid logging sensitive information.
	 *
	 * @param array $email_data Email data.
	 */
	public function log_new_email( $email_data ) {
		// Only log metadata for security - body may contain sensitive info
		$this->log(
			sprintf(
				'New Email - Subject: %s, From: %s',
				$email_data['subject'],
				$email_data['from']
			)
		);
	}

	/**
	 * Send email using PHPMailer
	 *
	 * @param string $to Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $body Email body.
	 * @param array  $headers Optional headers.
	 * @return bool True on success, false on failure.
	 */
	public function send_email( $to, $subject, $body, $headers = array() ) {
		$smtp_host     = $this->get_setting( 'smtp_host' );
		$smtp_port     = $this->get_setting( 'smtp_port' );
		$smtp_username = $this->get_setting( 'smtp_username' );
		$smtp_password = $this->get_setting( 'smtp_password' );

		if ( empty( $smtp_host ) || empty( $smtp_username ) || empty( $smtp_password ) ) {
			$this->log( 'SMTP credentials not configured', E_USER_WARNING );
			return false;
		}

		// Use wp_mail with custom PHPMailer configuration
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

		$result = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

		if ( $result ) {
			$this->log( 'Email sent successfully to: ' . $to );
		} else {
			$this->log( 'Failed to send email to: ' . $to, E_USER_ERROR );
		}

		return $result;
	}

	/**
	 * Configure PHPMailer for SMTP
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host       = $this->get_setting( 'smtp_host' );
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Port       = $this->get_setting( 'smtp_port' );
		$phpmailer->Username   = $this->get_setting( 'smtp_username' );
		$phpmailer->Password   = $this->get_setting( 'smtp_password' );
		$phpmailer->SMTPSecure = ( (int) $this->get_setting( 'smtp_port' ) === 465 ) ? 'ssl' : 'tls';
		$phpmailer->From       = $this->get_setting( 'smtp_username' );
		$phpmailer->FromName   = 'PersonalOS';
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
