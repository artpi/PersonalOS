<?php

class Notes_Module extends POS_Module {
    public $id = 'notes';
    public $name = "Notes";

    function register() {
        $this->register_post_type();
    }

    function create( $data ) {
        $post = array(
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_status' => 'publish',
            'post_type' => $this->id,
        );
        $post_id = wp_insert_post( $post );
        return $post_id;
    }
}