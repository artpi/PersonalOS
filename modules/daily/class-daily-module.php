<?php

class Daily_Module extends POS_Module {
	public $id   = 'daily';
	public $name = 'Daily Journal';
	private $notes;

	public function __construct( $notes ) {
		$this->notes = $notes;
		$this->register();
	}

	public function register() {}

	public function get_daily_note_for_date( $date = null ) {
		if ( ! $date ) {
			$date = time();
		}
		// @TODO This happens to be the case for my daily notes coming from Evernote, but we need to make this different when we have native daily notes.
		return $this->notes->get_notes(
			array(
				'title' => gmdate( 'M jS, Y', $date ),
			)
		);
	}
}
