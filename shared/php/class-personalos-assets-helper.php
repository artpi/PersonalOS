<?php
/**
 * Shared package asset registration.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Assets_Helper' ) ) {
	/**
	 * Registers package-local built assets.
	 */
	class PersonalOS_Assets_Helper {
		/**
		 * Register a script from a package build file.
		 *
		 * @param string $handle     Script handle.
		 * @param string $plugin_file Main plugin file.
		 * @param string $relative_js Relative JS path.
		 * @return bool
		 */
		public static function register_script( $handle, $plugin_file, $relative_js = 'build/index.js' ) {
			$path       = plugin_dir_path( $plugin_file ) . $relative_js;
			$asset_path = preg_replace( '/\.js$/', '.asset.php', $path );

			if ( ! file_exists( $path ) ) {
				return false;
			}

			$asset = file_exists( $asset_path ) ? require $asset_path : array(
				'dependencies' => array(),
				'version'      => filemtime( $path ),
			);

			wp_register_script(
				$handle,
				plugins_url( $relative_js, $plugin_file ),
				$asset['dependencies'],
				$asset['version'],
				true
			);

			return true;
		}

		/**
		 * Register a style from a package build file.
		 *
		 * @param string $handle       Style handle.
		 * @param string $plugin_file  Main plugin file.
		 * @param string $relative_css Relative CSS path.
		 * @param array  $dependencies Dependencies.
		 * @return bool
		 */
		public static function register_style( $handle, $plugin_file, $relative_css = 'build/style-index.css', $dependencies = array() ) {
			$path = plugin_dir_path( $plugin_file ) . $relative_css;

			if ( ! file_exists( $path ) ) {
				return false;
			}

			wp_register_style(
				$handle,
				plugins_url( $relative_css, $plugin_file ),
				$dependencies,
				filemtime( $path )
			);

			return true;
		}
	}
}
