<?php

class Notes_Module extends POS_Module {
    public $id = 'notes';
    public $name = "Notes";

    function register() {
        $this->register_post_type();
        add_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10, 3 );
    }

    public function autopublish_drafts( $post_id, $post, $updating) {
        if ( $post->post_status === 'draft' ) {
            wp_publish_post( $post );
        }
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