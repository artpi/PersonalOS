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
		add_action( 'pos_imap_new_email', array( $this, 'handle_new_email' ), 20, 1 );
	}

	/**
	 * Handle new incoming emails and send a thank-you response.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 */
	public function handle_new_email( array $email_data ): void {
		$recipient = $this->get_reply_address( $email_data );
		if ( empty( $recipient ) ) {
			$this->module->log( 'Auto-reply skipped: no valid recipient.' );
			return;
		}

		$imap_module = POS::get_module_by_id( 'imap' );
		if ( ! $imap_module || ! method_exists( $imap_module, 'send_email' ) ) {
			$this->module->log( 'Auto-reply skipped: IMAP module not available.', E_USER_WARNING );
			return;
		}

		$subject = $this->prepare_subject( isset( $email_data['subject'] ) ? $email_data['subject'] : '' );
		$body    = $this->prepare_body( $email_data );
		$headers = $this->prepare_headers( $email_data );

		$sent = $imap_module->send_email( $recipient, $subject, $body, $headers );

		if ( $sent ) {
			$this->module->log( 'Auto-reply sent to ' . $recipient );
		} else {
			$this->module->log( 'Auto-reply failed for ' . $recipient, E_USER_ERROR );
		}
	}

	/**
	 * Prepare the response subject, ensuring a proper "Re:" prefix.
	 *
	 * @param string $subject Original subject.
	 * @return string Reply subject.
	 */
	private function prepare_subject( string $subject ): string {
		$subject = trim( $subject );
		if ( '' === $subject ) {
			return 'Re: (No Subject)';
		}
		if ( preg_match( '/^Re:/i', $subject ) ) {
			return $subject;
		}
		return 'Re: ' . $subject;
	}

	/**
	 * Prepare the response body with a thank you and quoted original message.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return string Response body.
	 */
	private function prepare_body( array $email_data ): string {
		$body  = 'Thank you for your message.' . "\n\n";
		$intro = $this->build_original_intro( $email_data );
		if ( '' !== $intro ) {
			$body .= $intro . "\n";
		}
		if ( ! empty( $email_data['body'] ) ) {
			$body .= $this->quote_body( (string) $email_data['body'] );
		}
		return rtrim( $body ) . "\n";
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

		if ( ! empty( $email_data['reply_to'] ) ) {
			$reply_to = is_array( $email_data['reply_to'] ) ? $email_data['reply_to'] : explode( ',', $email_data['reply_to'] );
			$reply_to = array_filter( array_map( 'trim', $reply_to ) );
			if ( ! empty( $reply_to ) ) {
				$headers[] = 'Reply-To: ' . implode( ', ', $reply_to );
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
	 * Build the intro line referencing the original email.
	 *
	 * @param array $email_data Email data from the IMAP module.
	 * @return string Intro line.
	 */
	private function build_original_intro( array $email_data ): string {
		$from      = isset( $email_data['from'] ) ? $email_data['from'] : '';
		$from_name = isset( $email_data['from_name'] ) ? $email_data['from_name'] : '';
		$display   = $from;

		if ( '' !== $from_name && '' !== $from ) {
			$display = $from_name . ' <' . $from . '>';
		} elseif ( '' !== $from_name ) {
			$display = $from_name;
		}

		if ( '' === $display ) {
			return '';
		}

		$date = isset( $email_data['date'] ) ? trim( $email_data['date'] ) : '';
		if ( '' !== $date ) {
			return 'On ' . $date . ', ' . $display . ' wrote:';
		}

		return $display . ' wrote:';
	}

	/**
	 * Quote the original email body.
	 *
	 * @param string $body Original body.
	 * @return string Quoted body.
	 */
	private function quote_body( string $body ): string {
		$body  = str_replace( array( "\r\n", "\r" ), "\n", $body );
		$lines = explode( "\n", $body );
		$lines = array_map(
			function( $line ) {
				$line = rtrim( $line );
				return '> ' . $line;
			},
			$lines
		);
		return implode( "\n", $lines );
	}
}

