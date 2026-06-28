<?php
/**
 * Shared admin notices.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Admin_Notice_Helper' ) ) {
	/**
	 * Renders setup notices.
	 */
	class PersonalOS_Admin_Notice_Helper {
		/**
		 * Render a warning notice.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function warning( $message ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				wp_kses_post( $message )
			);
		}

		/**
		 * Render missing Knowledge notice.
		 *
		 * @param string $plugin_name Plugin name.
		 * @return void
		 */
		public static function missing_knowledge( $plugin_name ) {
			self::warning(
				sprintf(
					/* translators: %s: plugin name */
					__( '%s is active, but no Knowledge or Guidelines runtime is available. Install or enable a runtime that provides wp_knowledge/wp_knowledge_type or wp_guideline/wp_guideline_type.', 'personalos' ),
					esc_html( $plugin_name )
				)
			);
		}

		/**
		 * Render missing AI Client notice.
		 *
		 * @param string $plugin_name Plugin name.
		 * @return void
		 */
		public static function missing_ai_client( $plugin_name ) {
			self::warning(
				sprintf(
					/* translators: %s: plugin name */
					__( '%s is active. Configure WordPress AI Client and Connectors to use live chat.', 'personalos' ),
					esc_html( $plugin_name )
				)
			);
		}
	}
}
