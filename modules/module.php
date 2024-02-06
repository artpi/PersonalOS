<?php

Class POS_Module {
    public $id = 'module_id';
    public $name = "Module Name";
    public $description = "";
    public $settings = [];

    function get_module_description() {
        return $this->description;
    }

    function get_settings_fields() {
        return $this->settings;
    }

    function get_setting_option_name( $setting_id ) {
        return $this->id . '_' . $setting_id;
    }

    function get_setting( $id ) {
        return get_option( $this->get_setting_option_name( $id ) );
    }

    function __construct() {
        $this->register();
    }

    function register() {

    }

    function register_post_type( $args = [] ) {
        $labels = array(
            'name' => $this->name,
            'singular_name' => $this->name,
            'add_new' => 'Add New',
        );
        if (isset($args['labels'])) {
            $labels = array_merge($labels, $args['labels']);
            unset($args['labels']);
        }

        $defaults = array_merge(
            array(
                'show_in_rest' => true,
                'public' => false,
                'show_ui' => true,
                'has_archive' => false,
                //'show_in_menu' => 'pos',
                'rest_namespace' => 'pos/' . $this->id,
                'labels' => $labels,
                'supports' => array( 'title', 'editor', 'custom-fields' ),
                'taxonomies' => array( 'category', 'post_tag' ),
            ),
            $args
        );
        register_post_type( $this->id,$defaults );
    }
}
