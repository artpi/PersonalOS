<?php

Class POS_Module {
    public $id = 'module_id';
    public $name = "Module Name";

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
                'rest_namespace' => 'pos/' . $this->id,
                'labels' => $labels,
            ),
            $args
        );
        register_post_type( $this->id,$defaults );
    }
}
