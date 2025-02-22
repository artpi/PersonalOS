<?php
$todos = $this->list( array(), 'starter-content' );
foreach ( $todos as $note ) {
	wp_delete_post( $note->ID );
}


$this->create(
	array(
		'post_title'   => 'Regular TODO in NOW with action',
		'post_excerpt' => 'This TODO has an action.',
		'meta_input'   => array(
			'url' => 'tel://1234567890',
		),
	),
	array( 'starter-content', 'now' )
);

$blocking = $this->create(
	array(
		'post_title'   => 'Blocking todo',
		'post_excerpt' => 'This is blocking another todo.',
	),
	array( 'starter-content', 'now' )
);

$blocked = $this->create(
	array(
		'post_title'   => 'Blocked todo',
		'post_excerpt' => 'This is blocked by the blocking todo. It will move to NOW when the blocking todo is completed.',
		'meta_input'   => array(
			'pos_blocked_by'           => $blocking,
			'pos_blocked_pending_term' => 'now',
		),
	),
	array( 'starter-content' )
);

$tomorrow = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
$this->create(
	array(
		'post_title'   => 'Scheduled TODO',
		'post_excerpt' => 'This is a scheduled todo. It will move to NOW on ' . $tomorrow,
		'post_date'    => $tomorrow,
		'meta_input'   => array(
			'pos_blocked_pending_term' => 'now',
		),
	),
	array( 'starter-content' )
);

$this->create(
	array(
		'post_title'   => 'Recuring TODO',
		'post_excerpt' => 'This is a recurring TODO. It will automatically schedule a copy of itself 2 days after completion.',
		'meta_input'   => array(
			'pos_blocked_pending_term' => 'now',
			'pos_recurring_days'       => 2,
		),
	),
	array( 'starter-content' )
);
