<?php

class POS_AI_Podcast_Module extends POS_Module {
	public $id          = 'ai-podcast';
	public $name        = 'AI Podcast';
	public $description = 'AI Podcast';
	private $openai     = null;
	private $elevenlabs = null;
	private $hook_name = 'pos_generate_ai_podcast';
	private $soundtracks = array(
		'motivation-st-1.m4a',
		'motivation-st-2.m4a',
	);

	public function __construct( $openai, $elevenlabs ) {
		$this->openai = $openai;
		$this->elevenlabs = $elevenlabs;
		$this->register_cli_command( 'generate', 'cli' );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		$token = $this->get_setting( 'token' );
		$this->settings = array(
			'token'       => array(
				'type'    => 'text',
				'name'    => 'Private token for accessing the podcast feed',
				'label'   => strlen( $token ) < 3 ? 'You need a token longer than 3 characters to enable the podcast feed' : 'Your feed is accessible <a href="' . add_query_arg( 'token', $token, get_rest_url( null, $this->rest_namespace . '/ai-podcast' ) ) . '" target="_blank">here</a>',
				'default' => '0',
			),
			'tts_service' => array(
				'type'    => 'select',
				'name'    => 'TTS Service',
				'label'   => 'Select the TTS service to use for the podcast.',
				'default' => 'openai-gpt4o-audio',
				'options' => array(
					'openai-gpt4o-audio' => 'OpenAI GPT-4o Audio',
				),
			),
		);
		if ( $this->elevenlabs->is_configured() ) {
			$this->settings['tts_service']['options']['elevenlabs'] = 'ElevenLabs';
			$this->settings['elevenlabs_voice'] = array(
				'type'    => 'text',
				'name'    => 'ElevenLabs Voice ID',
				'label'   => 'The voice to use for your motivational podcast. Add this voice to your account or paste another id <a href="https://elevenlabs.io/app/voice-lab/share/f441776f9bb056eb2295e030ffce576ee35583946b9d95b273731d9887cb51e9/jB108zg64sTcu1kCbN9L" target="_blank">here</a>',
				'default' => 'jB108zg64sTcu1kCbN9L',
			);
		}

		if ( strlen( $token ) > 3 ) {
			add_action( $this->hook_name, array( $this, 'generate' ) );
			if ( ! wp_next_scheduled( $this->hook_name ) ) {
				$tomorrow_5am = strtotime( 'tomorrow 4am' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
				wp_schedule_event( $tomorrow_5am, 'daily', $this->hook_name );
			}
		}
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

	}

	public function admin_menu() {
		add_submenu_page( 'personalos', 'Hype Me', 'Hype Me', 'read', 'pos-hype-me', array( $this, 'render_admin_player_page' ) );
	}

	public function render_admin_player_page() {
		?>
		<section class="section section-about" id="about">
			<div class="section-content large-text d-flex flex-column justify-content-center h-100">
				<h1 class="big-title">PersonalOS Hype Player</h1>
				<p class="text-description">
					This will get your todos and create a motivational podcast for you.
				</p>
			</div>
		</section>
		<section id="player-loader">
			<p>Loading the sounds</p>
			<img src="<?php echo esc_url( get_admin_url() . 'images/spinner-2x.gif' ); ?>" />
		</section>
		<section id="hype-player" style="display: none;">
			<button id="playButton" style="display: none;" class="button button-primary button-hero">Play Audio</button>
			<div id="progressBar" style="margin-top: 20px;background-color: #f0f0f0;display: none;">
				<div id="progress" style="height: 10px; background-color: #0073aa;width: 0%;"></div>
			</div>
		</section>
		<?php
	}

	public function enqueue_scripts( $hook ) {
		// Only enqueue on the Hype Me page
		if ( 'personal-os_page_pos-hype-me' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'hype-player',
			plugins_url( 'hype-player.js', __FILE__ ),
			array( 'wp-api-fetch' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'hype-player.js' ),
			true
		);

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
		register_rest_route(
			$this->rest_namespace,
			'/ai-podcast/generate',
			array(
				'params'              => array(
					'token'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'prompt_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
				),
				'methods'             => 'POST',
				'callback'            => function( $request ) {
					return $this->generate( $request->has_param( 'prompt_id' ) ? $request->get_param( 'prompt_id' ) : null );
				},
				'permission_callback' => function( $request ) {
					return true;
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

	public function get_todos_now() {
		$now = get_posts(
			array(
				'post_type'      => 'todo',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 25,
				'tax_query'      => array(
					array(
						'taxonomy' => 'notebook',
						'field'    => 'slug',
						'terms'    => array( 'now' ),
					),
				),
			)
		);
		return implode(
			"\n\n",
			array_map(
				function( $post ) {
					$output = '### ' . $post->post_title . "\n";
					$tagged = get_the_terms( $post->ID, 'notebook' );
					if ( $tagged ) {
						$tagged = array_filter(
							array_map(
								function( $term ) {
									$termmeta = get_term_meta( $term->term_id, 'flag' );
									if ( ! in_array( 'project', $termmeta ) ) {
										return '';
									}
									return '#' . $term->name;
								},
								$tagged
							)
						);
						if ( ! empty( $tagged ) ) {
							$output .= 'Marked as: ' . implode( ', ', $tagged ) . "\n";
						}
					}
					if ( strlen( $post->post_excerpt ) > 0 ) {
						$output .= "\n" . $post->post_excerpt;
					}
					return $output;
				},
				$now
			)
		);
	}

	public function generate( $prompt_id = null ) {
		$notes_module = POS::get_module_by_id( 'notes' );
		$episode_generated_today = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'private',
				'meta_query'  => array(
					array(
						'key'     => 'pos_podcast',
						'value'   => gmdate( 'Y-m-d' ),
						'compare' => '=',
					),
				),
			)
		);
		if ( $episode_generated_today ) {
			return array(
				'media_id'       => $episode_generated_today[0]->ID,
				'media_url'      => wp_get_attachment_url( $episode_generated_today[0]->ID ),
				'prompt_id'      => get_post_meta( $episode_generated_today[0]->ID, 'prompt_id', true ),
				'text'           => $episode_generated_today[0]->post_content,
				'soundtrack_url' => get_post_meta( $episode_generated_today[0]->ID, 'soundtrack', true ),
			);
		}
		if ( $prompt_id ) {
			$template = get_post( $prompt_id );
		} else {
			$prompts = $notes_module->list( array(), 'prompts-podcast' );
			$template = $prompts[ array_rand( $prompts ) ];
		}
		$prompt_config = $this->openai->get_prompt_config( $template );
		$this->log( 'Generating podcast episode - ' . print_r( $template, true ) );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $prompt_config['prompt_string'],
			),
		);

		$this->log( 'Generating audio for the podcast' );
		$soundtrack_url = plugins_url( 'podcast-assets/' . $this->soundtracks[ array_rand( $this->soundtracks ) ], __FILE__ );

		$post_data = array(
			'post_title' => $template->post_title,
			'meta_input' => array(
				'pos_podcast' => gmdate( 'Y-m-d' ),
				'soundtrack'  => $soundtrack_url,
				'prompt_id'   => $template->ID,
			),
		);
		if ( $this->elevenlabs->is_configured() && $this->get_setting( 'tts_service' ) === 'elevenlabs' ) {
			$new_content = $this->openai->chat_completion( $messages );
			$file = $this->elevenlabs->tts(
				$new_content,
				$this->get_setting( 'elevenlabs_voice' ),
				$post_data,
			);
		} elseif ( $this->openai->is_configured() && $this->get_setting( 'tts_service' ) === 'openai-gpt4o-audio' ) {
			$file = $this->openai->tts(
				$messages,
				'ballad',
				$post_data,
			);
		} else {
			$this->log( 'No TTS service configured', E_USER_WARNING );
			return;
		}

		if ( is_wp_error( $file ) ) {
			$this->log( 'Generating podcast episode failed: ' . $file->get_error_message(), E_USER_WARNING );
			return;
		}
		$this->log( 'Generating podcast episode succeeded: ' . $file );
		return array(
			'media_id'       => $file,
			'media_url'      => wp_get_attachment_url( $file ),
			'prompt_id'      => $template->ID,
			'text'           => $new_content,
			'soundtrack_url' => $soundtrack_url,
		);
	}
}
