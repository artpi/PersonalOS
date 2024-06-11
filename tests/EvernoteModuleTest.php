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
		$twice_transformed = \Evernote::html2enml( $transformed );
		$this->assertXmlStringEqualsXmlString( trim( $enml ), trim( $twice_transformed ) );
	}
	/**
	 * A single example test.
	 */
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
}
