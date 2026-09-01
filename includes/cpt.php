<?php
/**
 * Events custom post type registration.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'custom_events_register_cpt' );

/**
 * Register the "events" post type.
 */
function custom_events_register_cpt() {
	$labels = array(
		'name'          => 'Events',
		'singular_name' => 'Event',
		'menu_name'     => 'Events',
		'add_new'       => 'Add New Event',
		'add_new_item'  => 'Add New Event',
		'edit_item'     => 'Edit Event',
		'all_items'     => 'All Events',
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => true,
		'menu_icon'    => 'dashicons-calendar-alt',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest' => true,
	);

	register_post_type( 'events', $args );
}