<?php

class POS_AI_Podcast_Module extends POS_Module {
	public $id          = 'ai-podcast';
	public $name        = 'AI Podcast';
	public $description = 'AI Podcast';
	private $openai     = null;

	public function __construct( $openai ) {
		$this->openai = $openai;
		$this->register_cli_command( 'generate', 'cli' );
	}

	/**
	 * Trigger generating a podcast episode
	 */
	public function cli( $args ) {
		$this->generate();
	}

	public function mark_podcast_as_private_for_itunes() {
		echo "<itunes:block>yes</itunes:block>\r\n";
	}
	public function output_feed() {
		global $wp_query;
		//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_query = new WP_Query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'private, publish, inherit',
				'meta_query'  => array(
					array(
						'key'     => 'pos_podcast',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$wp_query->is_feed = true;
		// This will mark podcast as private
		add_action( 'rss2_head', array( $this, 'mark_podcast_as_private_for_itunes' ) );
		include ABSPATH . WPINC . '/feed-rss2.php';
		die();
	}

	public function get_active_projects() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'notebook',
				'hide_empty' => false,
				'fields'     => 'all',
				'meta_query' => array(
					array(
						'key'     => 'flag',
						'value'   => 'project',
						'compare' => '=',
					),
				),
			)
		);
		return implode(
			"\n",
			array_map(
				function( $term ) {
					return '- ' . $term->name . ' (' . $term->description . ')';
				},
				$terms
			)
		);
	}


	public function generate() {
		$this->log( 'Generating podcast episode' );

		$new_content = $this->openai->chat_completion(
			array(
				array(
					'role'    => 'system',
					'content' => 'You are a motivational speech writer.',
				),
				array(
					'role'    => 'user',
					'content' => <<<EOF
                    Generate a motivational speech from Tony Robbins to start my day. The speech you generate will be read out by OpenAI speech generation models. so don't use any headings or titles.

                    Use the following framework : State, Story, Strategy.
                    1. Focus on getting me in a hyped-up state.
                    2. Shift my internal story into more hyped-up, actionable, full of energy
                    3. Help me develop a strategy for dealing with my important projects.
                Projects I want to focus on right now:
                {$this->get_active_projects()}
                EOF,
				),
			)
		);
		if ( is_wp_error( $new_content ) ) {
			$this->log( 'Generating podcast episode failed: ' . $new_content->get_error_message(), E_USER_WARNING );
			return;
		}
		$this->log( 'Generating audio for the podcast' );

		$file = $this->openai->tts(
			$new_content,
			'onyx',
			array(
				'post_title' => 'Motivational Podcast',
				'meta_input' => array(
					'pos_podcast' => gmdate( 'Y-m-d' ),
				),
			)
		);
		if ( is_wp_error( $file ) ) {
			$this->log( 'Generating podcast episode failed: ' . $file->get_error_message(), E_USER_WARNING );
			return;
		}
		$this->log( 'Generating podcast episode succeeded: ' . $file );
		return $file;
	}
}
