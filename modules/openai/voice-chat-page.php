<?php
/**
 * Voice Chat Admin Page
 *
 * @package PersonalOS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the voice chat admin page.
 *
 * @return void
 */
function pos_render_voice_chat_page() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin page HTML output.
	echo <<<EOF
	<div id="chat-container">
		<div id="messages">
			<!-- Chat messages will appear here -->
			<div class="message assistant">
				<b>OpenAI advanced voice mode</b>
			</div>
		</div>
		<div class="audio-container">
			<button id="start-session">Start Session</button>
		</div>
		<div id="input-container">
			<input type="text" id="message-input" placeholder="Type your message...">
			<button id="send-button">Send</button>
		</div>
		<div class="audio-controls">
			<span class="icon">🎤</span>
			<select id="audio-input"></select>
			<span class="icon">🎧</span>
			<select id="audio-output"></select>
		</div>
	</div>
	<style>
		#wpbody-content {
			margin-bottom:0;
			padding-bottom:0;
		}
		.audio-controls {
			display: flex;
			align-items: center;
			gap: 10px;
			display:flex;
			justify-content: space-around;
			padding: 10px;
		}
		.audio-controls .icon {
			font-size: 24px;
		}
		.audio-controls select {
			font-size: 11px;
		}
		#chat-container {
			width: 100%;
			max-width: 800px;
			margin: 0 auto;
			height: calc(100vh - 50px); /* Adjust for WP admin bar and padding */
			background-color: #ffffff;
			//box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
			border-radius: 8px;
			display: flex;
			flex-direction: column;
			overflow: hidden;
		}
		// #chat-container.session_active #input-container {
		// 	display: none;
		// }
		#messages {
			flex: 1;
			min-height: 0; /* Important for flex child scrolling */
			padding: 16px;
			overflow-y: auto;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.message {
			max-width: 70%;
			padding: 10px 14px;
			border-radius: 18px;
			font-size: 14px;
			line-height: 1.5;
		}
		.message.user {
			align-self: flex-end;
			background-color: #007bff;
			color: white;
			border-bottom-right-radius: 4px;
		}
		.message.assistant {
			align-self: flex-start;
			background-color: #e4e6eb;
			color: black;
			border-bottom-left-radius: 4px;
		}
		.message.tool {
			align-self: flex-start;
			background-color: #edf2f7;
			color: black;
			border-bottom-left-radius: 4px;
			cursor: pointer;
		}
		.message.tool pre {
			display: none;
			font-size: 0.75em;
			overflow-x: auto;
		}
		.message.tool:hover pre {
			display: block;
			border-bottom: 1px dashed #ccc;
		}

		#input-container {
			display: flex;
			padding: 12px;
			background-color: #f9f9f9;
			border-top: 1px solid #ddd;
		}
		#message-input {
			flex: 1;
			padding: 10px;
			border: 1px solid #ddd;
			border-radius: 20px;
			font-size: 14px;
		}
		#send-button {
			margin-left: 10px;
			padding: 10px 16px;
			border: none;
			border-radius: 20px;
			background-color: #007bff;
			color: white;
			font-size: 14px;
			cursor: pointer;
		}
		#send-button:hover {
			background-color: #0056b3;
		}

		/* Update pulsating orb button styles */
		#start-session {
			width: 80px;
			height: 80px;
			border-radius: 50%;
			background: radial-gradient(circle at 30% 30%, #5a9bff, #007bff);
			border: none;
			color: white;
			cursor: pointer;
			position: relative;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Added default shadow */
			transition: transform 0.2s;
		}

		#chat-container.session_active #start-session {
			animation: pulse 2s infinite;
			background: radial-gradient(circle at 30% 30%, #ff5a5a, #ff0000);
		}

		#chat-container.speaking #start-session {
			animation: pulse 2s infinite, scale 0.3s infinite;
		}

		#start-session:hover {
			transform: scale(1.05);
		}

		@keyframes pulse {
			0% {
				box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
			}
			70% {
				box-shadow: 0 0 0 15px rgba(0, 123, 255, 0);
			}
			100% {
				box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
			}
		}

		@keyframes scale {
			0%, 100% {
				transform: scale(1);
			}
			50% {
				transform: scale(1.2);
			}
		}

		.audio-container {
			display: flex;
			justify-content: center;
			padding: 20px 0;
		}
	</style>
	EOF;

	$openai_module_file = __DIR__ . '/class-openai-module.php';
	wp_enqueue_script( 'voice-chat', plugins_url( 'assets/voice-chat.js', $openai_module_file ), array( 'wp-api-fetch' ), time(), true );
	//wp_enqueue_style( 'voice-chat', plugins_url( 'assets/voice-chat.css', $openai_module_file ) );
}

