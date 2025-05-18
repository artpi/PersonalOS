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

// Static variables to store parts of the Next.js index.html.
// Using globals here for simplicity in a procedural context.
global $personalos_chatbot_head_content, $personalos_chatbot_body_attributes, $personalos_chatbot_body_inner_html;
$personalos_chatbot_head_content = '';
$personalos_chatbot_body_attributes = array();
$personalos_chatbot_body_inner_html = '';

/**
 * Prepares chatbot assets and data by parsing the Next.js index.html.
 * Populates global variables with extracted head content, body attributes, and body inner HTML.
 */
function personalos_prepare_chatbot_assets_and_data(): void {
	global $personalos_chatbot_head_content, $personalos_chatbot_body_attributes, $personalos_chatbot_body_inner_html;

	// Determine the plugin root directory.
	// Assumes chat-page.php is in personalos/modules/openai/chat-page.php
	$plugin_root_dir = dirname( __DIR__, 2 ); // Resolves to .../personalos/
	$index_html_path = $plugin_root_dir . '/build/chatbot/index.html';

	if ( ! file_exists( $index_html_path ) ) {
		// Error will be handled by personalos_render_chatbot_dashboard if $personalos_chatbot_body_inner_html remains empty.
		return;
	}

	$html_content = file_get_contents( $index_html_path );
	if ( false === $html_content ) {
		return;
	}

	$doc = new DOMDocument();
	// Suppress errors for HTML5 elements and ensure UTF-8 processing.
	// Minified Next.js HTML might not be perfectly formed for a strict parser.
	@$doc->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'UTF-8' ) );

	$head_node = $doc->getElementsByTagName( 'head' )->item( 0 );
	if ( $head_node ) {
		foreach ( $head_node->childNodes as $child_node ) {
			$personalos_chatbot_head_content .= $doc->saveHTML( $child_node );
		}
	}

	$body_node = $doc->getElementsByTagName( 'body' )->item( 0 );
	if ( $body_node ) {
		if ( $body_node->hasAttributes() ) {
			foreach ( $body_node->attributes as $attr ) {
				$personalos_chatbot_body_attributes[ $attr->nodeName ] = $attr->nodeValue;
			}
		}
		foreach ( $body_node->childNodes as $child_node ) {
			$personalos_chatbot_body_inner_html .= $doc->saveHTML( $child_node );
		}
	}
}

/**
 * Sets up hooks for the chatbot page after assets and data have been prepared.
 * This function is called on the load-{$page_hook} action.
 */
function personalos_chatbot_page_setup_hooks(): void {
	// Prepare assets and data first.
	personalos_prepare_chatbot_assets_and_data();

	// Now add hooks that will use the prepared data.
	add_action( 'admin_head', 'personalos_output_chatbot_head_content' );
	add_filter( 'admin_body_class', 'personalos_add_chatbot_body_classes' );
}

/**
 * Outputs the extracted <head> content from Next.js index.html into the WP admin head.
 */
function personalos_output_chatbot_head_content(): void {
	global $personalos_chatbot_head_content;
	if ( ! empty( $personalos_chatbot_head_content ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $personalos_chatbot_head_content;
	}
}

/**
 * Adds body classes from Next.js <body> tag to WordPress admin body classes.
 *
 * @param string $admin_body_classes Space-separated string of existing admin body classes.
 * @return string Modified string of admin body classes.
 */
function personalos_add_chatbot_body_classes( string $admin_body_classes ): string {
	global $personalos_chatbot_body_attributes;
	$nextjs_body_classes = isset( $personalos_chatbot_body_attributes['class'] ) ? $personalos_chatbot_body_attributes['class'] : '';

	if ( ! empty( $nextjs_body_classes ) ) {
		$admin_body_classes .= ' ' . $nextjs_body_classes;
	}
	return trim( $admin_body_classes );
}

/**
 * Adds the PersonalOS Chatbot admin page and associated hooks.
 */
function personalos_add_chatbot_admin_page(): void {
	$page_hook = add_menu_page(
		__( 'Chatbot Dashboard', 'personalos' ),
		__( 'Chatbot', 'personalos' ),
		'manage_options',
		'personalos-chatbot',
		'personalos_render_chatbot_dashboard',
		'dashicons-format-chat',
		20
	);

	// Setup hooks specific to this page when it loads.
	add_action( "load-{$page_hook}", 'personalos_chatbot_page_setup_hooks' );
}
add_action( 'admin_menu', 'personalos_add_chatbot_admin_page' );

/**
 * Renders the Chatbot Dashboard page content.
 * Outputs the inner HTML of the Next.js app's <body> tag.
 */
function personalos_render_chatbot_dashboard(): void {
	global $personalos_chatbot_body_inner_html;

	// Determine the plugin root directory for the error message.
	$plugin_root_dir = dirname( __DIR__, 2 );
	$index_html_path = $plugin_root_dir . '/build/chatbot/index.html';

	if ( empty( $personalos_chatbot_body_inner_html ) ) {
		// This indicates an issue in personalos_prepare_chatbot_assets_and_data()
		// (e.g., file not found, unreadable, or parsing failed).
		echo '<div class="error"><p>' .
			sprintf(
				/* translators: %1$s: Path to the Next.js index.html file. */
				esc_html__( 'Error: Chatbot content could not be loaded. Expected at: %1$s. Please ensure the Next.js application has been built correctly and the file is readable.', 'personalos' ),
				'<code>' . esc_html( $index_html_path ) . '</code>'
			) .
			'</p></div>';
		return;
	}

	// Output the Next.js application's body content.
	// This content has been extracted from the Next.js build's index.html.
	// It includes the necessary divs and scripts for the React app to hydrate and run.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $personalos_chatbot_body_inner_html;
}
