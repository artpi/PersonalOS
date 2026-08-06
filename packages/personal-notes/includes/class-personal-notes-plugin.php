<?php
/**
 * Personal Notes package.
 *
 * @package PersonalNotes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Split packages use their package text domain while root PHPCS still expects personalos.

/**
 * Notes UI and Knowledge repository.
 */
class Personal_Notes_Plugin extends PersonalOS_Plugin_Base {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'slug'         => 'personal-notes',
				'display_name' => 'Personal Notes',
				'text_domain'  => 'personal-notes',
				'version'      => PERSONAL_NOTES_VERSION,
				'plugin_file'  => PERSONAL_NOTES_FILE,
				'app'          => array(
					'path'         => 'notes',
					'name'         => 'Notes',
					'action_label' => 'Open App',
					'capability'   => 'edit_posts',
					'icon'         => 'dashicons-welcome-write-blog',
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( ! $this->knowledge()->is_available() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			return;
		}

		$this->register_common_knowledge_meta();
		$this->enable_knowledge_editor();
		$this->knowledge()->register_type_meta(
			'flag',
			array(
				'type'         => 'string',
				'single'       => false,
				'show_in_rest' => true,
			)
		);
		$this->register_blocks();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Enable the native editor without exposing the full Knowledge list UI.
	 *
	 * @return void
	 */
	private function enable_knowledge_editor() {
		$post_type_object = get_post_type_object( $this->knowledge()->post_type() );

		if ( $post_type_object ) {
			$post_type_object->show_ui = true;
		}
	}

	/**
	 * Enqueue the app and expose the resolved core Knowledge routes.
	 *
	 * @return void
	 */
	public function enqueue_package_assets() {
		parent::enqueue_package_assets();

		if ( ! $this->knowledge()->is_available() ) {
			return;
		}

		$taxonomy        = $this->knowledge()->type_taxonomy();
		$taxonomy_object = get_taxonomy( $taxonomy );

		wp_localize_script(
			$this->script_handle(),
			'personalNotesSettings',
			array(
				'knowledgeRestPath' => rest_get_route_for_post_type_items( $this->knowledge()->post_type() ),
				'taxonomyRestPath'  => rest_get_route_for_taxonomy_items( $taxonomy ),
				'taxonomyField'     => $taxonomy_object && $taxonomy_object->rest_base ? $taxonomy_object->rest_base : $taxonomy,
				'editPostUrl'       => admin_url( 'post.php' ),
			)
		);
	}

	/**
	 * Register package-owned blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$this->register_block_from_package( 'build/blocks/note' );
	}

	/**
	 * Add Notes menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$this->add_admin_page_hook(
			add_menu_page(
				'Notes',
				'Notes',
				'edit_posts',
				'personal-notes',
				array( $this, 'render_admin_page' ),
				'dashicons-welcome-write-blog',
				3
			)
		);
	}

	/**
	 * Register Notes REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'personal-notes/v1',
			'/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_list_notes' ),
					'permission_callback' => array( $this, 'can_read_notes' ),
					'args'                => $this->collection_rest_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_note' ),
					'permission_callback' => array( $this, 'can_create_notes' ),
					'args'                => $this->edit_rest_args(),
				),
			)
		);

		register_rest_route(
			'personal-notes/v1',
			'/notes/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_note' ),
					'permission_callback' => array( $this, 'can_read_note' ),
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
					'callback'            => array( $this, 'rest_update_note' ),
					'permission_callback' => array( $this, 'can_edit_note' ),
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
			'personal-notes/v1',
			'/terms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_list_terms' ),
					'permission_callback' => array( $this, 'can_read_notes' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_term' ),
					'permission_callback' => array( $this, 'can_manage_terms' ),
					'args'                => array(
						'name'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'parent' => array(
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'project', 'area', 'resource', 'archive' ),
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * List readable note Knowledge rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_list_notes( WP_REST_Request $request ) {
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

		$notes = $this->query_notes( $args, $this->request_term_slugs( $request ) );

		return rest_ensure_response( array_map( array( $this, 'format_note' ), $notes ) );
	}

	/**
	 * Create a note Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_create_note( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$term_slugs = $this->request_term_slugs( $request );
		if ( empty( $term_slugs ) ) {
			$term_slugs = array( 'inbox' );
		}

		$post_id = $this->create_note(
			sanitize_text_field( $request->get_param( 'title' ) ),
			wp_kses_post( $request->get_param( 'content' ) ),
			$term_slugs
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$response = rest_ensure_response( $this->format_note( get_post( $post_id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Get one note Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_note( WP_REST_Request $request ) {
		$note = get_post( (int) $request['id'] );

		if ( ! $this->is_note( $note ) ) {
			return new WP_Error( 'personal_notes_not_found', __( 'Note not found.', 'personal-notes' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->format_note( $note ) );
	}

	/**
	 * Update a note Knowledge row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_update_note( WP_REST_Request $request ) {
		$note = get_post( (int) $request['id'] );

		if ( ! $this->is_note( $note ) ) {
			return new WP_Error( 'personal_notes_not_found', __( 'Note not found.', 'personal-notes' ), array( 'status' => 404 ) );
		}

		$data = array( 'ID' => $note->ID );
		foreach ( array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		) as $request_key => $post_key ) {
			if ( null === $request->get_param( $request_key ) ) {
				continue;
			}

			$data[ $post_key ] = 'content' === $request_key ? wp_kses_post( $request->get_param( $request_key ) ) : sanitize_text_field( $request->get_param( $request_key ) );
		}

		if ( null !== $request->get_param( 'status' ) ) {
			$data['post_status'] = sanitize_key( $request->get_param( 'status' ) );
		}

		if ( count( $data ) > 1 ) {
			$updated = wp_update_post( $data, true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		if ( null !== $request->get_param( 'term' ) || null !== $request->get_param( 'terms' ) ) {
			$term_ids = $this->vocabulary()->resolve_assignable_term_ids( $this->note_update_terms( $note, $this->request_term_slugs( $request ) ) );
			if ( is_wp_error( $term_ids ) ) {
				return $term_ids;
			}

			wp_set_object_terms( $note->ID, $term_ids, $this->knowledge()->type_taxonomy(), false );
		}

		return rest_ensure_response( $this->format_note( get_post( $note->ID ) ) );
	}

	/**
	 * List assignable Knowledge type terms.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_list_terms() {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$ensured = $this->ensure_knowledge_terms();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		return rest_ensure_response( array_map( array( $this, 'format_term' ), $this->vocabulary()->get_assignable_terms() ) );
	}

	/**
	 * Create a project/area/resource/archive child term.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_create_term( WP_REST_Request $request ) {
		if ( ! $this->knowledge()->is_available() ) {
			return $this->missing_knowledge_error();
		}

		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$slug    = $request->get_param( 'slug' ) ? sanitize_title( $request->get_param( 'slug' ) ) : sanitize_title( $name );
		$term_id = $this->vocabulary()->ensure_child_term( $name, $slug, sanitize_key( $request->get_param( 'parent' ) ) );

		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		$response = rest_ensure_response( $this->format_term( get_term( $term_id, $this->knowledge()->type_taxonomy() ) ) );
		$response->set_status( 201 );

		return $response;
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
		if ( isset( $_POST['personal_notes_action'] ) && 'create' === sanitize_key( wp_unslash( $_POST['personal_notes_action'] ) ) ) {
			check_admin_referer( 'personal_notes_create' );

			$created = $this->create_note(
				sanitize_text_field( wp_unslash( $_POST['personal_notes_title'] ?? '' ) ),
				wp_kses_post( wp_unslash( $_POST['personal_notes_content'] ?? '' ) ),
				array_filter(
					array(
						sanitize_key( wp_unslash( $_POST['personal_notes_status'] ?? 'inbox' ) ),
					)
				)
			);
		}

		$notes = $this->query_notes();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->app_display_name() ); ?></h1>
			<?php if ( is_wp_error( $created ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $created->get_error_message() ); ?></p></div>
			<?php elseif ( $created ) : ?>
				<div class="notice notice-success"><p><a href="<?php echo esc_url( get_edit_post_link( $created ) ); ?>"><?php echo esc_html( get_the_title( $created ) ); ?></a></p></div>
			<?php endif; ?>

			<?php if ( ! $this->knowledge()->is_available() ) : ?>
				<p><?php esc_html_e( 'Knowledge is not available yet.', 'personal-notes' ); ?></p>
			<?php else : ?>
				<div id="personal-notes-admin-app" class="personal-notes-admin"></div>
				<div class="personal-notes-admin-fallback">
					<form method="post">
						<?php wp_nonce_field( 'personal_notes_create' ); ?>
						<input type="hidden" name="personal_notes_action" value="create">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="personal_notes_title"><?php esc_html_e( 'Title', 'personal-notes' ); ?></label></th>
									<td><input class="regular-text" type="text" id="personal_notes_title" name="personal_notes_title"></td>
								</tr>
								<tr>
									<th scope="row"><label for="personal_notes_content"><?php esc_html_e( 'Content', 'personal-notes' ); ?></label></th>
									<td><textarea class="large-text" rows="6" id="personal_notes_content" name="personal_notes_content"></textarea></td>
								</tr>
								<tr>
									<th scope="row"><label for="personal_notes_status"><?php esc_html_e( 'Status', 'personal-notes' ); ?></label></th>
									<td>
										<select id="personal_notes_status" name="personal_notes_status">
											<option value="inbox"><?php esc_html_e( 'Inbox', 'personal-notes' ); ?></option>
											<option value="now"><?php esc_html_e( 'Now', 'personal-notes' ); ?></option>
											<option value="later"><?php esc_html_e( 'Later', 'personal-notes' ); ?></option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
						<?php submit_button( __( 'Save Note', 'personal-notes' ) ); ?>
					</form>

					<h2><?php esc_html_e( 'Recent Notes', 'personal-notes' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Title', 'personal-notes' ); ?></th>
								<th><?php esc_html_e( 'Terms', 'personal-notes' ); ?></th>
								<th><?php esc_html_e( 'Date', 'personal-notes' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $notes as $note ) : ?>
								<?php
								$term_names = wp_get_object_terms( $note->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'names' ) );
								$term_names = is_wp_error( $term_names ) ? array() : $term_names;
								?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $note->ID ) ); ?>"><?php echo esc_html( get_the_title( $note ) ); ?></a></td>
									<td><?php echo esc_html( implode( ', ', $term_names ) ); ?></td>
									<td><?php echo esc_html( get_the_date( '', $note ) ); ?></td>
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
	 * Create a manual note.
	 *
	 * @param string $title      Title.
	 * @param string $content    Content.
	 * @param array  $term_slugs Additional term slugs.
	 * @param int    $user_id    Optional owner.
	 * @return int|WP_Error
	 */
	public function create_note( $title, $content, $term_slugs = array(), $user_id = 0 ) {
		$terms = array_values( array_unique( array_merge( array( 'artifact', 'note', 'manual' ), $term_slugs ) ) );

		return $this->create_knowledge_post(
			array(
				'post_title'        => $title ? $title : __( 'Untitled Note', 'personal-notes' ),
				'post_content'      => $content,
				'post_excerpt'      => wp_trim_words( wp_strip_all_tags( $content ), 24 ),
				'personalos_source' => 'native:personal-notes',
			),
			$terms,
			$user_id
		);
	}

	/**
	 * Query readable note-like Knowledge rows.
	 *
	 * @param array $args       Query args.
	 * @param array $term_slugs Additional term filters.
	 * @return WP_Post[]
	 */
	public function query_notes( $args = array(), $term_slugs = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		return $this->query_knowledge_posts( $args, array_values( array_unique( array_merge( array( 'note' ), $term_slugs ) ) ) );
	}

	/**
	 * Format a note for REST responses.
	 *
	 * @param WP_Post $note Note post.
	 * @return array
	 */
	public function format_note( WP_Post $note ) {
		$terms = wp_get_object_terms( $note->ID, $this->knowledge()->type_taxonomy() );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		return array(
			'id'           => $note->ID,
			'title'        => $note->post_title,
			'excerpt'      => $note->post_excerpt,
			'content'      => $note->post_content,
			'status'       => $note->post_status,
			'author'       => (int) $note->post_author,
			'date_gmt'     => $note->post_date_gmt,
			'modified_gmt' => $note->post_modified_gmt,
			'edit_url'     => get_edit_post_link( $note->ID, 'raw' ),
			'source'       => get_post_meta( $note->ID, $this->knowledge()->source_meta_key(), true ),
			'terms'        => array_map(
				function ( $term ) {
					return $term->slug;
				},
				$terms
			),
			'term_labels'  => array_map(
				function ( $term ) {
					return $term->name;
				},
				$terms
			),
		);
	}

	/**
	 * Format a Knowledge type term for REST responses.
	 *
	 * @param WP_Term $term Term.
	 * @return array
	 */
	public function format_term( WP_Term $term ) {
		$parent = $term->parent ? get_term( $term->parent, $this->knowledge()->type_taxonomy() ) : null;

		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'parent'      => (int) $term->parent,
			'parent_slug' => $parent && ! is_wp_error( $parent ) ? $parent->slug : '',
			'count'       => (int) $term->count,
			'flag'        => get_term_meta( $term->term_id, 'flag', false ),
		);
	}

	/**
	 * Can the current user list Notes?
	 *
	 * @return bool
	 */
	public function can_read_notes() {
		return is_user_logged_in();
	}

	/**
	 * Can the current user create Notes?
	 *
	 * @return bool
	 */
	public function can_create_notes() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Can the current user read this note?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_read_note( WP_REST_Request $request ) {
		return current_user_can( 'read_post', (int) $request['id'] );
	}

	/**
	 * Can the current user edit this note?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_note( WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Can the current user manage Knowledge organization terms?
	 *
	 * @return bool
	 */
	public function can_manage_terms() {
		return current_user_can( 'manage_categories' ) || current_user_can( 'manage_options' );
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
			'title'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'term'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			),
			'terms'   => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);
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
	 * Build the term set for a note update while preserving provenance.
	 *
	 * @param WP_Post $note        Note post.
	 * @param array   $term_slugs  Requested terms.
	 * @return string[]
	 */
	private function note_update_terms( WP_Post $note, $term_slugs ) {
		$existing = wp_get_object_terms( $note->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );
		$existing = is_wp_error( $existing ) ? array() : $existing;
		$preserve = array_intersect( $existing, array( 'manual', 'synced', 'readwise', 'evernote', 'ai-chat', 'personalos', 'daily-note' ) );

		return array_values( array_unique( array_merge( array( 'artifact', 'note' ), $preserve, $term_slugs ) ) );
	}

	/**
	 * Is this post a note-like Knowledge row?
	 *
	 * @param WP_Post|mixed $post Post.
	 * @return bool
	 */
	private function is_note( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->knowledge()->is_available() || $post->post_type !== $this->knowledge()->post_type() ) {
			return false;
		}

		$terms = wp_get_object_terms( $post->ID, $this->knowledge()->type_taxonomy(), array( 'fields' => 'slugs' ) );

		return ! is_wp_error( $terms ) && in_array( 'note', $terms, true );
	}

	/**
	 * Standard missing Knowledge REST error.
	 *
	 * @return WP_Error
	 */
	private function missing_knowledge_error() {
		return new WP_Error( 'personalos_missing_knowledge', __( 'Knowledge is not available.', 'personal-notes' ), array( 'status' => 503 ) );
	}
}
