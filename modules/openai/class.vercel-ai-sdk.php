<?php

class Vercel_AI_SDK {

	public static function sendHttpStreamHeaders(): void {
		// ---------- HTTP headers ----------
		header( 'Access-Control-Allow-Origin: *' ); // Allow all origins
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' ); // Allow specific methods
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Vercel-AI-Data-Stream' ); // Allow specific headers

		// Handle preflight OPTIONS request
		if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
			exit( 0 );
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'x-vercel-ai-data-stream: v1' );   // let the client know which protocol this is
		header( 'Cache-Control: no-cache' );

		// Turn off PHP's output buffering so each echo is sent immediately
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		ob_implicit_flush( true );
	}

	private function sendStreamPart( string $typePrefix, string $jsonPayload ): void {
		echo $typePrefix . $jsonPayload . "\n";
		flush(); // Push to socket
	}

	public function sendText( string $text ): void {
		$this->sendStreamPart( '0:', json_encode( $text ) );
	}

	public function sendReasoning( string $reasoningText ): void {
		$this->sendStreamPart( 'g:', json_encode( $reasoningText ) );
	}

	public function sendRedactedReasoning( string $data ): void {
		$this->sendStreamPart( 'i:', json_encode( array( 'data' => $data ) ) );
	}

	public function sendReasoningSignature( string $signature ): void {
		$this->sendStreamPart( 'j:', json_encode( array( 'signature' => $signature ) ) );
	}

	/**
	 * @param array{sourceType: string, id: string, url: string, title: string} $sourceData
	 */
	public function sendSource( array $sourceData ): void {
		$this->sendStreamPart( 'h:', json_encode( $sourceData ) );
	}

	/**
	 * @param array{data: string, mimeType: string} $fileData
	 */
	public function sendFile( array $fileData ): void {
		$this->sendStreamPart( 'k:', json_encode( $fileData ) );
	}

	/**
	 * @param list<mixed>|array<string, mixed> $jsonData
	 */
	public function sendData( array $jsonData ): void {
		$this->sendStreamPart( '2:', json_encode( $jsonData ) );
	}

	/**
	 * @param list<mixed>|array<string, mixed> $jsonData
	 */
	public function sendMessageAnnotation( array $jsonData ): void {
		$this->sendStreamPart( '8:', json_encode( $jsonData ) );
	}

	public function sendError( string $errorMessage ): void {
		$this->sendStreamPart( '3:', json_encode( $errorMessage ) );
	}

	public function startToolCallStream( string $toolCallId, string $toolName ): void {
		$payload = array(
			'toolCallId' => $toolCallId,
			'toolName'   => $toolName,
		);
		$this->sendStreamPart( 'b:', json_encode( $payload ) );
	}

	public function sendToolCallDelta( string $toolCallId, string $argsTextDelta ): void {
		$payload = array(
			'toolCallId'    => $toolCallId,
			'argsTextDelta' => $argsTextDelta,
		);
		$this->sendStreamPart( 'c:', json_encode( $payload ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function sendToolCall( string $toolCallId, string $toolName, array $args ): void {
		$payload = array(
			'toolCallId' => $toolCallId,
			'toolName'   => $toolName,
			'args'       => $args,
		);
		$this->sendStreamPart( '9:', json_encode( $payload ) );
	}

	/**
	 * @param mixed $result The result of the tool call (string or array).
	 */
	public function sendToolResult( string $toolCallId, mixed $result ): void {
		$payload = array(
			'toolCallId' => $toolCallId,
			'result'     => $result,
		);
		$this->sendStreamPart( 'a:', json_encode( $payload ) );
	}

	public function startStep( string $messageId ): void {
		$this->sendStreamPart( 'f:', json_encode( array( 'messageId' => $messageId ) ) );
	}

	/**
	 * @param array{promptTokens: int, completionTokens: int} $usage
	 */
	public function finishStep( string $finishReason, array $usage, bool $isContinued ): void {
		$payload = array(
			'finishReason' => $finishReason,
			'usage'        => $usage,
			'isContinued'  => $isContinued,
		);
		$this->sendStreamPart( 'e:', json_encode( $payload ) );
	}

	/**
	 * @param array{promptTokens: int, completionTokens: int} $usage
	 */
	public function finishMessage( string $finishReason, array $usage ): void {
		$payload = array(
			'finishReason' => $finishReason,
			'usage'        => $usage,
		);
		$this->sendStreamPart( 'd:', json_encode( $payload ) );
	}
}
