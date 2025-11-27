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
	 * @param array  $email_data  Email data from the IMAP module.
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

		// Parse subject for prompt slug (#prompt-slug) and conversation post ID ([123])
		$email_subject = isset( $email_data['subject'] ) ? $email_data['subject'] : '';
		$prompt_slug   = preg_match( '/#([a-zA-Z0-9_-]+)/', $email_subject, $m ) ? $m[1] : null;
		$post_id       = preg_match( '/\[(\d+)\]/', $email_subject, $m ) ? (int) $m[1] : null;

		// Look up prompt by slug if provided
		$prompt = null;
		if ( $prompt_slug ) {
			$notes_module = POS::get_module_by_id( 'notes' );
			if ( $notes_module ) {
				$prompts = $notes_module->list( array( 'name' => $prompt_slug ), 'prompts-chat' );
				$prompt  = ! empty( $prompts ) ? $prompts[0] : null;
			}
			if ( $prompt ) {
				$this->module->log( 'Using prompt: ' . $prompt->post_title . ' (slug: ' . $prompt_slug . ')' );
			} else {
				$this->module->log( 'Prompt not found for slug: ' . $prompt_slug . ', using default.' );
			}
		}

		// Load previous_response_id if continuing a conversation
		$previous_response_id = null;
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && 'notes' === $post->post_type ) {
				$previous_response_id = get_post_meta( $post_id, 'pos_last_response_id', true );
				if ( $previous_response_id ) {
					$this->module->log( 'Continuing conversation post ID: ' . $post_id );
				}
			} else {
				// Invalid post ID, ignore it
				$post_id = null;
				$this->module->log( 'Invalid post ID in subject: ' . $post_id . ', starting new conversation.' );
			}
		}

		$messages = $this->build_messages_for_email( $email_data );

		$assistant_reply = '';
		$conversation    = null;
		$new_post_id     = $post_id;

		$previous_user       = wp_get_current_user();
		$previous_user_id    = ( $previous_user instanceof WP_User ) ? (int) $previous_user->ID : 0;
		$previous_user_login = ( $previous_user instanceof WP_User ) ? $previous_user->user_login : '';

		wp_set_current_user( $matched_user->ID, $matched_user->user_login );

		try {
			// Build persistence config
			$persistence = array(
				'append' => true,
			);
			if ( $post_id ) {
				$persistence['search_args'] = array( 'ID' => $post_id );
			}

			$conversation = $this->module->complete_responses(
				$messages,
				function ( $event_type, $data ) use ( &$new_post_id ) {
					// Capture post ID from persistence if created
					if ( 'post_id' === $event_type ) {
						$new_post_id = $data;
					}
				},
				$previous_response_id ? $previous_response_id : null,
				$prompt,
				$persistence
			);
		} finally {
			if ( $previous_user_id > 0 ) {
				wp_set_current_user( $previous_user_id, $previous_user_login );
			} else {
				wp_set_current_user( 0 );
			}
		}

		if ( is_wp_error( $conversation ) ) {
			$this->module->log( 'Auto-reply AI failure: ' . $conversation->get_error_message(), E_USER_ERROR );
			return;
		} else {
			$assistant_reply = $this->extract_assistant_reply( $conversation );
			if ( '' === $assistant_reply ) {
				$this->module->log( 'Auto-reply skipped: AI returned no assistant message.' );
				return;
			}
		}

		// Build reply subject - use generated title if new conversation, otherwise preserve original
		$reply_subject = 'Re: ' . trim( $email_subject );

		// For new conversations, use the AI-generated title from the post
		if ( $new_post_id && ! $post_id ) {
			$conversation_post = get_post( $new_post_id );
			if ( $conversation_post && ! empty( $conversation_post->post_title ) ) {
				$reply_subject = 'Re: ' . $conversation_post->post_title;
			}
		}

		// Add [post_id] if we have one and it's not already in the subject
		if ( $new_post_id && ! preg_match( '/\[\d+\]/', $reply_subject ) ) {
			$reply_subject .= ' [' . $new_post_id . ']';
		}

		$body    = $this->compose_reply_body( $assistant_reply, $email_data );
		$headers = $this->prepare_headers( $email_data );

		$sent = $imap_module->send_email( $recipient, $reply_subject, $body, $headers );

		if ( $sent ) {
			$this->module->log( 'AI auto-reply sent to ' . $recipient . ' (post ID: ' . ( $new_post_id ? $new_post_id : 'none' ) . ')' );
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
				'model'           => 'gpt-4.1-mini',
				'messages'        => $messages,
				'response_format' => array( 'type' => 'json_object' ),
				'temperature'     => 0.3,
				'max_tokens'      => 100,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->module->log( 'Email classification failed: ' . $response->get_error_message(), E_USER_WARNING );
			// Default to not skipping on error
			return array(
				'skip'   => false,
				'reason' => '',
			);
		}

		if ( ! isset( $response->choices[0]->message->content ) ) {
			$this->module->log( 'Email classification returned invalid response', E_USER_WARNING );
			return array(
				'skip'   => false,
				'reason' => '',
			);
		}

		$result = json_decode( $response->choices[0]->message->content, true );
		if ( ! is_array( $result ) ) {
			$this->module->log( 'Email classification returned invalid JSON', E_USER_WARNING );
			return array(
				'skip'   => false,
				'reason' => '',
			);
		}

		return array(
			'skip'   => ! empty( $result['skip'] ),
			'reason' => isset( $result['reason'] ) ? $result['reason'] : '',
		);
	}

	/**
	 * Build messages array for the Responses API containing the email content.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return array Messages array for complete_responses.
	 */
	private function build_messages_for_email( array $email_data ): array {
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
				'content' => array(
					array(
						'type' => 'input_text',
						'text' => $this->normalize_backscroll_content( implode( "\n", $lines ) ),
					),
				),
			),
		);
	}

	/**
	 * Compose the response body by combining AI output with the original email content.
	 *
	 * Converts markdown to HTML for rich email formatting.
	 *
	 * @param string $assistant_reply Reply generated by the assistant.
	 * @param array  $email_data      Email data from the IMAP module.
	 * @return string HTML response body.
	 */
	private function compose_reply_body( string $assistant_reply, array $email_data ): string {
		$assistant_reply = trim( $assistant_reply );

		// Convert markdown to HTML using Parsedown
		$parsedown    = \Parsedown::instance();
		$html_content = $parsedown->text( $assistant_reply );

		// Build quoted original as HTML
		$quoted_html = $this->format_quoted_original_html( $email_data );

		// Wrap in a minimal email HTML template
		$body = $this->wrap_html_email( $html_content, $quoted_html );

		return $body;
	}

	/**
	 * Wrap content in a minimal HTML email template.
	 *
	 * @param string $html_content  Main HTML content.
	 * @param string $quoted_html   Quoted original message HTML.
	 * @return string Complete HTML email body.
	 */
	private function wrap_html_email( string $html_content, string $quoted_html ): string {
		$quoted_section = '';
		if ( '' !== $quoted_html ) {
			$quoted_section = '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ccc;">' . $quoted_html . '</div>';
		}

		return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
<div style="font-size: 15px;">
' . $html_content . '
</div>
' . $quoted_section . '
</body>
</html>';
	}

	/**
	 * Build a quoted block in HTML format representing the original message contents.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return string Quoted original message as HTML.
	 */
	private function format_quoted_original_html( array $email_data ): string {
		$original_body = isset( $email_data['body'] ) ? (string) $email_data['body'] : '';
		$original_body = trim( $original_body );

		if ( '' === $original_body ) {
			return '';
		}

		$from_email = isset( $email_data['from'] ) ? trim( (string) $email_data['from'] ) : '';
		$from_name  = isset( $email_data['from_name'] ) ? trim( (string) $email_data['from_name'] ) : '';
		$from_line  = 'unknown sender';

		if ( '' !== $from_name && '' !== $from_email ) {
			$from_line = esc_html( $from_name ) . ' &lt;' . esc_html( $from_email ) . '&gt;';
		} elseif ( '' !== $from_name ) {
			$from_line = esc_html( $from_name );
		} elseif ( '' !== $from_email ) {
			$from_line = esc_html( $from_email );
		}

		$date_header = isset( $email_data['date'] ) ? trim( (string) $email_data['date'] ) : '';
		$intro_parts = array();

		if ( '' !== $date_header ) {
			$intro_parts[] = esc_html( $date_header );
		}

		$intro_parts[] = $from_line;

		$intro = 'On ' . implode( ', ', $intro_parts ) . ' wrote:';

		// Escape and format the original body for HTML
		$escaped_body = esc_html( $original_body );
		$escaped_body = nl2br( $escaped_body );

		return '<p style="color: #666; font-size: 13px;">' . $intro . '</p>
<blockquote style="margin: 10px 0; padding: 10px 15px; border-left: 3px solid #ccc; color: #555; font-size: 14px;">
' . $escaped_body . '
</blockquote>';
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
			/**
			 * Filter to map email addresses to WordPress users.
			 *
			 * This allows users to associate additional email addresses with their account
			 * without changing their primary email. Return a WP_User object to override the default
			 * email lookup, or null/false to use the standard behavior.
			 *
			 * @since 0.2.5
			 *
			 * @param WP_User|false|null $user       WP_User object if found, false if not found, or null for default lookup.
			 * @param string              $email      Email address being checked.
			 * @param array               $email_data Full email data from IMAP module.
			 */
			$user = apply_filters( 'pos_resolve_user_from_email', $user, $candidate, $email_data );
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

		// Set content type to HTML for formatted markdown emails
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

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
	 * Extract the latest assistant reply from the completed conversation.
	 *
	 * @param array $conversation Conversation history returned from complete_responses.
	 * @return string Assistant reply content.
	 */
	private function extract_assistant_reply( array $conversation ): string {
		foreach ( array_reverse( $conversation ) as $message ) {
			$role    = null;
			$content = null;

			if ( is_array( $message ) ) {
				$role    = $message['role'] ?? null;
				$content = $message['content'] ?? null;
			} elseif ( is_object( $message ) ) {
				$role    = $message->role ?? null;
				$content = $message->content ?? null;
			}

			if ( 'assistant' !== $role || null === $content ) {
				continue;
			}

			// Handle string content directly
			if ( is_string( $content ) ) {
				return trim( $content );
			}

			// Handle Responses API format: array of output items
			if ( is_array( $content ) ) {
				$text_parts = array();
				foreach ( $content as $item ) {
					$item = is_object( $item ) ? (array) $item : $item;
					if ( isset( $item['type'] ) && 'output_text' === $item['type'] && isset( $item['text'] ) ) {
						$text_parts[] = $item['text'];
					} elseif ( isset( $item['text'] ) ) {
						$text_parts[] = $item['text'];
					}
				}
				if ( ! empty( $text_parts ) ) {
					return trim( implode( "\n", $text_parts ) );
				}
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
