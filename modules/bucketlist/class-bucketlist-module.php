<?php

class Bucketlist_Module extends POS_Module {
	public $id   = 'bucketlist';
	public $name = 'Bucketlist';

	public function register() {
		add_filter( 'pos_notebook_flags', array( $this, 'add_bucketlist_flag' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		$this->activate();

	}

	public function add_admin_menu(): void {
		add_menu_page(
			'Bucketlist',
			'Bucketlist',
			'read',
			'bucketlist',
			array( $this, 'render_admin_page' ),
			'dashicons-list-view'
		);
	}

	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="bucketlist-root"></div>
		</div>
		<?php
	}

	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'toplevel_page_bucketlist' !== $hook ) {
			return;
		}

		$asset_file = plugin_dir_path( __FILE__ ) . 'js/build/admin.asset.php';
		$asset = require $asset_file;

		wp_enqueue_script(
			'bucketlist-admin',
			plugin_dir_url( __FILE__ ) . 'js/build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			false
		);

		wp_set_script_translations( 'bucketlist-admin', 'personalos' );
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
