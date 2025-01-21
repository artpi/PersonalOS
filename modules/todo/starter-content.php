<?php
// This definitely runs after notes.
$starter_content_term = get_term_by( 'slug', 'starter-content', 'notebook' );
$now_term = get_term_by( 'slug', 'now', 'notebook' );

$todos = $this->list( [], 'starter-content' );
foreach ( $todos as $note ) {
	wp_delete_post( $note->ID );
}


$this->create( array(
	'post_title' => 'Regular TODO in NOW with action',
	'post_excerpt' => 'This TODO has an action.',
	'meta_input' => array(
		'url' => 'tel://1234567890',
	),
	'tax_input' => array(
		'notebook' => array( $starter_content_term->term_id, $now_term->term_id ),
	),
) );

$blocking = $this->create( array(
	'post_title' => 'Blocking todo',
	'post_excerpt' => 'This is blocking another todo.',
	'tax_input' => array(
		'notebook' => array( $starter_content_term->term_id, $now_term->term_id ),
	),
) );

$blocked = $this->create( array(
	'post_title' => 'Blocked todo',
	'post_excerpt' => 'This is blocked by the blocking todo. It will move to NOW when the blocking todo is completed.',
	'meta_input' => array(
		'pos_blocked_by' => $blocking,
		'pos_blocked_pending_term' => 'now',
	),
	'tax_input' => array(
		'notebook' => array( $starter_content_term->term_id ),
	),
) );

$tomorrow = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
$this->create( array(
	'post_title' => 'Scheduled TODO',
	'post_excerpt' => 'This is a scheduled todo. It will move to NOW on ' . $tomorrow,
	'post_date' => $tomorrow,
	'meta_input' => array(
		'pos_blocked_pending_term' => 'now',
	),
	'tax_input' => array(
		'notebook' => array( $starter_content_term->term_id ),
	),
) );

$this->create( array(
	'post_title' => 'Recuring TODO',
	'post_excerpt' => 'This is a recurring TODO. It will automatically schedule a copy of itself 2 days after completion.',
	'meta_input' => array(
		'pos_blocked_pending_term' => 'now',
		'pos_recurring_days' => 2,
	),
	'tax_input' => array(
		'notebook' => array( $starter_content_term->term_id, $now_term->term_id ),
	),
) );
