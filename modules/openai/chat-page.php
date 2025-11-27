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

/**
 * Get messages from a post and parse them into UIMessage format
 *
 * @param int $post_id The post ID to retrieve messages from.
 * @return array Parsed messages.
 */
/**
 * Fix corrupted newlines in message content.
 * WordPress's stripslashes corrupts \n in JSON to just 'n'.
 * This function restores them by detecting patterns like 'nn' before capitals and 'n-' for list items.
 *
 * @param string $content The potentially corrupted content.
 * @return string Content with newlines restored.
 */
function personalos_fix_corrupted_newlines( $content ) {
	// Fix "nn" followed by capital letter (paragraph break before new section)
	$content = preg_replace( '/nn(?=[A-Z])/', "\n\n", $content );
	// Fix "n- " (list item marker)
	$content = str_replace( 'n- ', "\n- ", $content );
	// Fix "n#" (markdown headers)
	$content = preg_replace( '/n(#{1,6}\s)/', "\n$1", $content );
	// Fix "n" followed by digit and period/parenthesis (numbered lists like "1." or "1)")
	$content = preg_replace( '/n(\d+[.\)])\s/', "\n$1 ", $content );

	return $content;
}

/**
 * Get messages from a post and parse them into UIMessage format
 *
 * @param int $post_id The post ID to retrieve messages from.
 * @return array Parsed messages.
 */
function personalos_get_messages_from_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	$blocks = parse_blocks( $post->post_content );
	$messages = array();

	foreach ( $blocks as $block ) {
		if ( $block['blockName'] === 'pos/ai-message' ) {
			$role = $block['attrs']['role'] ?? 'user';
			$content = $block['attrs']['content'] ?? '';
			$id = $block['attrs']['id'] ?? 'generated_' . uniqid();

			// Fix corrupted newlines from WordPress stripslashes
			$content = personalos_fix_corrupted_newlines( $content );

			$messages[] = array(
				'id'        => $id,
				'role'      => $role,
				'content'   => $content,
				'createdAt' => get_the_date( 'c', $post ), // Approximate
			);
		}
	}

	return $messages;
}

// This has to match the Config type in src-chatbot/lib/window.d.ts - Cursor please always check this
function personalos_chat_config() {
	$openai_module = POS::get_module_by_id( 'openai' );
	$prompts_data = array_values( $openai_module->get_chat_prompts() );

	$current_user_id = get_current_user_id();
	$last_chat_model  = get_user_meta( $current_user_id, 'pos_last_chat_model', true );

	// Get the 'ai-chats' notebook term ID
	$ai_chats_notebook = get_term_by( 'slug', 'ai-chats', 'notebook' );
	$ai_chats_notebook_id = $ai_chats_notebook ? $ai_chats_notebook->term_id : 0;

	// Handle Conversation Bootstrapping
	$conversation_id = 0;
	$conversation_messages = array();

	// Only load existing conversation if ID param is explicitly provided
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested_id = intval( $_GET['id'] );
		$post = get_post( $requested_id );

		if ( $post && current_user_can( 'edit_post', $post->ID ) && 'notes' === $post->post_type ) {
			$conversation_id = $requested_id;
			$conversation_messages = personalos_get_messages_from_post( $conversation_id );
		}
	}

	// Always create new conversation if no valid ID was provided
	// This ensures each page load creates a fresh conversation post
	if ( empty( $conversation_id ) ) {
		// Use empty backscroll to signal creation of a new empty post
		// This relies on save_backscroll handling empty input gracefully to create a post
		$openai_module = POS::get_module_by_id( 'openai' );
		if ( $openai_module ) {
			// Generate unique slug to avoid conflicts
			$unique_slug = 'chat-' . gmdate( 'Y-m-d-H-i-s' ) . '-' . wp_generate_password( 8, false );
			$conversation_id = $openai_module->save_backscroll( array(), array( 'name' => $unique_slug ) );
			if ( is_wp_error( $conversation_id ) ) {
				$conversation_id = 0; // Fallback or handle error? For now 0 implies failure/fallback in UI
			}
		} else {
			$conversation_id = 0; // Fallback
		}
	}

	return array(
		'rest_api_url'          => rest_url( '/' ),
		'wp_admin_url'          => admin_url(),
		'site_title'            => get_bloginfo( 'name' ),
		'nonce'                 => wp_create_nonce( 'wp_rest' ),
		'conversation_id'       => $conversation_id,
		'conversation_messages' => $conversation_messages,
		'ai_chats_notebook_id'  => $ai_chats_notebook_id,
		'projects'              => array_map(
			'personalos_map_notebook_to_para_item',
			POS::get_module_by_id( 'notes' )->get_notebooks_by_flag( 'project' )
		),
		'starred'               => array_map(
			'personalos_map_notebook_to_para_item',
			POS::get_module_by_id( 'notes' )->get_notebooks_by_flag( 'star' )
		),
		'chat_prompts'          => $prompts_data,
		'pos_last_chat_model'   => $last_chat_model ? $last_chat_model : '',
		'user'                  => array(
			'id'    => $current_user_id,
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
