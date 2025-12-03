<?php
/**
 * Custom GPT Admin Page
 *
 * @package PersonalOS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the custom GPT admin page.
 *
 * @return void
 */
function pos_render_custom_gpt_page() {
	ob_start();
	?>
	<h1>Configure your custom GPT</h1>
	<p><a href='https://chatgpt.com/gpts/mine' target='_blank'>First create a new custom GPT</a></p>
	<h2>System prompt</h2>
	<p>This is the system prompt for your custom GPT. Modify it to fit your needs.</p>
	<textarea style="width: 100%; height: 500px;">
	You are an assistant with access to my database of notes and todos.
	You will help me complete tasks and schedule my work.

	My work is organized in "notebooks"
	- Stuff to do right now is in notebook with the slug "now"
	- Stuff to do later is in notebook with the slug "later"
	- Default notebook has slug inbox

	You probably should download the list of notebooks to reference them while I am talking to you.
	When listing notes in particular notebook, use the notebook id and the notebook field of todo_get_items

	- NEVER say you created a todo without calling the appropriate action.
	- when I ask you to create a TODO, always return a URL
	- Alwas create new todos with 'private' status
	</textarea>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin page HTML output.
	echo ob_get_clean();
	// Reading local JSON file from plugin directory.
	global $wp_filesystem;
	require_once ABSPATH . '/wp-admin/includes/file.php';
	WP_Filesystem();
	$openai_module_file = __DIR__ . '/class-openai-module.php';
	$schema_file = plugin_dir_path( $openai_module_file ) . 'chatgpt_routes.json';
	$schema = $wp_filesystem->get_contents( $schema_file );
	$schema = json_decode( $schema, true );
	$schema['servers'][0]['url'] = get_rest_url( null, '' );
	$schema = wp_json_encode( $schema, JSON_PRETTY_PRINT );
	$schema = wp_unslash( $schema );
	$login = esc_attr( wp_get_current_user()->user_login );
	$schema = esc_textarea( $schema );
	ob_start();
	?>
	<h2>Schema</h2>
	<p>This is the schema for your custom GPT. It describes the API endpoints that your GPT can use. Copy it into your ChatGPT configuration.</p>
	<textarea style="width: 100%; height: 500px;">
	<?php echo $schema; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped with esc_textarea. ?>
	</textarea>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin page HTML output.
	echo ob_get_clean();

	ob_start();
	?>
	<h2>Auth</h2>
	<p>You can use basic request and application passwords to authenticate your requests:</p>
	<ol>
		<li><a href='authorize-application.php' target='_blank'>Create an Application Password for your user</a></li>
		<li>Paste the password and encode below using base64</li>
		<li>Use the encoded password as the token for basic auth in your ChatGPT configuration</li>
	</ol>
	<h3>Encode password</h3>
	<input type="hidden" id="app_username" value="<?php echo esc_attr( $login ); ?>">
	<input type="text" id="app_password" placeholder="Password">
	<button id="encode" onclick="encode()">Encode</button>
	<pre id="encoded"></pre>
	<script>
		function encode() {
			const username = document.getElementById('app_username').value;
			const password = document.getElementById('app_password').value;
			const encoded = btoa(username + ':' + password);
			document.getElementById('encoded').textContent = encoded;
		}
	</script>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin page HTML output.
	echo ob_get_clean();
}

