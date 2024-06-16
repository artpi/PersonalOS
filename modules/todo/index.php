<?php

class TODO_Module extends POS_Module {
	public $id   = 'todo';
	public $name = 'TODO';

	public function register() {
		$this->register_post_type(
			array(
				'supports'   => array( 'title', 'excerpt', 'custom-fields' ),
				'taxonomies' => array( 'notebook' ),
			)
		);
		add_filter( 'manage_notebook_custom_column', array( $this, 'notebook_taxonomy_column' ), 10, 3 );
		add_filter( 'manage_edit-notebook_columns', array( $this, 'notebook_taxonomy_columns' ) );

	}
	public function notebook_taxonomy_columns( $columns ) {
		$columns['todos'] = 'TODOs';
		$columns['posts'] = 'Notes';
		return $columns;
	}
	public function notebook_taxonomy_column( $output, $column_name, $term_id ) {
		if ( $column_name === 'todos' ) {
			$query = new WP_Query(
				array(
					'post_type' => $this->id,
					'tax_query' => array(
						array(
							'taxonomy' => 'notebook',
							'field'    => 'id',
							'terms'    => array( $term_id ),
						),
					),
				)
			);
			return "<a href='edit.php?notebook=inbox&post_type={$this->id}'>{$query->found_posts}</a>";
		}
		return $output;
	}

}
