<?php
/**
 * Shared package health checks.
 *
 * @package PersonalOS
 */

if ( ! class_exists( 'PersonalOS_Plugin_Health' ) ) {
	/**
	 * Small runtime health object.
	 */
	class PersonalOS_Plugin_Health {
		/**
		 * Knowledge bridge.
		 *
		 * @var PersonalOS_Knowledge_Bridge
		 */
		private $knowledge;

		/**
		 * Constructor.
		 *
		 * @param PersonalOS_Knowledge_Bridge $knowledge Knowledge bridge.
		 */
		public function __construct( PersonalOS_Knowledge_Bridge $knowledge ) {
			$this->knowledge = $knowledge;
		}

		/**
		 * Get package health data.
		 *
		 * @return array
		 */
		public function data() {
			return array(
				'knowledge_available' => $this->knowledge->is_available(),
				'post_type'           => $this->knowledge->post_type(),
				'type_taxonomy'       => $this->knowledge->type_taxonomy(),
				'rest_base'           => $this->knowledge->rest_base(),
				'source_meta_key'     => $this->knowledge->source_meta_key(),
			);
		}
	}
}
