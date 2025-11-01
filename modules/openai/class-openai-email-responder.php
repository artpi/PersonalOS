<?php
/**
 * Handles automatic email responses for new incoming emails.
 *
 * @package PersonalOS
 */

class OpenAI_Email_Responder {
	/**
	 * Parent OpenAI module.
	 *
	 * @var OpenAI_Module
	 */
	protected $module;

	/**
	 * Constructor.
	 *
	 * @param OpenAI_Module $module OpenAI module instance.
	 */
	public function __construct( OpenAI_Module $module ) {
		$this->module = $module;
		add_action( 'pos_imap_new_email', array( $this, 'handle_new_email' ), 20, 2 );
	}

	/**
	 * Handle new incoming emails and respond using the AI conversation completion.
	 *
	 * @param array $email_data   Email data from the IMAP module.
	 * @param object $imap_module IMAP module instance.
	 */
	public function handle_new_email( array $email_data, $imap_module = null ): void {
		// Classify the email first to check if it's an auto-responder or spam
		$classification = $this->classify_email( $email_data );
		if ( ! empty( $classification['skip'] ) ) {
			$reason = isset( $classification['reason'] ) ? $classification['reason'] : 'unknown';
			$from_address = isset( $email_data['from'] ) ? sanitize_email( $email_data['from'] ) : 'unknown';
			$this->module->log( 'Auto-reply skipped: ' . $reason . ' from ' . $from_address . '.' );
			return;
		}

		$matched_user = $this->resolve_user_from_email( $email_data );
		if ( ! $matched_user instanceof WP_User ) {
			$from_address = isset( $email_data['from'] ) ? sanitize_email( $email_data['from'] ) : '';
			if ( '' === $from_address ) {
				$from_address = 'unknown';
			}
			$this->module->log( 'Auto-reply skipped: no matching user for ' . $from_address . '.', E_USER_WARNING );
			return;
		}

		$recipient = $this->get_reply_address( $email_data );
		if ( empty( $recipient ) ) {
			$this->module->log( 'Auto-reply skipped: no valid recipient.' );
			return;
		}

		// Use the passed IMAP module or get it if not provided
		if ( ! $imap_module ) {
			$imap_module = POS::get_module_by_id( 'imap' );
		}
		if ( ! $imap_module || ! method_exists( $imap_module, 'send_email' ) ) {
			$this->module->log( 'Auto-reply skipped: IMAP module not available.', E_USER_WARNING );
			return;
		}

		$backscroll = $this->build_backscroll_for_email( $email_data );

		$assistant_reply = '';
		$used_fallback   = false;
		$conversation    = null;

		$previous_user       = wp_get_current_user();
		$previous_user_id    = ( $previous_user instanceof WP_User ) ? (int) $previous_user->ID : 0;
		$previous_user_login = ( $previous_user instanceof WP_User ) ? $previous_user->user_login : '';

		wp_set_current_user( $matched_user->ID, $matched_user->user_login );

		try {
			$conversation = $this->module->complete_backscroll( $backscroll );
		} finally {
			if ( $previous_user_id > 0 ) {
				wp_set_current_user( $previous_user_id, $previous_user_login );
			} else {
				wp_set_current_user( 0 );
			}
		}
		if ( is_wp_error( $conversation ) ) {
			$this->module->log( 'Auto-reply AI failure: ' . $conversation->get_error_message(), E_USER_ERROR );
			$used_fallback = true;
		} else {
			$assistant_reply = $this->extract_assistant_reply( $conversation );
			if ( '' === $assistant_reply ) {
				$this->module->log( 'Auto-reply skipped: AI returned no assistant message.' );
				return;
			}
		}

		$subject = 'Re: ' . trim( isset( $email_data['subject'] ) ? $email_data['subject'] : '' );
		$body    = $this->compose_reply_body( $assistant_reply, $email_data );
		$headers = $this->prepare_headers( $email_data );

		$sent = $imap_module->send_email( $recipient, $subject, $body, $headers );

		if ( $sent ) {
			$this->module->log( 'AI auto-reply sent to ' . $recipient );
		} else {
			$this->module->log( 'Auto-reply failed for ' . $recipient, E_USER_ERROR );
		}
	}

	/**
	 * Classify email using AI to detect auto-responders, spam, etc.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return array Classification result with 'skip' boolean and 'reason' string.
	 */
	private function classify_email( array $email_data ): array {
		$from_email = isset( $email_data['from'] ) ? $email_data['from'] : '';
		$from_name  = isset( $email_data['from_name'] ) ? $email_data['from_name'] : '';
		$subject    = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
		$body       = isset( $email_data['body'] ) ? substr( $email_data['body'], 0, 500 ) : '';

		$classification_prompt = sprintf(
			<<<'PROMPT'
Analyze this email and determine if it should be skipped (not replied to).

From: %s <%s>
Subject: %s
Body (first 500 chars): %s

Classify this email. Skip if it's:
- Auto-responder (out of office, vacation reply, etc.)
- Delivery failure notification
- Automated system message
You ONLY want to skip automated-looking emails.
YOU ARE A PERSONAL ASSISTANT. DO NOT SKIP INQUIRY, QUESTIONS OR TASKS.
Respond with JSON only: {'skip': true/false, 'reason': 'brief reason'}
PROMPT,
			$from_name,
			$from_email,
			$subject,
			$body
		);

		$messages = array(
			array(
				'role'    => 'user',
				'content' => $classification_prompt,
			),
		);

		$response = $this->module->api_call(
			'https://api.openai.com/v1/chat/completions',
			array(
				'model'             => 'gpt-4.1-mini',
				'messages'          => $messages,
				'response_format'   => array( 'type' => 'json_object' ),
				'temperature'       => 0.3,
				'max_tokens'        => 100,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->module->log( 'Email classification failed: ' . $response->get_error_message(), E_USER_WARNING );
			// Default to not skipping on error
			return array( 'skip' => false, 'reason' => '' );
		}

		if ( ! isset( $response->choices[0]->message->content ) ) {
			$this->module->log( 'Email classification returned invalid response', E_USER_WARNING );
			return array( 'skip' => false, 'reason' => '' );
		}

		$result = json_decode( $response->choices[0]->message->content, true );
		if ( ! is_array( $result ) ) {
			$this->module->log( 'Email classification returned invalid JSON', E_USER_WARNING );
			return array( 'skip' => false, 'reason' => '' );
		}

		return array(
			'skip'   => ! empty( $result['skip'] ),
			'reason' => isset( $result['reason'] ) ? $result['reason'] : '',
		);
	}

	/**
	 * Build minimal backscroll containing the full email content for the AI.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return array Backscroll messages.
	 */
	private function build_backscroll_for_email( array $email_data ): array {
		$from_email = isset( $email_data['from'] ) ? $email_data['from'] : '';
		$from_name  = isset( $email_data['from_name'] ) ? $email_data['from_name'] : '';
		$display    = $from_email;

		if ( '' !== $from_name && '' !== $from_email ) {
			$display = $from_name . ' <' . $from_email . '>';
		} elseif ( '' !== $from_name ) {
			$display = $from_name;
		}

		$lines   = array();
		$lines[] = 'From: ' . ( '' !== $display ? $display : 'unknown sender' );

		if ( ! empty( $email_data['subject'] ) ) {
			$lines[] = 'Subject: ' . trim( (string) $email_data['subject'] );
		}

		$lines[] = 'Body:';
		$lines[] = isset( $email_data['body'] ) && '' !== trim( (string) $email_data['body'] )
			? (string) $email_data['body']
			: '(No body content provided.)';

		return array(
			array(
				'role'    => 'user',
				'content' => $this->normalize_backscroll_content( implode( "\n", $lines ) ),
			),
		);
	}

	/**
	 * Compose the response body by combining AI output with the original email content.
	 *
	 * @param string $assistant_reply Reply generated by the assistant.
	 * @param array  $email_data      Email data from the IMAP module.
	 * @return string Response body.
	 */
	private function compose_reply_body( string $assistant_reply, array $email_data ): string {
		$assistant_reply = trim( $assistant_reply );
		$body            = $assistant_reply;
		$quoted_block    = $this->format_quoted_original( $email_data );

		if ( '' !== $quoted_block ) {
			$body .= "\n\n" . $quoted_block;
		}

		return $body . "\n";
	}

	/**
	 * Build a quoted block representing the original message contents.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return string Quoted original message.
	 */
	private function format_quoted_original( array $email_data ): string {
		$original_body = isset( $email_data['body'] ) ? (string) $email_data['body'] : '';
		$original_body = trim( $original_body );

		if ( '' === $original_body ) {
			return '';
		}

		$from_email = isset( $email_data['from'] ) ? trim( (string) $email_data['from'] ) : '';
		$from_name  = isset( $email_data['from_name'] ) ? trim( (string) $email_data['from_name'] ) : '';
		$from_line  = 'unknown sender';

		if ( '' !== $from_name && '' !== $from_email ) {
			$from_line = $from_name . ' <' . $from_email . '>';
		} elseif ( '' !== $from_name ) {
			$from_line = $from_name;
		} elseif ( '' !== $from_email ) {
			$from_line = $from_email;
		}

		$date_header = isset( $email_data['date'] ) ? trim( (string) $email_data['date'] ) : '';
		$intro_parts = array();

		if ( '' !== $date_header ) {
			$intro_parts[] = $date_header;
		}

		$intro_parts[] = $from_line;

		$intro = 'On ' . implode( ', ', $intro_parts ) . ' wrote:';

		$normalized_body = str_replace( array( "\r\n", "\r" ), "\n", $original_body );
		$lines           = explode( "\n", $normalized_body );
		$quoted_lines    = array();

		foreach ( $lines as $line ) {
			$line          = rtrim( $line );
			$quoted_lines[] = '' === $line ? '>' : '> ' . $line;
		}

		return $intro . "\n" . implode( "\n", $quoted_lines );
	}

	/**
	 * Resolve the WordPress user associated with the incoming email.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return WP_User|null Matching user or null when not found.
	 */
	private function resolve_user_from_email( array $email_data ) {
		$candidates = array();

		if ( ! empty( $email_data['reply_to'] ) ) {
			$reply_to = $email_data['reply_to'];
			if ( ! is_array( $reply_to ) ) {
				$reply_to = explode( ',', (string) $reply_to );
			}

			foreach ( $reply_to as $address ) {
				$sanitized = sanitize_email( $address );
				if ( is_email( $sanitized ) ) {
					$candidates[] = $sanitized;
				}
			}
		}

		if ( ! empty( $email_data['from'] ) ) {
			$from_address = sanitize_email( $email_data['from'] );
			if ( is_email( $from_address ) ) {
				$candidates[] = $from_address;
			}
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		$candidates = array_unique( $candidates );

		foreach ( $candidates as $candidate ) {
			$user = get_user_by( 'email', $candidate );
			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Prepare headers to ensure replies thread correctly.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return array List of headers.
	 */
	private function prepare_headers( array $email_data ): array {
		$headers = array();

		if ( ! empty( $email_data['message_id'] ) ) {
			$headers[] = 'In-Reply-To: ' . $email_data['message_id'];
			$references = ! empty( $email_data['references'] ) ? $email_data['references'] . ' ' . $email_data['message_id'] : $email_data['message_id'];
			$references = preg_replace( '/\s+/', ' ', trim( $references ) );
			$headers[] = 'References: ' . $references;
		}

		$imap_module = POS::get_module_by_id( 'imap' );
		if ( $imap_module && method_exists( $imap_module, 'get_default_from_address' ) ) {
			$reply_to_address = sanitize_email( $imap_module->get_default_from_address() );
			if ( is_email( $reply_to_address ) ) {
				$headers[] = 'Reply-To: PersonalOS <' . $reply_to_address . '>';
			}
		}

		return $headers;
	}

	/**
	 * Determine the address to reply to.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return string Reply address.
	 */
	private function get_reply_address( array $email_data ): string {
		if ( ! empty( $email_data['reply_to'] ) ) {
			$reply_to = is_array( $email_data['reply_to'] ) ? $email_data['reply_to'] : explode( ',', $email_data['reply_to'] );
			$reply_to = array_filter( array_map( 'trim', $reply_to ) );
			if ( ! empty( $reply_to ) ) {
				return sanitize_email( reset( $reply_to ) );
			}
		}
		return isset( $email_data['from'] ) ? sanitize_email( $email_data['from'] ) : '';
	}

	/**
	 * Extract the latest assistant reply from the completed backscroll.
	 *
	 * @param array $conversation Conversation history returned from complete_backscroll.
	 * @return string Assistant reply content.
	 */
	private function extract_assistant_reply( array $conversation ): string {
		foreach ( array_reverse( $conversation ) as $message ) {
			if ( is_array( $message ) && isset( $message['role'], $message['content'] ) ) {
				if ( 'assistant' !== $message['role'] ) {
					continue;
				}
				return trim( is_string( $message['content'] ) ? $message['content'] : wp_json_encode( $message['content'] ) );
			}

			if ( is_object( $message ) && isset( $message->role, $message->content ) ) {
				if ( 'assistant' !== $message->role ) {
					continue;
				}
				return trim( is_string( $message->content ) ? $message->content : wp_json_encode( $message->content ) );
			}
		}

		return '';
	}

	/**
	 * Normalise conversation text for the backscroll.
	 *
	 * @param string $content     Text content.
	 * @param int    $max_length  Maximum length in characters.
	 * @return string Normalised content.
	 */
	private function normalize_backscroll_content( string $content, int $max_length = 4000 ): string {
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );
		$content = trim( $content );

		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $content, 'UTF-8' ) > $max_length ) {
				$content = mb_substr( $content, 0, $max_length, 'UTF-8' ) . "\n\n[truncated]";
			}
		} elseif ( strlen( $content ) > $max_length ) {
			$content = substr( $content, 0, $max_length ) . "\n\n[truncated]";
		}

		return $content;
	}
}
