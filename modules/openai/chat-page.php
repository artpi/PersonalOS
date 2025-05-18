<?php
/**
 * PersonalOS Dashboard Page
 *
 * @package PersonalOS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the PersonalOS Chatbot admin page.
 */
function personalos_add_chatbot_admin_page(): void {
	add_menu_page(
		__( 'Chatbot Dashboard', 'personalos' ),
		__( 'Chatbot', 'personalos' ),
		'manage_options',
		'personalos-chatbot',
		'personalos_render_chatbot_dashboard',
		'dashicons-format-chat',
		20
	);
}
add_action( 'admin_menu', 'personalos_add_chatbot_admin_page' );

/**
 * Renders the Chatbot Dashboard page.
 *
 * This function includes the static Next.js build.
 */
function personalos_render_chatbot_dashboard(): void {
	// Determine the plugin directory path and URL.
	// Assumes dashboard.php is in the root of the plugin 'personalos'.
	// If dashboard.php is in a subdirectory, __FILE__ needs to be adjusted.
	// For example, if in personalos/admin/dashboard.php, use plugin_dir_path( dirname( __FILE__, 2 ) )
	$plugin_dir_path = plugin_dir_path( __DIR__ . '/../../..' ); // Gets /path/to/wp-content/plugins/personalos/
	$plugin_dir_url  = plugin_dir_url( __DIR__ . '/../../..' );  // Gets http(s)://.../wp-content/plugins/personalos/

	$chatbot_build_path = $plugin_dir_path . 'build/chatbot/';
	$index_html_path    = $chatbot_build_path . 'index.html';

	if ( file_exists( $index_html_path ) ) {
		$html_content = file_get_contents( $index_html_path );

		if ( false === $html_content ) {
			echo '<div class="error"><p>' . esc_html__( 'Error: Could not read the chatbot index file.', 'personalos' ) . '</p></div>';
			return;
		}

		// Temporarily disable ALL modifications to $html_content for debugging hydration
		/*
		// Rewrite asset paths.
		// Ensure trailing slashes are consistent.
		$base_build_url = esc_url( $plugin_dir_url . 'build/chatbot/' );

		// 1. Replace /_next/ paths for CSS, JS, media
		$html_content = str_replace( 'href="/_next/', 'href="' . $base_build_url . '_next/', $html_content );
		$html_content = str_replace( 'src="/_next/', 'src="' . $base_build_url . '_next/', $html_content );

		// 2. Replace /favicon.ico
		$html_content = str_replace( 'href="/favicon.ico"', 'href="' . $base_build_url . 'favicon.ico"', $html_content );
		*/

		// WordPress admin environment specifics:
		// Remove default WordPress admin styling that might conflict.
		// This is a heavy-handed approach; more targeted CSS reset/scoping might be needed.
		// echo '<style>
		// 	#wpadminbar, #adminmenumain, #wpfooter, .wrap h1, .wrap .notice, .wrap .updated, .wrap .error { display: none !important; }
		// 	html.wp-toolbar { padding-top: 0px !important; }
		// 	body.wp-admin { background: #fff; /* Or match Next.js app background */ }
		// 	#wpcontent { padding-left: 0 !important; margin-left: 0 !important; /* Reset WP content area styling */ }
		// 	#personalos_chatbot_container { width: 100%; height: 100vh; overflow: auto; }
		// </style>';
		// echo '<div id="personalos_chatbot_container">';

		// Nonce for security can be added if there are interactions back to WordPress.
		// For now, just displaying the static content.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html_content;

		// echo '</div>';

	} else {
		echo '<div class="error"><p>' .
			sprintf(
				/* translators: %s: path to index.html */
				esc_html__( 'Error: Chatbot index file not found. Expected at: %s. Please run the build process.', 'personalos' ),
				'<code>' . esc_html( $index_html_path ) . '</code>'
			) .
			'</p></div>';
	}
}
