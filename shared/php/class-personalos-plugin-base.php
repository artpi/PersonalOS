<?php
/**
 * Shared PersonalOS package base class.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Plugin_Base' ) ) {
	/**
	 * Base class for independent Personal packages.
	 */
	class PersonalOS_Plugin_Base {
		/**
		 * Package slug.
		 *
		 * @var string
		 */
		protected $slug = 'personal-package';

		/**
		 * Display name.
		 *
		 * @var string
		 */
		protected $display_name = 'Personal Package';

		/**
		 * Text domain.
		 *
		 * @var string
		 */
		protected $text_domain = 'personalos';

		/**
		 * Version.
		 *
		 * @var string
		 */
		protected $version = '0.1.0';

		/**
		 * Main plugin file.
		 *
		 * @var string
		 */
		protected $plugin_file = '';

		/**
		 * Settings config.
		 *
		 * @var array
		 */
		protected $settings = array();

		/**
		 * WpApp configuration.
		 *
		 * @var array
		 */
		protected $app = array();

		/**
		 * Knowledge bridge.
		 *
		 * @var PersonalOS_Knowledge_Bridge|null
		 */
		private $knowledge = null;

		/**
		 * Vocabulary helper.
		 *
		 * @var PersonalOS_Knowledge_Type_Vocabulary|null
		 */
		private $vocabulary = null;

		/**
		 * Settings helper.
		 *
		 * @var PersonalOS_Settings_Helper|null
		 */
		private $settings_helper = null;

		/**
		 * Admin page hook suffixes owned by this package.
		 *
		 * @var string[]
		 */
		private $admin_page_hooks = array();

		/**
		 * Constructor.
		 *
		 * @param array $args Package args.
		 */
		public function __construct( $args = array() ) {
			foreach ( array( 'slug', 'display_name', 'text_domain', 'version', 'plugin_file', 'settings', 'app' ) as $key ) {
				if ( isset( $args[ $key ] ) ) {
					$this->{$key} = $args[ $key ];
				}
			}
		}

		/**
		 * Register package hooks.
		 *
		 * @return void
		 */
		public function register() {
		}

		/**
		 * Get package slug.
		 *
		 * @return string
		 */
		public function slug() {
			return $this->slug;
		}

		/**
		 * Get display name.
		 *
		 * @return string
		 */
		public function display_name() {
			return $this->display_name;
		}

		/**
		 * Get the short in-product app name.
		 *
		 * @return string
		 */
		public function app_display_name() {
			$name = isset( $this->app['name'] ) ? $this->app['name'] : $this->display_name;

			if ( function_exists( 'translate' ) && did_action( 'init' ) ) {
				// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain -- Package app names use package-configured text domains and are translated after init.
				$translated = translate( $name, $this->text_domain );
				if ( is_string( $translated ) && '' !== trim( $translated ) ) {
					return $translated;
				}
			}

			return $name;
		}

		/**
		 * Get package directory.
		 *
		 * @return string
		 */
		public function package_dir() {
			return plugin_dir_path( $this->plugin_file );
		}

		/**
		 * Get package URL.
		 *
		 * @return string
		 */
		public function package_url() {
			return plugin_dir_url( $this->plugin_file );
		}

		/**
		 * Get Knowledge bridge.
		 *
		 * @return PersonalOS_Knowledge_Bridge
		 */
		public function knowledge() {
			if ( ! $this->knowledge ) {
				$this->knowledge = new PersonalOS_Knowledge_Bridge( $this->slug );
			}

			return $this->knowledge;
		}

		/**
		 * Get vocabulary helper.
		 *
		 * @return PersonalOS_Knowledge_Type_Vocabulary
		 */
		public function vocabulary() {
			if ( ! $this->vocabulary ) {
				$this->vocabulary = new PersonalOS_Knowledge_Type_Vocabulary( $this->knowledge() );
			}

			return $this->vocabulary;
		}

		/**
		 * Get settings helper.
		 *
		 * @return PersonalOS_Settings_Helper
		 */
		public function settings_helper() {
			if ( ! $this->settings_helper ) {
				$this->settings_helper = new PersonalOS_Settings_Helper( $this->slug, $this->settings );
			}

			return $this->settings_helper;
		}

		/**
		 * Get setting storage key.
		 *
		 * @param string $setting_id Setting ID.
		 * @return string
		 */
		public function get_setting_storage_key( $setting_id ) {
			return $this->settings_helper()->storage_key( $setting_id );
		}

		/**
		 * Get a setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @param int    $user_id    Optional user ID.
		 * @return mixed
		 */
		public function get_setting( $setting_id, $user_id = 0 ) {
			return $this->settings_helper()->get( $setting_id, $user_id );
		}

		/**
		 * Update a setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @param mixed  $value      Value.
		 * @param int    $user_id    Optional user ID.
		 * @return bool|int
		 */
		public function update_setting( $setting_id, $value, $user_id = 0 ) {
			return $this->settings_helper()->update( $setting_id, $value, $user_id );
		}

		/**
		 * Ensure shared Knowledge terms.
		 *
		 * @param array $slugs Term slugs.
		 * @return array|WP_Error
		 */
		public function ensure_knowledge_terms( $slugs = array() ) {
			return $this->vocabulary()->ensure_terms( $slugs );
		}

		/**
		 * Register standard package meta on Knowledge rows.
		 *
		 * @return void
		 */
		public function register_common_knowledge_meta() {
			$this->knowledge()->register_post_meta( 'url' );
			$this->knowledge()->register_post_meta( '_personalos_source' );
			$this->knowledge()->register_post_meta( '_personalos_external_id' );
			$this->knowledge()->register_post_meta( '_personalos_source_url' );
			$this->knowledge()->register_post_meta( '_personalos_synced_at' );
			$this->knowledge()->register_post_meta( '_personalos_source_hash' );
		}

		/**
		 * Register package-local built assets.
		 *
		 * @return array
		 */
		public function register_package_assets() {
			return array(
				'script' => PersonalOS_Assets_Helper::register_script( $this->script_handle(), $this->plugin_file ),
				'style'  => PersonalOS_Assets_Helper::register_style(
					$this->style_handle(),
					$this->plugin_file,
					'build/style-index.css',
					array( 'wp-components' )
				),
			);
		}

		/**
		 * Enqueue package-local built assets.
		 *
		 * @return void
		 */
		public function enqueue_package_assets() {
			$registered = $this->register_package_assets();

			if ( ! empty( $registered['script'] ) ) {
				wp_enqueue_script( $this->script_handle() );
			}

			if ( ! empty( $registered['style'] ) ) {
				wp_enqueue_style( $this->style_handle() );
			}
		}

		/**
		 * Track an admin page hook that should receive package assets.
		 *
		 * @param string $hook_suffix Admin page hook suffix.
		 * @return void
		 */
		public function add_admin_page_hook( $hook_suffix ) {
			if ( $hook_suffix ) {
				$this->admin_page_hooks[] = $hook_suffix;
			}
		}

		/**
		 * Enqueue package assets on owned admin pages.
		 *
		 * @param string $hook_suffix Current admin page hook suffix.
		 * @return void
		 */
		public function enqueue_admin_assets( $hook_suffix ) {
			if ( ! in_array( $hook_suffix, $this->admin_page_hooks, true ) ) {
				return;
			}

			$this->enqueue_package_assets();
		}

		/**
		 * Register the package's WpApp route and renderer.
		 *
		 * @param callable $content_callback Package UI renderer.
		 * @return bool Whether the app was registered.
		 */
		public function register_wp_app( $content_callback ) {
			if ( empty( $this->app['path'] ) ) {
				return false;
			}

			$app = new PersonalOS_Wp_App(
				array_merge(
					$this->app,
					array(
						'name'             => $this->app_display_name(),
						'text_domain'      => $this->text_domain,
						'plugin_file'      => $this->plugin_file,
						'template_dir'     => $this->package_dir() . 'templates',
						'content_callback' => $content_callback,
						'asset_callback'   => array( $this, 'enqueue_package_assets' ),
						'style_handle'     => $this->style_handle(),
					)
				)
			);

			return $app->register();
		}

		/**
		 * Get package script handle.
		 *
		 * @return string
		 */
		public function script_handle() {
			return $this->slug . '-admin';
		}

		/**
		 * Get package style handle.
		 *
		 * @return string
		 */
		public function style_handle() {
			return $this->slug . '-admin';
		}

		/**
		 * Create a Knowledge row and assign Knowledge type terms.
		 *
		 * @param array $data       Post data.
		 * @param array $term_slugs Knowledge term slugs or IDs.
		 * @param int   $user_id    Optional owner.
		 * @return int|WP_Error
		 */
		public function create_knowledge_post( $data, $term_slugs, $user_id = 0 ) {
			if ( ! $this->knowledge()->is_available() ) {
				return new WP_Error( 'personalos_missing_knowledge', __( 'Knowledge is not available.', 'personalos' ) );
			}

			$term_ids = $this->vocabulary()->resolve_assignable_term_ids( $term_slugs );
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}

			$user_id = $user_id ? (int) $user_id : get_current_user_id();

			$defaults = array(
				'post_type'    => $this->knowledge()->post_type(),
				'post_status'  => 'private',
				'post_author'  => $user_id,
				'post_title'   => '',
				'post_excerpt' => '',
				'post_content' => '',
				'meta_input'   => array(),
			);
			$data = wp_parse_args( $data, $defaults );
			$data['post_type'] = $this->knowledge()->post_type();

			if ( ! isset( $data['meta_input'] ) || ! is_array( $data['meta_input'] ) ) {
				$data['meta_input'] = array();
			}

			if ( ! empty( $data['personalos_source'] ) ) {
				$data['meta_input'][ $this->knowledge()->source_meta_key() ] = sanitize_text_field( $data['personalos_source'] );
				unset( $data['personalos_source'] );
			}

			$post_id = wp_insert_post( $data, true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			wp_set_object_terms( $post_id, $term_ids, $this->knowledge()->type_taxonomy(), false );

			return $post_id;
		}

		/**
		 * Query readable Knowledge rows by term filters.
		 *
		 * @param array $args       WP_Query args.
		 * @param array $term_slugs Term slugs or IDs.
		 * @return WP_Post[]
		 */
		public function query_knowledge_posts( $args = array(), $term_slugs = array() ) {
			if ( ! $this->knowledge()->is_available() ) {
				return array();
			}

			$defaults = array(
				'post_type'      => $this->knowledge()->post_type(),
				'post_status'    => array( 'private', 'publish', 'future' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			);

			$args = wp_parse_args( $args, $defaults );

			if ( ! empty( $term_slugs ) ) {
				$term_ids = $this->vocabulary()->expand_terms_for_query( $term_slugs );
				if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
					return array();
				}

				$args['tax_query'] = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
				$args['tax_query'][] = array(
					'taxonomy' => $this->knowledge()->type_taxonomy(),
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'AND',
				);
			}

			$posts = get_posts( $args );

			return array_values(
				array_filter(
					$posts,
					function ( $post ) {
						return current_user_can( 'read_post', $post->ID );
					}
				)
			);
		}

		/**
		 * Register a package-local block.
		 *
		 * @param string $relative_dir Relative block directory.
		 * @param array  $args         Block args.
		 * @return bool
		 */
		public function register_block_from_package( $relative_dir, $args = array() ) {
			$dir = trailingslashit( $this->package_dir() ) . ltrim( $relative_dir, '/' );

			if ( ! file_exists( trailingslashit( $dir ) . 'block.json' ) ) {
				return false;
			}

			register_block_type( $dir, $args );

			return true;
		}

		/**
		 * Register a package-local WP-CLI command.
		 *
		 * @param string $command Command suffix.
		 * @param string $method  Instance method.
		 * @return void
		 */
		public function register_cli_command( $command, $method ) {
			if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
				WP_CLI::add_command(
					$this->cli_namespace() . ' ' . $command,
					array( $this, $method )
				);
			}
		}

		/**
		 * Get CLI namespace.
		 *
		 * @return string
		 */
		protected function cli_namespace() {
			$short_slug = preg_replace( '/^personal-/', '', $this->slug );
			return 'personal ' . $short_slug;
		}

		/**
		 * Log with package context.
		 *
		 * @param string     $message Message.
		 * @param string|int $level   Level.
		 * @return void
		 */
		public function log( $message, $level = 'DEBUG' ) {
			$map = array(
				E_USER_NOTICE  => 'NOTICE',
				E_USER_WARNING => 'WARNING',
				E_USER_ERROR   => 'ERROR',
			);

			if ( array_key_exists( $level, $map ) ) {
				$level = $map[ $level ];
			} elseif ( ! is_string( $level ) ) {
				$level = 'DEBUG';
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[{$level}] [{$this->slug}] {$message}" );

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::line( "[{$level}] [{$this->slug}] {$message}" );
			}
		}

		/**
		 * Register a non-fatal missing Knowledge notice.
		 *
		 * @return void
		 */
		public function register_missing_knowledge_notice() {
			if ( $this->knowledge()->is_available() ) {
				return;
			}

			add_action(
				'admin_notices',
				function () {
					PersonalOS_Admin_Notice_Helper::missing_knowledge( $this->display_name );
				}
			);
		}
	}
}
