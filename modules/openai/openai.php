<?php

Class OpenAI_Module extends POS_Module {
    public $id = 'openai';
    public $name = "OpenAI";
    public $description = "OpenAI module";
    public $settings = [
        'api_key' => [
            'type' => 'text',
            'name' => 'OpenAI API Key',
            'label' => 'You can get it from <a href="https://platform.openai.com/account/api-keys">here</a>',
        ],
    ];

    public function is_configured() {
        return ! empty( $this->settings['api_key'] );
    }

    public function register() {
        add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
    }

    public function rest_api_init() {
        register_rest_route( $this->rest_namespace, '/openai/chat/completions', [
            'methods' => 'POST',
            'callback' => [ $this, 'chat_api' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }
    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    public function chat_api( WP_REST_Request $request ) {
        $api_key = $this->get_setting( 'api_key' );
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = $request->get_json_params();

        $response = wp_remote_post( $url, [
            'timeout'     => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode( $data ),
        ] );
        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body );
    }
}
