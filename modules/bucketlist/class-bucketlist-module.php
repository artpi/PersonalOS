<?php

class Bucketlist_Module extends POS_Module {
	public $id   = 'bucketlist';
	public $name = 'Bucketlist';

	public function register() {
		add_filter( 'pos_notebook_flags', array( $this, 'add_bucketlist_flag' ) );
		$this->activate();
	}

	public function add_bucketlist_flag( $flags ) {
		$flags['bucketlist'] = 'This notebook is a Bucketlist Item';
		return $flags;
	}

	public function activate() {
		if ( ! term_exists( 'Bucketlist', 'notebook' ) ) {
			wp_insert_term(
				'Bucketlist',
				'notebook',
				array(
					'slug' => 'bucketlist',
				)
			);
		}
	}
}
