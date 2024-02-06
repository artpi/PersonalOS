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
	}

    public static function load_modules() {
        require_once( plugin_dir_path( __FILE__ ) . 'modules/module.php' );
        require_once( plugin_dir_path( __FILE__ ) . 'modules/notes/index.php' );
        require_once( plugin_dir_path( __FILE__ ) . 'modules/readwise/index.php' );

        $notes = new Notes_Module();
        $readwise = new Readwise( $notes );
        self::$modules = [
            $notes,
            $readwise,
        ];
        require_once( plugin_dir_path( __FILE__ ) . 'settings.php' );
        $settings = new POS_Settings( self::$modules );
    }
}
add_action( 'init', 'POS::init' );

