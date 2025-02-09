<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slack_Module extends POS_Module {
	public $id = 'slack';
	public $name = 'Slack Interface';
	public $description = 'Interface for Slack to trigger OpenAI completions.';
	public $settings = array(
		'slack_token' => array(
			'type'  => 'text',
			'name'  => 'Slack Verification Token',
			'label' => 'Enter your Slack verification token.',
		),
		'api_token'   => array(
			'type'  => 'text',
			'name'  => 'Slack API Token',
			'label' => 'Enter your Slack API token (xoxp-...).',
		),
	);

	public function register(): void {
		if ( ! $this->get_setting( 'slack_token' ) || ! $this->get_setting( 'api_token' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
		add_action( 'pos_process_slack_callback', array( $this, 'pos_process_slack_callback' ) );

	}

	public function register_rest_endpoints(): void {
		register_rest_route(
			$this->rest_namespace,
			'/slack/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'slack_callback_handler' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handler for Slack callback. It validates the token, responds immediately, and schedules background processing.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function slack_callback_handler( WP_REST_Request $request ): mixed {
		$payload = $request->get_json_params();

		// Handle Slack URL verification challenge
		if ( isset( $payload['type'] ) && 'url_verification' === $payload['type'] ) {
			return rest_ensure_response( array( 'challenge' => $payload['challenge'] ) );
		}

		//Validate token using the token saved in settings
		if ( empty( $payload['token'] ) || $payload['token'] !== $this->get_setting( 'slack_token' ) ) {
			$this->log( 'Invalid Slack token: ' . $payload['token'] . '/' . $this->get_setting( 'slack_token' ) );
			return new WP_Error( 'invalid_token', 'Invalid Slack token', array( 'status' => 403 ) );
		}

		// Immediate response to Slack
		$response = array( 'text' => 'Processing your request...' );

		// Schedule a background process to handle the Slack callback
		wp_schedule_single_event( time(), 'pos_process_slack_callback', array( $payload ) );
		// This will trigger the cron job
		wp_remote_post(
			site_url( '/wp-cron.php' ),
			array(
				'timeout'   => 0.01,   // Super short timeout
				'blocking'  => false,  // Don't wait for a response
				'sslverify' => false,  // Skip SSL verification if needed
			)
		);

		return rest_ensure_response( $response );
	}

	public function slack_gpt_retrieve_backscroll( $thread, $channel ) {
		$response = wp_remote_get(
			"https://slack.com/api/conversations.replies?channel={$channel}&ts={$thread}",
			array(
				'headers' => array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Authorization' => 'Bearer ' . $this->get_setting( 'api_token' ),
				),
			)
		);
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		return array_map( array( $this, 'slack_message_to_gpt_message' ), $data->messages ?? array() );
	}

	public function slack_message_to_gpt_message( $message ) {
		$text = preg_replace( '#\s?<@[0-9A-Z]+>\s?#is', '', $message->text );
		$role = isset( $message->bot_id ) ? 'assistant' : 'user';
		return array(
			'role'    => $role,
			'content' => $text,
		);
	}

	/**
	 * Abstraction method to process OpenAI chat completions. It calls the OpenAI module's chat_assistant functionality.
	 *
	 * @param array $messages The messages array for completion.
	 * @return array
	 */
	public function process_chat_completion( array $messages ): array {
		$openai = POS::get_module_by_id( 'openai' );
		if ( ! $openai || ! method_exists( $openai, 'chat_assistant' ) ) {
			// Log error if OpenAI module not available
			return array();
		}

		return $openai->complete_backscroll( $messages );
	}

	public function slack_gpt_respond_in_thread( $ts, $channel, $response ) {
		// Convert markdown URLs to Slack format
		$response = preg_replace(
			'/\[([^\]]+)\]\(([^\)]+)\)/',
			'<$2|$1>',
			$response
		);
		$data = array(
			'channel'   => $channel,
			'thread_ts' => $ts,
			'mrkdwn'    => true,
			'text'      => $response,
		);

		$res = wp_remote_post(
			'https://slack.com/api/chat.postMessage',
			array(
				'headers' => array(
					'Content-type'  => 'application/json; charset=utf-8',
					'Authorization' => 'Bearer ' . $this->get_setting( 'api_token' ),
				),
				'body'    => json_encode( $data ),
			)
		);

	}


	public function pos_process_slack_callback( array $payload ): void {
		POS::get_module_by_id( 'notes' )->switch_to_user();
		$this->log( 'pos_process_slack_callback:' . wp_json_encode( $payload ) );
		$backscroll = $this->slack_gpt_retrieve_backscroll( $payload['event']['thread_ts'] ?? $payload['event']['ts'], $payload['event']['channel'] );
		$backscroll = array_map(
			function( $message ) {
				$message['old'] = true;
				return $message;
			},
			$backscroll
		);
		$response = $this->process_chat_completion( $backscroll );
		foreach ( $response as $message ) {
			if ( ! is_array( $message ) && $message->role === 'assistant' ) {
				$this->slack_gpt_respond_in_thread( $payload['event']['thread_ts'] ?? $payload['event']['ts'], $payload['event']['channel'], $message->content );
			}
		}
	}
}
