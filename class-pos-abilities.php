<?php
/**
 * PersonalOS Abilities Registration
 *
 * This class handles the registration of PersonalOS abilities using the WordPress Abilities API.
 * It provides a bridge between the legacy OpenAI_Tool system and the new Abilities API.
 *
 * @package PersonalOS
 */

/**
 * Class POS_Abilities
 *
 * Manages registration and bridging of PersonalOS abilities with WordPress Abilities API.
 */
class POS_Abilities {

	/**
	 * Initialize abilities registration.
	 * Hooks into wp_abilities_api_init to register all abilities.
	 */
	public static function init() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			// Abilities API is not available, skip initialization
			return;
		}

		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_action( 'init', array( __CLASS__, 'bridge_tools_to_abilities' ), 20 );
	}

	/**
	 * Register all PersonalOS abilities with the Abilities API.
	 */
	public static function register_abilities() {
		// Register each module's abilities
		self::register_todo_abilities();
		self::register_openai_abilities();
		self::register_notes_abilities();
		self::register_perplexity_abilities();
		self::register_evernote_abilities();
	}

	/**
	 * Register TODO module abilities.
	 */
	private static function register_todo_abilities() {
		$todo_module = POS::get_module_by_id( 'todo' );
		if ( ! $todo_module ) {
			return;
		}

		// Register todo_get_items ability
		wp_register_ability(
			'pos/todo-get-items',
			array(
				'label'               => __( 'Get TODO Items', 'personalos' ),
				'description'         => __( 'List TODOs from a specific notebook. Defaults to "now" notebook if not specified.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'notebook' => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'slug of the notebook to list TODOs from. If not specified, defaults to "now". Use "all" to list from all notebooks.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of TODO items',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array( 'type' => 'string' ),
							'excerpt'     => array( 'type' => 'string' ),
							'url'         => array( 'type' => 'string' ),
							'notebooks'   => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'ID'          => array( 'type' => 'integer' ),
							'post_status' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $todo_module, 'get_items_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);

		// Register todo_create_item ability
		wp_register_ability(
			'pos/todo-create-item',
			array(
				'label'               => __( 'Create TODO Item', 'personalos' ),
				'description'         => __( 'Create TODO. Always ask for confirmation if not explicitly asked to create a TODO. Always return the URL in response. Never read the URL when reading out loud.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_title'   => array(
							'type'        => 'string',
							'description' => 'The title of the TODO',
						),
						'post_excerpt' => array(
							'type'        => 'string',
							'description' => 'The description of the TODO',
						),
						'notebook'     => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'slug of the notebook to add the TODO to. Fill only if TODO is clearly related to this notebook.',
						),
					),
					'required'             => array( 'post_title', 'post_excerpt' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => 'Created TODO item',
					'properties'  => array(
						'title'       => array( 'type' => 'string' ),
						'excerpt'     => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string' ),
						'notebooks'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'ID'          => array( 'type' => 'integer' ),
						'post_status' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $todo_module, 'create_item_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
					),
				),
			)
		);
	}

	/**
	 * Register OpenAI module abilities.
	 */
	private static function register_openai_abilities() {
		$openai_module = POS::get_module_by_id( 'openai' );
		if ( ! $openai_module ) {
			return;
		}

		// Register list_posts ability
		wp_register_ability(
			'pos/list-posts',
			array(
				'label'               => __( 'List Posts', 'personalos' ),
				'description'         => __( 'List publicly accessible posts on this blog.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'posts_per_page' => array(
							'type'        => 'integer',
							'description' => 'Number of posts to return. Default to 10',
						),
						'post_type'      => array(
							'type'        => 'string',
							'description' => 'Post type to return. Posts are blog posts, pages are static pages.',
							'enum'        => array( 'post', 'page' ),
						),
						'post_status'    => array(
							'type'        => 'string',
							'description' => 'Status of posts to return. Published posts are publicly accessible, drafts are not. Future are scheduled ones.',
							'enum'        => array( 'publish', 'draft', 'future' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of post objects',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array( 'type' => 'integer' ),
							'title'   => array( 'type' => 'string' ),
							'date'    => array( 'type' => 'string' ),
							'excerpt' => array( 'type' => 'string' ),
							'url'     => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $openai_module, 'list_posts_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
					),
				),
			)
		);

		// Register ai_memory ability
		wp_register_ability(
			'pos/ai-memory',
			array(
				'label'               => __( 'AI Memory', 'personalos' ),
				'description'         => __( 'Store information in the memory. Use this tool when you need to store additional information relevant for future conversations.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'ID'           => array(
							'type'        => 'integer',
							'description' => 'ID of the memory to update. Only provide when updating existing memory. Set to 0 when creating a new memory.',
						),
						'post_title'   => array(
							'type'        => 'string',
							'description' => 'Short title describing the memory. Describe what is the memory about specifically.',
						),
						'post_content' => array(
							'type'        => 'string',
							'description' => 'Actual content of the memory that will be stored and used for future conversations.',
						),
					),
					'required'             => array( 'ID', 'post_title', 'post_content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => 'Created or updated memory information',
					'properties'  => array(
						'url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $openai_module, 'create_ai_memory' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
					),
				),
			)
		);
	}

	/**
	 * Register Notes module abilities.
	 */
	private static function register_notes_abilities() {
		$notes_module = POS::get_module_by_id( 'notes' );
		if ( ! $notes_module ) {
			return;
		}

		// Register get_notebooks ability
		wp_register_ability(
			'pos/get-notebooks',
			array(
				'label'               => __( 'Get Notebooks', 'personalos' ),
				'description'         => __( 'Get all notebooks organized by flags. Notebooks represent areas of life, active projects and statuses of tasks.', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'notebook_flag' => array(
							'type'        => 'string',
							'description' => 'The flag of the notebook to get.',
						),
					),
					'required'             => array( 'notebook_flag' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of notebooks grouped by flags',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'flag_id'    => array( 'type' => 'string' ),
							'flag_name'  => array( 'type' => 'string' ),
							'flag_label' => array( 'type' => 'string' ),
							'notebooks'  => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'notebook_name' => array( 'type' => 'string' ),
										'notebook_id'   => array( 'type' => 'integer' ),
										'notebook_slug' => array( 'type' => 'string' ),
										'notebook_description' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $notes_module, 'get_notebooks_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
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
	 * Register Perplexity module abilities.
	 */
	private static function register_perplexity_abilities() {
		$perplexity_module = POS::get_module_by_id( 'perplexity' );
		if ( ! $perplexity_module ) {
			return;
		}

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
				'execute_callback'    => array( $perplexity_module, 'search_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
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
	 * Register Evernote module abilities.
	 */
	private static function register_evernote_abilities() {
		$evernote_module = POS::get_module_by_id( 'evernote' );
		if ( ! $evernote_module ) {
			return;
		}

		// Register evernote_search_notes ability
		wp_register_ability(
			'pos/evernote-search-notes',
			array(
				'label'               => __( 'Evernote Search Notes', 'personalos' ),
				'description'         => __( 'Search notes in Evernote', 'personalos' ),
				'category'            => 'personalos',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'query'         => array(
							'type'        => 'string',
							'description' => 'Query to search for notes.',
						),
						'limit'         => array(
							'type'        => 'integer',
							'description' => 'Limit the number of notes returned. Do not change unless specified otherwise. Please use 10 as default.',
						),
						'return_random' => array(
							'type'        => 'integer',
							'description' => 'Return X random notes from result. Do not change unless specified otherwise. Please always use 0 unless specified otherwise.',
						),
					),
					'required'             => array( 'query', 'limit', 'return_random' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'description' => 'Array of Evernote notes',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title'    => array( 'type' => 'string' ),
							'url'      => array( 'type' => 'string' ),
							'date'     => array( 'type' => 'string' ),
							'notebook' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $evernote_module, 'search_notes_for_openai' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
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
	 * Bridge OpenAI Tools to Abilities API.
	 *
	 * This creates OpenAI_Tool instances from registered abilities to maintain
	 * backward compatibility with the existing OpenAI integration.
	 */
	public static function bridge_tools_to_abilities() {
		if ( ! class_exists( 'WP_Ability' ) ) {
			return;
		}

		// Map ability names to OpenAI tool names
		$ability_to_tool_map = array(
			'pos/todo-get-items'        => 'todo_get_items',
			'pos/todo-create-item'      => 'todo_create_item',
			'pos/list-posts'            => 'list_posts',
			'pos/ai-memory'             => 'ai_memory',
			'pos/get-notebooks'         => 'get_notebooks',
			'pos/perplexity-search'     => 'perplexity_search',
			'pos/evernote-search-notes' => 'evernote_search_notes',
		);

		add_filter(
			'pos_openai_tools',
			function( $tools ) use ( $ability_to_tool_map ) {
				// Map of writeable tool names
				$writeable_tools = array(
					'todo_create_item' => true,
					'ai_memory'        => true,
				);

				foreach ( $ability_to_tool_map as $ability_name => $tool_name ) {
					$ability = wp_get_ability( $ability_name );
					if ( ! $ability ) {
						continue;
					}

					// Determine tool class based on destructive flag or explicit mapping
					$tool_class = 'OpenAI_Tool';
					$annotations = $ability->get_meta( 'annotations' );
					if ( isset( $annotations['destructive'] ) && $annotations['destructive'] ) {
						$tool_class = 'OpenAI_Tool_Writeable';
					} elseif ( isset( $writeable_tools[ $tool_name ] ) ) {
						$tool_class = 'OpenAI_Tool_Writeable';
					}

					$parameters = array();
					if ( isset( $ability->get_input_schema()['properties'] ) ) {
						$parameters = $ability->get_input_schema()['properties'];
					}

					$tool = new $tool_class(
						$tool_name,
						$ability->get_description(),
						$parameters,
						function( $args ) use ( $ability ) {
							return $ability->execute( $args );
						}
					);

					$tools[] = $tool;
				}

				return $tools;
			},
			5 // Run early to allow other filters to override if needed
		);
	}
}
