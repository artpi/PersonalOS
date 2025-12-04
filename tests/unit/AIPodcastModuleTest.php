<?php

class Dummy_ElevenLabs_Module {
	public function is_configured() {
		return false;
	}
}

class AIPodcastModuleTest extends WP_UnitTestCase {

	private $module;
	private $token_user_id;

	public function set_up(): void {
		parent::set_up();

		$openai      = new stdClass();
		$eleven_labs = new Dummy_ElevenLabs_Module();

		$this->module = new POS_AI_Podcast_Module( $openai, $eleven_labs );

		$this->token_user_id = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'podcast-user@example.com',
			)
		);
		update_user_meta( $this->token_user_id, 'pos_ai-podcast_token', 'podcast-token' );

		rest_get_server();
		do_action( 'rest_api_init' );
	}

	public function test_permission_callback_accepts_valid_token() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pos/v1/ai-podcast', $routes );

		$endpoint            = $routes['/pos/v1/ai-podcast'][0];
		$permission_callback = $endpoint['permission_callback'];

		$request = new WP_REST_Request( 'GET', '/pos/v1/ai-podcast' );
		$request->set_param( 'token', 'podcast-token' );

		wp_set_current_user( 0 );
		$this->assertTrue( $permission_callback( $request ) );
		$this->assertSame( $this->token_user_id, get_current_user_id() );
	}

	public function test_permission_callback_rejects_invalid_token() {
		$routes = rest_get_server()->get_routes();
		$endpoint            = $routes['/pos/v1/ai-podcast'][0];
		$permission_callback = $endpoint['permission_callback'];

		$request = new WP_REST_Request( 'GET', '/pos/v1/ai-podcast' );
		$request->set_param( 'token', 'invalid' );

		$this->assertFalse( $permission_callback( $request ) );
	}
}

