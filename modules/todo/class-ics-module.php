<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICS_Module extends POS_Module {
	public $id = 'ics';
	public $name = 'ICS Calendar Export';
	public $description = 'Export TODOs as an ICS calendar feed that can be imported into calendar apps.';
	public $settings = array(
		'token' => array(
			'type'    => 'text',
			'name'    => 'Token for the ICS feed',
			'label'   => 'This is a token that will be used to access the ICS feed. It can be used to generate a private feed.',
			'default' => '',
		),
	);

	public function register(): void {
		if ( strlen( $this->get_setting( 'token' ) ) > 0 ) {
			add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
		}
	}

	public function register_rest_endpoints(): void {
		register_rest_route(
			$this->rest_namespace,
			'/ics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'generate_ics_feed' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'token' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission to access the ICS feed
	 */
	public function check_permission( WP_REST_Request $request ): bool {
		return $request->get_param( 'token' ) === $this->get_setting( 'token' );
	}

	/**
	 * Generate ICS feed from TODOs
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function generate_ics_feed( WP_REST_Request $request ): mixed {

		$ics_content = $this->generate_ics_content();

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="todos.ics"' );
		echo $ics_content;
		die();
		// return $response;
	}

	/**
	 * Generate ICS content from TODOs
	 *
	 * @param WP_Post[] $todos Array of TODO posts
	 * @return string The ICS content
	 */
	private function generate_ics_content(): string {
		$output = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//PersonalOS//TODO Calendar//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:PersonalOS TODOs',
		);
		$scheduled_todos = POS::get_module_by_id( 'todo' )->get_scheduled_todos();
		foreach ( $scheduled_todos as $scheduled ) {
			$start_date = $scheduled['timestamp'];
			$todo = get_post( $scheduled['todo_id'] );
			if ( ! $todo ) {
				continue;
			}

			// Format date for ICS
			$start_date_formatted = gmdate( 'Ymd\THis\Z', $start_date );

			// Get notebooks for categories
			$notebooks = wp_get_post_terms( $todo->ID, 'notebook', array( 'fields' => 'names' ) );
			$categories = ! empty( $notebooks ) ? implode( ',', $notebooks ) : '';

			$output[] = 'BEGIN:VEVENT';
			$output[] = 'UID:todo-' . $todo->ID . '@personalos';
			$output[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$output[] = 'DTSTART:' . $start_date_formatted;
			$output[] = 'SUMMARY:' . $this->escape_ics_text( $todo->post_title );

			if ( ! empty( $todo->post_excerpt ) ) {
				$output[] = 'DESCRIPTION:' . $this->escape_ics_text( $todo->post_excerpt );
			}

			if ( ! empty( $categories ) ) {
				$output[] = 'CATEGORIES:' . $this->escape_ics_text( $categories );
			}

			// Add URL if exists
			$url = get_post_meta( $todo->ID, 'url', true );
			if ( ! empty( $url ) ) {
				$output[] = 'URL:' . $url;
			}

			$output[] = 'END:VEVENT';
		}

		$output[] = 'END:VCALENDAR';

		return implode( "\r\n", $output );
	}

	/**
	 * Escape special characters in text for ICS format
	 *
	 * @param string $text The text to escape
	 * @return string The escaped text
	 */
	private function escape_ics_text( string $text ): string {
		$text = str_replace( array( "\r\n", "\n", "\r" ), '\n', $text );
		$text = str_replace( array( ',', ';', '\\' ), array( '\,', '\;', '\\\\' ), $text );
		return $text;
	}
}
