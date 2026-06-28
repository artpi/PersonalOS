<?php
/**
 * Shared package settings helper.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Settings_Helper' ) ) {
	/**
	 * Stores site settings in options and user settings/state in user meta.
	 */
	class PersonalOS_Settings_Helper {
		/**
		 * Package slug.
		 *
		 * @var string
		 */
		private $package_slug;

		/**
		 * Settings config.
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * Constructor.
		 *
		 * @param string $package_slug Package slug.
		 * @param array  $settings     Settings config.
		 */
		public function __construct( $package_slug, $settings = array() ) {
			$this->package_slug = sanitize_key( $package_slug );
			$this->settings     = $settings;
		}

		/**
		 * Get package-prefixed storage key.
		 *
		 * @param string $setting_id Setting ID.
		 * @return string
		 */
		public function storage_key( $setting_id ) {
			return str_replace( '-', '_', $this->package_slug ) . '_' . sanitize_key( $setting_id );
		}

		/**
		 * Get a setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @param int    $user_id    Optional user ID for user-scoped settings.
		 * @return mixed
		 */
		public function get( $setting_id, $user_id = 0 ) {
			$config  = $this->setting_config( $setting_id );
			$key     = $this->storage_key( $setting_id );
			$default = array_key_exists( 'default', $config ) ? $config['default'] : false;

			if ( 'user' === $config['scope'] ) {
				$user_id = $user_id ? (int) $user_id : get_current_user_id();
				if ( ! $user_id ) {
					return $default;
				}

				return metadata_exists( 'user', $user_id, $key ) ? get_user_meta( $user_id, $key, true ) : $default;
			}

			return get_option( $key, $default );
		}

		/**
		 * Update a setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @param mixed  $value      Value.
		 * @param int    $user_id    Optional user ID.
		 * @return bool|int
		 */
		public function update( $setting_id, $value, $user_id = 0 ) {
			$config = $this->setting_config( $setting_id );
			$key    = $this->storage_key( $setting_id );
			$value  = $this->sanitize_value( $value, $config );

			if ( 'user' === $config['scope'] ) {
				$user_id = $user_id ? (int) $user_id : get_current_user_id();
				if ( ! $user_id ) {
					return false;
				}

				return update_user_meta( $user_id, $key, $value );
			}

			return update_option( $key, $value );
		}

		/**
		 * Delete a setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @param int    $user_id    Optional user ID.
		 * @return bool
		 */
		public function delete( $setting_id, $user_id = 0 ) {
			$config = $this->setting_config( $setting_id );
			$key    = $this->storage_key( $setting_id );

			if ( 'user' === $config['scope'] ) {
				$user_id = $user_id ? (int) $user_id : get_current_user_id();
				return $user_id ? delete_user_meta( $user_id, $key ) : false;
			}

			return delete_option( $key );
		}

		/**
		 * Find user IDs that have a non-empty setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @return int[]
		 */
		public function get_user_ids_with_setting( $setting_id ) {
			$key = $this->storage_key( $setting_id );

			$users = get_users(
				array(
					'fields'       => 'ids',
					'meta_key'     => $key,
					'meta_compare' => 'EXISTS',
				)
			);

			return array_values(
				array_filter(
					array_map( 'intval', $users ),
					function ( $user_id ) use ( $setting_id ) {
						return '' !== (string) $this->get( $setting_id, $user_id );
					}
				)
			);
		}

		/**
		 * Resolve a user-owned token to a user ID.
		 *
		 * @param string $setting_id Setting ID.
		 * @param string $token      Token.
		 * @return int
		 */
		public function find_user_for_setting_token( $setting_id, $token ) {
			$token = is_string( $token ) ? trim( $token ) : '';

			if ( strlen( $token ) < 12 ) {
				return 0;
			}

			$key   = $this->storage_key( $setting_id );
			$users = get_users(
				array(
					'fields'     => 'ids',
					'meta_key'   => $key,
					'meta_value' => $token,
					'number'     => 1,
				)
			);

			return empty( $users ) ? 0 : (int) $users[0];
		}

		/**
		 * Return setting config with defaults.
		 *
		 * @param string $setting_id Setting ID.
		 * @return array
		 */
		public function setting_config( $setting_id ) {
			$config = isset( $this->settings[ $setting_id ] ) ? $this->settings[ $setting_id ] : array();

			return wp_parse_args(
				$config,
				array(
					'type'    => 'text',
					'scope'   => 'site',
					'default' => false,
				)
			);
		}

		/**
		 * Sanitize a setting value.
		 *
		 * @param mixed $value  Value.
		 * @param array $config Setting config.
		 * @return mixed
		 */
		private function sanitize_value( $value, $config ) {
			if ( isset( $config['sanitize_callback'] ) && is_callable( $config['sanitize_callback'] ) ) {
				return call_user_func( $config['sanitize_callback'], $value );
			}

			if ( 'bool' === $config['type'] ) {
				return ! empty( $value ) ? '1' : '';
			}

			if ( 'textarea' === $config['type'] ) {
				return sanitize_textarea_field( $value );
			}

			if ( 'array' === $config['type'] ) {
				return array_map( 'sanitize_text_field', (array) $value );
			}

			return sanitize_text_field( $value );
		}
	}
}
