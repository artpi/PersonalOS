<?php
/**
 * Class EvernoteModuleTest
 *
 * @package Personalos
 */

/**
 * Sample test case.
 */
class EvernoteModuleTest extends WP_UnitTestCase {

	private function cycle_enml( $enml ) {
		$transformed = \Evernote::enml2html( $enml );
		$post = new \WP_Post( (object) array( 'post_content' => $transformed ) );
		$post = sanitize_post( $post, 'db' );
		$saved = wp_unslash( $post->post_content );
		$twice_transformed = \Evernote::html2enml( $saved );
		$this->assertXmlStringEqualsXmlString( trim( $enml ), trim( $twice_transformed ), "ENML got mangled. Transformed: \n{$transformed}\n Stored in DB:\n{$saved}" );
	}

	public function test_evernote_links() {
		$enml = <<<EOF
			<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
			<en-note>
			<h1>Test</h1>
			<div>Test paragraph</div>
			<div><a href="evernote:///view/1967834/s13/092a5913-c4dd-41bf-ab06-7039921ba433/092a5913-c4dd-41bf-ab06-7039921ba433/">Note Link</a></div>
			</en-note>
		EOF;
		$this->cycle_enml( $enml );
	}

	public function test_evernote_checkbox() {
		$enml = <<<EOF
			<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
			<en-note>
			<h1>Lets do this!</h1>
			<div><en-todo checked="true"/>Checked</div>
			<div><en-todo checked="false"/>False</div>
			</en-note>
		EOF;
		$this->cycle_enml( $enml );
	}

	public function test_evernote_media() {
		$enml = <<<EOF
			<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
			<en-note>
			<h1>This is some media that wont get uploaded</h1>
			<en-media hash="0a35baf77505fa7867468ec2b1b21865" type="audio/m4a" />
			</en-note>
		EOF;
		$this->cycle_enml( $enml );
	}
}
