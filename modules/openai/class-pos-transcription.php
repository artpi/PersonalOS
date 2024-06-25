<?php

//phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
//phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
//phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
//phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close

class POS_Transcription extends POS_Module {
	public $id          = 'transcription';
	public $name        = 'Transcription';
	public $description = 'Transcription module';
	private $openai     = null;
	private $notes      = null;

	public function __construct( $openai, $notes ) {
		$this->openai = $openai;
		$this->notes  = $notes;
		$this->register_meta( 'pos_transcribe', 'attachment' );
		add_action( 'pos_' . $this->id, array( $this, 'transcribe' ) );
		add_action( 'add_attachment', array( $this, 'schedule_transcription' ), 100 );
		add_action( 'edit_attachment', array( $this, 'schedule_transcription' ), 100 );
		$this->register_cli_command( 'transcribe', 'cli' );
	}

	public function schedule_transcription( $attachment_id ) {
		if ( $this->transcription_checks( $attachment_id ) !== true ) {
			return;
		}
		$this->log( 'Scheduling transcription for ' . $attachment_id );
		wp_schedule_single_event( time(), 'pos_' . $this->id, array( $attachment_id ) );
	}

	public function transcription_checks( $attachment_id ) {
		$last_transcription = get_post_meta( $attachment_id, 'pos_transcribe', true );

		if ( ! $last_transcription ) {
			return 'This is not scheduled for transcription.';
		}

		if ( $last_transcription && is_numeric( $last_transcription ) && $last_transcription > 1000 ) {
			return ( 'This file was already transcribed on ' . gmdate( 'Y-m-d', $last_transcription ) );
		}

		$file = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $file ) ) {
			return 'No attachment metadata.';
		}
		$mime_type = explode( '/', $file['mime_type'] );
		if ( $mime_type[0] !== 'audio' ) {
			return ( 'Transcription: not an audio file' );
		}
		$mb = $file['filesize'] / 1024 / 1024;
		if ( $mb > 25 ) {
			return ( 'Transcription: file too big' );
		}
		return true;
	}

	/**
	 * Force transcribe attachment ID
	 * <id>
	 * : ID of the attachment to transcribe.
	 */
	public function cli( $args ) {
		return $this->transcribe( $args[0] );
	}

	public function transcribe( $attachment_id ) {
		$this->log( 'Transcribing ' . $attachment_id );
		$checks = $this->transcription_checks( $attachment_id );
		if ( $checks !== true ) {
			$this->log( $checks );
			return;
		}
		$file = wp_get_attachment_metadata( $attachment_id );
		update_post_meta( $attachment_id, 'pos_transcribe', time() );
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions' );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . $this->openai->get_setting( 'api_key' ),
			)
		);
		curl_setopt( $ch, CURLOPT_POST, 1 );
		$file_path = get_attached_file( $attachment_id );
		$cfile = new CURLFile( $file_path, $file['mime_type'] );
		curl_setopt(
			$ch,
			CURLOPT_POSTFIELDS,
			array(
				'file'            => $cfile,
				'model'           => 'whisper-1',
				'response_format' => 'json',
			)
		);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

		// Execute the cURL session
		$response = curl_exec( $ch );

		// Close the cURL session
		curl_close( $ch );
		$response = json_decode( $response );

		if ( ! $response || ! isset( $response->text ) ) {
			$this->log( 'Transcription: no response or bad response ' );
			echo 'Transcription failed';
			return;
		}

		// Save transcription to the media itself.
		$post               = get_post( $attachment_id );
		$post->post_content = $response->text;
		wp_update_post( $post );

		// Is this a child post?
		if ( empty( $post->post_parent ) ) {
			// Save a note.
			$audio_block = get_comment_delimited_block_content(
				'audio',
				array(
					'id' => $attachment_id,
				),
				'<figure class="wp-block-audio"><audio controls src="' . wp_get_attachment_url( $attachment_id ) . '"></audio></figure>'
			);
			$this->notes->create( 'Transcription', "{$audio_block}<p>{$response->text}</p>", true );
			//TODO: Add recording as note child?
			return;
		} else {
			// We have to figure out where to insert the transcription to the post.
			// Let's append for now.
			$parent = get_post( $post->post_parent );
			$new_content = $this->openai->chat_completion(
				array(
					array(
						'role'    => 'system',
						'content' => <<<EOF
					You are a helpful secretary.
					You will get a html file with a template and a transcription of a recording.
					Format the attached transcription as if it was dictated to you. Make complete sentences out of rambly speech, but otherwise try to keep it in bullet points.
					Correct grammar and spelling, and break it into bullet points where needed.
					Fit the different pieces of trascription into the template where appropriate.
					Format for readability.
					Do not delete any data from the HTML template.
					Return ONLY valid HTML to replace the template. DO NOT include any markdown.
					Please use the language from the transcription.
					EOF,
					),
					array(
						'role'    => 'user',
						'content' => $parent->post_content,
					),
					array(
						'role'    => 'user',
						'content' => "Transcription: \n\n" . $response->text,
					),
				)
			);
			if ( is_wp_error( $new_content ) ) {
				$this->log( 'Chat completion failed [' . $attachment_id . ']: ' . $new_content->get_error_message(), E_USER_WARNING );
				return;
			}
			$parent->post_content = $new_content;
			wp_update_post( $parent );
		}

	}
}
