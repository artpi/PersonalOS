<?php




// Notebook structure according to PARA.

$term_status = $this->create_term_if_not_exists( '1-Status', 'status', array() );
$this->create_term_if_not_exists( 'Inbox', 'inbox', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );
$this->create_term_if_not_exists( 'NOW', 'now', array( array( 'flag', 'star' ) ), array( 'parent' => $term_status ) );

$term_project = $this->create_term_if_not_exists( '2-Projects', 'projects', array() );
$this->create_term_if_not_exists( 'My main project now', 'project1', array( array( 'flag', 'project' ), array( 'flag', 'star' ) ), array( 'parent' => $term_project ) );
$this->create_term_if_not_exists( 'World Domination', 'project2', array( array( 'flag', 'project' ) ), array( 'parent' => $term_project ) );

$term_areas = $this->create_term_if_not_exists( '3-Areas', 'areas', array() );
$this->create_term_if_not_exists( 'Health', 'health', array(), array( 'parent' => $term_areas ) );
$this->create_term_if_not_exists( 'Work', 'work', array(), array( 'parent' => $term_areas ) );
$this->create_term_if_not_exists( 'Family', 'family', array(), array( 'parent' => $term_areas ) );

$term_resources = $this->create_term_if_not_exists( '4-Resources', 'resources', array() );
$starter_content = $this->create_term_if_not_exists( 'Starter Content', 'starter-content', array( array( 'flag', 'star' ) ), array( 'parent' => $term_resources ) );

$prompts = $this->create_term_if_not_exists( 'AI Prompts', 'prompts', array(), array( 'parent' => $term_resources ) );
$podcast_prompts = $this->create_term_if_not_exists( 'Prompts: Podcast', 'prompts-podcast', array(), array( 'parent' => $prompts ) );

$ai_memory = $this->create_term_if_not_exists( 'AI Memory', 'ai-memory', array(), array( 'parent' => $term_resources ) );
$this->create_term_if_not_exists( 'Nice Quotes', 'nice-quotes', array(), array( 'parent' => $term_resources ) );

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

// Default prompts.

$this->create(
	'Daily Podcast - Tony Robbins style',
	<<<EOF
	<!-- wp:preformatted -->
	<pre class="wp-block-preformatted">Generate a motivational speech from Tony Robbins to start my day. Be dramatic in your speech, use pauses, sometimes speak faster, sometimes slower.<br>The speech you generate will be read out by OpenAI speech generation models. so don't use any headings or titles.<br><br>Use the following framework : State, Story, Strategy.<br></pre>
	<!-- /wp:preformatted -->

	<!-- wp:list {"ordered":true} -->
	<ol class="wp-block-list"><!-- wp:list-item -->
	<li> Focus on getting me in a hyped-up state.</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>Shift my internal story into more hyped-up, actionable, full of energy</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>Help me develop a strategy for dealing with my important projects.</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>Walk me through my todos for today.</li>
	<!-- /wp:list-item --></ol>
	<!-- /wp:list -->

	<!-- wp:heading -->
	<h2 class="wp-block-heading">Projects I want to focus on right now:</h2>
	<!-- /wp:heading -->

	<!-- wp:pos/ai-tool {"tool":"get_notebooks","parameters":{"notebook_flag":"project"}} -->
	<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
	<!-- /wp:pos/ai-tool -->

	<!-- wp:heading -->
	<h2 class="wp-block-heading">My TODOS for Today</h2>
	<!-- /wp:heading -->

	<!-- wp:pos/ai-tool {"tool":"todo_get_items","parameters":{}} -->
	<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
	<!-- /wp:pos/ai-tool -->

	<!-- wp:paragraph -->
	<p></p>
	<!-- /wp:paragraph -->
	EOF,
	array( 'prompts-podcast', 'prompts', 'starter-content' )
);
