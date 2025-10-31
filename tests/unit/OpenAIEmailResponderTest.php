<?php
/**
 * Tests for the OpenAI email responder.
 *
 * @package PersonalOS
 */

use PHPUnit\Framework\MockObject\MockObject;

class POS_Test_EML_Parser {
	/**
	 * Parse a fixture from the tests/fixtures directory.
	 *
	 * @param string $filename Fixture filename.
	 * @return array Parsed email data compatible with IMAP module handlers.
	 */
	public static function parse_fixture( string $filename ): array {
		$path = dirname( __FILE__ ) . '/../fixtures/' . $filename;
		if ( ! file_exists( $path ) ) {
			throw new InvalidArgumentException( 'Fixture not found: ' . $filename );
		}

		return self::parse_file( $path );
	}

	/**
	 * Parse an .eml file.
	 *
	 * @param string $path Absolute path to .eml file.
	 * @return array
	 */
	public static function parse_file( string $path ): array {
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			throw new RuntimeException( 'Unable to read fixture: ' . $path );
		}

		$parts        = preg_split( "/\r?\n\r?\n/", $raw, 2 );
		$header_block = isset( $parts[0] ) ? $parts[0] : '';
		$body_block   = isset( $parts[1] ) ? $parts[1] : '';

		$headers = self::parse_headers( $header_block );

		$from_info = self::parse_address( isset( $headers['From'] ) ? $headers['From'] : '' );
		$reply_to  = array();

		if ( isset( $headers['Reply-To'] ) ) {
			$reply_to_address = self::parse_address( $headers['Reply-To'] );
			if ( '' !== $reply_to_address['email'] ) {
				$reply_to[] = $reply_to_address['email'];
			}
		}

		if ( empty( $reply_to ) && '' !== $from_info['email'] ) {
			$reply_to[] = $from_info['email'];
		}

		$body = self::extract_body( $body_block, isset( $headers['Content-Type'] ) ? $headers['Content-Type'] : '' );

		return array(
			'subject'    => isset( $headers['Subject'] ) ? $headers['Subject'] : '',
			'from'       => $from_info['email'],
			'from_name'  => $from_info['name'],
			'body'       => $body,
			'message_id' => isset( $headers['Message-ID'] ) ? $headers['Message-ID'] : '',
			'references' => isset( $headers['References'] ) ? $headers['References'] : '',
			'reply_to'   => $reply_to,
		);
	}

	/**
	 * Parse header block into associative array.
	 *
	 * @param string $header_block Header block.
	 * @return array
	 */
	protected static function parse_headers( string $header_block ): array {
		$headers     = array();
		$lines       = preg_split( "/\r?\n/", $header_block );
		$current_key = '';

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			if ( preg_match( '/^[\t ]+/', $line ) && '' !== $current_key ) {
				$headers[ $current_key ] .= ' ' . trim( $line );
				continue;
			}

			if ( false !== strpos( $line, ':' ) ) {
				list( $key, $value ) = explode( ':', $line, 2 );
				$current_key        = trim( $key );
				$headers[ $current_key ] = trim( $value );
			}
		}

		return $headers;
	}

	/**
	 * Parse address header.
	 *
	 * @param string $header Header value.
	 * @return array{name:string,email:string}
	 */
	protected static function parse_address( string $header ): array {
		$header = trim( $header );
		if ( '' === $header ) {
			return array(
				'name'  => '',
				'email' => '',
			);
		}

		if ( preg_match( '/^"?([^"<]+)"?\s*<([^>]+)>$/', $header, $matches ) ) {
			return array(
				'name'  => trim( $matches[1] ),
				'email' => trim( $matches[2] ),
			);
		}

		return array(
			'name'  => '',
			'email' => $header,
		);
	}

	/**
	 * Extract body content, handling multipart messages.
	 *
	 * @param string $body_block Body block.
	 * @param string $content_type_header Content-Type header.
	 * @return string
	 */
	protected static function extract_body( string $body_block, string $content_type_header ): string {
		if ( '' === $body_block ) {
			return '';
		}

		if ( stripos( $content_type_header, 'multipart/' ) === 0 && preg_match( '/boundary="([^"]+)"/', $content_type_header, $matches ) ) {
			$boundary = $matches[1];
			$parts    = explode( '--' . $boundary, $body_block );

			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' === $part || '--' === $part ) {
					continue;
				}

				$part = ltrim( $part, "\r\n" );
				if ( substr( $part, -2 ) === '--' ) {
					$part = substr( $part, 0, -2 );
				}

				$sub_parts    = preg_split( "/\r?\n\r?\n/", $part, 2 );
				$part_headers = isset( $sub_parts[0] ) ? self::parse_headers( $sub_parts[0] ) : array();
				$part_body    = isset( $sub_parts[1] ) ? $sub_parts[1] : '';

				$content_type = isset( $part_headers['Content-Type'] ) ? $part_headers['Content-Type'] : 'text/plain';
				if ( stripos( $content_type, 'text/plain' ) === 0 ) {
					$encoding = isset( $part_headers['Content-Transfer-Encoding'] ) ? strtolower( $part_headers['Content-Transfer-Encoding'] ) : '';
					return self::decode_part_body( $part_body, $encoding );
				}
			}

			// Fallback: return first part body if no text/plain found.
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( '' === $part || '--' === $part ) {
					continue;
				}
				$part      = ltrim( $part, "\r\n" );
				$sub_parts = preg_split( "/\r?\n\r?\n/", $part, 2 );
				$part_body = isset( $sub_parts[1] ) ? $sub_parts[1] : '';
				return trim( $part_body );
			}
		}

		return trim( $body_block );
	}

	/**
	 * Decode a MIME part body according to encoding.
	 *
	 * @param string $body Body text.
	 * @param string $encoding Encoding name.
	 * @return string
	 */
	protected static function decode_part_body( string $body, string $encoding ): string {
		$body = rtrim( $body );

		switch ( $encoding ) {
			case 'base64':
				$decoded = base64_decode( $body, true );
				return false === $decoded ? trim( $body ) : trim( $decoded );
			case 'quoted-printable':
				return trim( quoted_printable_decode( $body ) );
			default:
				return trim( $body );
		}
	}
}

class POS_Test_IMAP_Module_Spy {
	/**
	 * Module identifier.
	 *
	 * @var string
	 */
	public $id = 'imap';

	/**
	 * Captured send operations.
	 *
	 * @var array
	 */
	public $sent = array();

	/**
	 * Record the attempted email send.
	 *
	 * @param string $to Recipient.
	 * @param string $subject Subject.
	 * @param string $body Body.
	 * @param array  $headers Headers.
	 * @return bool Always true for test purposes.
	 */
	public function send_email( $to, $subject, $body, $headers = array() ) {
		$this->sent[] = array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
			'headers' => $headers,
		);

		return true;
	}
}

/**
 * @group openai
 */
class OpenAI_Email_Responder_Test extends WP_UnitTestCase {

	/**
	 * Original module list.
	 *
	 * @var array
	 */
	protected $original_modules = array();

	/**
	 * IMAP spy instance.
	 *
	 * @var POS_Test_IMAP_Module_Spy
	 */
	protected $imap_spy;

	/**
	 * Responder under test.
	 *
	 * @var OpenAI_Email_Responder
	 */
	protected $responder;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_modules = POS::$modules;
		$this->imap_spy         = new POS_Test_IMAP_Module_Spy();

		$filtered_modules = array();
		foreach ( $this->original_modules as $module ) {
			if ( isset( $module->id ) && 'imap' === $module->id ) {
				continue;
			}
			$filtered_modules[] = $module;
		}
		$filtered_modules[] = $this->imap_spy;

		POS::$modules = $filtered_modules;
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		if ( $this->responder ) {
			remove_action( 'pos_imap_new_email', array( $this->responder, 'handle_new_email' ), 20 );
			$this->responder = null;
		}

		POS::$modules = $this->original_modules;

		parent::tearDown();
	}

	/**
	 * Create a responder with a mocked OpenAI module.
	 *
	 * @param callable $expectation Callback to configure expectations.
	 * @return OpenAI_Email_Responder
	 */
	protected function create_responder( callable $expectation ) {
		/** @var OpenAI_Module&MockObject $openai_module */
		$openai_module = $this->getMockBuilder( OpenAI_Module::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'complete_backscroll', 'log' ) )
			->getMock();

		$openai_module->method( 'log' )->willReturn( null );

		$expectation( $openai_module );

		$this->responder = new OpenAI_Email_Responder( $openai_module );

		return $this->responder;
	}

	/**
	 * Load an email fixture.
	 *
	 * @param string $fixture Fixture filename.
	 * @param array  $overrides Overrides for the parsed data.
	 * @return array
	 */
	protected function load_email_fixture( string $fixture, array $overrides = array() ): array {
		$email_data = POS_Test_EML_Parser::parse_fixture( $fixture );
		return array_merge( $email_data, $overrides );
	}

	/**
	 * Test that an AI generated reply is sent and original message appended.
	 */
	public function test_handle_new_email_sends_ai_reply() {
		$email_data    = $this->load_email_fixture( 'original_msg.eml' );
		$expected_body = $email_data['body'];

		$this->create_responder(
			function( MockObject $openai_module ) use ( $expected_body ) {
				$openai_module
					->expects( $this->once() )
					->method( 'complete_backscroll' )
					->with( $this->callback(
						function( $backscroll ) use ( $expected_body ) {
							self::assertIsArray( $backscroll );
							self::assertCount( 1, $backscroll );
							self::assertSame( 'user', $backscroll[0]['role'] );
							self::assertStringContainsString( 'From: Artur Piszek <artur.piszek@gmail.com>', $backscroll[0]['content'] );
							self::assertStringContainsString( 'Body:', $backscroll[0]['content'] );
							self::assertStringContainsString( $expected_body, $backscroll[0]['content'] );
							return true;
						}
					) )
					->willReturn(
						array(
							array(
								'role'    => 'assistant',
								'content' => 'Greetings Artur! Here is what you need to know.',
							),
						)
					);
			}
		);

		$this->responder->handle_new_email( $email_data );

		$this->assertCount( 1, $this->imap_spy->sent );
		$sent = $this->imap_spy->sent[0];

		$this->assertSame( 'artur.piszek@gmail.com', $sent['to'] );
		$this->assertSame( 'Re: (No Subject)', $sent['subject'] );
		$this->assertStringStartsWith( 'Greetings Artur! Here is what you need to know.', $sent['body'] );
		$this->assertStringContainsString( $expected_body, $sent['body'] );
		$this->assertContains( 'In-Reply-To: <CABPa1J96sEuS68V9czgyQybQH0f50t-=ESper-d6yHB55pfDjg@mail.gmail.com>', $sent['headers'] );
		$this->assertContains( 'References: <CABPa1J96sEuS68V9czgyQybQH0f50t-=ESper-d6yHB55pfDjg@mail.gmail.com>', $sent['headers'] );
		$this->assertContains( 'Reply-To: artur.piszek@gmail.com', $sent['headers'] );
	}

	/**
	 * Test that the original body is preserved verbatim and Reply-To header targets the sender.
	 */
	public function test_handle_new_email_preserves_original_body_and_reply_to() {
		$email_data = $this->load_email_fixture( 'original_msg.eml' );
		$assistant  = "Here are your current TODOs:\n\n1. Example";

		$this->create_responder(
			function( MockObject $openai_module ) use ( $assistant ) {
				$openai_module
					->expects( $this->once() )
					->method( 'complete_backscroll' )
					->willReturn(
						array(
							array(
								'role'    => 'assistant',
								'content' => $assistant,
							),
						)
					);
			}
		);

		$this->responder->handle_new_email( $email_data );

		$this->assertCount( 1, $this->imap_spy->sent );
		$sent = $this->imap_spy->sent[0];

		$expected_body = $assistant . "\n\n" . $email_data['body'] . "\n";
		$this->assertSame( $expected_body, $sent['body'] );
		$this->assertContains( 'Reply-To: artur.piszek@gmail.com', $sent['headers'] );
	}

	/**
	 * Test that a fallback response is used when the AI call fails.
	 */
	public function test_handle_new_email_uses_fallback_when_ai_errors() {
		$email_data = $this->load_email_fixture( 'original_msg.eml', array( 'body' => '' ) );

		$this->create_responder(
			function( MockObject $openai_module ) {
				$openai_module
					->expects( $this->once() )
					->method( 'complete_backscroll' )
					->willReturn( new WP_Error( 'openai', 'API failure' ) );
			}
		);

		$this->responder->handle_new_email( $email_data );

		$this->assertCount( 1, $this->imap_spy->sent );
		$sent = $this->imap_spy->sent[0];

		$this->assertSame( "Thank you for your message.\n", $sent['body'] );
	}

	/**
	 * Test that an empty assistant reply triggers the fallback message and subject defaults.
	 */
	public function test_handle_new_email_uses_fallback_when_ai_returns_empty_message() {
		$email_data = $this->load_email_fixture( 'original_msg.eml', array( 'subject' => '' ) );

		$this->create_responder(
			function( MockObject $openai_module ) {
				$openai_module
					->expects( $this->once() )
					->method( 'complete_backscroll' )
					->willReturn(
						array(
							array(
								'role'    => 'assistant',
								'content' => '',
							),
						)
					);
			}
		);

		$this->responder->handle_new_email( $email_data );

		$this->assertCount( 1, $this->imap_spy->sent );
		$sent = $this->imap_spy->sent[0];

		$this->assertSame( 'Re: (No Subject)', $sent['subject'] );
		$this->assertStringStartsWith( 'Thank you for your message.', $sent['body'] );
		$this->assertStringContainsString( $email_data['body'], $sent['body'] );
	}
}
