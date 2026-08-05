<?php
/**
 * Shared WpApp integration for independent Personal plugins.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Wp_App' ) ) {
	/**
	 * Loads WpApp and connects a package renderer to a private app route.
	 */
	class PersonalOS_Wp_App {
		/**
		 * Registered Personal app contexts, keyed by URL path.
		 *
		 * @var PersonalOS_Wp_App[]
		 */
		private static $contexts = array();

		/**
		 * App configuration.
		 *
		 * @var array
		 */
		private $config = array();

		/**
		 * WpApp runtime instance.
		 *
		 * @var \WpApp\WpApp|null
		 */
		private $app = null;

		/**
		 * Constructor.
		 *
		 * @param array $config App configuration.
		 */
		public function __construct( $config ) {
			$this->config = wp_parse_args(
				$config,
				array(
					'path'             => '',
					'name'             => '',
					'text_domain'      => 'personalos',
					'capability'       => 'read',
					'icon'             => 'dashicons-admin-generic',
					'action_label'     => 'Open App',
					'plugin_file'      => '',
					'template_dir'     => '',
					'content_callback' => null,
					'asset_callback'   => null,
					'style_handle'     => '',
				)
			);
		}

		/**
		 * Register the package as a WpApp application.
		 *
		 * @return bool Whether the app was registered.
		 */
		public function register() {
			if ( ! $this->config['path'] || ! $this->load_runtime() ) {
				return false;
			}

			$this->app = new \WpApp\WpApp(
				$this->config['template_dir'],
				$this->config['path'],
				array(
					'app_name'                     => $this->config['name'],
					'app_name_textdomain'          => $this->config['text_domain'],
					'require_capability'           => $this->config['capability'],
					'show_masterbar_for_anonymous' => false,
					'my_apps'                      => $this->config['name'],
					'my_apps_icon'                 => $this->config['icon'],
				)
			);

			self::$contexts[ $this->config['path'] ] = $this;
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( $this->config['plugin_file'] ), array( $this, 'add_plugin_action_link' ) );
			$this->app->init();

			return true;
		}

		/**
		 * Load the bundled WpApp runtime or the monorepo Composer copy.
		 *
		 * @return bool Whether the runtime is available.
		 */
		private function load_runtime() {
			if ( class_exists( '\\WpApp\\WpApp' ) ) {
				return true;
			}

			$plugin_dir = plugin_dir_path( $this->config['plugin_file'] );
			$candidates = array(
				$plugin_dir . 'vendor/akirk/wp-app/src',
				dirname( $plugin_dir, 2 ) . '/vendor/akirk/wp-app/src',
			);

			foreach ( $candidates as $source_dir ) {
				if ( ! file_exists( $source_dir . '/class-wpapp.php' ) ) {
					continue;
				}

				foreach ( array(
					'class-registry.php',
					'class-settings.php',
					'class-router.php',
					'class-masterbar.php',
					'class-wpapp.php',
					'BaseStorage.php',
					'abstract-baseapp.php',
					'functions.php',
				) as $runtime_file ) {
					require_once $source_dir . '/' . $runtime_file;
				}

				return class_exists( '\\WpApp\\WpApp' );
			}

			return false;
		}

		/**
		 * Enqueue the package's normal assets on its app route.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			if ( ! function_exists( 'wp_app_is_app_url_request' ) || ! wp_app_is_app_url_request( $this->config['path'] ) ) {
				return;
			}

			if ( is_callable( $this->config['asset_callback'] ) ) {
				call_user_func( $this->config['asset_callback'] );
			}

			if ( $this->config['style_handle'] && wp_style_is( $this->config['style_handle'], 'enqueued' ) ) {
				wp_add_inline_style( $this->config['style_handle'], $this->get_shell_css() );
			}
		}

		/**
		 * Add an app launcher beside the normal plugin actions.
		 *
		 * @param string[] $links Existing plugin action links.
		 * @return string[]
		 */
		public function add_plugin_action_link( $links ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $this->url() ),
					esc_html( $this->translate_text( $this->config['action_label'] ) )
				)
			);

			return $links;
		}

		/**
		 * Translate package-configured app text after WordPress initialization.
		 *
		 * @param string $text App text.
		 * @return string
		 */
		private function translate_text( $text ) {
			if ( ! function_exists( 'translate' ) || ! did_action( 'init' ) ) {
				return $text;
			}

			// phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain -- Package app text uses a package-configured text domain.
			$translated = translate( $text, $this->config['text_domain'] );

			return is_string( $translated ) && '' !== trim( $translated ) ? $translated : $text;
		}

		/**
		 * Get the app URL.
		 *
		 * @return string
		 */
		public function url() {
			return home_url( '/' . trim( $this->config['path'], '/' ) . '/' );
		}

		/**
		 * Render the package app selected by WpApp routing.
		 *
		 * @return void
		 */
		public static function render_current() {
			$path = function_exists( 'wp_app_get_current_app_path' ) ? wp_app_get_current_app_path() : '';
			if ( ! isset( self::$contexts[ $path ] ) ) {
				return;
			}

			self::$contexts[ $path ]->render_document();
		}

		/**
		 * Render the theme-isolated HTML document and package UI.
		 *
		 * @return void
		 */
		private function render_document() {
			if ( ! function_exists( 'submit_button' ) ) {
				require_once ABSPATH . 'wp-admin/includes/template.php';
			}

			$body_class = 'wp-app-body personalos-wp-app-body personalos-wp-app-' . sanitize_html_class( $this->config['path'] );
			?>
			<!doctype html>
			<html <?php wp_app_language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title><?php echo wp_app_title( $this->config['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WpApp. ?></title>
				<?php wp_app_head(); ?>
			</head>
			<body class="<?php echo esc_attr( $body_class ); ?>">
				<?php wp_app_body_open(); ?>
				<main class="personalos-wp-app-main">
					<?php
					if ( is_callable( $this->config['content_callback'] ) ) {
						call_user_func( $this->config['content_callback'] );
					}
					?>
				</main>
				<?php wp_app_body_close(); ?>
			</body>
			</html>
			<?php
		}

		/**
		 * Shared layout styles for app and PHP fallback screens.
		 *
		 * @return string
		 */
		private function get_shell_css() {
			return <<<'CSS'
html {
	background: var(--wp-app-color-background);
}

body.personalos-wp-app-body {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	letter-spacing: 0;
	margin: 0;
	min-height: 100vh;
}

.personalos-wp-app-main {
	box-sizing: border-box;
	margin: 0 auto;
	max-width: 1240px;
	padding: 32px 24px 48px;
	width: 100%;
}

.personalos-wp-app-main .wrap {
	margin: 0;
}

.personalos-wp-app-main h1 {
	font-size: 28px;
	line-height: 1.25;
	margin: 0 0 24px;
}

.personalos-wp-app-main h2 {
	font-size: 18px;
	line-height: 1.4;
	margin: 28px 0 12px;
}

.personalos-wp-app-main .form-table,
.personalos-wp-app-main .widefat {
	background: var(--wp-app-color-surface);
	border: 1px solid var(--wp-app-color-border);
	border-collapse: collapse;
	color: var(--wp-app-color-text);
	width: 100%;
}

.personalos-wp-app-main .form-table th,
.personalos-wp-app-main .form-table td,
.personalos-wp-app-main .widefat th,
.personalos-wp-app-main .widefat td {
	border-bottom: 1px solid var(--wp-app-color-border);
	padding: 14px 16px;
	text-align: left;
	vertical-align: top;
}

.personalos-wp-app-main .form-table th {
	width: 220px;
}

.personalos-wp-app-main input[type="text"],
.personalos-wp-app-main input[type="password"],
.personalos-wp-app-main input[type="number"],
.personalos-wp-app-main select,
.personalos-wp-app-main textarea {
	background: var(--wp-app-color-surface);
	border: 1px solid var(--wp-app-color-border);
	border-radius: 4px;
	box-sizing: border-box;
	color: var(--wp-app-color-text);
	font: inherit;
	max-width: 100%;
	padding: 8px 10px;
	width: 100%;
}

.personalos-wp-app-main .submit {
	margin: 16px 0 0;
}

.personalos-wp-app-main .button,
.personalos-wp-app-main input[type="submit"] {
	border: 1px solid var(--wp-app-color-border);
	border-radius: 4px;
	cursor: pointer;
	font: inherit;
	min-height: 36px;
	padding: 6px 12px;
}

@media (max-width: 700px) {
	.personalos-wp-app-main {
		padding: 24px 16px 40px;
	}

	.personalos-wp-app-main .form-table th,
	.personalos-wp-app-main .form-table td {
		display: block;
		width: auto;
	}
}
CSS;
		}

		/**
		 * Schedule rewrite rule generation after activation.
		 *
		 * @return void
		 */
		public static function activate() {
			update_option( 'wp_app_flush_rewrite_rules', true );
		}

		/**
		 * Remove persisted routes after deactivation.
		 *
		 * @return void
		 */
		public static function deactivate() {
			delete_option( 'wp_app_flush_rewrite_rules' );
			delete_option( 'rewrite_rules' );
		}
	}
}
