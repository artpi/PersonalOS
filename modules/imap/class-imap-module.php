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
	 * Cached From address used during a mail send.
	 *
	 * @var string
	 */
	protected $current_from_address = '';

	/**
	 * Cached From name used during a mail send.
	 *
	 * @var string
	 */
	protected $current_from_name = 'PersonalOS';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Register custom cron interval for checking every minute (must be before register_sync)
		add_filter( 'cron_schedules', array( $this, 'add_minutely_cron_interval' ) );

		$this->register_sync( 'minutely' );

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
		$emails = imap_search( $imap, 'UNSEEN' );

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

		$from_name = '';
		if ( isset( $header->from[0]->personal ) ) {
			$from_name = sanitize_text_field( $this->decode_header( $header->from[0]->personal ) );
		}

		$reply_to = array();
		if ( ! empty( $header->reply_to ) && is_array( $header->reply_to ) ) {
			foreach ( $header->reply_to as $reply_to_entry ) {
				if ( empty( $reply_to_entry->mailbox ) || empty( $reply_to_entry->host ) ) {
					continue;
				}
				$address = sanitize_email( $reply_to_entry->mailbox . '@' . $reply_to_entry->host );
				if ( ! empty( $address ) ) {
					$reply_to[] = $address;
				}
			}
		}

		$message_id = isset( $header->message_id ) ? $this->sanitize_header_value( $header->message_id ) : '';
		$references = isset( $header->references ) ? $this->sanitize_header_value( $header->references ) : '';

		// Prepare email data
		$email_data = array(
			'id'         => $email_id,
			'subject'    => isset( $header->subject ) ? sanitize_text_field( $this->decode_header( $header->subject ) ) : '(No Subject)',
			'from'       => isset( $header->from[0] ) ? sanitize_email( $header->from[0]->mailbox . '@' . $header->from[0]->host ) : 'unknown',
			'from_name'  => $from_name,
			'date'       => isset( $header->date ) ? sanitize_text_field( $header->date ) : '',
			'body'       => $body,
			'reply_to'   => $reply_to,
			'message_id' => $message_id,
			'references' => $references,
		);

		// Trigger action for new email
		do_action( 'pos_imap_new_email', $email_data );
	}

	/**
	 * Get email body (always returns plain text)
	 *
	 * @param resource $imap IMAP connection.
	 * @param int      $email_id Email ID.
	 * @param object   $structure Email structure.
	 * @return string Email body as plain text.
	 */
	private function get_email_body( $imap, $email_id, $structure ) {
		$body        = '';
		$html_body   = '';
		$is_html     = false;

		// Check if email has parts (multipart)
		if ( isset( $structure->parts ) && count( $structure->parts ) ) {
			// Multipart email - collect both plain and HTML parts
			foreach ( $structure->parts as $part_num => $part ) {
				// Look for text/plain or text/html
				if ( $part->subtype === 'PLAIN' || $part->subtype === 'HTML' ) {
					$part_body = imap_fetchbody( $imap, $email_id, $part_num + 1 );

					// Decode based on encoding
					if ( isset( $part->encoding ) ) {
						$part_body = $this->decode_body( $part_body, $part->encoding );
					}

					if ( $part->subtype === 'PLAIN' && ! empty( $part_body ) ) {
						// Found plain text - use it immediately
						$body = $part_body;
						break;
					} elseif ( $part->subtype === 'HTML' && ! empty( $part_body ) ) {
						// Store HTML as fallback
						$html_body = $part_body;
					}
				}
			}

			// If no plain text found, use HTML and convert to plain text
			if ( empty( $body ) && ! empty( $html_body ) ) {
				$body    = $html_body;
				$is_html = true;
			}
		} else {
			// Simple email
			$body = imap_body( $imap, $email_id );

			if ( isset( $structure->encoding ) ) {
				$body = $this->decode_body( $body, $structure->encoding );
			}

			// Check if simple email is HTML type
			if ( isset( $structure->subtype ) && 'HTML' === $structure->subtype ) {
				$is_html = true;
			}
		}

		// Convert HTML to plain text if needed
		if ( $is_html && ! empty( $body ) ) {
			$body = wp_strip_all_tags( $body );
			// Decode HTML entities
			$body = html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
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
	 * Sanitize header values for safe use in outgoing headers.
	 *
	 * @param mixed $value Header value.
	 * @return string Sanitized header value.
	 */
	private function sanitize_header_value( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ' ', $value );
		}

		$value = (string) $value;
		$value = preg_replace( '/[\r\n]+/', ' ', $value );

		return trim( $value );
	}

	/**
	 * Log new email (hooked to pos_imap_new_email action)
	 * Note: Logs truncated body content. Avoid logging sensitive information.
	 *
	 * @param array $email_data Email data.
	 */
	public function log_new_email( $email_data ) {
		// Truncate body for security (body may contain sensitive info)
		$body = isset( $email_data['body'] ) ? $email_data['body'] : '';
		$body_preview = ! empty( $body ) ? substr( $body, 0, 200 ) : '(empty)';
		if ( strlen( $body ) > 200 ) {
			$body_preview .= '...';
		}

		$this->log(
			sprintf(
				'New Email - Subject: %s, From: %s, Body: %s',
				$email_data['subject'],
				$email_data['from'],
				$body_preview
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

		$this->current_from_name    = 'PersonalOS';
		$this->current_from_address = $this->resolve_from_address();

		if ( ! is_email( $this->current_from_address ) ) {
			$this->log( 'Unable to determine valid From address for SMTP send.', E_USER_ERROR );
			return false;
		}

		$this->log( 'Using From address ' . $this->current_from_address . ' for outgoing mail.' );

		add_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_wp_mail_from_name' ) );
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_wp_mail_failure' ) );

		$result = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		remove_action( 'wp_mail_failed', array( $this, 'log_wp_mail_failure' ) );
		remove_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'filter_wp_mail_from_name' ) );

		if ( $result ) {
			$this->log( 'Email sent successfully to: ' . $to );
		} else {
			$this->log( 'Failed to send email to: ' . $to . ' Subject: ' . $subject, E_USER_ERROR );
		}

		return $result;
	}

	/**
	 * Resolve a valid From address for outgoing mail.
	 *
	 * @return string
	 */
	private function resolve_from_address(): string {
		$from_address = sanitize_email( $this->get_setting( 'smtp_username' ) );
		if ( is_email( $from_address ) ) {
			return $from_address;
		}

		$admin_email = sanitize_email( get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			return $admin_email;
		}

		$site_domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $site_domain ) ) {
			$site_domain = 'localhost.local';
		}
		if ( false === strpos( $site_domain, '.' ) ) {
			$site_domain .= '.local';
		}

		$fallback_address = sanitize_email( 'no-reply@' . $site_domain );
		if ( is_email( $fallback_address ) ) {
			return $fallback_address;
		}

		return '';
	}

	/**
	 * Get the default From address used for outgoing mail.
	 *
	 * @return string
	 */
	public function get_default_from_address(): string {
		return $this->resolve_from_address();
	}

	/**
	 * Configure PHPMailer for SMTP
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		$this->log( 'configure_phpmailer invoked.' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host       = $this->get_setting( 'smtp_host' );
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Port       = $this->get_setting( 'smtp_port' );
		$phpmailer->Username   = $this->get_setting( 'smtp_username' );
		$phpmailer->Password   = $this->get_setting( 'smtp_password' );
		$phpmailer->SMTPSecure = ( (int) $this->get_setting( 'smtp_port' ) === 465 ) ? 'ssl' : 'tls';

		$from_address = $this->current_from_address;
		if ( ! is_email( $from_address ) ) {
			$from_address = $this->resolve_from_address();
		}

		$from_name = $this->current_from_name;
		if ( empty( $from_name ) ) {
			$from_name = 'PersonalOS';
		}

		if ( is_email( $from_address ) ) {
			try {
				$phpmailer->setFrom( $from_address, $from_name, false );
				$phpmailer->Sender = $from_address;
				$this->log( 'Configured PHPMailer From: ' . $from_address . ' (Host: ' . $phpmailer->Host . ')' );
			} catch ( \PHPMailer\PHPMailer\Exception $exception ) {
				$this->log( 'Failed to set From address: ' . $exception->getMessage(), E_USER_WARNING );
			}
		} else {
			$this->log( 'No valid From address available, falling back to WordPress default.', E_USER_WARNING );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Filter callback to override wp_mail From address.
	 *
	 * @param string $from Original From address.
	 * @return string Filtered From address.
	 */
	public function filter_wp_mail_from( $from ) {
		if ( is_email( $this->current_from_address ) ) {
			return $this->current_from_address;
		}

		$resolved = $this->resolve_from_address();
		return is_email( $resolved ) ? $resolved : $from;
	}

	/**
	 * Filter callback to override wp_mail From name.
	 *
	 * @param string $name Original From name.
	 * @return string Filtered From name.
	 */
	public function filter_wp_mail_from_name( $name ) {
		if ( ! empty( $this->current_from_name ) ) {
			return $this->current_from_name;
		}

		return ! empty( $name ) ? $name : 'PersonalOS';
	}

	/**
	 * Log wp_mail failures with additional context.
	 *
	 * @param WP_Error $wp_error WP_Error instance containing failure details.
	 */
	public function log_wp_mail_failure( $wp_error ) {
		if ( ! is_wp_error( $wp_error ) ) {
			$this->log( 'wp_mail failed without WP_Error context.', E_USER_ERROR );
			return;
		}

		$error_messages = $wp_error->get_error_messages();
		$error_codes    = $wp_error->get_error_codes();
		$error_message  = implode( '; ', array_map( 'trim', $error_messages ) );
		$error_data     = $wp_error->get_error_data();

		if ( ! is_scalar( $error_data ) ) {
			$error_data = wp_json_encode( $error_data );
			if ( false === $error_data ) {
				$error_data = 'Unable to encode error data.';
			}
		}

		$codes_output = ! empty( $error_codes ) ? implode( ', ', $error_codes ) : 'n/a';

		$this->log(
			sprintf(
				'wp_mail failure (codes: %s): %s | data: %s',
				$codes_output,
				$error_message,
				esc_html( (string) $error_data )
			),
			E_USER_ERROR
		);
	}
}
