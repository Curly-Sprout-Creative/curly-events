<?php
/**
 * Curly Events → Oxygen 6 Post Loop bridge.
 *
 * Expands post_type=events archive/loop queries into one virtual post per
 * occurrence and serves reserved occurrence meta keys (_event_occurrence_*) for
 * the Event Card. Opt out per-query with 'virtual_events' => 'off'.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expand events queries into one virtual post per occurrence.
 *
 * @param WP_Post[] $posts Posts.
 * @param WP_Query  $query The query.
 * @return WP_Post[]
 */
function custom_events_expand_virtual_occurrences( $posts, $query ) {
	if ( is_admin() ) {
		return $posts;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return $posts;
	}
	if ( empty( $posts ) || ! is_object( $posts[0] ) ) {
		return $posts;
	}
	if ( $query->is_single() ) {
		return $posts;
	}
	if ( $query->is_feed() ) {
		return $posts;
	}

	$pt        = $query->get( 'post_type' );
	$is_events = ( 'events' === $pt ) || ( is_array( $pt ) && in_array( 'events', $pt, true ) );
	if ( ! $is_events ) {
		return $posts;
	}
	if ( 'off' === $query->get( 'virtual_events' ) ) {
		return $posts;
	}

	$occurrences = get_virtual_event_occurrences(
		current_time( 'Y-m-d' ),
		custom_events_virtual_horizon_end()
	);
	if ( empty( $occurrences ) ) {
		return $posts;
	}

	$by_id = array();
	foreach ( $occurrences as $o ) {
		$by_id[ $o['post_id'] ][] = $o;
	}

	$expanded = array();
	foreach ( $posts as $event ) {
		if ( ! empty( $by_id[ $event->ID ] ) ) {
			foreach ( $by_id[ $event->ID ] as $o ) {
				$c = clone $event;
				$c->post_date    = $o['date'] . ' ' . ( $o['start_time'] ?: '00:00:00' );
				$c->post_date_gmt = $c->post_date;
				$c->virtual_occurrence = $o;
				$expanded[] = $c;
			}
		} else {
			$expanded[] = $event;
		}
	}

	if ( count( $expanded ) === count( $posts ) ) {
		return $posts;
	}

	usort(
		$expanded,
		function ( $a, $b ) {
			return strcmp( $a->post_date, $b->post_date );
		}
	);

	// Publish pagination metadata + slice by page. pre_get_posts forced
	// posts_per_page=-1 (so all real events are fetched) and saved the
	// configured limit as virtual_per_page.
	$total = count( $expanded );
	$query->found_posts = $total;

	$per_page = (int) $query->get( 'virtual_per_page' );
	if ( $per_page <= 0 ) {
		$per_page = (int) $query->get( 'posts_per_page' );
	}
	if ( $per_page > 0 ) {
		$query->max_num_pages = (int) ceil( $total / $per_page );
		$paged                = max( 1, (int) $query->get( 'paged' ) );
		$expanded             = array_slice( $expanded, ( $paged - 1 ) * $per_page, $per_page );
		// Restore the configured limit so O6's loop pagination computes page
		// count from found_posts / posts_per_page (the SQL already ran with -1).
		$query->set( 'posts_per_page', $per_page );
	} else {
		$query->max_num_pages = 0;
	}

	return $expanded;
}
add_filter( 'the_posts', 'custom_events_expand_virtual_occurrences', 20, 2 );

/**
 * Fetch all real event posts (no per-page limit) so the_posts can expand every
 * occurrence and paginate manually.
 *
 * @param WP_Query $query The query.
 */
function custom_events_prepare_virtual_query( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( $query->is_single() ) {
		return;
	}
	if ( $query->is_feed() ) {
		return;
	}

	$pt        = $query->get( 'post_type' );
	$is_events = ( 'events' === $pt ) || ( is_array( $pt ) && in_array( 'events', $pt, true ) );
	if ( ! $is_events ) {
		return;
	}
	if ( 'off' === $query->get( 'virtual_events' ) ) {
		return;
	}

	$query->set( 'virtual_per_page', $query->get( 'posts_per_page' ) );
	// Events archive limit (3×3 grid). Secondary loop queries inherit this via
	// the main query's restored posts_per_page.
	if ( $query->is_main_query() ) {
		$query->set( 'posts_per_page', CUSTOM_EVENTS_VIRTUAL_PER_PAGE );
	}
	$query->set( 'posts_per_page', -1 );
}
add_action( 'pre_get_posts', 'custom_events_prepare_virtual_query', 20 );

/**
 * Serve reserved occurrence meta keys for the Event Card.
 *
 * On virtual clones the per-occurrence values are served; on real event posts
 * they fall back to stored meta so the same card also works on single pages.
 *
 * @param mixed  $value     Current value.
 * @param int    $object_id Post ID.
 * @param string $meta_key  Meta key.
 * @param bool   $single    Whether a single value is requested.
 * @return mixed
 */
function custom_events_virtual_meta( $value, $object_id, $meta_key, $single ) {
	if ( ! is_string( $meta_key ) ) {
		return $value;
	}

	$reserved = array(
		'_event_occurrence_date',
		'_event_occurrence_time',
		'_event_occurrence_end_time',
		'_event_occurrence_location',
		'_event_occurrence_time_range',
		'_event_next_occurrence_date',
		'_event_recurrence_text',
	);
	if ( ! in_array( $meta_key, $reserved, true ) ) {
		return $value;
	}

	global $post;
	$clone = ( $post && (int) $post->ID === (int) $object_id && ! empty( $post->virtual_occurrence ) ) ? $post->virtual_occurrence : null;

	$start_time = $clone ? $clone['start_time'] : get_post_meta( $object_id, '_event_start_time', true );
	$end_time   = $clone ? $clone['end_time_text'] : get_post_meta( $object_id, '_event_end_time_text', true );

	$formatted_start = ( ! empty( $start_time ) && '00:00' !== $start_time ) ? date( 'g:i A', strtotime( $start_time ) ) : '';

	if ( $clone ) {
		$next_date = custom_events_format_date( $clone['date'] );
	} else {
		$next      = custom_events_get_next_occurrence( $object_id );
		$next_date = custom_events_format_date( $next ? $next['date'] : get_post_meta( $object_id, '_event_start_date', true ) );
	}

	$map = array(
		'_event_occurrence_date'       => custom_events_format_date( $clone ? $clone['date'] : get_post_meta( $object_id, '_event_start_date', true ) ),
		'_event_occurrence_time'       => $formatted_start,
		'_event_occurrence_end_time'   => $end_time,
		'_event_occurrence_location'   => wp_strip_all_tags( $clone ? $clone['location'] : get_post_meta( $object_id, '_event_location', true ) ),
		'_event_occurrence_time_range' => custom_events_format_time_range( $start_time, $end_time ),
		'_event_next_occurrence_date'  => $next_date,
		'_event_recurrence_text'       => custom_events_recurrence_text( $object_id ),
	);

	return array( $map[ $meta_key ] );
}
add_filter( 'get_post_metadata', 'custom_events_virtual_meta', 10, 4 );

/**
 * "September 6, 2026" from "2026-09-06"; "" for empty input.
 *
 * @param string $ymd Date Y-m-d.
 * @return string
 */
function custom_events_format_date( $ymd ) {
	if ( empty( $ymd ) ) {
		return '';
	}
	$t = strtotime( $ymd );
	return $t ? date( 'F j, Y', $t ) : $ymd;
}

/**
 * "1:15 PM", "9:00 PM", "1:15 PM – 9:00 PM", "1:15 PM – Until Late", or "".
 *
 * @param string $start_time    HH:MM.
 * @param string $end_time_text Flexible text.
 * @return string
 */
function custom_events_format_time_range( $start_time, $end_time_text ) {
	$parts = array();
	if ( ! empty( $start_time ) && '00:00' !== $start_time ) {
		$parts[] = date( 'g:i A', strtotime( $start_time ) );
	}
	$end = trim( (string) $end_time_text );
	if ( '' !== $end ) {
		$parts[] = $end;
	}
	return implode( ' – ', $parts );
}

/**
 * First upcoming occurrence for a post in [today, horizon], or null.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function custom_events_get_next_occurrence( $post_id ) {
	$occurrences = get_virtual_event_occurrences( current_time( 'Y-m-d' ), custom_events_virtual_horizon_end() );
	foreach ( $occurrences as $o ) {
		if ( (int) $o['post_id'] === (int) $post_id ) {
			return $o;
		}
	}
	return null;
}

/**
 * Human-readable schedule: "Weekly on Monday, Wednesday and Friday",
 * "Monthly on the 16th", "Monthly on the second Tuesday", or "" for none.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function custom_events_recurrence_text( $post_id ) {
	$rec_type = get_post_meta( $post_id, '_event_rec_type', true ) ?: 'none';
	if ( 'none' === $rec_type ) {
		return '';
	}

	$day_names = array( 'sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday' );
	$day_order = array( 'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6 );

	if ( 'weekly' === $rec_type ) {
		$days = get_post_meta( $post_id, '_event_weekly_days', true ) ?: array();
		if ( empty( $days ) ) {
			return 'Weekly';
		}
		$picked = array();
		foreach ( $days as $d ) {
			if ( isset( $day_names[ $d ] ) ) {
				$picked[ $day_order[ $d ] ] = $day_names[ $d ];
			}
		}
		ksort( $picked );
		return 'Weekly on ' . custom_events_list_with_and( array_values( $picked ) );
	}

	if ( 'monthly' === $rec_type ) {
		if ( 'day_num' === get_post_meta( $post_id, '_event_monthly_type', true ) ) {
			$day_num = (int) get_post_meta( $post_id, '_event_monthly_day_num', true );
			if ( $day_num < 1 ) {
				$day_num = 1;
			}
			return 'Monthly on the ' . custom_events_ordinal( $day_num );
		}
		$pos        = get_post_meta( $post_id, '_event_monthly_nth_pos', true ) ?: 'first';
		$day        = get_post_meta( $post_id, '_event_monthly_nth_day', true ) ?: 'sun';
		$pos_names  = array( 'first' => 'first', 'second' => 'second', 'third' => 'third', 'fourth' => 'fourth', 'last' => 'last' );
		return 'Monthly on the ' . ( isset( $pos_names[ $pos ] ) ? $pos_names[ $pos ] : 'first' ) . ' ' . ( isset( $day_names[ $day ] ) ? $day_names[ $day ] : 'Sunday' );
	}

	return '';
}

/**
 * "Monday, Wednesday and Friday" (no Oxford comma; "and" before the last).
 *
 * @param string[] $items Items.
 * @return string
 */
function custom_events_list_with_and( $items ) {
	if ( count( $items ) === 1 ) {
		return $items[0];
	}
	if ( count( $items ) === 2 ) {
		return $items[0] . ' and ' . $items[1];
	}
	return implode( ', ', array_slice( $items, 0, -1 ) ) . ' and ' . end( $items );
}

/**
 * 1 -> "1st", 2 -> "2nd", 3 -> "3rd", 11 -> "11th", 21 -> "21st".
 *
 * @param int $n Number.
 * @return string
 */
function custom_events_ordinal( $n ) {
	if ( in_array( ( $n % 100 ), array( 11, 12, 13 ), true ) ) {
		return $n . 'th';
	}
	switch ( $n % 10 ) {
		case 1:
			return $n . 'st';
		case 2:
			return $n . 'nd';
		case 3:
			return $n . 'rd';
	}
	return $n . 'th';
}