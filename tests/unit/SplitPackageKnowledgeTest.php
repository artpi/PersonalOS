<?php
/**
 * Tests for split package Knowledge behavior.
 *
 * @package Personalos
 */

/**
 * Split package repository tests.
 */
class SplitPackageKnowledgeTest extends WP_UnitTestCase {
	/**
	 * Load split classes once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		foreach ( array(
			'shared/php/class-personalos-knowledge-bridge.php',
			'shared/php/class-personalos-knowledge-type-vocabulary.php',
			'shared/php/class-personalos-settings-helper.php',
			'shared/php/class-personalos-admin-notice-helper.php',
			'shared/php/class-personalos-assets-helper.php',
			'shared/php/class-personalos-plugin-health.php',
			'shared/php/class-personalos-plugin-base.php',
			'shared/php/class-personalos-sync-plugin-base.php',
			'packages/personal-notes/includes/class-personal-notes-plugin.php',
			'packages/personal-todo/includes/class-personal-todo-plugin.php',
			'packages/personal-readwise-sync/includes/class-personal-readwise-sync-plugin.php',
			'packages/personal-evernote-sync/includes/class-personal-evernote-sync-plugin.php',
			'packages/personal-ai-chat/includes/class-personal-ai-chat-plugin.php',
		) as $file ) {
			require_once dirname( dirname( __DIR__ ) ) . '/' . $file;
		}

		if ( ! defined( 'PERSONAL_NOTES_VERSION' ) ) {
			define( 'PERSONAL_NOTES_VERSION', '0.1.0-test' );
		}
		if ( ! defined( 'PERSONAL_NOTES_FILE' ) ) {
			define( 'PERSONAL_NOTES_FILE', dirname( dirname( __DIR__ ) ) . '/packages/personal-notes/personal-notes.php' );
		}
		if ( ! defined( 'PERSONAL_TODO_VERSION' ) ) {
			define( 'PERSONAL_TODO_VERSION', '0.1.0-test' );
		}
		if ( ! defined( 'PERSONAL_TODO_FILE' ) ) {
			define( 'PERSONAL_TODO_FILE', dirname( dirname( __DIR__ ) ) . '/packages/personal-todo/personal-todo.php' );
		}
		if ( ! defined( 'PERSONAL_READWISE_SYNC_VERSION' ) ) {
			define( 'PERSONAL_READWISE_SYNC_VERSION', '0.1.0-test' );
		}
		if ( ! defined( 'PERSONAL_READWISE_SYNC_FILE' ) ) {
			define( 'PERSONAL_READWISE_SYNC_FILE', dirname( dirname( __DIR__ ) ) . '/packages/personal-readwise-sync/personal-readwise-sync.php' );
		}
		if ( ! defined( 'PERSONAL_EVERNOTE_SYNC_VERSION' ) ) {
			define( 'PERSONAL_EVERNOTE_SYNC_VERSION', '0.1.0-test' );
		}
		if ( ! defined( 'PERSONAL_EVERNOTE_SYNC_FILE' ) ) {
			define( 'PERSONAL_EVERNOTE_SYNC_FILE', dirname( dirname( __DIR__ ) ) . '/packages/personal-evernote-sync/personal-evernote-sync.php' );
		}
		if ( ! defined( 'PERSONAL_AI_CHAT_VERSION' ) ) {
			define( 'PERSONAL_AI_CHAT_VERSION', '0.1.0-test' );
		}
		if ( ! defined( 'PERSONAL_AI_CHAT_FILE' ) ) {
			define( 'PERSONAL_AI_CHAT_FILE', dirname( dirname( __DIR__ ) ) . '/packages/personal-ai-chat/personal-ai-chat.php' );
		}
	}

	/**
	 * Register Guidelines fallback runtime.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( post_type_exists( 'wp_knowledge' ) ) {
			unregister_post_type( 'wp_knowledge' );
		}
		if ( taxonomy_exists( 'wp_knowledge_type' ) ) {
			unregister_taxonomy( 'wp_knowledge_type' );
		}
		$this->register_runtime();
	}

	/**
	 * Manual notes are private Knowledge artifacts with note/manual terms.
	 */
	public function test_notes_create_manual_knowledge_note() {
		$plugin = new Personal_Notes_Plugin();
		$plugin->register();

		$post_id = $plugin->create_note( 'A note', 'A useful body', array( 'inbox' ) );

		$this->assertIsInt( $post_id );
		$post = get_post( $post_id );
		$this->assertSame( 'wp_guideline', $post->post_type );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( get_current_user_id(), (int) $post->post_author );

		$terms = wp_get_object_terms( $post_id, 'wp_guideline_type', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'artifact', $terms );
		$this->assertContains( 'note', $terms );
		$this->assertContains( 'manual', $terms );
		$this->assertContains( 'inbox', $terms );
	}

	/**
	 * Notes REST endpoints create, filter, and update Knowledge rows.
	 */
	public function test_notes_rest_create_list_and_update() {
		$plugin = new Personal_Notes_Plugin();
		$plugin->register();

		$request = new WP_REST_Request( 'POST', '/personal-notes/v1/notes' );
		$request->set_param( 'title', 'REST note' );
		$request->set_param( 'content', '<strong>Body</strong>' );
		$request->set_param( 'term', 'now' );

		$response = $plugin->rest_create_note( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$created = $response->get_data();
		$this->assertContains( 'note', $created['terms'] );
		$this->assertContains( 'manual', $created['terms'] );
		$this->assertContains( 'now', $created['terms'] );

		$list_request = new WP_REST_Request( 'GET', '/personal-notes/v1/notes' );
		$list_request->set_param( 'term', 'now' );
		$list_response = $plugin->rest_list_notes( $list_request );
		$list_data     = $list_response->get_data();

		$this->assertCount( 1, $list_data );
		$this->assertSame( $created['id'], $list_data[0]['id'] );

		$update_request = new WP_REST_Request( 'PUT', '/personal-notes/v1/notes/' . $created['id'] );
		$update_request->set_param( 'id', $created['id'] );
		$update_request->set_param( 'title', 'Updated REST note' );
		$update_request->set_param( 'terms', array( 'later' ) );

		$update_response = $plugin->rest_update_note( $update_request );
		$updated         = $update_response->get_data();

		$this->assertSame( 'Updated REST note', $updated['title'] );
		$this->assertContains( 'manual', $updated['terms'] );
		$this->assertContains( 'later', $updated['terms'] );
		$this->assertNotContains( 'now', $updated['terms'] );
	}

	/**
	 * Notes owns creation of PARA child terms.
	 */
	public function test_notes_rest_creates_para_child_term() {
		$plugin = new Personal_Notes_Plugin();
		$plugin->register();

		$request = new WP_REST_Request( 'POST', '/personal-notes/v1/terms' );
		$request->set_param( 'name', 'Split Plugins' );
		$request->set_param( 'slug', 'split-plugins' );
		$request->set_param( 'parent', 'project' );

		$response = $plugin->rest_create_term( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'split-plugins', $data['slug'] );
		$this->assertSame( 'project', $data['parent_slug'] );
	}

	/**
	 * TODO creates task artifacts, not plan/personalos rows.
	 */
	public function test_todo_creates_task_artifact() {
		$plugin = new Personal_TODO_Plugin();
		$plugin->register();

		$post_id = $plugin->create_task(
			array(
				'post_title'   => 'Ship split',
				'post_excerpt' => 'Make TODO Knowledge backed.',
			),
			array( 'now' )
		);

		$this->assertIsInt( $post_id );
		$post = get_post( $post_id );
		$this->assertSame( 'wp_guideline', $post->post_type );

		$terms = wp_get_object_terms( $post_id, 'wp_guideline_type', array( 'fields' => 'slugs' ) );
		$this->assertContains( 'artifact', $terms );
		$this->assertContains( 'todo', $terms );
		$this->assertContains( 'now', $terms );
		$this->assertNotContains( 'plan', $terms );
		$this->assertNotContains( 'personalos', $terms );
	}

	/**
	 * TODO REST endpoints create, filter, update, and complete task rows.
	 */
	public function test_todo_rest_create_list_update_and_complete() {
		$plugin = new Personal_TODO_Plugin();
		$plugin->register();

		$request = new WP_REST_Request( 'POST', '/personal-todo/v1/tasks' );
		$request->set_param( 'title', 'REST task' );
		$request->set_param( 'excerpt', 'Created through the package route.' );
		$request->set_param( 'term', 'now' );
		$request->set_param( 'url', 'https://example.com/task' );
		$request->set_param( 'pos_recurring_days', 7 );

		$response = $plugin->rest_create_task( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$created = $response->get_data();
		$this->assertContains( 'todo', $created['terms'] );
		$this->assertContains( 'now', $created['terms'] );
		$this->assertSame( 'https://example.com/task', $created['url'] );
		$this->assertSame( 7, $created['pos_recurring_days'] );

		$list_request = new WP_REST_Request( 'GET', '/personal-todo/v1/tasks' );
		$list_request->set_param( 'term', 'now' );
		$list_response = $plugin->rest_list_tasks( $list_request );
		$list_data     = $list_response->get_data();

		$this->assertCount( 1, $list_data );
		$this->assertSame( $created['id'], $list_data[0]['id'] );

		$update_request = new WP_REST_Request( 'PUT', '/personal-todo/v1/tasks/' . $created['id'] );
		$update_request->set_param( 'id', $created['id'] );
		$update_request->set_param( 'title', 'Updated REST task' );
		$update_request->set_param( 'terms', array( 'later' ) );
		$update_request->set_param( 'pos_blocked_pending_term', 'follow-up' );

		$update_response = $plugin->rest_update_task( $update_request );
		$updated         = $update_response->get_data();

		$this->assertSame( 'Updated REST task', $updated['title'] );
		$this->assertContains( 'todo', $updated['terms'] );
		$this->assertContains( 'later', $updated['terms'] );
		$this->assertNotContains( 'now', $updated['terms'] );
		$this->assertSame( 'follow-up', $updated['pos_blocked_pending_term'] );

		$complete_request = new WP_REST_Request( 'POST', '/personal-todo/v1/tasks/' . $created['id'] . '/complete' );
		$complete_request->set_param( 'id', $created['id'] );

		$complete_response = $plugin->rest_complete_task( $complete_request );
		$completed         = $complete_response->get_data();

		$this->assertSame( 'trash', $completed['post_status'] );
		$this->assertStringContainsString( 'Completed task.', implode( "\n", wp_list_pluck( $completed['history'], 'content' ) ) );
	}

	/**
	 * TODO records edit and Knowledge type changes as task comments.
	 */
	public function test_todo_records_history_comments_for_edits_and_terms() {
		$plugin = new Personal_TODO_Plugin();
		$plugin->register();

		$post_id = $plugin->create_task(
			array(
				'post_title'   => 'History task',
				'post_excerpt' => 'Original notes.',
			),
			array( 'now' )
		);

		$this->assertIsInt( $post_id );

		$update_request = new WP_REST_Request( 'PUT', '/personal-todo/v1/tasks/' . $post_id );
		$update_request->set_param( 'id', $post_id );
		$update_request->set_param( 'title', 'History task renamed' );
		$update_request->set_param( 'excerpt', 'Updated notes.' );
		$update_request->set_param( 'content', 'Readable details for Knowledge consumers.' );
		$update_request->set_param( 'terms', array( 'later' ) );

		$update_response = $plugin->rest_update_task( $update_request );
		$updated         = $update_response->get_data();

		$this->assertArrayHasKey( 'history', $updated );
		$this->assertNotEmpty( $updated['history'] );

		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => 'todo_note',
				'status'  => 'approve',
			)
		);
		$this->assertNotEmpty( $comments );

		foreach ( $comments as $comment ) {
			$this->assertSame( 'todo_note', $comment->comment_type );
			$this->assertSame( $post_id, (int) $comment->comment_post_ID );
		}

		$history = implode( "\n", wp_list_pluck( $comments, 'comment_content' ) );

		$this->assertStringContainsString( 'Title changed', $history );
		$this->assertStringContainsString( 'History task', $history );
		$this->assertStringContainsString( 'History task renamed', $history );
		$this->assertStringContainsString( 'Notes changed', $history );
		$this->assertStringContainsString( 'Task details updated.', $history );
		$this->assertStringContainsString( 'Added to Knowledge type', $history );
		$this->assertStringContainsString( 'Later', $history );
		$this->assertStringContainsString( 'Removed from Knowledge type', $history );
		$this->assertStringContainsString( 'Now', $history );
	}

	/**
	 * TODO ability callbacks update and complete Knowledge task rows.
	 */
	public function test_todo_update_and_complete_ability_callbacks() {
		$plugin = new Personal_TODO_Plugin();
		$plugin->register();

		$post_id = $plugin->create_task(
			array(
				'post_title'   => 'Ability task',
				'post_excerpt' => 'Created before ability update.',
			),
			array( 'now' )
		);

		$this->assertIsInt( $post_id );

		$updated = $plugin->ability_update_task(
			array(
				'id'                       => $post_id,
				'title'                    => 'Ability task updated',
				'excerpt'                  => 'Changed by ability.',
				'url'                      => 'https://example.com/ability-task',
				'terms'                    => array( 'later' ),
				'pos_blocked_pending_term' => 'follow-up',
				'pos_recurring_days'       => 3,
			)
		);

		$this->assertIsArray( $updated );
		$this->assertSame( 'Ability task updated', $updated['title'] );
		$this->assertSame( 'https://example.com/ability-task', $updated['url'] );
		$this->assertContains( 'later', $updated['terms'] );
		$this->assertNotContains( 'now', $updated['terms'] );
		$this->assertSame( 'follow-up', $updated['pos_blocked_pending_term'] );
		$this->assertSame( 3, $updated['pos_recurring_days'] );

		$listed = $plugin->ability_list_tasks(
			array(
				'search' => 'Ability task updated',
				'term'   => 'later',
			)
		);

		$this->assertCount( 1, $listed );
		$this->assertSame( $post_id, $listed[0]['id'] );

		$completed = $plugin->ability_complete_task(
			array(
				'id' => $post_id,
			)
		);

		$this->assertIsArray( $completed );
		$this->assertSame( 'trash', $completed['post_status'] );
		$this->assertStringContainsString( 'Completed task.', implode( "\n", wp_list_pluck( $completed['history'], 'content' ) ) );
	}

	/**
	 * ICS tokens resolve to the owning user.
	 */
	public function test_todo_ics_token_maps_to_user() {
		$user_id = get_current_user_id();
		$plugin = new Personal_TODO_Plugin();
		$plugin->register();
		$plugin->update_setting( 'ics_token', '1234567890123456', $user_id );

		$request = new WP_REST_Request( 'GET', '/personal-todo/v1/ics' );
		$request->set_param( 'token', '1234567890123456' );

		$this->assertTrue( $plugin->check_ics_permission( $request ) );
		$this->assertSame( $user_id, (int) $request->get_param( 'personal_todo_user_id' ) );
	}

	/**
	 * Readwise book summary generation uses a package route and AI Client filter.
	 */
	public function test_readwise_book_summary_generation_route() {
		$plugin = new Personal_Readwise_Sync_Plugin();
		$plugin->register();

		$captured_prompt = '';
		$callback        = function ( $pre, $full_prompt ) use ( &$captured_prompt ) {
			$captured_prompt = $full_prompt;
			return '<h2>Generated summary</h2><p>Body.</p>';
		};

		add_filter( 'personal_readwise_sync_pre_generate_book_summary', $callback, 10, 5 );

		try {
			$request = new WP_REST_Request( 'POST', '/personal-readwise-sync/v1/book-summary' );
			$request->set_param( 'prompt', 'Summarize the highlighted passages.' );
			$request->set_param( 'system_prompt', 'Return simple HTML.' );

			$response = $plugin->rest_generate_book_summary( $request );
		} finally {
			remove_filter( 'personal_readwise_sync_pre_generate_book_summary', $callback, 10 );
		}

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertSame( '<h2>Generated summary</h2><p>Body.</p>', $data['summary'] );
		$this->assertStringContainsString( 'System instructions: Return simple HTML.', $captured_prompt );
		$this->assertStringContainsString( 'Summarize the highlighted passages.', $captured_prompt );
	}

	/**
	 * Evernote sync loops active users and stores private Knowledge per owner.
	 */
	public function test_evernote_sync_imports_active_users_independently() {
		$user_one = get_current_user_id();
		$user_two = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$plugin   = new Personal_Evernote_Sync_Plugin();
		$plugin->register();

		$plugin->update_setting( 'token', 'token-one', $user_one );
		$plugin->update_setting( 'active', '1', $user_one );
		$plugin->update_setting( 'synced_notebooks', array( 'notebook-one' ), $user_one );

		$plugin->update_setting( 'token', 'token-two', $user_two );
		$plugin->update_setting( 'active', '1', $user_two );
		$plugin->update_setting( 'synced_notebooks', array( 'notebook-two' ), $user_two );

		$callback = function ( $payload, $user_id, $token, $synced_notebooks ) {
			return array(
				'notes'             => array(
					(object) array(
						'guid'         => 'note-' . $token,
						'title'        => 'Evernote note for ' . $token,
						'content'      => '<p>Imported body.</p>',
						'notebookGuid' => $synced_notebooks[0],
						'attributes'   => (object) array(
							'sourceURL' => 'https://example.com/' . $token,
						),
					),
					(object) array(
						'guid'         => 'skipped-' . $token,
						'title'        => 'Skipped note',
						'content'      => '<p>Outside configured notebook.</p>',
						'notebookGuid' => 'other-notebook',
					),
				),
				'usn'               => 23,
				'last_update_count' => 42,
				'cached_data'       => array(
					'notebooks' => array(
						$synced_notebooks[0] => array( 'name' => 'Synced Notebook' ),
					),
				),
			);
		};

		add_filter( 'personal_evernote_sync_fetch_notes', $callback, 10, 5 );

		try {
			$plugin->sync();
		} finally {
			remove_filter( 'personal_evernote_sync_fetch_notes', $callback, 10 );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wp_guideline',
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'meta_key'       => 'evernote_guid',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$this->assertCount( 2, $posts );
		$this->assertSame( array( $user_one, $user_two ), array_map( 'intval', wp_list_pluck( $posts, 'post_author' ) ) );
		$this->assertSame( '23', $plugin->get_setting( 'usn', $user_one ) );
		$this->assertSame( '42', $plugin->get_setting( 'last_update_count', $user_two ) );
		$this->assertStringContainsString( 'Synced Notebook', $plugin->get_setting( 'cached_data', $user_one ) );

		foreach ( $posts as $post ) {
			$terms = wp_get_object_terms( $post->ID, 'wp_guideline_type', array( 'fields' => 'slugs' ) );
			$this->assertContains( 'artifact', $terms );
			$this->assertContains( 'note', $terms );
			$this->assertContains( 'evernote', $terms );
			$this->assertContains( 'synced', $terms );
			$this->assertStringStartsWith( 'note-token-', get_post_meta( $post->ID, 'evernote_guid', true ) );
			$this->assertSame( get_post_meta( $post->ID, 'evernote_guid', true ), get_post_meta( $post->ID, '_personalos_external_id', true ) );
		}
	}

	/**
	 * Evernote sync can use a package-owned SDK client transport.
	 */
	public function test_evernote_sync_imports_from_client_transport() {
		$user_id = get_current_user_id();
		$plugin  = new Personal_Evernote_Sync_Plugin();
		$plugin->register();

		$note = (object) array(
			'guid'         => 'sdk-note',
			'title'        => 'SDK note',
			'content'      => '',
			'contentHash'  => 'sdk-hash',
			'notebookGuid' => 'notebook-one',
			'tagGuids'     => array( 'tag-one' ),
			'attributes'   => (object) array(
				'sourceURL' => 'https://example.com/sdk-note',
			),
		);

		$note_store = new class( $note ) {
			public $note;

			public function __construct( $note ) {
				$this->note = $note;
			}

			public function getSyncState() {
				return (object) array(
					'currentTime'    => 1234567890,
					'fullSyncBefore' => 0,
					'updateCount'    => 88,
				);
			}

			public function getFilteredSyncChunk( $usn, $limit, $filter ) {
				return (object) array(
					'chunkHighUSN' => 12,
					'notebooks'    => array(
						(object) array(
							'guid' => 'notebook-one',
							'name' => 'Imported Notebook',
						),
					),
					'tags'         => array(
						(object) array(
							'guid' => 'tag-one',
							'name' => 'Imported Tag',
						),
					),
					'notes'        => array( $this->note ),
				);
			}

			public function getNoteContent( $guid ) {
				return '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd"><en-note><div>Hello <b>Evernote</b></div><en-todo checked="true"/></en-note>';
			}
		};

		$client   = new class( $note_store ) {
			private $note_store;

			public function __construct( $note_store ) {
				$this->note_store = $note_store;
			}

			public function getNoteStore() {
				return $this->note_store;
			}
		};
		$callback = function () use ( $client ) {
			return $client;
		};

		$plugin->update_setting( 'token', 'sdk-token', $user_id );
		$plugin->update_setting( 'active', '1', $user_id );
		$plugin->update_setting( 'synced_notebooks', array( 'notebook-one' ), $user_id );

		add_filter( 'personal_evernote_sync_client', $callback, 10, 4 );

		try {
			$synced = $plugin->sync_user( $user_id );
		} finally {
			remove_filter( 'personal_evernote_sync_client', $callback, 10 );
		}

		$this->assertSame( 1, $synced );
		$this->assertSame( '12', $plugin->get_setting( 'usn', $user_id ) );
		$this->assertSame( '88', $plugin->get_setting( 'last_update_count', $user_id ) );
		$this->assertSame( '1234567890', $plugin->get_setting( 'last_sync', $user_id ) );
		$this->assertStringContainsString( 'Imported Notebook', $plugin->get_setting( 'cached_data', $user_id ) );

		$posts = get_posts(
			array(
				'post_type'      => 'wp_guideline',
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_key'       => 'evernote_guid',
				'meta_value'     => 'sdk-note',
			)
		);

		$this->assertCount( 1, $posts );
		$this->assertStringContainsString( 'Hello <b>Evernote</b>', $posts[0]->post_content );
		$this->assertStringContainsString( '[x]', $posts[0]->post_content );
	}

	/**
	 * AI Chat creates private Knowledge conversation rows.
	 */
	public function test_ai_chat_creates_conversation_knowledge_row() {
		$plugin = new Personal_AI_Chat_Plugin();
		$plugin->register();

		$request = new WP_REST_Request( 'POST', '/personal-ai-chat/v1/conversations' );
		$request->set_param( 'title', 'Split chat' );
		$request->set_param( 'content', 'Initial transcript' );
		$request->set_param( 'pos_model', 'test-model' );

		$response = $plugin->rest_create_conversation( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Split chat', $data['title'] );
		$this->assertSame( 'test-model', $data['pos_model'] );
		$this->assertContains( 'artifact', $data['terms'] );
		$this->assertContains( 'conversation', $data['terms'] );
		$this->assertContains( 'ai-chat', $data['terms'] );
		$this->assertContains( 'personalos', $data['terms'] );

		$post = get_post( $data['id'] );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( get_current_user_id(), (int) $post->post_author );
	}

	/**
	 * AI Chat appends transcript messages as AI message blocks.
	 */
	public function test_ai_chat_appends_message_block() {
		$plugin = new Personal_AI_Chat_Plugin();
		$plugin->register();

		$post_id = $plugin->create_conversation( 'Block chat', '', array() );
		$this->assertIsInt( $post_id );

		$request = new WP_REST_Request( 'POST', '/personal-ai-chat/v1/conversations/' . $post_id . '/messages' );
		$request->set_param( 'id', $post_id );
		$request->set_param( 'role', 'assistant' );
		$request->set_param( 'content', 'A saved answer.' );

		$response = $plugin->rest_append_message( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertStringContainsString( 'wp:pos/ai-message', $data['content'] );
		$this->assertStringContainsString( 'A saved answer.', $data['content'] );
		$this->assertStringContainsString( '"role":"assistant"', $data['content'] );
	}

	/**
	 * AI Chat can generate and persist an assistant reply through the AI Client path.
	 */
	public function test_ai_chat_generates_assistant_response() {
		$plugin = new Personal_AI_Chat_Plugin();
		$plugin->register();

		$post_id = $plugin->create_conversation( 'Generate chat', '', array() );
		$this->assertIsInt( $post_id );

		$captured_prompt = '';
		$callback        = function ( $pre, $prompt ) use ( &$captured_prompt ) {
			$captured_prompt = $prompt;
			return 'Generated assistant answer.';
		};

		add_filter( 'personal_ai_chat_pre_generate_text', $callback, 10, 5 );

		try {
			$request = new WP_REST_Request( 'POST', '/personal-ai-chat/v1/conversations/' . $post_id . '/generate' );
			$request->set_param( 'id', $post_id );
			$request->set_param( 'message', 'Hello from the user.' );
			$request->set_param( 'pos_model', 'test-model' );

			$response = $plugin->rest_generate_message( $request );
		} finally {
			remove_filter( 'personal_ai_chat_pre_generate_text', $callback, 10 );
		}

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertSame( 'test-model', $data['pos_model'] );
		$this->assertStringContainsString( 'User: Hello from the user.', $captured_prompt );
		$this->assertStringContainsString( 'wp:pos/ai-message', $data['content'] );
		$this->assertStringContainsString( 'Hello from the user.', $data['content'] );
		$this->assertStringContainsString( 'Generated assistant answer.', $data['content'] );
		$this->assertStringContainsString( '"role":"assistant"', $data['content'] );
	}

	/**
	 * Register a Guidelines-like runtime only.
	 *
	 * @return void
	 */
	private function register_runtime() {
		if ( ! post_type_exists( 'wp_guideline' ) ) {
			register_post_type(
				'wp_guideline',
				array(
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => true,
					'rest_base'    => 'guidelines',
					'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields', 'comments' ),
				)
			);
		}

		if ( ! taxonomy_exists( 'wp_guideline_type' ) ) {
			register_taxonomy(
				'wp_guideline_type',
				array( 'wp_guideline' ),
				array(
					'public'       => false,
					'hierarchical' => true,
					'show_ui'      => true,
					'show_in_rest' => true,
				)
			);
		}
	}
}
