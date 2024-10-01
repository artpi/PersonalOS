<?php

class POS_AI_Podcast_Module extends POS_Module {
	public $id          = 'ai-podcast';
	public $name        = 'AI Podcast';
	public $description = 'AI Podcast';
	private $openai     = null;
	private $hook_name = 'pos_generate_ai_podcast';

	public function __construct( $openai ) {
		$this->openai = $openai;
		$this->register_cli_command( 'generate', 'cli' );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		$token = $this->get_setting( 'token' );
		$this->settings = array(
			'token' => array(
				'type'    => 'text',
				'name'    => 'Private token for accessing the podcast feed',
				'label'   => strlen( $token ) < 3 ? 'You need a token longer than 3 characters to enable the podcast feed' : 'Your feed is accessible <a href="' . add_query_arg( 'token', $token, get_rest_url( null, $this->rest_namespace . '/ai-podcast' ) ) . '" target="_blank">here</a>',
				'default' => '0',
			),
		);

		if ( strlen( $token ) > 3 ) {
			add_action( $this->hook_name, array( $this, 'generate' ) );
			if ( ! wp_next_scheduled( $this->hook_name ) ) {
				wp_schedule_event( time(), 'daily', $this->hook_name );
			}
		}

	}

	/**
	 * Trigger generating a podcast episode
	 */
	public function cli( $args ) {
		$this->generate();
	}

	public function mark_podcast_as_private_for_itunes() {
		echo "\r\n<itunes:block>yes</itunes:block>\r\n";
	}
	public function output_feed() {
		//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$loop = new WP_Query(
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
		global $post;
		header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';
		?>

		<?php // Start the iTunes RSS Feed: https://www.apple.com/itunes/podcasts/specs.html ?>
		<rss xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" version="2.0">
		<channel>
			<title>Good morning from <?php echo get_bloginfo( 'name' ); ?></title>
			<link><?php echo get_bloginfo( 'url' ); ?></link>
			<language><?php echo get_bloginfo( 'language' ); ?></language>
			<copyright><?php echo date( 'Y' ); ?> <?php echo get_bloginfo( 'name' ); ?></copyright>
			<itunes:author><?php echo get_bloginfo( 'name' ); ?></itunes:author>
			<itunes:summary>Private podcast with all the hype and energy you need to start your day.</itunes:summary>
			<itunes:owner>
			<itunes:name><?php echo get_bloginfo( 'name' ); ?></itunes:name>
			<itunes:email><?php echo get_bloginfo( 'admin_email' ); ?></itunes:email>
			</itunes:owner>
			<?php
				$logo = get_custom_logo();
			if ( $logo ) {
				echo "<itunes:image href=\"{$logo}\" />";
			}
			?>

			<itunes:category text="Education">
			<itunes:category text="Self-Improvement"/>
			</itunes:category>
			<itunes:explicit>yes</itunes:explicit>

			<?php
			// Start the loop for Podcast posts
			while ( $loop->have_posts() ) :
				$loop->the_post();
				?>
			<item>
			<title><?php the_title_rss(); ?></title>
			<itunes:author><?php echo get_bloginfo( 'name' ); ?></itunes:author>
			<itunes:summary></itunes:summary>
			<?php
				$attachment_id = $post->ID;
				$fileurl = wp_get_attachment_url( $attachment_id );
				$filesize = filesize( get_attached_file( $attachment_id ) );
				$dateformatstring = _x( 'D, d M Y H:i:s O', 'Date formating for iTunes feed.' );
			?>

			<enclosure url="<?php echo esc_url( $fileurl ); ?>" length="<?php echo esc_attr( $filesize ); ?>" type="audio/mpeg" />
			<guid><?php echo esc_url( $fileurl ); ?></guid>
			<pubDate><?php echo esc_html( gmdate( $dateformatstring, strtotime( $post->post_date ) ) ); ?></pubDate>
			<itunes:duration>
				<?php
				$metadata = wp_get_attachment_metadata( $attachment_id );
				echo esc_html( isset( $metadata['length_formatted'] ) ? $metadata['length_formatted'] : '' );
				?>
			</itunes:duration>
			</item>
			<?php endwhile; ?>

		</channel>

		</rss>
		<?php
		die();
	}

	public function rest_api_init() {
		register_rest_route(
			$this->rest_namespace,
			'/ai-podcast',
			array(
				'params'              => array(
					'token' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'methods'             => 'GET',
				'callback'            => array( $this, 'output_feed' ),
				'permission_callback' => function( $request ) {
					$token = $this->get_setting( 'token' );
					if ( strlen( $token ) < 3 ) {
						return false;
					}
					return $token === $request->get_param( 'token' );
				},
			)
		);
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
