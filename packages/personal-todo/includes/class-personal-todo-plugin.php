<?php
/**
 * Personal TODO package.
 *
 * @package PersonalTODO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Split packages use their package text domain while root PHPCS still expects personalos.

/**
 * TODO UI, ICS feed, and Abilities over Knowledge.
 */
class Personal_TODO_Plugin extends PersonalOS_Plugin_Base {
	/**
	 * Cron hook for scheduled tasks.
	 *
	 * @var string
	 */
	private $scheduled_hook = 'personal_todo_scheduled';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-todo',
				'display_name' => 'Personal TODO',
				'text_domain'  => 'personal-todo',
				'version'      => PERSONAL_TODO_VERSION,
				'plugin_file'  => PERSONAL_TODO_FILE,
				'app'          => array(
					'path'         => 'todo',
					'name'         => 'TODO',
					'action_label' => 'Open App',
					'capability'   => 'edit_posts',
					'icon'         => 'dashicons-list-view',
				),
				'settings'     => array(
					'ics_token' => array(
						'type'    => 'text',
						'scope'   => 'user',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Register hooks and runtime integrations.
	 *
	 * @return void
	 */
	public function register() {
		$this->vocabulary()->register_type_labels();
		$this->register_missing_knowledge_notice();
		$this->register_wp_app( array( $this, 'render_admin_page' ) );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( function_exists( 'wp_register_ability_category' ) ) {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		$this->register_common_knowledge_meta();
		$this->knowledge()->register_post_meta( 'reminders_id' );
		$this->knowledge()->register_post_meta( 'pos_blocked_pending_term' );
		$this->knowledge()->register_post_meta(
			'pos_blocked_by',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);
		$this->knowledge()->register_post_meta(
			'pos_recurring_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);

		add_action( $this->scheduled_hook, array( $this, 'scheduled_task_now' ), 10, 1 );
		add_action( 'wp_trash_post', array( $this, 'unblock_tasks_when_completing' ), 10, 2 );
		add_action( 'save_post_' . $this->knowledge()->post_type(), array( $this, 'save_task_meta_side_effects' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'save_task_update_history' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'save_task_term_history' ), 10, 6 );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$this->add_admin_page_hook(
			add_menu_page(
				'TODO',
				'TODO',
				'edit_posts',
				'personal-todo',
				array( $this, 'render_admin_page' ),
				'dashicons-list-view',
				4
			)
		);
	}

	/**
	 * Enqueue the app and expose the resolved Knowledge taxonomy route.
	 *
	 * @return void
	 */
	public function enqueue_package_assets() {
		parent::enqueue_package_assets();

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		wp_localize_script(
			$this->script_handle(),
			'personalTodoSettings',
			array(
				'taxonomyRestPath' => rest_get_route_for_taxonomy_items( $this->knowledge()->type_taxonomy() ),
			)
		);
	}

	/**
	 * Render a small native admin UI.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$created = null;
		if ( isset( $_POST['personal_todo_action'] ) ) {
			check_admin_referer( 'personal_todo_action' );
			$action = sanitize_key( wp_unslash( $_POST['personal_todo_action'] ) );

			if ( 'create' === $action ) {
				$created = $this->create_task(
					array(
						'post_title'   => sanitize_text_field( wp_unslash( $_POST['personal_todo_title'] ?? '' ) ),
						'post_excerpt' => sanitize_textarea_field( wp_unslash( $_POST['personal_todo_excerpt'] ?? '' ) ),
					),
					array( sanitize_key( wp_unslash( $_POST['personal_todo_status'] ?? 'inbox' ) ) )
				);
			} elseif ( 'save-token' === $action ) {
				$this->update_setting( 'ics_token', sanitize_text_field( wp_unslash( $_POST['personal_todo_ics_token'] ?? '' ) ), get_current_user_id() );
			}
		}

		$tasks = $this->list_tasks();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->app_display_name() ); ?></h1>
			<?php if ( is_wp_error( $created ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $created->get_error_message() ); ?></p></div>
			<?php elseif ( $created ) : ?>
				<div class="notice notice-success"><p><a href="<?php echo esc_url( get_edit_post_link( $created ) ); ?>"><?php echo esc_html( get_the_title( $created ) ); ?></a></p></div>
			<?php endif; ?>

			<?php if ( ! $this->knowledge()->is_available() ) : ?>
				<p><?php esc_html_e( 'Knowledge is not available yet.', 'personal-todo' ); ?></p>
			<?php else : ?>
				<div id="personal-todo-admin-app" class="personal-todo-admin"></div>
				<div class="personal-todo-admin-fallback">
					<form method="post">
						<?php wp_nonce_field( 'personal_todo_action' ); ?>
						<input type="hidden" name="personal_todo_action" value="create">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="personal_todo_title"><?php esc_html_e( 'Task', 'personal-todo' ); ?></label></th>
									<td><input class="regular-text" type="text" id="personal_todo_title" name="personal_todo_title"></td>
								</tr>
								<tr>
									<th scope="row"><label for="personal_todo_excerpt"><?php esc_html_e( 'Notes', 'personal-todo' ); ?></label></th>
									<td><textarea class="large-text" rows="3" id="personal_todo_excerpt" name="personal_todo_excerpt"></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="personal_todo_status"><?php esc_html_e( 'Status', 'personal-todo' ); ?></label></th>
									<td>
										<select id="personal_todo_status" name="personal_todo_status">
											<option value="inbox"><?php esc_html_e( 'Inbox', 'personal-todo' ); ?></option>
											<option value="now"><?php esc_html_e( 'Now', 'personal-todo' ); ?></option>
											<option value="later"><?php esc_html_e( 'Later', 'personal-todo' ); ?></option>
											<option value="follow-up"><?php esc_html_e( 'Follow Up', 'personal-todo' ); ?></option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
						<?php submit_button( __( 'Add Task', 'personal-todo' ) ); ?>
					</form>

					<h2><?php esc_html_e( 'ICS Feed', 'personal-todo' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'personal_todo_action' ); ?>
						<input type="hidden" name="personal_todo_action" value="save-token">
						<input class="regular-text" type="text" name="personal_todo_ics_token" value="<?php echo esc_attr( $this->get_setting( 'ics_token', get_current_user_id() ) ); ?>">
						<?php submit_button( __( 'Save Feed Token', 'personal-todo' ), 'secondary', 'submit', false ); ?>
					</form>

					<h2><?php esc_html_e( 'Open Tasks', 'personal-todo' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Task', 'personal-todo' ); ?></th>
								<th><?php esc_html_e( 'Terms', 'personal-todo' ); ?></th>
								<th><?php esc_html_e( 'Date', 'personal-todo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $tasks as $task ) : ?>
								<?php
								$term_names = wp_get_object_terms( $task->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'names' ) );
								$term_names = is_wp_error( $term_names ) ? array() : $term_names;
								?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $task->ID ) ); ?>"><?php echo esc_html( get_the_title( $task ) ); ?></a></td>
									<td><?php echo esc_html( implode( ', ', $term_names ) ); ?></td>
									<td><?php echo esc_html( get_the_date( '', $task ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register ICS REST route.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'personal-todo/v1',
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_list_tasks' ),
					'permission_callback' => array( $this, 'can_read_tasks' ),
					'args'                => $this->collection_rest_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_task' ),
					'permission_callback' => array( $this, 'can_create_task' ),
					'args'                => $this->edit_rest_args(),
				),
			)
		);

		register_rest_route(
			'personal-todo/v1',
			'/tasks/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_task' ),
					'permission_callback' => array( $this, 'can_read_task' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_task' ),
					'permission_callback' => array( $this, 'can_edit_task' ),
					'args'                => array_merge(
						array(
							'id' => array(
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
							),
						),
						$this->edit_rest_args()
					),
				),
			)
		);

		register_rest_route(
			'personal-todo/v1',
			'/tasks/(?P<id>[\d]+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_complete_task' ),
				'permission_callback' => array( $this, 'can_edit_task' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'personal-todo/v1',
			'/ics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_ics_feed' ),
				'permission_callback' => array( $this, 'check_ics_permission' ),
				'args'                => array(
					'token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * List readable task Knowledge rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_list_tasks( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 20;
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$orderby  = $request->get_param( 'orderby' ) ? sanitize_key( $request->get_param( 'orderby' ) ) : 'date';
		$order    = $request->get_param( 'order' ) ? strtoupper( sanitize_key( $request->get_param( 'order' ) ) ) : 'DESC';
		$per_page = min( 100, max( 1, $per_page ) );
		$page     = max( 1, $page );
		$args     = array(
			'posts_per_page' => $per_page,
			'offset'         => ( $page - 1 ) * $per_page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( '' !== (string) $request->get_param( 'search' ) ) {
			$args['s'] = sanitize_text_field( $request->get_param( 'search' ) );
		}

		if ( '' !== (string) $request->get_param( 'status' ) ) {
			$args['post_status'] = array_map( 'sanitize_key', (array) $request->get_param( 'status' ) );
		}

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}

		$tasks = $this->list_tasks( $args, $this->request_term_slugs( $request ) );

		return rest_ensure_response( array_map( array( $this, 'format_task' ), $tasks ) );
	}

	/**
	 * Create a task Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_create_task( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$post_id = $this->create_task( $this->task_data_from_request( $request ), $this->request_term_slugs( $request ) );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$response = rest_ensure_response( $this->format_task( get_post( $post_id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Get one task Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_task( WP_REST_Request $request ) {
		$task = get_post( (int) $request['id'] );

		if ( ! $this->is_task( $task ) ) {
			return new WP_Error( 'personal_todo_not_found', __( 'Task not found.', 'personal-todo' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->format_task( $task ) );
	}

	/**
	 * Update a task Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_update_task( WP_REST_Request $request ) {
		$task = get_post( (int) $request['id'] );

		if ( ! $this->is_task( $task ) ) {
			return new WP_Error( 'personal_todo_not_found', __( 'Task not found.', 'personal-todo' ), array( 'status' => 404 ) );
		}

		$data = array_merge( array( 'ID' => $task->ID ), $this->task_data_from_request( $request ) );
		if ( null !== $request->get_param( 'scheduled_for' ) ) {
			$scheduled = wp_next_scheduled( $this->scheduled_hook, array( $task->ID ) );
			if ( $scheduled ) {
				wp_unschedule_event( $scheduled, $this->scheduled_hook, array( $task->ID ) );
			}
		}
		if ( count( $data ) > 1 ) {
			$updated = wp_update_post( $data, true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		if ( null !== $request->get_param( 'term' ) || null !== $request->get_param( 'terms' ) ) {
			$term_ids = $this->vocabulary()->resolve_assignable_term_ids(
				array_values(
					array_unique(
						array_merge( array( 'artifact', 'todo' ), $this->request_term_slugs( $request ) )
					)
				)
			);
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}

			wp_set_object_terms( $task->ID, $term_ids, $this->knowledge()->type_taxonomy(), false );
		}

		return rest_ensure_response( $this->format_task( get_post( $task->ID ) ) );
	}

	/**
	 * Complete a task through the existing trash workflow.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_complete_task( WP_REST_Request $request ) {
		$task = get_post( (int) $request['id'] );

		if ( ! $this->is_task( $task ) ) {
			return new WP_Error( 'personal_todo_not_found', __( 'Task not found.', 'personal-todo' ), array( 'status' => 404 ) );
		}

		$trashed = wp_trash_post( $task->ID );
		if ( ! $trashed ) {
			return new WP_Error( 'personal_todo_complete_failed', __( 'Task could not be completed.', 'personal-todo' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $this->format_task( get_post( $task->ID ) ) );
	}

	/**
	 * Check ICS token and attach owner to request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_ics_permission( WP_REST_Request $request ) {
		$user_id = $this->settings_helper()->find_user_for_setting_token( 'ics_token', $request->get_param( 'token' ) );

		if ( ! $user_id ) {
			return new WP_Error( 'personal_todo_invalid_token', __( 'Invalid feed token.', 'personal-todo' ), array( 'status' => 403 ) );
		}

		$request->set_param( 'personal_todo_user_id', $user_id );

		return true;
	}

	/**
	 * Generate ICS response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function generate_ics_feed( WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'personal_todo_user_id' );
		$content = $this->generate_ics_content( $user_id );
		$response = new WP_REST_Response( $content );

		$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="todos.ics"' );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_ics_response' ), 10, 4 );

		return $response;
	}

	/**
	 * Serve ICS responses as raw text instead of JSON.
	 *
	 * @param bool             $served  Whether the request has been served.
	 * @param WP_HTTP_Response $result  Response result.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  REST server.
	 * @return bool
	 */
	public function serve_ics_response( $served, $result, $request, $server ) {
		unset( $server );

		if ( '/personal-todo/v1/ics' !== $request->get_route() ) {
			return $served;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result->get_data();

		return true;
	}

	/**
	 * Create a task Knowledge row.
	 *
	 * @param array $data       Post data.
	 * @param array $term_slugs Additional term slugs.
	 * @param int   $user_id    Optional owner.
	 * @return int|WP_Error
	 */
	public function create_task( $data = array(), $term_slugs = array(), $user_id = 0 ) {
		$defaults = array(
			'post_title'        => '',
			'post_excerpt'      => '',
			'post_content'      => '',
			'post_status'       => 'private',
			'post_date'         => gmdate( 'Y-m-d H:i:s' ),
			'personalos_source' => 'native:personal-todo',
			'meta_input'        => array(
				'url'                      => '',
				'pos_recurring_days'       => 0,
				'pos_blocked_by'           => 0,
				'pos_blocked_pending_term' => '',
			),
		);

		$data = wp_parse_args( $data, $defaults );
		$terms = array_values( array_unique( array_merge( array( 'artifact', 'todo' ), empty( $term_slugs ) ? array( 'inbox' ) : $term_slugs ) ) );

		$post_id = $this->create_knowledge_post( $data, $terms, $user_id );
		if ( ! is_wp_error( $post_id ) ) {
			$this->save_task_meta_side_effects( $post_id, get_post( $post_id ), false );
		}

		return $post_id;
	}

	/**
	 * List readable task Knowledge rows.
	 *
	 * @param array        $args       Query args.
	 * @param array|string $term_slugs Additional term filters.
	 * @return WP_Post[]
	 */
	public function list_tasks( $args = array(), $term_slugs = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		return $this->query_knowledge_posts( $args, array_values( array_unique( array_merge( array( 'todo' ), (array) $term_slugs ) ) ) );
	}

	/**
	 * Format a task for abilities.
	 *
	 * @param WP_Post $task Task post.
	 * @return array
	 */
	public function format_task( WP_Post $task ) {
		$terms = wp_get_object_terms( $task->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$scheduled = wp_next_scheduled( $this->scheduled_hook, array( $task->ID ) );
		$blocking  = array_map(
			function ( $blocked_task ) {
				return (int) $blocked_task->ID;
			},
			$this->get_tasks_blocked_by( $task->ID )
		);

		return array(
			'id'                       => $task->ID,
			'title'                    => $task->post_title,
			'excerpt'                  => $task->post_excerpt,
			'content'                  => $task->post_content,
			'url'                      => get_post_meta( $task->ID, 'url', true ),
			'edit_url'                 => get_edit_post_link( $task->ID, 'raw' ),
			'terms'                    => $terms,
			'post_status'              => $task->post_status,
			'author'                   => (int) $task->post_author,
			'date_gmt'                 => $task->post_date_gmt,
			'modified_gmt'             => $task->post_modified_gmt,
			'pos_blocked_by'           => (int) get_post_meta( $task->ID, 'pos_blocked_by', true ),
			'pos_blocked_pending_term' => get_post_meta( $task->ID, 'pos_blocked_pending_term', true ),
			'pos_recurring_days'       => (int) get_post_meta( $task->ID, 'pos_recurring_days', true ),
			'blocking'                 => $blocking,
			'scheduled'                => $scheduled ? gmdate( 'c', $scheduled ) : '',
			'history'                  => $this->format_task_history( $task->ID ),
		);
	}

	/**
	 * Register ability category.
	 *
	 * @return void
	 */
	public function register_ability_category() {
		wp_register_ability_category(
			'personal-todo',
			array(
				'label'       => __( 'Personal TODO', 'personal-todo' ),
				'description' => __( 'Task abilities provided by Personal TODO.', 'personal-todo' ),
			)
		);
	}

	/**
	 * Register task abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		wp_register_ability(
			'personal-todo/list-tasks',
			array(
				'label'               => __( 'List Tasks', 'personal-todo' ),
				'description'         => __( 'List readable TODO Knowledge rows, optionally filtered by a status, project, area, or resource term.', 'personal-todo' ),
				'category'            => 'personal-todo',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'Optional search query.',
						),
						'status' => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'Optional WordPress post status to include.',
						),
						'term'   => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'Optional Knowledge type term slug. Use all for every open task.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'execute_callback'    => array( $this, 'ability_list_tasks' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'personal-todo/create-task',
			array(
				'label'               => __( 'Create Task', 'personal-todo' ),
				'description'         => __( 'Create a private TODO Knowledge row for the current user.', 'personal-todo' ),
				'category'            => 'personal-todo',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'term'    => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'Optional assignable Knowledge type term slug.',
						),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( $this, 'ability_create_task' ),
				'permission_callback' => array( $this, 'can_create_task' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'personal-todo/update-task',
			array(
				'label'               => __( 'Update Task', 'personal-todo' ),
				'description'         => __( 'Update an editable TODO Knowledge row, including task fields, URL, blocking, recurrence, and Knowledge type terms.', 'personal-todo' ),
				'category'            => 'personal-todo',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'                       => array( 'type' => 'integer' ),
						'title'                    => array( 'type' => 'string' ),
						'excerpt'                  => array( 'type' => 'string' ),
						'content'                  => array( 'type' => 'string' ),
						'url'                      => array( 'type' => 'string' ),
						'term'                     => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'Optional assignable Knowledge type term slug.',
						),
						'terms'                    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'pos_blocked_by'           => array( 'type' => 'integer' ),
						'pos_blocked_pending_term' => array( 'type' => 'string' ),
						'pos_recurring_days'       => array( 'type' => 'integer' ),
						'scheduled_for'            => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( $this, 'ability_update_task' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'personal-todo/complete-task',
			array(
				'label'               => __( 'Complete Task', 'personal-todo' ),
				'description'         => __( 'Complete an editable TODO Knowledge row through the trash workflow so recurrence and unblock side effects run.', 'personal-todo' ),
				'category'            => 'personal-todo',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array( 'type' => 'integer' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( $this, 'ability_complete_task' ),
				'permission_callback' => 'is_user_logged_in',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Ability callback: list tasks.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public function ability_list_tasks( $args ) {
		$term       = isset( $args['term'] ) && 'all' !== $args['term'] ? sanitize_key( $args['term'] ) : null;
		$query_args = array();

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$query_args['post_status'] = array_map( 'sanitize_key', (array) $args['status'] );
		}

		$tasks = $this->list_tasks( $query_args, $term ? array( $term ) : array() );

		return array_map( array( $this, 'format_task' ), $tasks );
	}

	/**
	 * Ability callback: create task.
	 *
	 * @param array $args Args.
	 * @return array|WP_Error
	 */
	public function ability_create_task( $args ) {
		$terms = empty( $args['term'] ) ? array( 'inbox' ) : array( sanitize_key( $args['term'] ) );
		$post_id = $this->create_task(
			array(
				'post_title'   => sanitize_text_field( $args['title'] ),
				'post_excerpt' => sanitize_textarea_field( $args['excerpt'] ?? '' ),
			),
			$terms
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return $this->format_task( get_post( $post_id ) );
	}

	/**
	 * Ability callback: update task.
	 *
	 * @param array $args Args.
	 * @return array|WP_Error
	 */
	public function ability_update_task( $args ) {
		$post_id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		$task    = get_post( $post_id );

		if ( ! $this->is_task( $task ) ) {
			return new WP_Error( 'personal_todo_not_found', __( 'Task not found.', 'personal-todo' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'personal_todo_forbidden', __( 'You cannot edit this task.', 'personal-todo' ), array( 'status' => 403 ) );
		}

		$request = new WP_REST_Request( 'PUT', '/personal-todo/v1/tasks/' . $post_id );
		$request->set_param( 'id', $post_id );

		foreach ( array( 'title', 'excerpt', 'content', 'url', 'term', 'terms', 'pos_blocked_by', 'pos_blocked_pending_term', 'pos_recurring_days', 'scheduled_for' ) as $key ) {
			if ( array_key_exists( $key, $args ) ) {
				$request->set_param( $key, $args[ $key ] );
			}
		}

		$response = $this->rest_update_task( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
	}

	/**
	 * Ability callback: complete task.
	 *
	 * @param array $args Args.
	 * @return array|WP_Error
	 */
	public function ability_complete_task( $args ) {
		$post_id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		$task    = get_post( $post_id );

		if ( ! $this->is_task( $task ) ) {
			return new WP_Error( 'personal_todo_not_found', __( 'Task not found.', 'personal-todo' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'personal_todo_forbidden', __( 'You cannot edit this task.', 'personal-todo' ), array( 'status' => 403 ) );
		}

		$request = new WP_REST_Request( 'POST', '/personal-todo/v1/tasks/' . $post_id . '/complete' );
		$request->set_param( 'id', $post_id );

		$response = $this->rest_complete_task( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
	}

	/**
	 * Can current user create a task?
	 *
	 * @return bool
	 */
	public function can_create_task() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Can the current user list tasks?
	 *
	 * @return bool
	 */
	public function can_read_tasks() {
		return is_user_logged_in();
	}

	/**
	 * Can the current user read this task?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_read_task( WP_REST_Request $request ) {
		return current_user_can( 'read_post', (int) $request['id'] );
	}

	/**
	 * Can the current user edit this task?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_task( WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Get scheduled task events.
	 *
	 * @param int $user_id Optional owner.
	 * @return array
	 */
	public function get_scheduled_tasks( $user_id = 0 ) {
		$scheduled_events = array();
		$crons = _get_cron_array();

		if ( empty( $crons ) ) {
			return $scheduled_events;
		}

		foreach ( $crons as $timestamp => $cron ) {
			if ( empty( $cron[ $this->scheduled_hook ] ) ) {
				continue;
			}

			foreach ( $cron[ $this->scheduled_hook ] as $event ) {
				if ( empty( $event['args'][0] ) ) {
					continue;
				}

				$task = get_post( (int) $event['args'][0] );
				if ( ! $task || $task->post_type !== $this->knowledge()->post_type() ) {
					continue;
				}
				if ( $user_id && (int) $task->post_author !== (int) $user_id ) {
					continue;
				}
				if ( ! current_user_can( 'read_post', $task->ID ) && ( ! $user_id || (int) $task->post_author !== (int) $user_id ) ) {
					continue;
				}

				$scheduled_events[] = array(
					'timestamp' => $timestamp,
					'task_id'   => $task->ID,
				);
			}
		}

		return $scheduled_events;
	}

	/**
	 * Generate ICS content.
	 *
	 * @param int $user_id Owner.
	 * @return string
	 */
	public function generate_ics_content( $user_id ) {
		$output = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Personal TODO//TODO Calendar//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:Personal TODOs',
		);

		foreach ( $this->get_scheduled_tasks( $user_id ) as $scheduled ) {
			$task = get_post( $scheduled['task_id'] );
			if ( ! $task ) {
				continue;
			}

			$terms = wp_get_post_terms( $task->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'names' ) );
			$categories = is_wp_error( $terms ) ? '' : implode( ',', $terms );

			$output[] = 'BEGIN:VEVENT';
			$output[] = 'UID:personal-todo-' . $task->ID . '@personal-todo';
			$output[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$output[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $scheduled['timestamp'] );
			$output[] = 'SUMMARY:' . $this->escape_ics_text( $task->post_title );

			if ( '' !== $task->post_excerpt ) {
				$output[] = 'DESCRIPTION:' . $this->escape_ics_text( $task->post_excerpt );
			}
			if ( '' !== $categories ) {
				$output[] = 'CATEGORIES:' . $this->escape_ics_text( $categories );
			}

			$url = get_post_meta( $task->ID, 'url', true );
			if ( '' !== $url ) {
				$output[] = 'URL:' . esc_url_raw( $url );
			}

			$output[] = 'END:VEVENT';
		}

		$output[] = 'END:VCALENDAR';

		return implode( "\r\n", $output );
	}

	/**
	 * Schedule future task side effects.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Whether this is an existing post.
	 * @return void
	 */
	public function save_task_meta_side_effects( $post_id, $post, $update ) {
		unset( $update );

		if ( ! $this->is_task( $post ) || 'trash' === $post->post_status ) {
			return;
		}

		$time = strtotime( $post->post_date_gmt . ' GMT' );
		if ( $time > time() && ! wp_next_scheduled( $this->scheduled_hook, array( $post_id ) ) ) {
			$scheduled = wp_schedule_single_event( $time, $this->scheduled_hook, array( $post_id ) );
			if ( $scheduled && ! is_wp_error( $scheduled ) ) {
				$this->add_task_history(
					$post_id,
					array(
						sprintf(
							/* translators: %s: scheduled date and time */
							__( 'Scheduled for %s.', 'personal-todo' ),
							esc_html( wp_date( 'Y-m-d H:i:s', $time ) )
						),
					)
				);
			}
		}
	}

	/**
	 * Record task title/body updates as todo_note comments.
	 *
	 * @param int     $post_id  Post ID.
	 * @param WP_Post $post     Updated post.
	 * @param WP_Post $old_post Previous post.
	 * @return void
	 */
	public function save_task_update_history( $post_id, $post, $old_post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if (
			! $post instanceof WP_Post ||
			! $old_post instanceof WP_Post ||
			! $this->is_task( $post ) ||
			$this->knowledge()->post_type() !== $old_post->post_type
		) {
			return;
		}

		$changes = array();

		if ( $old_post->post_title !== $post->post_title ) {
			$changes[] = sprintf(
				/* translators: 1: old title, 2: new title */
				__( 'Title changed from <b><i>%1$s</i></b> to <b><i>%2$s</i></b>.', 'personal-todo' ),
				esc_html( $old_post->post_title ),
				esc_html( $post->post_title )
			);
		}

		if ( $old_post->post_excerpt !== $post->post_excerpt ) {
			if ( '' === $post->post_excerpt ) {
				$changes[] = sprintf(
					/* translators: %s: old notes */
					__( 'Notes cleared: <strike>%s</strike>.', 'personal-todo' ),
					esc_html( $old_post->post_excerpt )
				);
			} elseif ( '' === $old_post->post_excerpt ) {
				$changes[] = sprintf(
					/* translators: %s: new notes */
					__( 'Notes changed to %s.', 'personal-todo' ),
					esc_html( $post->post_excerpt )
				);
			} else {
				$changes[] = sprintf(
					/* translators: 1: old notes, 2: new notes */
					__( 'Notes changed from <strike>%1$s</strike> to %2$s.', 'personal-todo' ),
					esc_html( $old_post->post_excerpt ),
					esc_html( $post->post_excerpt )
				);
			}
		}

		if ( $old_post->post_content !== $post->post_content ) {
			$changes[] = __( 'Task details updated.', 'personal-todo' );
		}

		$this->add_task_history( $post_id, $changes );
	}

	/**
	 * Record task Knowledge type changes as todo_note comments.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Term IDs or slugs.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy name.
	 * @param bool   $append     Whether terms were appended.
	 * @param array  $old_tt_ids Previous term taxonomy IDs.
	 * @return void
	 */
	public function save_task_term_history( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $append );

		if ( $this->knowledge()->type_taxonomy() !== $taxonomy ) {
			return;
		}

		$post = get_post( $object_id );
		if ( ! $this->is_task( $post ) ) {
			return;
		}

		$tt_ids     = array_map( 'intval', (array) $tt_ids );
		$old_tt_ids = array_map( 'intval', (array) $old_tt_ids );
		$changes    = array();

		$added_terms = array_diff( $tt_ids, $old_tt_ids );
		if ( ! empty( $added_terms ) ) {
			$changes[] = sprintf(
				/* translators: %s: comma-separated term names */
				__( 'Added to Knowledge type: %s.', 'personal-todo' ),
				implode( ', ', $this->format_history_term_names( $added_terms ) )
			);
		}

		$removed_terms = array_diff( $old_tt_ids, $tt_ids );
		if ( ! empty( $removed_terms ) ) {
			$changes[] = sprintf(
				/* translators: %s: comma-separated term names */
				__( 'Removed from Knowledge type: <strike>%s</strike>.', 'personal-todo' ),
				implode( ', ', $this->format_history_term_names( $removed_terms ) )
			);
		}

		$this->add_task_history( $object_id, $changes );
	}

	/**
	 * Scheduled task is now actionable.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function scheduled_task_now( $post_id ) {
		$this->perform_pending_action( get_post( $post_id ) );
	}

	/**
	 * Unblock dependent tasks and duplicate recurring tasks when completing.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Previous status.
	 * @return void
	 */
	public function unblock_tasks_when_completing( $post_id, $previous_status ) {
		$post = get_post( $post_id );

		if ( ! $this->is_task( $post ) ) {
			return;
		}

		$this->add_task_history( $post_id, array( __( 'Completed task.', 'personal-todo' ) ) );

		$recurring_days = (int) get_post_meta( $post_id, 'pos_recurring_days', true );
		if ( $recurring_days > 0 ) {
			$this->duplicate_task(
				$post,
				array(
					'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+ ' . $recurring_days . ' days' ) ),
					'post_status' => $previous_status,
				)
			);
		}

		foreach ( $this->get_tasks_blocked_by( $post_id ) as $blocked_post ) {
			$this->perform_pending_action( $blocked_post );
		}

		$scheduled = wp_next_scheduled( $this->scheduled_hook, array( $post_id ) );
		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, $this->scheduled_hook, array( $post_id ) );
		}
	}

	/**
	 * Is this post a TODO Knowledge row?
	 *
	 * @param WP_Post|mixed $post Post.
	 * @return bool
	 */
	private function is_task( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->knowledge()->is_available() || $post->post_type !== $this->knowledge()->post_type() ) {
			return false;
		}

		$terms = wp_get_object_terms( $post->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );
		return ! is_wp_error( $terms ) && in_array( 'todo', $terms, true );
	}

	/**
	 * Query tasks blocked by another task.
	 *
	 * @param int $post_id Blocking post ID.
	 * @return WP_Post[]
	 */
	private function get_tasks_blocked_by( $post_id ) {
		return $this->list_tasks(
			array(
				'meta_query' => array(
					array(
						'key'   => 'pos_blocked_by',
						'value' => (int) $post_id,
					),
				),
			)
		);
	}

	/**
	 * Duplicate a recurring task.
	 *
	 * @param WP_Post $post    Source post.
	 * @param array   $changes Changes.
	 * @return void
	 */
	private function duplicate_task( WP_Post $post, $changes ) {
		$blocked_pending_term = get_post_meta( $post->ID, 'pos_blocked_pending_term', true );
		if ( empty( $blocked_pending_term ) ) {
			return;
		}

		$meta = array_filter(
			get_post_meta( $post->ID ),
			function ( $key ) {
				return '_' !== substr( $key, 0, 1 );
			},
			ARRAY_FILTER_USE_KEY
		);
		$meta = array_map(
			function ( $value ) {
				return is_array( $value ) && 1 === count( $value ) ? $value[0] : $value;
			},
			$meta
		);

		$current_terms = wp_get_object_terms( $post->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_terms ) ) {
			$current_terms = array();
		}

		$pending = get_term_by( 'slug', $blocked_pending_term, $this->knowledge()->type_taxonomy() );
		$terms = $pending ? array_diff( $current_terms, array( $pending->term_id ) ) : $current_terms;

		$new_post_data = wp_parse_args(
			$changes,
			array(
				'post_title'   => $post->post_title,
				'post_excerpt' => $post->post_excerpt,
				'post_content' => $post->post_content,
				'post_status'  => 'private',
				'meta_input'   => $meta,
			)
		);

		$new_post = $this->create_knowledge_post( $new_post_data, $terms, (int) $post->post_author );
		if ( ! is_wp_error( $new_post ) ) {
			$this->add_task_history(
				$new_post,
				array(
					sprintf(
						/* translators: %d: source task ID */
						__( 'Duplicated from task %d.', 'personal-todo' ),
						(int) $post->ID
					),
				)
			);
		}
	}

	/**
	 * Record task history as a todo_note comment.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $changes Change messages.
	 * @return void
	 */
	private function add_task_history( $post_id, array $changes ) {
		$changes = array_filter( $changes );
		if ( empty( $changes ) ) {
			return;
		}

		$items = array_map(
			function ( $change ) {
				return '<li>' . wp_kses_post( $change ) . '</li>';
			},
			$changes
		);

		$content = '<h4>' . esc_html( wp_date( 'Y-m-d H:i:s', time() ) ) . '</h4><ul>' . implode( "\n", $items ) . '</ul>';
		wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => wp_kses_post( $content ),
				'user_id'          => get_current_user_id(),
				'comment_type'     => 'todo_note',
				'comment_approved' => 1,
			)
		);
	}

	/**
	 * Format recent task history comments.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function format_task_history( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => 'todo_note',
				'status'  => 'approve',
				'number'  => 20,
				'orderby' => 'comment_ID',
				'order'   => 'DESC',
			)
		);

		return array_map(
			function ( $comment ) {
				return array(
					'id'       => (int) $comment->comment_ID,
					'content'  => wp_kses_post( $comment->comment_content ),
					'date_gmt' => $comment->comment_date_gmt,
					'user_id'  => (int) $comment->user_id,
				);
			},
			$comments
		);
	}

	/**
	 * Format term taxonomy IDs for history comments.
	 *
	 * @param int[] $term_taxonomy_ids Term taxonomy IDs.
	 * @return string[]
	 */
	private function format_history_term_names( $term_taxonomy_ids ) {
		$names = array();

		foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
			$term = get_term_by( 'term_taxonomy_id', (int) $term_taxonomy_id, $this->knowledge()->type_taxonomy() );
			if ( ! $term || is_wp_error( $term ) ) {
				$names[] = '<b>' . esc_html( (string) $term_taxonomy_id ) . '</b>';
				continue;
			}

			$names[] = '<b>' . esc_html( $term->name ) . '</b>';
		}

		return $names;
	}

	/**
	 * Perform pending term move after unblock/schedule.
	 *
	 * @param WP_Post|false $blocked_post Blocked post.
	 * @return void
	 */
	private function perform_pending_action( $blocked_post ) {
		if ( ! $this->is_task( $blocked_post ) ) {
			return;
		}

		$pending_slug = get_post_meta( $blocked_post->ID, 'pos_blocked_pending_term', true );
		if ( empty( $pending_slug ) ) {
			return;
		}

		$term = get_term_by( 'slug', $pending_slug, $this->knowledge()->type_taxonomy() );
		if ( ! $term ) {
			return;
		}

		wp_set_object_terms( $blocked_post->ID, array( $term->term_id ), $this->knowledge()->type_taxonomy(), true );
		delete_post_meta( $blocked_post->ID, 'pos_blocked_by' );
	}

	/**
	 * Escape text for ICS.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function escape_ics_text( $text ) {
		$text = str_replace( array( "\r\n", "\n", "\r" ), '\n', $text );
		return str_replace( array( ',', ';', '\\' ), array( '\,', '\;', '\\\\' ), $text );
	}

	/**
	 * Args common to collection routes.
	 *
	 * @return array
	 */
	private function collection_rest_args() {
		return array(
			'search'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'term'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'terms'    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'status'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'orderby'  => array(
				'type'              => 'string',
				'default'           => 'date',
				'sanitize_callback' => 'sanitize_key',
			),
			'order'    => array(
				'type'              => 'string',
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Args common to create/update routes.
	 *
	 * @return array
	 */
	private function edit_rest_args() {
		return array(
			'title'                    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'excerpt'                  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'                  => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'url'                      => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'pos_blocked_by'           => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'pos_blocked_pending_term' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'pos_recurring_days'       => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'scheduled_for'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'term'                     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'terms'                    => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Extract task post data and meta from a REST request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function task_data_from_request( WP_REST_Request $request ) {
		$data = array();

		foreach ( array(
			'title'   => 'post_title',
			'excerpt' => 'post_excerpt',
			'content' => 'post_content',
		) as $request_key => $post_key ) {
			if ( null === $request->get_param( $request_key ) ) {
				continue;
			}

			$data[ $post_key ] = 'content' === $request_key ? wp_kses_post( $request->get_param( $request_key ) ) : sanitize_text_field( $request->get_param( $request_key ) );
		}

		$meta = array();
		foreach ( array( 'url', 'pos_blocked_by', 'pos_blocked_pending_term', 'pos_recurring_days' ) as $meta_key ) {
			if ( null === $request->get_param( $meta_key ) ) {
				continue;
			}

			if ( 'url' === $meta_key ) {
				$meta[ $meta_key ] = esc_url_raw( $request->get_param( $meta_key ) );
			} elseif ( in_array( $meta_key, array( 'pos_blocked_by', 'pos_recurring_days' ), true ) ) {
				$meta[ $meta_key ] = absint( $request->get_param( $meta_key ) );
			} else {
				$meta[ $meta_key ] = sanitize_key( $request->get_param( $meta_key ) );
			}
		}

		if ( ! empty( $meta ) ) {
			$data['meta_input'] = $meta;
		}

		if ( null !== $request->get_param( 'scheduled_for' ) ) {
			$scheduled_for = sanitize_text_field( $request->get_param( 'scheduled_for' ) );
			$timestamp     = $scheduled_for ? strtotime( $scheduled_for ) : time();
			if ( false !== $timestamp ) {
				$data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
				$data['post_date']     = get_date_from_gmt( $data['post_date_gmt'] );
			}
		}

		return $data;
	}

	/**
	 * Extract term slugs from a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	private function request_term_slugs( WP_REST_Request $request ) {
		$terms = (array) $request->get_param( 'terms' );

		if ( '' !== (string) $request->get_param( 'term' ) ) {
			$terms[] = $request->get_param( 'term' );
		}

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $terms ) ) ) );
	}

	/**
	 * Standard missing Knowledge REST error.
	 *
	 * @return WP_Error
	 */
	private function missing_knowledge_error() {
		return new WP_Error( 'personalos_missing_knowledge', __( 'Knowledge is not available.', 'personal-todo' ), array( 'status' => 503 ) );
	}
}
