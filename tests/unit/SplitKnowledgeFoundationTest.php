<?php
/**
 * Tests for split plugin Knowledge helpers.
 *
 * @package Personalos
 */

/**
 * Split Knowledge foundation tests.
 */
class SplitKnowledgeFoundationTest extends WP_UnitTestCase {
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
			'shared/php/class-personalos-wp-app.php',
			'shared/php/class-personalos-plugin-base.php',
			'shared/php/class-personalos-sync-plugin-base.php',
		) as $file ) {
			require_once dirname( dirname( __DIR__ ) ) . '/' . $file;
		}
	}

	/**
	 * Register fake Knowledge and Guidelines runtime surfaces.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->register_runtime( 'wp_guideline', 'wp_guideline_type', 'guidelines' );
		$this->register_runtime( 'wp_knowledge', 'wp_knowledge_type', 'knowledge' );
	}

	/**
	 * Clean up runtime globals.
	 */
	public function tear_down(): void {
		delete_option( 'personal_readwise_sync_last_sync' );
		foreach ( array( 'wp_knowledge', 'wp_guideline' ) as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				unregister_post_type( $post_type );
			}
		}
		foreach ( array( 'wp_knowledge_type', 'wp_guideline_type' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}
		parent::tear_down();
	}

	/**
	 * The bridge prefers Knowledge names over Guidelines names.
	 */
	public function test_bridge_prefers_knowledge_runtime() {
		$bridge = new PersonalOS_Knowledge_Bridge( 'personal-test' );

		$this->assertSame( 'wp_knowledge', $bridge->post_type() );
		$this->assertSame( 'wp_knowledge_type', $bridge->type_taxonomy() );
		$this->assertSame( 'knowledge', $bridge->rest_base() );
	}

	/**
	 * The vocabulary normalizes PARA hierarchy and numbered labels.
	 */
	public function test_vocabulary_normalizes_para_terms() {
		$bridge = new PersonalOS_Knowledge_Bridge( 'personal-test' );
		$vocabulary = new PersonalOS_Knowledge_Type_Vocabulary( $bridge );

		$terms = $vocabulary->ensure_terms( array( 'status', 'now', 'project', 'area', 'resource' ) );

		$this->assertIsArray( $terms );
		$status = get_term_by( 'slug', 'status', 'wp_knowledge_type' );
		$now    = get_term_by( 'slug', 'now', 'wp_knowledge_type' );

		$this->assertSame( '1-Status', $status->name );
		$this->assertSame( (int) $status->term_id, (int) $now->parent );
		$this->assertSame( '2-Projects', get_term_by( 'slug', 'project', 'wp_knowledge_type' )->name );
		$this->assertSame( '3-Areas', get_term_by( 'slug', 'area', 'wp_knowledge_type' )->name );
		$this->assertSame( '4-Resources', get_term_by( 'slug', 'resource', 'wp_knowledge_type' )->name );
	}

	/**
	 * Container terms cannot be assigned directly.
	 */
	public function test_container_terms_are_not_assignable() {
		$bridge = new PersonalOS_Knowledge_Bridge( 'personal-test' );
		$vocabulary = new PersonalOS_Knowledge_Type_Vocabulary( $bridge );

		$result = $vocabulary->resolve_assignable_term_ids( array( 'artifact', 'project' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'personalos_container_term_not_assignable', $result->get_error_code() );
	}

	/**
	 * User and site settings use separate WordPress stores.
	 */
	public function test_settings_scope_and_token_lookup() {
		$user_id = self::factory()->user->create();
		$settings = new PersonalOS_Settings_Helper(
			'personal-readwise-sync',
			array(
				'token'     => array(
					'type'  => 'text',
					'scope' => 'user',
				),
				'last_sync' => array(
					'type'  => 'text',
					'scope' => 'site',
				),
			)
		);

		$settings->update( 'token', '1234567890123456', $user_id );
		$settings->update( 'last_sync', '2026-06-16T09:00:00+00:00' );

		$this->assertSame( '1234567890123456', get_user_meta( $user_id, 'personal_readwise_sync_token', true ) );
		$this->assertSame( '2026-06-16T09:00:00+00:00', get_option( 'personal_readwise_sync_last_sync' ) );
		$this->assertSame( $user_id, $settings->find_user_for_setting_token( 'token', '1234567890123456' ) );
		$this->assertSame( 0, $settings->find_user_for_setting_token( 'token', 'short' ) );
	}

	/**
	 * Register a runtime pair.
	 *
	 * @param string $post_type Post type.
	 * @param string $taxonomy  Taxonomy.
	 * @param string $rest_base Rest base.
	 * @return void
	 */
	private function register_runtime( $post_type, $taxonomy, $rest_base ) {
		if ( ! post_type_exists( $post_type ) ) {
			register_post_type(
				$post_type,
				array(
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => true,
					'rest_base'    => $rest_base,
					'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields', 'comments' ),
				)
			);
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( $post_type ),
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
