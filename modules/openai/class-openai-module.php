<?php

class OpenAI_Module extends POS_Module {
	public $id          = 'openai';
	public $name        = 'OpenAI';
	public $description = 'OpenAI module';
	public $settings    = array(
		'api_key' => array(
			'type'  => 'text',
			'name'  => 'OpenAI API Key',
			'label' => 'You can get it from <a href="https://platform.openai.com/account/api-keys">here</a>',
		),
	);

	public function is_configured() {
		return ! empty( $this->settings['api_key'] );
	}

	public function register() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	public function rest_api_init() {
		register_rest_route(
			$this->rest_namespace,
			'/openai/chat/completions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat_api' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
		register_rest_route(
			$this->rest_namespace,
			'/openai/media/describe/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'media_describe' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public function media_describe( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		$media = wp_get_attachment_url( $id );
		if ( ! $media ) {
			return new WP_Error( 'no-media', 'Media not found' );
		}
		$result = $this->api_call( 'https://api.openai.com/v1/chat/completions', array(
			'model' => 'gpt-4o',
			'messages' => array(
				array( 'role' => 'system', 'content' => 'Please describe the image in detail. If there are items in the image, list them and describe them.' ),
				array( 'role' => 'user', 'content' => [ [
					'image_url' => $media,
				] ] ),
			),
		) );
		$this->log( 'Media describe result: ' . print_r( $result, true ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $result->error ) ) {
			return new WP_Error( 'openai-error', $result->error->message );
		}
		if ( ! isset( $result->choices[0]->message->content) ) {
			return new WP_Error( 'no-response', 'No response from OpenAI' );
		}
		$description = $result->choices[0]->message->content;
		wp_update_post( array(
			'ID' => $id,
			'post_content' => $description,
		) );
	
		return [ 'description' => $result->choices[0]->message->content ];
	}

	public function chat_api( WP_REST_Request $request ) {
		return $this->api_call( 'https://api.openai.com/v1/chat/completions', $request->get_json_params() );
	}

	public function api_call( $url, $data ) {
		$api_key = $this->get_setting( 'api_key' );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		$body     = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return json_decode( $body );
	}

	public function chat_completion( $messages = array(), $model = 'gpt-4o' ) {
		$data = array(
			'model'    => $model,
			'messages' => $messages,
		);
		$response = $this->api_call( 'https://api.openai.com/v1/chat/completions', $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response->choices[0]->message->content ) ) {
			return new WP_Error( 'no-response', 'No response from OpenAI' );
		}
		return $response->choices[0]->message->content;
	}
}
