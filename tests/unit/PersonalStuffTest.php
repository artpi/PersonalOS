<?php
/**
 * Native storage and upload behavior for Personal Stuff.
 *
 * @package PersonalOS
 */
class PersonalStuffTest extends WP_UnitTestCase {
	private $plugin;
	private $ids;

	public function set_up(): void {
		parent::set_up();
		require_once dirname( __DIR__, 2 ) . '/packages/personal-stuff/personal-stuff.php';
		require_once dirname( __DIR__, 2 ) . '/packages/personal-notes/includes/class-personal-notes-plugin.php';
		if ( ! defined( 'PERSONAL_NOTES_VERSION' ) ) {
			define( 'PERSONAL_NOTES_VERSION', '0.1.0' );
			define( 'PERSONAL_NOTES_FILE', dirname( __DIR__, 2 ) . '/packages/personal-notes/personal-notes.php' );
		}
		if ( ! post_type_exists( 'wp_knowledge' ) ) {
			register_post_type( 'wp_knowledge', array( 'public' => false, 'show_in_rest' => true, 'rest_base' => 'knowledge', 'supports' => array( 'title', 'editor', 'custom-fields' ) ) );
		}
		if ( ! taxonomy_exists( 'wp_knowledge_type' ) ) {
			register_taxonomy( 'wp_knowledge_type', 'wp_knowledge', array( 'hierarchical' => true, 'show_in_rest' => true ) );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->plugin = new Personal_Stuff_Plugin();
		$this->ids = $this->plugin->ensure_terms();
	}

	private function item( $content = '' ) {
		$id = self::factory()->post->create( array( 'post_type' => 'wp_knowledge', 'post_status' => 'private', 'post_author' => get_current_user_id(), 'post_content' => $content ) );
		wp_set_object_terms( $id, array( $this->ids['artifact'], $this->ids['stuff-item'] ), 'wp_knowledge_type' );
		return $id;
	}

	public function test_terms_are_idempotent_raw_terms_without_plugin_options() {
		global $wpdb;
		$before = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'personal_stuff_%'" );
		$place = wp_insert_term( 'Garage', 'wp_knowledge_type', array( 'parent' => $this->ids['stuff-places'], 'description' => 'Raw term description' ) );
		$this->assertSame( $this->ids, $this->plugin->ensure_terms() );
		$this->assertSame( 0, (int) get_term( $this->ids['stuff'], 'wp_knowledge_type' )->parent );
		foreach ( array( 'stuff-item', 'stuff-places', 'stuff-tags' ) as $slug ) {
			$this->assertSame( $this->ids['stuff'], (int) get_term( $this->ids[ $slug ], 'wp_knowledge_type' )->parent );
		}
		$this->assertSame( 'Raw term description', get_term( $place['term_id'], 'wp_knowledge_type' )->description );
		$this->assertSame( array(), get_term_meta( $place['term_id'] ) );
		$this->assertSame( $before, $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'personal_stuff_%'" ) );
	}

	public function test_core_term_rest_descriptions_explain_empty_terms_without_changing_stored_descriptions() {
		$this->plugin->vocabulary()->register_rest_descriptions();
		$place = wp_insert_term( 'Blaszak', 'wp_knowledge_type', array( 'parent' => $this->ids['stuff-places'] ) );
		$tag   = wp_insert_term( 'Travel', 'wp_knowledge_type', array( 'parent' => $this->ids['stuff-tags'], 'description' => 'Used away from home.' ) );
		$server = rest_get_server();
		do_action( 'rest_api_init', $server );
		$request  = new WP_REST_Request( 'GET', rest_get_route_for_taxonomy_items( 'wp_knowledge_type' ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'hide_empty', false );
		$response = $server->dispatch( $request );
		$terms    = array_column( $response->get_data(), null, 'id' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			sprintf(
				'Personal Stuff place. Path: Stuff (%d) › Places (%d) › Blaszak (%d)',
				$this->ids['stuff'],
				$this->ids['stuff-places'],
				$place['term_id']
			),
			$terms[ $place['term_id'] ]['description']
		);
		$this->assertSame( 'Used away from home.', $terms[ $tag['term_id'] ]['description'] );
		$this->assertSame( '', get_term( $place['term_id'], 'wp_knowledge_type' )->description );
	}

	public function test_provisioning_can_be_deferred_until_knowledge_exists() {
		unregister_taxonomy( 'wp_knowledge_type' );
		$this->assertSame( array(), $this->plugin->ensure_terms() );
		register_taxonomy( 'wp_knowledge_type', 'wp_knowledge', array( 'hierarchical' => true, 'show_in_rest' => true ) );
		$this->assertCount( 5, $this->plugin->ensure_terms() );
	}

	public function test_editor_selection_coexists_with_notes_for_empty_and_block_items() {
		$notes = new Personal_Notes_Plugin();
		add_filter( 'use_block_editor_for_post', array( $notes, 'use_block_editor_for_knowledge_post' ), 10, 2 );
		add_filter( 'use_block_editor_for_post', array( $this->plugin, 'use_item_editor' ), 20, 2 );
		$this->assertTrue( use_block_editor_for_post( $this->item() ) );
		$this->assertTrue( use_block_editor_for_post( $this->item( '<!-- wp:paragraph --><p>Camera</p><!-- /wp:paragraph -->' ) ) );
		$plain = self::factory()->post->create( array( 'post_type' => 'wp_knowledge', 'post_content' => '# Markdown' ) );
		$this->assertFalse( use_block_editor_for_post( $plain ) );
		$block = self::factory()->post->create( array( 'post_type' => 'wp_knowledge', 'post_content' => '<!-- wp:paragraph --><p>Note</p><!-- /wp:paragraph -->' ) );
		$this->assertTrue( use_block_editor_for_post( $block ) );
	}

	public function test_upload_names_are_random_only_for_authorized_item_context() {
		$file = array( 'name' => 'secret-camera.JPG', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/example', 'error' => 0 );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $this->item() );
		$this->plugin->begin_upload( null, array(), $request );
		$first = $this->plugin->randomize_upload( $file );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}\.jpg$/', $first['name'] );
		$this->assertNotSame( $first['name'], $this->plugin->randomize_upload( $file )['name'] );
		$this->assertNotEmpty( $this->plugin->randomize_upload( array_merge( $file, array( 'name' => 'secret.php' ) ) )['error'] );
		wp_set_current_user( 0 );
		$this->assertSame( $file, $this->plugin->randomize_upload( $file ) );
		$this->plugin->end_upload( null, array(), $request );
		$this->assertSame( $file, $this->plugin->randomize_upload( $file ) );
	}

	public function test_core_rest_creates_private_item_and_preserves_content_and_unrelated_terms() {
		$server = rest_get_server();
		do_action( 'rest_api_init', $server );
		$extra = wp_insert_term( 'Other consumer', 'wp_knowledge_type', array( 'slug' => 'other-consumer' ) );
		$raw = '<!-- wp:paragraph --><p>Description</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:example/unknown {"data":"keep"} --><aside>Unchanged</aside><!-- /wp:example/unknown -->';
		$request = new WP_REST_Request( 'POST', '/wp/v2/knowledge' );
		$request->set_body_params( array( 'title' => 'Camera', 'status' => 'private', 'content' => $raw, 'wp_knowledge_type' => array( $this->ids['artifact'], $this->ids['stuff-item'], $extra['term_id'] ) ) );
		$response = $server->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];
		$this->assertTrue( $this->plugin->is_item( $id ) );
		$update = new WP_REST_Request( 'POST', '/wp/v2/knowledge/' . $id );
		$update->set_param( 'title', 'Renamed camera' );
		$this->assertSame( 200, $server->dispatch( $update )->get_status() );
		$this->assertSame( $raw, get_post( $id )->post_content );
		$this->assertSame( 'private', get_post_status( $id ) );
		$this->assertTrue( has_term( 'other-consumer', 'wp_knowledge_type', $id ) );
		wp_set_current_user( 0 );
		$read = new WP_REST_Request( 'GET', '/wp/v2/knowledge/' . $id );
		$this->assertSame( 401, $server->dispatch( $read )->get_status() );
	}

	public function test_raw_media_upload_and_generated_sizes_keep_random_basename() {
		add_filter( 'rest_request_before_callbacks', array( $this->plugin, 'begin_upload' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this->plugin, 'end_upload' ), 10, 3 );
		add_filter( 'wp_handle_sideload_prefilter', array( $this->plugin, 'randomize_upload' ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $this->item() );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename="identifying-camera.jpg"' );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];
		$file = get_attached_file( $id );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}\.jpg$/', basename( $file ) );
		$stem = pathinfo( $file, PATHINFO_FILENAME );
		$metadata = wp_get_attachment_metadata( $id );
		foreach ( $metadata['sizes'] as $size ) {
			$this->assertStringStartsWith( $stem . '-', $size['file'] );
		}
		$this->assertSame( 'ordinary.jpg', $this->plugin->randomize_upload( array( 'name' => 'ordinary.jpg' ) )['name'] );
		wp_delete_attachment( $id, true );
	}
}
