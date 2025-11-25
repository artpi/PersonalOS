<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Perplexity_Module extends POS_Module {
	public $id = 'perplexity';
	public $name = 'Perplexity AI';
	public $description = 'Interface for Perplexity AI to generate completions and responses.';
	public $settings = array(
		'api_token' => array(
			'type'  => 'text',
			'name'  => 'Perplexity API Token',
			'label' => 'Enter your Perplexity API token.',
		),
	);

	public function register(): void {
		if ( ! $this->get_setting( 'api_token' ) ) {
			return;
		}
		$this->register_cli_command( 'search', 'cli_search' );

		// Register abilities
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register Perplexity module abilities with WordPress Abilities API.
	 */
	public function register_abilities() {
		// Register perplexity_search ability
		wp_register_ability(
			'pos/perplexity-search',
			array(
				'label'               => __( 'Perplexity Search', 'personalos' ),
				'description'         => __( 'Search the web using Perplexity search. Use this tool only if you are certain you need the information from the internet.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'The search query to send to Perplexity',
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'string',
					'description' => 'Search result content from Perplexity',
				),
				'execute_callback'    => array( $this, 'search_ability' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);
	}

	/**
	 * Search the web using Perplexity ability.
	 *
	 * @param array $arguments Arguments with query string.
	 * @return string|WP_Error Search result content or error.
	 */
	public function search_ability( $arguments ) {
		$result = $this->search( $arguments['query'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result['content'];
	}

	/**
	 * Make an API call to Perplexity
	 *
	 * @param string $url The API endpoint URL
	 * @param array  $data The request data
	 * @return mixed|WP_Error The API response or error
	 */
	public function api_call( string $url, array $data ): mixed {
		$api_key = $this->get_setting( 'api_token' );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		$body     = wp_remote_retrieve_body( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return json_decode( $body );
	}

	/**
	 * Generate a chat completion using Perplexity API
	 *
	 * @param array  $messages Array of messages for the conversation
	 * @param string $model The model to use (default: 'sonar')
	 * @return array|WP_Error The completion text or error
	 */
	public function chat_completion( array $messages = array(), string $model = 'sonar' ): array|WP_Error {
		$data = array(
			'model'    => $model,
			'messages' => $messages,
		);
		$response = $this->api_call( 'https://api.perplexity.ai/chat/completions', $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response->choices[0]->message->content ) ) {
			return new WP_Error( 'no-response', 'No response from Perplexity' );
		}
		$citations = $response->citations;

		return array(
			'content'   => $response->choices[0]->message->content,
			'role'      => $response->choices[0]->message->role,
			'citations' => $citations,
		);
	}

	public function search( $query ): array|WP_Error {
		$result = $this->chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => $query,
				),
			),
			'sonar'
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Replace citation markers with markdown links
		$citations = array();
		if ( ! empty( $result['citations'] ) ) {
			foreach ( $result['citations'] as $index => $citation ) {
				$citation = '[' . ( $index + 1 ) . '](' . esc_url( $citation ) . ')';
				$result['content'] = str_replace( '[' . ( $index + 1 ) . ']', $citation, $result['content'] );
				$citations[] = $citation;
			}
			$citations = implode( ', ', $citations );
			$result['content'] .= "\n\nReferences: {$citations}";
		}

		return $result;
	}

	/**
	 * CLI command to search using Perplexity AI
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : The search query to send to Perplexity
	 *
	 */
	public function cli_search( array $args, array $assoc_args = array() ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide a search query' );
			return;
		}

		$response = $this->search( $args[0] );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->get_error_message() );
			return;
		}

		WP_CLI::success( $response['content'] );
	}
}
