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
	 * Track recently processed Message-IDs to detect loops.
	 *
	 * @var array
	 */
	private $processed_message_ids = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Register custom cron interval for checking every minute (must be before register_sync)
		add_filter( 'cron_schedules', array( $this, 'add_minutely_cron_interval' ) );

		$this->register_sync( 'minutely' );

		// Register actions to log emails
		add_action( 'pos_imap_new_email', array( $this, 'log_new_email' ), 10, 3 );
		add_action( 'pos_imap_new_email_unverified', array( $this, 'log_new_email_unverified' ), 10, 3 );
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
	 * @return \IMAP\Connection|false IMAP connection or false on failure.
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
	 * @param \IMAP\Connection $imap IMAP connection.
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

		$from_email  = 'unknown';
		$from_domain = '';
		if ( isset( $header->from[0] ) && ! empty( $header->from[0]->mailbox ) && ! empty( $header->from[0]->host ) ) {
			$raw_from = $header->from[0]->mailbox . '@' . $header->from[0]->host;
			$sanitized = sanitize_email( $raw_from );
			if ( ! empty( $sanitized ) ) {
				$from_email  = $sanitized;
				$from_domain = $this->extract_domain( $from_email );
			}
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

		// Check for processing loops - if we've seen this Message-ID recently, skip it
		if ( ! empty( $message_id ) && $this->is_recently_processed( $message_id ) ) {
			$this->log( 'Skipping email (potential loop detected): Message-ID already processed recently: ' . $message_id, E_USER_WARNING );
			return;
		}

		$unfolded_headers = $this->get_unfolded_headers( $imap, $email_id );
		$auth_evaluation  = $this->evaluate_sender_trust( $unfolded_headers, $from_domain );

		// Track this Message-ID to prevent loops
		if ( ! empty( $message_id ) ) {
			$this->mark_as_processed( $message_id );
		}

		// Prepare email data
		$email_data = array(
			'id'         => $email_id,
			'subject'    => isset( $header->subject ) ? sanitize_text_field( $this->decode_header( $header->subject ) ) : '(No Subject)',
			'from'       => $from_email,
			'from_name'  => $from_name,
			'date'       => isset( $header->date ) ? sanitize_text_field( $header->date ) : '',
			'body'       => $body,
			'reply_to'   => $reply_to,
			'message_id' => $message_id,
			'references' => $references,
			'auth'       => $auth_evaluation,
			'is_trusted' => (bool) $auth_evaluation['is_trusted'],
		);

		$recipient_addresses            = $this->extract_recipient_addresses( $header, $unfolded_headers );
		$email_data['recipients']       = $recipient_addresses;
		$matched_user_ids               = $this->match_recipient_user_ids( $recipient_addresses, $email_data );
		$email_data['matched_user_ids'] = $matched_user_ids;

		// Trigger appropriate action based on authentication status
		if ( $email_data['is_trusted'] ) {
			$this->dispatch_email_action( 'pos_imap_new_email', $email_data, $matched_user_ids );
		} else {
			$this->dispatch_email_action( 'pos_imap_new_email_unverified', $email_data, $matched_user_ids );
		}
	}

	/**
	 * Fetch unfolded raw headers for the specified email.
	 *
	 * @param \IMAP\Connection $imap IMAP connection.
	 * @param int      $email_id Email ID.
	 * @return string Unfolded raw headers.
	 */
	private function get_unfolded_headers( $imap, int $email_id ): string {
		$raw_headers = imap_fetchheader( $imap, $email_id );
		if ( ! is_string( $raw_headers ) || '' === $raw_headers ) {
			return '';
		}

		$lines     = preg_split( '/\r\n|\r|\n/', $raw_headers );
		$unfolded = array();

		foreach ( $lines as $line ) {
			if ( '' !== $line && ( ' ' === $line[0] || "\t" === $line[0] ) && ! empty( $unfolded ) ) {
				$unfolded[ count( $unfolded ) - 1 ] .= ' ' . trim( $line );
				continue;
			}

			$unfolded[] = $line;
		}

		return implode( "\r\n", $unfolded );
	}

	/**
	 * Check if a Message-ID was recently processed (loop detection).
	 *
	 * @param string $message_id Message-ID to check.
	 * @return bool True if recently processed.
	 */
	private function is_recently_processed( string $message_id ): bool {
		// Check in-memory cache first (for same sync run)
		if ( in_array( $message_id, $this->processed_message_ids, true ) ) {
			return true;
		}

		// Check transient (24-hour window)
		$transient_key = 'pos_imap_processed_' . md5( $message_id );
		$is_processed  = get_transient( $transient_key );

		return false !== $is_processed;
	}

	/**
	 * Mark a Message-ID as processed.
	 *
	 * @param string $message_id Message-ID to mark.
	 */
	private function mark_as_processed( string $message_id ): void {
		// Add to in-memory cache
		$this->processed_message_ids[] = $message_id;

		// Store in transient for 24 hours
		$transient_key = 'pos_imap_processed_' . md5( $message_id );
		set_transient( $transient_key, true, DAY_IN_SECONDS );
	}

	/**
	 * Extract email addresses from IMAP header entries (to/cc).
	 *
	 * @param array|null $entries Header entries.
	 * @return array
	 */
	private function extract_addresses_from_header_entries( $entries ): array {
		$addresses = array();
		if ( empty( $entries ) || ! is_array( $entries ) ) {
			return $addresses;
		}

		foreach ( $entries as $entry ) {
			if ( empty( $entry->mailbox ) || empty( $entry->host ) ) {
				continue;
			}
			$email = sanitize_email( $entry->mailbox . '@' . $entry->host );
			if ( ! empty( $email ) ) {
				$addresses[] = $email;
			}
		}

		return $addresses;
	}

	/**
	 * Extract addresses from raw headers (e.g., Delivered-To).
	 *
	 * @param string $headers     Raw unfolded headers.
	 * @param string $header_name Header name to extract.
	 * @return array
	 */
	private function extract_addresses_from_raw_headers( string $headers, string $header_name ): array {
		if ( '' === $headers ) {
			return array();
		}

		$pattern = '/^' . preg_quote( $header_name, '/' ) . ':\s*(.+)$/mi';
		if ( 0 === preg_match_all( $pattern, $headers, $matches ) ) {
			return array();
		}

		$addresses = array();
		foreach ( $matches[1] as $line ) {
			$line_addresses = $this->parse_address_list( $line );
			if ( ! empty( $line_addresses ) ) {
				$addresses = array_merge( $addresses, $line_addresses );
			}
		}

		return $addresses;
	}

	/**
	 * Parse address list string using IMAP helpers when available.
	 *
	 * @param string $value Header value.
	 * @return array
	 */
	private function parse_address_list( string $value ): array {
		if ( '' === trim( $value ) ) {
			return array();
		}

		if ( ! function_exists( 'imap_rfc822_parse_adrlist' ) ) {
			$pieces = array_map( 'trim', explode( ',', $value ) );
			return array_filter( array_map( 'sanitize_email', $pieces ) );
		}

		$parsed = imap_rfc822_parse_adrlist( $value, '' );
		if ( empty( $parsed ) || ! is_array( $parsed ) ) {
			return array();
		}

		$addresses = array();
		foreach ( $parsed as $entry ) {
			if ( empty( $entry->mailbox ) || empty( $entry->host ) ) {
				continue;
			}
			$email = sanitize_email( $entry->mailbox . '@' . $entry->host );
			if ( ! empty( $email ) ) {
				$addresses[] = $email;
			}
		}

		return $addresses;
	}

	/**
	 * Extract all recipient addresses for the message.
	 *
	 * @param object $header            IMAP header info object.
	 * @param string $unfolded_headers  Raw unfolded headers.
	 * @return array
	 */
	private function extract_recipient_addresses( $header, string $unfolded_headers ): array {
		$addresses = array();

		if ( isset( $header->to ) ) {
			$addresses = array_merge( $addresses, $this->extract_addresses_from_header_entries( $header->to ) );
		}

		if ( isset( $header->cc ) ) {
			$addresses = array_merge( $addresses, $this->extract_addresses_from_header_entries( $header->cc ) );
		}

		if ( empty( $addresses ) ) {
			$addresses = array_merge( $addresses, $this->extract_addresses_from_raw_headers( $unfolded_headers, 'Delivered-To' ) );
		}

		$addresses = array_map( 'sanitize_email', $addresses );
		$addresses = array_filter( $addresses, 'is_email' );

		return array_values( array_unique( $addresses ) );
	}

	/**
	 * Map recipient addresses to WordPress user IDs.
	 *
	 * @param array $addresses  Recipient email addresses.
	 * @param array $email_data Email data payload.
	 * @return array
	 */
	private function match_recipient_user_ids( array $addresses, array $email_data ): array {
		if ( empty( $addresses ) ) {
			return array();
		}

		$user_ids = array();
		foreach ( $addresses as $address ) {
			$user = get_user_by( 'email', $address );
			/**
			 * Filter to map recipient email addresses to WordPress users.
			 *
			 * @param WP_User|false|null $user       Matched user object if found.
			 * @param string             $address    Email address being checked.
			 * @param array              $email_data Full email data.
			 */
			$user = apply_filters( 'pos_imap_map_recipient_user', $user, $address, $email_data );
			if ( $user instanceof WP_User ) {
				$user_ids[] = (int) $user->ID;
			}
		}

		return array_values( array_unique( $user_ids ) );
	}

	/**
	 * Dispatch IMAP email actions for each matched user.
	 *
	 * @param string $hook      Hook name.
	 * @param array  $email_data Email payload.
	 * @param array  $user_ids   Matched user IDs.
	 * @return void
	 */
	private function dispatch_email_action( string $hook, array $email_data, array $user_ids ): void {
		if ( empty( $user_ids ) ) {
			$email_data['matched_user_id'] = 0;
			do_action( $hook, $email_data, $this, 0 );
			return;
		}

		foreach ( $user_ids as $user_id ) {
			$this->run_for_user(
				$user_id,
				function() use ( $hook, $email_data, $user_id ) {
					$email_data['matched_user_id'] = $user_id;
					do_action( $hook, $email_data, $this, $user_id );
				}
			);
		}
	}

	/**
	 * Extract domain portion from an email address.
	 *
	 * @param string $email Email address.
	 * @return string Domain portion (lowercase) or empty string.
	 */
	private function extract_domain( string $email ): string {
		$email = trim( $email );
		$at    = strrpos( $email, '@' );
		if ( false === $at ) {
			return '';
		}

		return strtolower( substr( $email, $at + 1 ) );
	}

	/**
	 * Determine relaxed alignment between two domains.
	 *
	 * @param string $from_domain Header From domain.
	 * @param string $auth_domain DKIM or SPF domain.
	 * @return bool
	 */
	private function is_relaxed_aligned( string $from_domain, string $auth_domain ): bool {
		$from_domain = strtolower( $from_domain );
		$auth_domain = strtolower( $auth_domain );

		if ( '' === $from_domain || '' === $auth_domain ) {
			return false;
		}

		if ( $from_domain === $auth_domain ) {
			return true;
		}

		return (bool) preg_match( '/(^|\.)' . preg_quote( $from_domain, '/' ) . '$/i', $auth_domain );
	}

	/**
	 * Evaluate sender trust based on Authentication-Results and header alignment.
	 *
	 * @param string $unfolded_headers Unfolded headers for parsing.
	 * @param string $from_domain      Header From domain.
	 * @param array  $trusted_authserv_ids Trusted authserv identifiers.
	 * @return array
	 */
	private function evaluate_sender_trust( string $unfolded_headers, string $from_domain, array $trusted_authserv_ids = array( 'mx.google.com', 'outlook.com' ) ): array {
		$result = array(
			'is_trusted'          => false,
			'summary'             => 'no authentication results',
			'authserv'            => '',
			'dmarc'               => '',
			'dkim'                => '',
			'dkim_domain'         => '',
			'spf'                 => '',
			'spf_domain'          => '',
			'return_path'         => '',
			'return_path_domain'  => '',
			'return_path_aligned' => false,
			'auth_headers'        => array(),
		);

		if ( '' === $unfolded_headers ) {
			return $result;
		}

		$return_path = '';
		if ( preg_match( '/Return-Path:\s*<?([^\s>]+)>?/i', $unfolded_headers, $matches ) ) {
			$return_candidate = sanitize_email( $matches[1] );
			if ( ! empty( $return_candidate ) ) {
				$return_path = $return_candidate;
			}
		}

		$result['return_path'] = $return_path;
		if ( '' !== $return_path ) {
			$return_domain                   = $this->extract_domain( $return_path );
			$result['return_path_domain']    = $return_domain;
			$result['return_path_aligned']   = $this->is_relaxed_aligned( $from_domain, $return_domain );
		}

		$records            = array();
		$auth_debug_headers = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $unfolded_headers ) as $line ) {
			$trimmed_line = trim( $line );
			if ( '' === $trimmed_line ) {
				continue;
			}
			if ( 0 === stripos( $trimmed_line, 'Authentication-Results:' ) ) {
				$records[]          = trim( substr( $trimmed_line, strlen( 'Authentication-Results:' ) ) );
				$auth_debug_headers[] = $trimmed_line;
				continue;
			}
			if ( 0 === stripos( $trimmed_line, 'X-Gm-Authentication-Results:' ) ) {
				$records[]          = trim( substr( $trimmed_line, strlen( 'X-Gm-Authentication-Results:' ) ) );
				$auth_debug_headers[] = $trimmed_line;
				continue;
			}
			if ( 0 === stripos( $trimmed_line, 'Received-SPF:' ) || 0 === stripos( $trimmed_line, 'Authentication-Results-Original:' ) ) {
				$auth_debug_headers[] = $trimmed_line;
				continue;
			}
			if ( 0 === stripos( $trimmed_line, 'DKIM-Signature:' ) || 0 === stripos( $trimmed_line, 'X-Google-DKIM-Signature:' ) ) {
				$auth_debug_headers[] = $trimmed_line;
			}
		}
		$result['auth_headers'] = $auth_debug_headers;

		$trusted_authserv_ids = array_map( 'strtolower', $trusted_authserv_ids );
		$imap_host            = strtolower( trim( (string) $this->get_setting( 'imap_host' ) ) );
		if ( '' !== $imap_host ) {
			$trusted_authserv_ids[] = $imap_host;
		}
		/**
		 * Filter the list of trusted Authentication-Results authserv identifiers.
		 *
		 * @since 0.2.4
		 *
		 * @param array  $trusted_authserv_ids Authserv identifiers considered trusted.
		 * @param string $from_domain          Parsed From domain for the message.
		 * @param string $unfolded_headers     Full raw headers.
		 */
		$trusted_authserv_ids = apply_filters( 'pos_imap_trusted_authserv', array_unique( $trusted_authserv_ids ), $from_domain, $unfolded_headers );

		if ( empty( $records ) ) {
			if ( ! empty( $auth_debug_headers ) ) {
				$this->log( 'Authentication headers collected (no results): ' . implode( ' || ', array_slice( $auth_debug_headers, 0, 5 ) ) );
			}
			return $result;
		}

		$trusted_lookup = array();
		foreach ( $trusted_authserv_ids as $id ) {
			$trusted_lookup[] = strtolower( $id );
		}

		foreach ( $records as $record ) {
			if ( ! preg_match( '/^\s*([^\s;]+)\s*;(.+)$/i', $record, $record_matches ) ) {
				continue;
			}

			$authserv = strtolower( trim( $record_matches[1] ) );
			$rest     = ' ' . $record_matches[2] . ' ';

			if ( ! in_array( $authserv, $trusted_lookup, true ) ) {
				continue;
			}

			$dmarc = '';
			if ( preg_match( '/\bdmarc\s*=\s*(pass|fail|temperror|permerror)\b/i', $rest, $dmarc_matches ) ) {
				$dmarc = strtolower( $dmarc_matches[1] );
			}

			$dkim = '';
			if ( preg_match( '/\bdkim\s*=\s*(pass|fail|none|neutral|policy|temperror|permerror)\b/i', $rest, $dkim_matches ) ) {
				$dkim = strtolower( $dkim_matches[1] );
			}

			$spf = '';
			if ( preg_match( '/\bspf\s*=\s*(pass|fail|softfail|neutral|none|temperror|permerror)\b/i', $rest, $spf_matches ) ) {
				$spf = strtolower( $spf_matches[1] );
			}

			$dkim_domain = '';
			if ( preg_match( '/\bdkim[^;]*\bd=\s*([^\s;]+)\b/i', $rest, $dkim_domain_matches ) ) {
				$dkim_domain = strtolower( $dkim_domain_matches[1] );
			}

			$spf_domain = '';
			if ( preg_match( '/\bsmtp\.mailfrom=\s*([^\s;]+)\b/i', $rest, $spf_domain_matches ) ) {
				$spf_domain = strtolower( $spf_domain_matches[1] );
			}

			$result['authserv']    = $authserv;
			$result['dmarc']       = $dmarc;
			$result['dkim']        = $dkim;
			$result['dkim_domain'] = $dkim_domain;
			$result['spf']         = $spf;
			$result['spf_domain']  = $spf_domain;

			if ( 'pass' === $dmarc ) {
				$result['is_trusted'] = true;
				$result['summary']    = 'dmarc=pass @ ' . $authserv;
				return $result;
			}

			if ( 'pass' === $dkim && ( '' === $dkim_domain || $this->is_relaxed_aligned( $from_domain, $dkim_domain ) ) ) {
				$result['is_trusted'] = true;
				$result['summary']    = 'dkim=pass aligned @ ' . $authserv;
				return $result;
			}

			if ( 'pass' === $spf && ( '' === $spf_domain || $this->is_relaxed_aligned( $from_domain, $spf_domain ) ) ) {
				$result['is_trusted'] = true;
				$result['summary']    = 'spf=pass aligned @ ' . $authserv;
				return $result;
			}

			$result['summary'] = 'auth present without pass @ ' . $authserv;
		}

		if ( ! $result['is_trusted'] && ! empty( $auth_debug_headers ) ) {
			$this->log( 'Authentication headers evaluated (no trust): ' . implode( ' || ', array_slice( $auth_debug_headers, 0, 5 ) ) );
		}

		return $result;
	}

	/**
	 * Get email body (always returns plain text)
	 *
	 * @param \IMAP\Connection $imap IMAP connection.
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
	 * Log verified/trusted email (hooked to pos_imap_new_email action)
	 *
	 * @param array $email_data Email data.
	 * @param IMAP_Module|null $module  Module instance (unused, provided for action parity).
	 * @param int              $user_id Matched WordPress user ID (0 when none).
	 */
	public function log_new_email( $email_data, $module = null, $user_id = 0 ) {
		// Truncate body for security (body may contain sensitive info)
		$body = isset( $email_data['body'] ) ? $email_data['body'] : '';
		$body_preview = ! empty( $body ) ? substr( $body, 0, 200 ) : '(empty)';
		if ( strlen( $body ) > 200 ) {
			$body_preview .= '...';
		}

		$user_fragment = $user_id ? 'User #' . $user_id . ' | ' : '';

		$this->log(
			sprintf(
				'[VERIFIED] %sEmail - Subject: %s, From: %s, Body: %s',
				$user_fragment,
				$email_data['subject'],
				$email_data['from'],
				$body_preview
			)
		);
	}

	/**
	 * Log unverified/untrusted email (hooked to pos_imap_new_email_unverified action)
	 *
	 * @param array $email_data Email data.
	 * @param IMAP_Module|null $module  Module instance (unused, provided for action parity).
	 * @param int              $user_id Matched WordPress user ID (0 when none).
	 */
	public function log_new_email_unverified( $email_data, $module = null, $user_id = 0 ) {
		$user_fragment = $user_id ? 'User #' . $user_id . ' | ' : '';

		$this->log(
			sprintf(
				'[UNVERIFIED] %sEmail - Subject: %s, From: %s',
				$user_fragment,
				$email_data['subject'],
				$email_data['from']
			),
			E_USER_WARNING
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
