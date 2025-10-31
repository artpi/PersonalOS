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

// Static variable to store the full HTML content of the Next.js index.html.
global $personalos_chatbot_full_html_content;
$personalos_chatbot_full_html_content = '';

function personalos_map_notebook_to_para_item( $notebook ) {
	return array(
		'id'   => $notebook->slug,
		'name' => $notebook->name,
		'icon' => 'FileIcon',
	);
}

// This has to match the Config type in src-chatbot/lib/window.d.ts - Cursor please always check this
function personalos_chat_config() {
	return array(
		'rest_api_url' => rest_url( '/' ),
		'wp_admin_url' => admin_url(),
		'site_title'   => get_bloginfo( 'name' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'projects'     => array_map(
			'personalos_map_notebook_to_para_item',
			POS::get_module_by_id( 'notes' )->get_notebooks_by_flag( 'project' )
		),
		'starred'      => array_map(
			'personalos_map_notebook_to_para_item',
			POS::get_module_by_id( 'notes' )->get_notebooks_by_flag( 'star' )
		),
		'user'         => array(
			'id'    => get_current_user_id(),
			'login' => wp_get_current_user()->user_login,
		),
	);
}

/**
 * Prepares chatbot assets by reading the Next.js index.html file.
 * Populates a global variable with the full HTML content.
 */
function personalos_prepare_chatbot_assets_and_data(): void {
	global $personalos_chatbot_full_html_content;

	// Determine the plugin root directory.
	// Assumes chat-page.php is in personalos/modules/openai/chat-page.php
	$plugin_root_dir = dirname( __DIR__, 2 ); // Resolves to .../personalos/
	$index_html_path = $plugin_root_dir . '/build/chatbot/index.html';

	if ( ! file_exists( $index_html_path ) ) {
		// Error will be handled by personalos_render_chatbot_dashboard if $personalos_chatbot_full_html_content remains empty.
		$personalos_chatbot_full_html_content = ''; // Ensure it's empty on failure.
		return;
	}

	$html_content = file_get_contents( $index_html_path );
	if ( false === $html_content ) {
		$personalos_chatbot_full_html_content = ''; // Ensure it's empty on failure.
		return;
	}

	// Get the URL to the chatbot build directory using proper WordPress functions
	$chatbot_url = plugins_url( 'build/chatbot', dirname( dirname( __FILE__ ) ) );

	$html_content = str_replace(
		'/wp-content/plugins/personalos/build/chatbot',
		$chatbot_url,
		$html_content
	);

	// JSON encode the data and escape it for safe insertion into a script tag.
	$json_data = wp_json_encode( personalos_chat_config() );

	// Create the script tag.
	// Using <script type="text/javascript"> for broader compatibility, though type is optional in HTML5.
	$script_tag = "<script type=\"text/javascript\">\n";
	$script_tag .= "\twindow.config = " . $json_data . ";\n";
	$script_tag .= "</script>\n";

	// Inject the script tag before the closing </body> tag.
	// This is generally a safe place that ensures the data is available before app scripts run.
	$html_content = str_replace( '</body>', $script_tag . '</body>', $html_content );

	$personalos_chatbot_full_html_content = $html_content;
}

/**
 * Sets up hooks for the chatbot page after assets and data have been prepared.
 * This function is called on the load-{$page_hook} action.
 */
function personalos_chatbot_page_setup_hooks(): void {
	global $personalos_chatbot_full_html_content; // Ensure access to the global variable.

	// Prepare assets and data first.
	personalos_prepare_chatbot_assets_and_data();

	// If HTML content is successfully loaded, output it directly and terminate.
	if ( ! empty( $personalos_chatbot_full_html_content ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $personalos_chatbot_full_html_content;
		die();
	}
	// If $personalos_chatbot_full_html_content is empty (e.g., file not found),
	// execution will continue. WordPress will then call personalos_render_chatbot_dashboard(),
	// which will display the appropriate error message within the standard admin page structure.
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
 * Outputs the full HTML content from the Next.js app's index.html file and terminates.
 */
function personalos_render_chatbot_dashboard(): void {
	global $personalos_chatbot_full_html_content;

	// Determine the plugin root directory for the error message.
	// This needs to be calculated here again as it's used in the error message
	// and personalos_prepare_chatbot_assets_and_data might not have set it if it bailed early.
	$plugin_root_dir = dirname( __DIR__, 2 );
	$index_html_path = $plugin_root_dir . '/build/chatbot/index.html';

	if ( empty( $personalos_chatbot_full_html_content ) ) {
		// This indicates an issue in personalos_prepare_chatbot_assets_and_data()
		// (e.g., file not found, unreadable).
		echo '<div class="error"><p>' .
			sprintf(
				/* translators: %1$s: Path to the Next.js index.html file. */
				esc_html__( 'Error: Chatbot content could not be loaded. Expected at: %1$s. Please ensure the Next.js application has been built correctly and the file is readable.', 'personalos' ),
				'<code>' . esc_html( $index_html_path ) . '</code>'
			) .
			'</p></div>';
		return; // WordPress will render its usual admin page structure here if we just return.
				// To prevent this, ensure WordPress admin styles/scripts are dequeued if full HTML isn't available,
				// or reconsider if an empty page with just this error is acceptable.
				// For raw HTML output, if this error occurs, we are not outputting raw HTML.
	}

	// Output the Next.js application's full HTML content.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $personalos_chatbot_full_html_content;
	die(); // Terminate to prevent any further WordPress output.
}
