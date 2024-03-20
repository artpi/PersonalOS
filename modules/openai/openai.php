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
}
