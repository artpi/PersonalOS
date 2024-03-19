<?php

class POS_Transcription extends POS_Module {
    public $id = 'transcription';
    public $name = "Transcription";
    public $description = "Transcription module";
    private $openai = null;
    private $notes = null;

    function __construct( $openai, $notes ) {
        $this->openai = $openai;
        $this->notes = $notes;
        $this->register_meta( 'pos_transcribe', 'attachment' );
        add_action( 'pos_' . $this->id , array( $this, 'transcribe' ) );
    }

    public function transcribe( $attachment_id ) {
        $file = wp_get_attachment_metadata( $attachment_id );
        $mime_type = explode( '/', $file['mime_type'] );
        if ( $mime_type[0] !== 'audio' ) {
            error_log( 'Transcription: not an audio file');
            return;
        }
        $mb = $file['filesize'] / 1024 / 1024;
        if ( $mb > 25 ) {
            error_log( 'Transcription: file too big');
            return;
        }
        // Upload file using curl to openai whisper
        
        update_post_meta( $attachment_id, 'pos_transcribe', time() );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->openai->get_setting( 'api_key' ),
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        $filePath = get_attached_file( $attachment_id );
        $cfile = new CURLFile($filePath, $file['mime_type']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'file' => $cfile,
            'model' => 'whisper-1',
            'response_format' => 'json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the cURL session
        $response = curl_exec($ch);

        // Close the cURL session
        curl_close( $ch );
        $response = json_decode( $response );

        if ( ! $response || ! isset( $response->text ) ) {
            error_log( 'Transcription: no response or bad response ' );
            echo 'Transcription failed';
            return;
        }

        // Save transcription to the media itself.
        $post = get_post( $attachment_id );
        $post->post_content = $response->text;
        wp_update_post( $post );

        // Save a note.
        $audio_block = get_comment_delimited_block_content(
            'audio',
            [
                'id' => $attachment_id,
            ],
            '<figure class="wp-block-audio"><audio controls src="'.wp_get_attachment_url( $attachment_id ).'"></audio></figure>'
        );
        $this->notes->create( 'Transcription', "{$audio_block}<p>{$response->text}</p>", true );
    }
}
