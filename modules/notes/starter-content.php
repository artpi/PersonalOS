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
$chat_prompts = $this->create_term_if_not_exists( 'Prompts: Chat', 'prompts-chat', array(), array( 'parent' => $prompts ) );

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

	<!-- wp:pos/ai-tool {"tool":"pos/get-notebooks","parameters":{"notebook_flag":"project"}} -->
	<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
	<!-- /wp:pos/ai-tool -->

	<!-- wp:heading -->
	<h2 class="wp-block-heading">My TODOS for Today</h2>
	<!-- /wp:heading -->

	<!-- wp:pos/ai-tool {"tool":"pos/todo-get-items","parameters":{}} -->
	<div class="wp-block pos-ai-tool"><p>This is a static block.</p></div>
	<!-- /wp:pos/ai-tool -->

	<!-- wp:paragraph -->
	<p></p>
	<!-- /wp:paragraph -->
	EOF,
	array( 'prompts-podcast', 'prompts', 'starter-content' )
);

// Chat prompts.
// TODO: Only recreate them if they don't exist?

$base_prompt_content = <<<EOF
<!-- wp:paragraph -->
<p>Your name is PersonalOS. You are a plugin installed on my WordPress site.<br>
<br>Apart from WordPress functionality, you have certain modules enabled, and functionality exposed as tools.<br>
<br>You can use these tools to perform actions on my behalf.<br>
<br>Use simple markdown to format your responses.<br>
<br>NEVER read the URLs (http://, https://, evernote://, etc) out loud in voice mode.<br>
<br>When answering a question about my todos or notes, stick only to the information from the tools. DO NOT make up information.</p>
<!-- /wp:paragraph -->
EOF;

// TODO: Make this note impossible to delete - probably a hook in the openai module.
$base_prompt = $this->create(
	'Default Prompt',
	$base_prompt_content,
	array( 'prompts-chat', 'prompts', 'starter-content' ),
	array(
		'meta_input' => array(
			'pos_model' => 'gpt-4.1',
		),
		'post_name' => 'prompt_default',
	)
);

$chat_prompt_1 = $this->create(
	'Helpful Assistant - GPT-4.1',
	'<!-- wp:paragraph -->
	<p>You are a helpful assistant. Keep your responses concise, clear, and actionable. Focus on being practical and solution-oriented.</p>
	<!-- /wp:paragraph -->

	<!-- wp:pos/note {"note_id":' . $base_prompt . '} -->
	<div class="wp-block-pos-note"><div>' . $base_prompt_content . '</div></div>
	<!-- /wp:pos/note -->',
	array( 'prompts-chat', 'prompts', 'starter-content' ),
	array(
		'meta_input' => array(
			'pos_model' => 'gpt-4.1',
		),
		'post_name' => 'prompt_gpt41',
	)
);

$chat_prompt_2 = $this->create(
	'Helpful Assistant - GPT-5',
	'<!-- wp:paragraph -->
	<p>You are a helpful assistant. Keep your responses concise, clear, and actionable. Focus on being practical and solution-oriented.</p>
	<!-- /wp:paragraph -->

	<!-- wp:pos/note {"note_id":' . $base_prompt . '} -->
	<div class="wp-block-pos-note"><div>' . $base_prompt_content . '</div></div>
	<!-- /wp:pos/note -->',
	array( 'prompts-chat', 'prompts', 'starter-content' ),
	array(
		'meta_input' => array(
			'pos_model' => 'gpt-5',
		),
		'post_name' => 'prompt_gpt5',
	)
);
