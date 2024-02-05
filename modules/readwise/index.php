<?php

Class External_Service_Module extends POS_Module {
    public $id = 'external_service';
    public $name = "External Service";

    function sync() {}
}


Class Readwise extends External_Service_Module {
    public $id = 'readwise';
    public $name = "Readwise";
    public $description = "Syncs with readwise service";

    public $settings = [
        'token' => [
            'type' => 'text',
            'name' => 'Readwise API Token',
            'label' => 'You can get it from <a href="https://readwise.io/access_token">here</a>',
        ],
    ];

    public $notes_module = null;

    function __construct( $notes_module ) {
        $this->notes_module = $notes_module;
    }

    function sync() {
        $token = $this->get_setting( 'token' );
        if( ! $token ) {
            return false;
        }
        $request = wp_remote_get(
            'https://readwise.io/api/v2/export/?updatedAfter=2024-02-01T16:41:53.186Z',
            array(
                'headers' => array(
                    'Authorization' => 'Token ' . $token
                )
            )
        );
        if ( is_wp_error( $request ) ) {
            return false; // Bail early
        }
        
        $body = wp_remote_retrieve_body( $request );
        $data = json_decode( $body );
        return $data;
    }
}
