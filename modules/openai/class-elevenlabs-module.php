<?php

class ElevenLabs_Module extends POS_Module {
	public $id          = 'elevenlabs';
	public $name        = 'Eleven Labs';
	public $description = 'Eleven Labs module';
	public $settings    = array(
		'api_key'  => array(
			'type'  => 'text',
			'name'  => 'Eleven labs API Key',
			'label' => '',
		),
	);

	public function is_configured() {
		return ! empty( $this->settings['api_key'] );
	}

	public function register() {
		//add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		$this->register_cli_command( 'generate', 'cli' );
	}

	/**
	 * Trigger generating a podcast episode
	 */
	public function cli( $args ) {
		print_r( $this->tts( "Now, lets shift to your strategy. We’ve got two major projects: building your new house and losing weight. These aren’t just tasks; these are opportunities for you to step up. With your house, every step of the process, from planning to construction, is a chance to take ownership. Break it down, every decision you make moves you closer to your vision. Schedule it out, talk to the right people, and be relentless in pushing forward. No hesitation, no delays—action, action, action!", 'jB108zg64sTcu1kCbN9L' ) );
	}

	public function get_voices() {
		$response = $this->api_call( 'https://api.elevenlabs.io/v1/voices', array() );
		return $response;
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


	public function api_call( $url, $data ) {
		$api_key = $this->get_setting( 'api_key' );

		$args = array(
			'timeout' => 120,
			'headers' => array(
				'xi-api-key' => $api_key,
				'Content-Type'  => 'application/json',
			),
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
			$args['method'] = 'POST';
		}

		$response = wp_remote_get(
			$url,
			$args,
		);
		$body     = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return json_decode( $body );
	}

	public function tts( $text, $voice = 'jB108zg64sTcu1kCbN9L', $data = array() ) {
		$api_key = $this->get_setting( 'api_key' );
		$file_name = 'speech-' . uniqid() . '.mp3';

		$response = wp_remote_post(
			'https://api.elevenlabs.io/v1/text-to-speech/' . $voice,
			array(
				'timeout'  => 360,
				'headers'  => array(
					'xi-api-key' => $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'     => wp_json_encode(
					array(
						'model_id' => 'eleven_turbo_v2_5',
						'text' => $text,
						'voice_settings' => array(
							'stability' => 0.1,
							'use_speaker_boost' => true,
							'similarity_boost' => 0,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tempfile = wp_tempnam();
		global $wp_filesystem;
		WP_Filesystem();
		$wp_filesystem->put_contents( $tempfile, wp_remote_retrieve_body( $response ) );

		$file = array(
			'name'     => wp_hash( time() ) . '-' . $file_name, // This hash is used to obfuscate the file names which should NEVER be exposed.
			'type'     => 'audio/mpeg',
			'tmp_name' => $tempfile,
			'error'    => 0,
			'size'     => filesize( $tempfile ),
		);

		$data['post_content'] = $text;
		$data['post_status'] = 'private';

		$media_id = media_handle_sideload( $file, 0, null, $data );

		return $media_id;
	}
}
