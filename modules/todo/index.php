<?php

class TODO_Module extends POS_Module {
    public $id = 'todo';
    public $name = "TODO";

    function register() {
        $this->register_post_type([
            'supports' => array( 'title', 'excerpt', 'custom-fields' ),
            'taxonomies' => [],
        ] );
        register_taxonomy( 'todo_category', $this->id, array(
            'label'                 => 'TODO Context',
            'public'                => false,
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'show_in_rest'          => true,
            'rest_namespace'        => $this->rest_namespace,
            'rewrite'               => array( 'slug' => 'todo_category' ),
        ) );
    }

}