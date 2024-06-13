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

	private function assert_enml_transformed_to_html_and_stored_unserializes_correctly( $enml ) {
		// Fix for evernote putting end ; in styles.
		$enml = str_replace(';"', '"', $enml );
		$transformed = \Evernote::enml2html( $enml );
		$post = new \WP_Post( (object) array( 'post_content' => $transformed ) );
		// We are running this through post sanitization so we know we are not losing valuable data in this process
		$post = sanitize_post( $post, 'db' );
		$saved = wp_unslash( $post->post_content );
		$twice_transformed = \Evernote::html2enml( $saved );
		$this->assertXmlStringEqualsXmlString( trim( $enml ), trim( $twice_transformed ), "ENML got mangled. Transformed: \n{$transformed}\n Stored in DB:\n{$saved}" );
		// now real post insert
		$note = new \EDAM\Types\Note();
		$note->content = $enml;
		$module = \POS::$modules[2];
		$html = $module->get_note_html( $note );

		$post_id = wp_insert_post( [
			'post_title' => 'WordPress',
			'post_content' => $html,
			'post_status' => 'publish',
			'post_type' => 'notes',
		] );
		$post = get_post( $post_id );
		$this->assertXmlStringEqualsXmlString( trim( $enml ), trim( $module::html2enml( $post->post_content ) ) );
		wp_delete_post( $post_id, true );
	}

	public function test_evernote_links() {
		$enml = <<<EOF
			<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
			<en-note>
			<h1>Test</h1>
			<div>Test paragraph</div>
			<div><a href="evernote:///view/1967834/s13/092a5913-c4dd-41bf-ab06-7039921ba433/092a5913-c4dd-41bf-ab06-7039921ba433/">Note Link</a></div>
			<div><a href='evernote:///view/1967834/s13/970d08c4-ee40-4145-9722-32f28e0fc35a/970d08c4-ee40-4145-9722-32f28e0fc35a/'>(*)</a></div>

			</en-note>
		EOF;
		$this->assert_enml_transformed_to_html_and_stored_unserializes_correctly( $enml );
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
		$this->assert_enml_transformed_to_html_and_stored_unserializes_correctly( $enml );
	}

	public function test_evernote_media() {
		$enml = <<<EOF
			<?xml version="1.0" encoding="UTF-8"?>
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
			<en-note>
			<h1>This is some media that wont get uploaded</h1>
			<en-media hash="0a35baf77505fa7867468ec2b1b21865" type="audio/m4a" />
			<br /><en-media hash="13708d05e93f6a57e37a8b3c08022406" type="image/png" ></en-media>
			</en-note>
		EOF;
		$this->assert_enml_transformed_to_html_and_stored_unserializes_correctly( $enml );
	}

	public function test_arturs_daily_note() {
		$enml = <<<EOF
			<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd"><en-note><div><b>Jakie są highlighty z poprzedniego dnia?</b></div><ul><li><div>ale był piknink!</div></li><li><div><br/></div></li></ul><div><br/></div><div><b>On a scale of 1 to 10, how fully engaged are you in your work? What is standing in your way? <a rev="en_rl_none" href="evernote:///view/324/s13/092a5913-c4dd-41bf-ab06-7039921ba433/092a5913-42324-41bf-ab06-24234/">(*)</a>?</b></div><ul><li><div>I love my work! </div></li></ul><div><br/></div><div style="text-align:center;"><i>Stop offering up advice with a question mark attached. That doesn’t count as asking a questionMichael Bungay Stanier <a rev="en_rl_none" href="evernote:///view/3422/s13/424234-b4ab-4bf6-4200-2433/b36d240c-b4ab-4bf6-4200-243/">(*)</a></i></div><hr/><div><b>Freeform:</b></div><en-media hash="aa41437475456934234681fb3f738d92acc1" type="audio/m4a" /><div>i’ll</div><hr/><div><b>Dobry dzień:</b></div><div><br/></div><div><br/></div><ul style="--en-todo:true;"><li style="--en-checked:false;"><div>Nie jeść węgli</div></li><li style="--en-checked:false;"><div>Intermittent fasting</div></li><li style="--en-checked:false;"><div>Spędzić czas na dworze / ruszać sie</div></li></ul><ul><li><div><en-todo checked="false" /><b>00:00</b> <a href="https://www.google.com/calendar/event?eid=anMzcGhvaTJ2cDIyYmh2bXRj2423423IucGlzemVrQGE4Yy5jb20" rev="en_rl_none">Parental Leave</a></div></li><li><div><en-todo checked="false" /><b>01:00</b> <a href="https://www.google.com/calendar/event?eid=NGM5cDMxZDBh24324kZDYxMWtfM243234YXJ0dXIucGlzemVrQGE4Yy5jb20" rev="en_rl_none">Home</a></div></li><li><div><en-todo checked="false" /><b>21:00</b> <a href="https://www.google.com/calendar/event?eid=MXNrOTN1cHA1bWxzOXZwbWttdWE3MGQxMHRfMjAyNDA2MTBUMTkwMDAwWiBhcnR1ci5waXN6ZWtAbQ" rev="en_rl_none">Mogą być znowu zorze</a></div></li><li><div><en-todo checked="false" /><b>01:00</b> <a href="https://www.google.com/calendar/event?eid=cW04YmZnaDZ234234xMmR2dmV1NWdfMjAyNDA2MTEgYXJ0dXIucGlzemVrQGE4Yy5jb20" rev="en_rl_none">Home</a></div></li><li><div><en-todo checked="false" /><a href="evernote:///view/2434/s13/76d1d561-a8e3-4dd1-929a-4234/76d1d561-a8e3-4dd1-929a-24/" rev="en_rl_none">Total Recall - Arnold Schwarzenegger</a></div></li><li><div><en-todo checked="false" /><a href="evernote:///view/424/s13/4cb2b2a3-e9c3-48fc-b8e8-58ca7bd9a13a/42343e9c3-48fc-b8e8-58ca7bd9a13a/" rev="en_rl_none">Fajne Kursy</a></div></li><li><div><en-todo checked="false" /><a href="evernote:///view/4234/s13/0dd95026-8c53-4978-9add-2e4243/0dd95026-8c53-4978-9add-2e2bf34c01f9/" rev="en_rl_none">Dobre rzeczy 2016</a></div></li><li><div><a href="evernote:///view/423/s13/3c7c0188-d86b-4812-89a8-423/3c7c0188-d86b-4812-89a8-6f5507b0e1d3/" rev="en_rl_none">Dec 15th, 2023</a></div></li><li><div><a href="evernote:///view/4234/s13/019af669-7122-45c1-8b8e-8ae45896740d/424-7122-45c1-8b8e-8ae45896740d/" rev="en_rl_none">Sep 9th, 2023</a></div></li><li><div><a href="evernote:///view/424/s13/019af669-7122-45c1-8b8e-4243/019af669-7122-45c1-8b8e-8ae45896740d/" rev="en_rl_none">Sep 9th, 2023</a></div></li></ul><div><br/></div><div><b>Decyzje do podjęcia</b></div><ul><li><div><br/></div></li></ul><div><br/></div><div><b>Szalone pomysły:</b></div><ul><li><div><br/></div></li></ul><div><br/></div><div><b>Co ma sens w długim teraz?:</b></div><ul><li><div><br/></div></li></ul><div><br/></div><div><br/></div><div><br/></div><h3 style="--en-nodeId:e8aaf7f2-55a9-47cc-b431-e5279aa935c9;">Photos</h3><en-media style="--en-naturalWidth:160;--en-naturalHeight:120;" type="image/png" hash="d7892d13bcd1a905428d52a05d305345" /><div><a href="https://photos.google.com/lr/photo/AJ2nVKhwUo-6euq0Yov8IAeIx_Q28Q6nDtNwL3xO874NwpfFlVKzo_s_8Eg0YarqW0NAF0YFdZK16omQA" rev="en_rl_none">🔗</a></div><en-media style="--en-naturalWidth:160;--en-naturalHeight:120;" type="image/png" hash="a0a8872dd746c91ea1f76b8306a46602" /><div><a href="https://photos.google.com/lr/photo/AJ2nVKjGpwfp2gnAZRO0xn8acg1I6H_2odHV4kiELKqJff9zH8xqsFldtMy_UQ42lOGClpHq3PMtTGqcw" rev="en_rl_none">🔗</a></div><en-media style="--en-naturalWidth:160;--en-naturalHeight:213;" type="image/png" hash="9b166f0224a03f0603d14563a3a7cf7f" /><div><a href="https://photos.google.com/lr/photo/AJ2nVKjkJt-cbN_66KYNHssKovWpwfpfpuTJsC_k9g-Fk6j_RwQPvFJKYvxoA5kgExh2uZ8cZLx-NmiH4whER59xGGg" rev="en_rl_none">🔗</a></div></en-note>
		EOF;
		$this->assert_enml_transformed_to_html_and_stored_unserializes_correctly( $enml );
	}

	public function test_extension_from_mime() {
		$this->assertEquals( 'png', \Evernote::get_extension_from_mime( 'image/png' ) );
		$this->assertEquals( 'jpg', \Evernote::get_extension_from_mime( 'image/jpeg' ) );
		$this->assertEquals( 'mp4', \Evernote::get_extension_from_mime( 'video/mp4' ) );
		// Evernote has 'audio/m4a' mime type for m4a files
		$this->assertEquals( 'm4a', \Evernote::get_extension_from_mime( 'audio/m4a' ) );
		$this->assertEquals( 'amr', \Evernote::get_extension_from_mime( 'audio/amr' ) );
	}

	public function test_create_note_from_evernote() {
		$module = \POS::$modules[2];
		$post_id = wp_insert_post( [
			'post_title' => 'WordPress',
			'post_content' => 'Test WordPress content',
			'post_status' => 'publish',
			'post_type' => 'notes',
		] );

		$note = new \EDAM\Types\Note();
		$note->title = 'Evernote';
		$note->guid = 'potato';
		$note->content = $module::wrap_note( <<<EOF
			<h1>Test</h1>
			<div>First Test paragraph</div>
		EOF );
		$note->contentHash = md5( $note->content, true );
		$note->created = time() * 1000;

		$module->update_note_from_evernote( $note, get_post( $post_id ) );
		$updated_note = get_post( $post_id );
		$this->assertEquals( 'Evernote', $updated_note->post_title );
		$this->assertStringContainsString( 'First Test paragraph', $updated_note->post_content );

		$note->content = $module::wrap_note( <<<EOF
			<h1>Test</h1>
			<div>Replaced Test Paragraph</div>
			EOF );
		$module->update_note_from_evernote( $note, get_post( $post_id ) );
		$this->assertStringNotContainsString( 'Replaced Test Paragraph', get_post( $post_id )->post_content, 'Content remains unchanged if bodyhash is the same' );
		$note->contentHash = md5( $note->content, true );
		$module->update_note_from_evernote( $note, get_post( $post_id ) );
		$this->assertStringContainsString( 'Replaced Test Paragraph', get_post( $post_id )->post_content, 'Content is updated when bodyhash changes' );
		$this->assertEquals( bin2hex( $note->contentHash ), get_post_meta( $post_id, 'evernote_content_hash', true ), 'Content hash is updated' );
		wp_delete_post( $post_id, true );
	}
}
