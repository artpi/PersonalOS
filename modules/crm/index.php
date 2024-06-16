<?php

class CRM_Module extends POS_Module {
	public $id   = 'crm';
	public $name = 'CRM';

	function add_meta_box() {
		add_meta_box(
			'crm_person_meta_box',
			'Person Details',
			array( $this, 'display_crm_person_meta_box' ),
			'crm_person',
			'normal',
			'high'
		);
	}
	function display_crm_person_meta_box() {
		global $post;
		$custom  = get_post_custom( $post->ID );
		$phone   = $custom['phone'][0];
		$email   = $custom['email'][0];
		$address = $custom['address'][0];
		?>
		<div>
			<label for="phone">Phone</label>
			<input name="phone" value="<?php echo $phone; ?>">
		</div>
		<div>
			<label for="email">Email</label>
			<input name="email" value="<?php echo $email; ?>">
		</div>
		<div>
			<label for="address">Address</label>
			<input name="address" value="<?php echo $address; ?>">
		</div>
		<?php
	}

	function register() {
		register_post_type(
			$this->id . '_person',
			array(
				'labels'               => array(
					'name'          => 'People',
					'singular_name' => 'Person',
					'add_new'       => 'Add New Person',
					'add_new_item'  => 'Add New Person',
				),
				//'show_in_menu' => 'personalos',
				'register_meta_box_cb' => array( $this, 'add_meta_box' ),
				'show_in_rest'         => true,
				'public'               => false,
				'show_ui'              => true,
				'has_archive'          => false,
				'rest_namespace'       => 'pos/' . $this->id,
				'supports'             => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			)
		);
	}
}
