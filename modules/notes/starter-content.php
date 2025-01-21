<?php




// Notebook structure according to PARA.

$term_status = $this->create_term_if_not_exists( '1-Status', 'status', array() );
$this->create_term_if_not_exists( 'Inbox', 'inbox', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );
$this->create_term_if_not_exists( 'NOW', 'now', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );

$term_project = $this->create_term_if_not_exists( '2-Projects', 'projects', array() );
$this->create_term_if_not_exists( 'My main project now', 'project1', array( array( 'flag', 'project' ), array( 'flag', 'star' ) ), array( 'parent' => $term_project ) );
$this->create_term_if_not_exists( 'World Domination', 'project2', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

$term_areas = $this->create_term_if_not_exists( '3-Areas', 'areas', array() );
$this->create_term_if_not_exists( 'Health', 'area1', array(), array( 'parent' => $term_areas ) );
$this->create_term_if_not_exists( 'Work', 'area2', array(), array( 'parent' => $term_areas ) );
$this->create_term_if_not_exists( 'Family', 'area3', array(), array( 'parent' => $term_areas ) );

$term_resources = $this->create_term_if_not_exists( '4-Resources', 'resources', array() );
$starter_content = $this->create_term_if_not_exists( 'Starter Content', 'starter-content', array( array( 'flag', 'star' ) ), array( 'parent' => $term_resources ) );
$this->create_term_if_not_exists( 'Nice Quotes', 'resource2', array(), array( 'parent' => $term_resources ) );

// Lets delete all starter-content notes.

$notes = get_posts(
	array(
		'post_type'   => 'notes',
		'numberposts' => -1,
		'tax_query'   => array(
			array(
				'taxonomy' => 'notebook',
				'field'    => 'slug',
				'terms'    => array( 'starter-content' ),
			),
		),
	)
);

foreach ( $notes as $note ) {
	wp_delete_post( $note->ID );
}

// Now lets create notes.

$note = $this->create(
	'Embedded note',
	get_comment_delimited_block_content(
		'paragraph',
		array(),
		'<p>This is a test note that can be embedded in other notes by using the <code>pos/note</code> block. You can edit this in the original note, or from the note that this note is embedded in.</p>'
	),
	array( 'starter-content' )
);

$this->create(
	'Note with embedded note inside',
	get_comment_delimited_block_content(
		'paragraph',
		array(),
		'<p>Some content that is not embedded.</p>'
	) .
	get_comment_delimited_block_content(
		'pos/note',
		array(
			'note_id' => $note,
		),
		'<div class="wp-block-pos-note"><div></div></div>'
	),
	array( 'starter-content' )
);
