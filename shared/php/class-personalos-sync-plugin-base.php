<?php
/**
 * Shared sync package base class.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Sync_Plugin_Base' ) ) {
	/**
	 * Base class for independent sync packages.
	 */
	class PersonalOS_Sync_Plugin_Base extends PersonalOS_Plugin_Base {
		/**
		 * Get package cron hook.
		 *
		 * @return string
		 */
		public function get_sync_hook_name() {
			return str_replace( '-', '_', $this->slug() ) . '_cron';
		}

		/**
		 * Register recurring sync.
		 *
		 * @param string $interval Cron interval.
		 * @return void
		 */
		public function register_sync( $interval = 'hourly' ) {
			$hook = $this->get_sync_hook_name();

			add_action( $hook, array( $this, 'sync' ) );

			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $interval, $hook );
			}
		}

		/**
		 * Unschedule recurring sync.
		 *
		 * @return void
		 */
		public function unschedule_sync() {
			wp_clear_scheduled_hook( $this->get_sync_hook_name() );
		}

		/**
		 * Schedule one sync continuation.
		 *
		 * @param int $delay Delay in seconds.
		 * @return void
		 */
		public function schedule_single_sync( $delay = 60 ) {
			wp_schedule_single_event( time() + (int) $delay, $this->get_sync_hook_name() );
		}

		/**
		 * Empty sync callback for packages to override.
		 *
		 * @return void
		 */
		public function sync() {
			$this->log( 'Empty sync callback.' );
		}

		/**
		 * Run callback as a specific user and restore current user afterwards.
		 *
		 * @param int      $user_id  User ID.
		 * @param callable $callback Callback.
		 * @return mixed
		 */
		public function run_for_user( $user_id, $callback ) {
			$previous_user_id = get_current_user_id();
			wp_set_current_user( (int) $user_id );

			try {
				$result = call_user_func( $callback, (int) $user_id );
			} catch ( Exception $exception ) {
				wp_set_current_user( $previous_user_id );
				throw $exception;
			}

			wp_set_current_user( $previous_user_id );

			return $result;
		}

		/**
		 * Get user IDs with a configured setting.
		 *
		 * @param string $setting_id Setting ID.
		 * @return int[]
		 */
		public function get_user_ids_with_setting( $setting_id ) {
			return $this->settings_helper()->get_user_ids_with_setting( $setting_id );
		}

		/**
		 * Resolve a user token.
		 *
		 * @param string $setting_id Setting ID.
		 * @param string $token      Token.
		 * @return int
		 */
		public function find_user_for_setting_token( $setting_id, $token ) {
			return $this->settings_helper()->find_user_for_setting_token( $setting_id, $token );
		}
	}
}
