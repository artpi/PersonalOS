<?php

class Notes_Module extends POS_Module {
    public $id = 'notes';
    public $name = "Notes";

    function register() {
        register_taxonomy( 'notebook', [ $this->id, 'todo' ], array(
            'label'                 => 'Notebook',
            'public'                => false,
            'hierarchical'          => true,
            'show_ui'               => true,
            'show_in_menu'          => 'personalos',
            'default_term' => [
                'name' => 'Inbox', 
                'slug' => 'inbox',
                'description' => 'Default notebook for notes and todos.',
            ],
            'show_admin_column'     => true,
            'query_var'             => true,
            'show_in_rest'          => true,
            'rest_namespace'        => $this->rest_namespace,
            'rewrite'               => array( 'slug' => 'notebook' ),
        ) );
        $this->register_post_type( [
            'taxonomies' => [ 'notebook', 'post_tag' ],
        ] );

        add_action( 'save_post_' . $this->id, array( $this, 'autopublish_drafts' ), 10, 3 );
        add_action( 'wp_dashboard_setup', array( $this,'init_admin_widgets' ) );
    }

    public function autopublish_drafts( $post_id, $post, $updating) {
        if ( $post->post_status === 'draft' ) {
            wp_publish_post( $post );
        }
    }

    public function create( $title, $content, $inbox = false ) {
        $post = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => $this->id,
        );
        $post_id = wp_insert_post( $post );
        return $post_id;
    }
    public function init_admin_widgets() {
        $terms = get_terms( [ 'taxonomy' => 'notebook', 'hide_empty' => false ] );
        foreach( $terms as $term ) {
            $this->register_notebook_admin_widget( $term );
        }
    }
    public function register_notebook_admin_widget( $term ) {
        wp_add_dashboard_widget(
            'pos_notebook_' . $term->slug,
            $term->name,
            array( $this,'notebook_admin_widget' ),
            null,
            $term
        );
    }
    public function notebook_admin_widget( $widget_config, $conf ) {
        $notes = get_posts( array(
            'post_type' => $this->id,
            'tax_query' => [
                [
                    'taxonomy' => 'notebook',
                    'field' => 'slug',
                    'terms' => [
                        $conf['args']->slug
                    ]
                ]
            ]
        ) );
        if ( count( $notes ) > 0 ) {
            echo "<h3>{$conf['args']->name}: Notes</h3>";
            $notes = array_map( function( $note ) {
                return "<li style='margin-bottom:1em'><div class='draft-title'><a style='margin: 0 5px 0 0 ' href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'>{$note->post_title}</a><time style='color:#646970;' datetime='{$note->post_date}'>" . date( 'F j, Y', strtotime( $note->post_date ) ) . "</time></div><p>" . get_the_excerpt( $note ) . "</p></li>";
            }, $notes );
    
            echo '<ul>' . implode( '', $notes ) . '</ul>'; 
        }
        $notes = get_posts( array(
            'post_type' => 'todo',
            'tax_query' => [
                [
                    'taxonomy' => 'notebook',
                    'field' => 'slug',
                    'terms' => [
                        $conf['args']->slug
                    ]
                ]
            ]
        ) );
        if ( count( $notes ) > 0 ) {
            echo "<h3>{$conf['args']->name}: TODOs</h3>";
            $notes = array_map( function( $note ) {
                return "<li style='margin-bottom:1em'><div class='draft-title'><a style='margin: 0 5px 0 0 ' href='" . get_edit_post_link( $note->ID ) . "' aria-label='Edit “{$note->post_title}”'>{$note->post_title}</a></div></li>";
            }, $notes );
    
            echo '<ul>' . implode( '', $notes ) . '</ul>'; 
        }

        //$term = get_term_by( 'slug', $conf['args']['notebook'], 'notebook' );
        //$query = new WP_Query( array( 'post_type' => $this->id, 'tax_query' => [ [ 'taxonomy' => 'notebook', 'field' => 'slug', 'terms' => [  ] ] ] ) );
        //echo "<p>Notes in this  notebook: {$query->found_posts}</p>";
    }
}