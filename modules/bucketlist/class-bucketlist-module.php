<?php

class Bucketlist_Module extends POS_Module {
	public $id   = 'bucketlist';
	public $name = 'Bucketlist';

	public function register() {
		add_filter( 'pos_notebook_flags', array( $this, 'add_bucketlist_flag' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$this->activate();

	}

	public function add_admin_menu(): void {
		add_submenu_page( 'personalos', 'Bucketlist', 'Bucketlist', 'read', 'pos-bucketlist', array( $this, 'render_admin_page' ) );
	}

	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="bucketlist-root" class="pos__dataview"></div>
		</div>
		<?php
		wp_enqueue_script( 'pos' );
		wp_enqueue_style( 'pos' );
		//wp_add_inline_script( 'pos', 'wp.domReady( () => { window.renderNotebookAdmin( document.getElementById( "bucketlist-root" ), { view: { filters: [ { field: "flags", operator: "isAny", value: ["bucketlist"] } ] } } ); } );', 'after' );
		wp_add_inline_script( 'pos', 'wp.domReady( () => { window.renderTodoAdmin( document.getElementById( "bucketlist-root" ), {} ); } );', 'after' );

	}

	public function add_bucketlist_flag( $flags ) {
		$flags[] = array(
			'id' => 'bucketlist',
			'name' => 'Bucketlist',
			'label' => 'This notebook is a Bucketlist Item',
		);
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
