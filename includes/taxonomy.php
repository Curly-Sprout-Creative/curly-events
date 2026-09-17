<?php
/**
 * Events category taxonomy.
 *
 * A dedicated taxonomy (not core `category`, which is already used by blog
 * posts) so events can be grouped for topic pages - e.g. "Physical Culture"
 * and "Manual Therapy" - and queried in an Oxygen 6 Post Loop.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'custom_events_register_taxonomy', 11 );

/**
 * Register the "event_category" taxonomy for the events post type.
 *
 * Priority 11 so the CPT (registered on init 10) exists first. Rewrite is
 * disabled: the taxonomy is for admin tagging + loop queries, not front-end
 * term archives, which also avoids needing a rewrite flush on plugin update.
 */
function custom_events_register_taxonomy() {
	$labels = array(
		'name'              => 'Event Categories',
		'singular_name'     => 'Event Category',
		'search_items'      => 'Search Event Categories',
		'all_items'         => 'All Event Categories',
		'parent_item'       => 'Parent Event Category',
		'parent_item_colon' => 'Parent Event Category:',
		'edit_item'         => 'Edit Event Category',
		'update_item'       => 'Update Event Category',
		'add_new_item'      => 'Add New Event Category',
		'new_item_name'     => 'New Event Category Name',
		'menu_name'         => 'Event Categories',
	);

	register_taxonomy(
		'event_category',
		array( 'events' ),
		array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'rewrite'           => false,
			'query_var'         => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		)
	);
}
