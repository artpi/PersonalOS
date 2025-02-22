<?php

class OpenAI_Endpoints {
	private string $namespace = 'pos/v1';

	/**
	 * Get OpenAPI compatible schema for registered endpoints
	 */
	public function get_schema(): array {
		$routes = rest_get_server()->get_routes();
		$openapi = array(
			'openapi' => '3.1.0',
			'info'    => array(
				'title'   => 'PersonalOS API',
				'version' => '1.0.0',
			),
			'servers' => array(
				array(
					'url' => rest_url(),
				),
			),
			'paths'   => array(),
		);

		// $openapi['paths'] = array(
		// 	'/pos/v1/todo' => array(
		// 		'get'  => array(
		// 			'operationId' => 'todo_get_items',
		// 			'tags'        => array(
		// 				'todo',
		// 			),
		// 			'summary'     => '',
		// 			'description' => '',
		// 			'parameters'  => array(
		// 				array(
		// 					'name'        => 'context',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'string',
		// 						'enum'    => array(
		// 							'edit',
		// 						),
		// 						'default' => 'edit',
		// 					),
		// 					'description' => 'Scope under which the request is made; determines fields present in response.',
		// 				),
		// 				array(
		// 					'name'        => 'page',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'integer',
		// 						'default' => 1,
		// 					),
		// 					'description' => 'Current page of the collection.',
		// 				),
		// 				array(
		// 					'name'        => 'per_page',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'integer',
		// 						'default' => 10,
		// 					),
		// 					'description' => 'Maximum number of items to be returned in result set.',
		// 				),
		// 				array(
		// 					'name'        => 'search',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 					),
		// 					'description' => 'Limit results to those matching a string.',
		// 				),
		// 				array(
		// 					'name'        => 'after',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 					),
		// 					'description' => 'Limit response to posts published after a given ISO8601 compliant date.',
		// 				),
		// 				array(
		// 					'name'        => 'modified_after',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 					),
		// 					'description' => 'Limit response to posts modified after a given ISO8601 compliant date.',
		// 				),
		// 				array(
		// 					'name'        => 'before',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 					),
		// 					'description' => 'Limit response to posts published before a given ISO8601 compliant date.',
		// 				),
		// 				array(
		// 					'name'        => 'modified_before',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 					),
		// 					'description' => 'Limit response to posts modified before a given ISO8601 compliant date.',
		// 				),
		// 				array(
		// 					'name'        => 'exclude',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'array',
		// 						'items'   => array(
		// 							'type' => 'integer',
		// 						),
		// 						'default' => array(),
		// 					),
		// 					'description' => 'Ensure result set excludes specific IDs.',
		// 				),
		// 				array(
		// 					'name'        => 'include',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'array',
		// 						'items'   => array(
		// 							'type' => 'integer',
		// 						),
		// 						'default' => array(),
		// 					),
		// 					'description' => 'Limit result set to specific IDs.',
		// 				),
		// 				array(
		// 					'name'        => 'offset',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'integer',
		// 					),
		// 					'description' => 'Offset the result set by a specific number of items.',
		// 				),
		// 				array(
		// 					'name'        => 'order',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'string',
		// 						'enum'    => array(
		// 							'asc',
		// 							'desc',
		// 						),
		// 						'default' => 'desc',
		// 					),
		// 					'description' => 'Order sort attribute ascending or descending.',
		// 				),
		// 				array(
		// 					'name'        => 'orderby',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'string',
		// 						'enum'    => array(
		// 							'author',
		// 							'date',
		// 							'id',
		// 							'include',
		// 							'modified',
		// 							'parent',
		// 							'relevance',
		// 							'slug',
		// 							'include_slugs',
		// 							'title',
		// 						),
		// 						'default' => 'date',
		// 					),
		// 					'description' => 'Sort collection by post attribute.',
		// 				),
		// 				array(
		// 					'name'        => 'search_columns',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'array',
		// 						'items'   => array(
		// 							'type' => 'string',
		// 							'enum' => array(
		// 								'post_title',
		// 								'post_content',
		// 								'post_excerpt',
		// 							),
		// 						),
		// 						'default' => array(),
		// 					),
		// 					'description' => 'Array of column names to be searched.',
		// 				),
		// 				array(
		// 					'name'        => 'slug',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'  => 'array',
		// 						'items' => array(
		// 							'type' => 'string',
		// 						),
		// 					),
		// 					'description' => 'Limit result set to posts with one or more specific slugs.',
		// 				),
		// 				array(
		// 					'name'        => 'status',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'    => 'array',
		// 						'items'   => array(
		// 							'type' => 'string',
		// 							'enum' => array(
		// 								'publish',
		// 								'future',
		// 								'draft',
		// 								'pending',
		// 								'private',
		// 								'trash',
		// 								'auto-draft',
		// 								'inherit',
		// 								'request-pending',
		// 								'request-confirmed',
		// 								'request-failed',
		// 								'request-completed',
		// 								'any',
		// 							),
		// 						),
		// 						'default' => array(
		// 							'private',
		// 							'publish',
		// 							'future',
		// 						),
		// 					),
		// 					'description' => 'Limit result set to posts assigned one or more statuses. ',
		// 				),
		// 				array(
		// 					'name'        => 'tax_relation',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type' => 'string',
		// 						'enum' => array(
		// 							'AND',
		// 							'OR',
		// 						),
		// 					),
		// 					'description' => 'Limit result set based on relationship between multiple taxonomies.',
		// 				),
		// 				array(
		// 					'name'        => 'notebook',
		// 					'in'          => 'query',
		// 					'required'    => false,
		// 					'schema'      => array(
		// 						'type'  => 'array',
		// 						'items' => array(
		// 							'type' => 'integer',
		// 						),
		// 					),
		// 					'description' => 'Limit result set to items with specific terms assigned in the notebook taxonomy.',
		// 				),
		// 			),
		// 			'responses'   => array(
		// 				array(
		// 					'description' => 'Successful response',
		// 				),
		// 				array(
		// 					'description' => 'Unauthorized',
		// 				),
		// 			),
		// 		),
		// 		'post' => array(
		// 			'operationId' => 'todo_create_item',
		// 			'tags'        => array(
		// 				'todo',
		// 			),
		// 			'summary'     => '',
		// 			'description' => 'Create a TODO item',
		// 			'responses'   => array(
		// 				array(
		// 					'description' => 'Successful response',
		// 				),
		// 				array(
		// 					'description' => 'Unauthorized',
		// 				),
		// 			),
		// 			'requestBody' => array(
		// 				'required' => true,
		// 				'content'  => array(
		// 					'application/json' => array(
		// 						'schema' => array(
		// 							'type'       => 'object',
		// 							'properties' => array(
		// 								'date'     => array(
		// 									'type'        => 'string',
		// 									'description' => "The date the post was published, in the site's timezone.",
		// 								),
		// 								'status'   => array(
		// 									'type'        => 'string',
		// 									'enum'        => array(
		// 										'publish',
		// 										'future',
		// 										'draft',
		// 										'pending',
		// 										'private',
		// 									),
		// 									'description' => 'A named status for the post.',
		// 									'default'     => 'private',
		// 								),
		// 								'title'    => array(
		// 									'type'        => 'string',
		// 									'description' => 'The title for the post.',
		// 								),
		// 								'excerpt'  => array(
		// 									'type'        => 'string',
		// 									'description' => 'The excerpt for the post.',
		// 								),
		// 								'notebook' => array(
		// 									'type'        => 'array',
		// 									'items'       => array(
		// 										'type' => 'integer',
		// 									),
		// 									'description' => 'The terms assigned to the post in the notebook taxonomy.',
		// 								),
		// 							),
		// 						),
		// 					),
		// 				),
		// 			),
		// 		),
		// 	),
		// );
		foreach ( $routes as $route => $handlers ) {
			// Only include pos/v1 namespace and notebook taxonomy endpoints
			if ( ! $this->should_include_route( $route ) ) {
				continue;
			}

			$path = $this->convert_wp_route_to_openapi( $route );
			$openapi['paths'][ $path ] = $this->get_path_item( $handlers, $route );
			//echo $route;
			// echo json_encode($handlers);
			// die();
		}

		return $openapi;
	}

	/**
	 * Check if route should be included in OpenAPI schema
	 */
	private function should_include_route( string $route ): bool {
		$permitted_routes = array(
			"/{$this->namespace}/todo",
			"/{$this->namespace}/notes",
			"/{$this->namespace}/notebook",
		);
		foreach ( $permitted_routes as $permitted_route ) {
			if ( strpos( $route, $permitted_route ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert WordPress route pattern to OpenAPI path
	 */
	private function convert_wp_route_to_openapi( string $route ): string {
		// Convert WP style params (?P<param>\d+) to OpenAPI style {param}
		return preg_replace( '/\(\?P<(\w+)>[^)]+\)/', '{$1}', $route );
	}

	/**
	 * Generate OpenAPI path item object from WP REST handlers
	 */
	private function get_path_item( array $handlers, string $route ): array {
		$path_item = array();

		foreach ( $handlers as $handler ) {
			if ( ! isset( $handler['methods'] ) ) {
				continue;
			}

			$methods = array_map( 'strtolower', array_keys( $handler['methods'] ) );

			foreach ( $methods as $method ) {
				$operation = array(
					'operationId' => $this->get_tag_from_route( $route ) . '_' . $handler['callback'][1] ?? '',
					//'tags' => [$this->get_tag_from_route($route)],
					'summary'     => $handler['summary'] ?? '',
					'description' => $handler['description'] ?? '',
					'parameters'  => $this->get_parameters( $route, $handler ),
					'responses'   => array(
						'200' => array(
							'description' => 'Successful response',
						),
						'401' => array(
							'description' => 'Unauthorized',
						),
					),
				);

				if ( in_array( $method, array( 'post', 'put', 'patch' ) ) ) {
					$operation['requestBody'] = $this->get_request_body( $handler );
				}

				$path_item[ $method ] = $operation;
			}
		}

		return $path_item;
	}

	/**
	 * Extract tag name from route
	 */
	private function get_tag_from_route( string $route ): string {
		$parts = explode( '/', trim( $route, '/' ) );
		return count( $parts ) > 2 ? $parts[2] : 'default';
	}

	/**
	 * Generate OpenAPI parameters from route and handler
	 */
	private function get_parameters( string $route, array $handler ): array {
		$parameters = array();

		// Path parameters
		preg_match_all( '/\(\?P<(\w+)>[^)]+\)/', $route, $matches );
		foreach ( $matches[1] as $param ) {
			$parameters[] = array(
				'name'     => $param,
				'in'       => 'path',
				'required' => true,
				'schema'   => array(
					'type' => 'string',
				),
			);
		}

		// Query parameters from args
		if ( isset( $handler['args'] ) ) {
			foreach ( $handler['args'] as $name => $arg ) {
				$parameters[] = array(
					'name'        => $name,
					'in'          => 'query',
					'required'    => ! empty( $arg['required'] ),
					'schema'      => $this->get_parameter_schema( $arg ),
					'description' => $arg['description'] ?? '',
				);
			}
		}

		return $parameters;
	}

	/**
	 * Generate OpenAPI request body schema
	 */
	private function get_request_body( array $handler ): array {
		$properties = $this->get_properties_from_args( $handler['args'] ?? array() );
		// print_r($handler['args']);
		// die();
		// Get post type from route if available
		$post_type = $this->get_post_type_from_handler( $handler );

		// Add registered REST fields if we have a post type
		if ( $post_type ) {
			$rest_fields = $this->get_rest_fields( $post_type );
			foreach ( $rest_fields as $field_name => $schema ) {
				$properties[ $field_name ] = $this->convert_wp_schema_to_openapi( $schema );
			}
		}

		return array(
			'required' => true,
			'content'  => array(
				'application/json' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => $properties,
					),
				),
			),
		);
	}

	/**
	 * Get post type from REST handler
	 */
	private function get_post_type_from_handler( array $handler ): ?string {
		// Check if post_type is directly available
		if ( ! empty( $handler['post_type'] ) ) {
			return $handler['post_type'];
		}

		// Try to get from callback
		if ( ! empty( $handler['callback'] ) && is_array( $handler['callback'] ) ) {
			$callback_class = $handler['callback'][0] ?? null;

			// Handle WP core post controller
			if ( $callback_class instanceof WP_REST_Posts_Controller ) {
				// Use get_post_type() method if available
				if ( method_exists( $callback_class, 'get_post_type' ) ) {
					return $callback_class->get_post_type();
				}
			}

			// Handle our custom CPT controller
			if ( $callback_class instanceof POS_CPT_Rest_Controller ) {
				// Get post type from class name
				$class_name = get_class( $callback_class );
				if ( preg_match( '/^POS_(\w+)_Rest_Controller$/', $class_name, $matches ) ) {
					return strtolower( $matches[1] );
				}
			}
		}

		return null;
	}

	/**
	 * Helper function to get registered REST fields
	 */
	private function get_rest_fields( string $post_type ): array {
		global $wp_rest_additional_fields;

		$fields = array();

		// Get fields from global additional fields
		if ( isset( $wp_rest_additional_fields[ $post_type ] ) ) {
			foreach ( $wp_rest_additional_fields[ $post_type ] as $field_name => $field_options ) {
				if ( ! empty( $field_options['schema'] ) ) {
					$fields[ $field_name ] = $field_options['schema'];
				}
			}
		}

		// Get fields from registered meta
		$registered_meta = get_registered_meta_keys( 'post', $post_type );
		foreach ( $registered_meta as $meta_key => $meta_args ) {
			if ( ! empty( $meta_args['show_in_rest'] ) ) {
				$fields[ $meta_key ] = array(
					'type'        => $meta_args['type'],
					'description' => $meta_args['description'] ?? '',
					'default'     => $meta_args['default'] ?? null,
				);
			}
		}

		return $fields;
	}

	/**
	 * Convert WordPress schema to OpenAPI schema
	 */
	private function convert_wp_schema_to_openapi( array $schema ): array {
		$openapi_schema = array(
			'type' => $this->get_openapi_type( $schema['type'] ?? 'string' ),
		);

		if ( isset( $schema['description'] ) ) {
			$openapi_schema['description'] = $schema['description'];
		}

		if ( isset( $schema['enum'] ) ) {
			$openapi_schema['enum'] = $schema['enum'];
		}

		if ( isset( $schema['default'] ) ) {
			$openapi_schema['default'] = $schema['default'];
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$openapi_schema['items'] = $this->convert_wp_schema_to_openapi( $schema['items'] );
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			$openapi_schema['properties'] = array_map(
				array( $this, 'convert_wp_schema_to_openapi' ),
				$schema['properties']
			);
		}

		return $openapi_schema;
	}

	/**
	 * Convert WordPress argument definition to OpenAPI schema
	 */
	private function get_parameter_schema( array $arg, $include_description = false ): array {
		$schema = array(
			'type' => 'string',
		);

		if ( isset( $arg['type'] ) ) {
			if ( is_array( $arg['type'] ) ) {
				// Handle array of types - take the first non-null type
				$types = array_filter(
					$arg['type'],
					function( $type ) {
						return $type !== 'null';
					}
				);
				if ( ! empty( $types ) ) {
					$schema['type'] = $this->get_openapi_type( reset( $types ) );
				}
			} else {
				$schema['type'] = $this->get_openapi_type( $arg['type'] );
			}
			if ( $schema['type'] === 'array' && isset( $arg['items'] ) ) {
				$schema['items'] = $this->convert_wp_schema_to_openapi( $arg['items'] );
			}
			if ( $schema['type'] === 'object' ) {
				//print_r($arg);
				//die();
			}
		}

		if ( isset( $arg['enum'] ) ) {
			$schema['enum'] = $arg['enum'];
		}

		if ( isset( $arg['default'] ) ) {
			$schema['default'] = $arg['default'];
			if ( $schema['type'] === 'array' && is_string( $schema['default'] ) ) {
				$schema['default'] = array( $schema['default'] );
				// TODO: Hardcode private.
			}
		}

		if ( isset( $arg['description'] ) && $include_description ) {
			$schema['description'] = $arg['description'];
		}

		return $schema;
	}

	/**
	 * Convert WordPress argument properties to OpenAPI properties
	 */
	private function get_properties_from_args( array $args ): array {
		$properties = array();
		foreach ( $args as $name => $arg ) {
			$properties[ $name ] = $this->get_parameter_schema( $arg, true );
		}
		return $properties;
	}

	/**
	 * Convert WordPress type to OpenAPI type
	 */
	private function get_openapi_type( string $wp_type ): string {
		$type_map = array(
			'integer' => 'integer',
			'number'  => 'number',
			'boolean' => 'boolean',
			'array'   => 'array',
			'object'  => 'object',
		);

		return $type_map[ $wp_type ] ?? 'string';
	}
}
