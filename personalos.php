<?php

/**
 * Plugin Name:     Personal OS
 * Description:     Manage your life.
 * Version:         0.0.1
 * Author:          Artur Piszek (artpi)
 * Author URI:      https://piszek.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     pos
 *
 * @package         artpi
 */


class POS {
	public static $modules = [];

	public static function init() {
        self::load_modules();
        add_action( 'enqueue_block_editor_assets', array( 'POS', 'enqueue_assets' ) );

	}

    public static function enqueue_assets() {
        $script_asset = require( plugin_dir_path( __FILE__ ) .'/build/index.asset.php' );
		wp_enqueue_script(
			'pos',
			plugins_url( 'build/index.js', __FILE__ ),
			$script_asset['dependencies'],
			$script_asset['version']
		);
	}

    public static function load_modules() {
        require_once( plugin_dir_path( __FILE__ ) . 'modules/module.php' );
        require_once( plugin_dir_path( __FILE__ ) . 'modules/notes/index.php' );
        require_once( plugin_dir_path( __FILE__ ) . 'modules/readwise/index.php' );
        require_once( plugin_dir_path( __FILE__ ) . 'modules/todo/index.php' );

        $todo = new TODO_Module();
        $notes = new Notes_Module();
        $readwise = new Readwise( $notes );
        self::$modules = [
            $notes,
            $readwise,
            $todo,
        ];
        require_once( plugin_dir_path( __FILE__ ) . 'settings.php' );
        $settings = new POS_Settings( self::$modules );
    }
}
add_action( 'init', 'POS::init' );

