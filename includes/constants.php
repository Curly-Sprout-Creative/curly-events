<?php
/**
 * Curly Events constants and shared helpers.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Virtual-occurrence horizon for the Post Loop bridge + pagination. The events
// loop generates occurrences from today out to CUSTOM_EVENTS_VIRTUAL_HORIZON_YEARS
// years ahead, and the same horizon is the hard cap in get_virtual_event_occurrences()
// for every caller (list, calendar, loop). CUSTOM_EVENTS_VIRTUAL_PER_PAGE limits
// the archive grid per page.
define( 'CUSTOM_EVENTS_VIRTUAL_PER_PAGE', 18 );
define( 'CUSTOM_EVENTS_VIRTUAL_HORIZON_YEARS', 3 );

// Occurrence cache TTL. The main loop window (today → horizon) rotates daily on
// its own; a short TTL keeps calendar/list windows fresh after edits without a
// save hook race. Bumped by 1s on event save to force refresh.
define( 'CUSTOM_EVENTS_OCCURRENCE_TTL', 12 * HOUR_IN_SECONDS );

/**
 * End date (Y-m-d) of the virtual-occurrence horizon.
 *
 * @return string
 */
function custom_events_virtual_horizon_end() {
	return date( 'Y-m-d', strtotime( '+' . CUSTOM_EVENTS_VIRTUAL_HORIZON_YEARS . ' years', current_time( 'timestamp' ) ) );
}

/**
 * Cache key for a given occurrence window.
 *
 * @param string $range_start_date Y-m-d.
 * @param string $range_end_date   Y-m-d.
 * @return string
 */
function custom_events_occurrence_cache_key( $range_start_date, $range_end_date ) {
	return 'curly_events_occ_' . md5( $range_start_date . '|' . $range_end_date );
}

/**
 * Invalidate every cached occurrence window.
 *
 * Hooked to event create/save/trash/untrash/delete so list, calendar, loop and
 * REST responses never serve stale data.
 */
function custom_events_flush_occurrence_cache() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_curly_events_occ_%'
		    OR option_name LIKE '_transient_timeout_curly_events_occ_%'"
	);
}

add_action( 'save_post_events', 'custom_events_flush_occurrence_cache' );
add_action( 'wp_trash_post', 'custom_events_flush_occurrence_cache' );
add_action( 'untrash_post', 'custom_events_flush_occurrence_cache' );
add_action( 'deleted_post', 'custom_events_flush_occurrence_cache' );