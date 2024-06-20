<?php
/**
 * Sample test case.
 */
class EvernoteModuleIntegrationTest extends WP_UnitTestCase {

	private $module     = null;

    public function set_up() {
		parent::set_up();
		$this->module = \POS::$modules[2];
        if ( empty( $_ENV['EVERNOTE_TOKEN'] ) ) {
            $this->markTestSkipped( 'No Evernote token provided.' );
        }
        $this->module->token = $_ENV['EVERNOTE_TOKEN'];
        $this->module->connect();
	}

    public function test_get_note() {
        $note = $this->module->advanced_client->getNoteStore()->getNote( 'bc03207a-0d46-49b7-9920-40e637e6f294', false, false, false, false );
        print_r( $note );
        $this->assertNotEmpty( $note );
    }
}
