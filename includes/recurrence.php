<?php
/**
 * Curly Events virtual recurrence engine.
 *
 * Derives on-demand occurrences for each event within a date window. Results
 * are cached in a transient keyed by the window so the archive/loop expansion
 * and calendar/list renderings are not recomputed on every request.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compute all virtual occurrences in [range_start_date, range_end_date].
 *
 * Hard-capped at the 3-year horizon. Public API — used by the loop bridge, the
 * shortcodes, and the REST endpoint.
 *
 * @param string $range_start_date Y-m-d.
 * @param string $range_end_date   Y-m-d.
 * @return array[] List of occurrence arrays (post_id, title, permalink, date,
 *                 start_time, end_time_text, location).
 */
function get_virtual_event_occurrences( $range_start_date, $range_end_date ) {
	// Hard cap: never generate virtual occurrences beyond the 3-year horizon.
	$hard_cap = custom_events_virtual_horizon_end();
	if ( strcmp( $range_end_date, $hard_cap ) > 0 ) {
		$range_end_date = $hard_cap;
	}
	if ( strcmp( $range_start_date, $range_end_date ) > 0 ) {
		return array();
	}

	// Serve from cache when available (the loop window rotates daily; a save
	// flushes the cache). Avoids the posts_per_page=-1 query + per-event meta
	// on every page load.
	$cache_key = custom_events_occurrence_cache_key( $range_start_date, $range_end_date );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$events = get_posts(
		array(
			'post_type'      => 'events',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		)
	);

	$occurrences = array();
	$win_start   = new DateTime( $range_start_date );
	$win_end     = new DateTime( $range_end_date );

	foreach ( $events as $event ) {
		$start_date_str = get_post_meta( $event->ID, '_event_start_date', true );
		if ( ! $start_date_str ) {
			continue;
		}

		$start_time    = get_post_meta( $event->ID, '_event_start_time', true ) ?: '00:00';
		$end_time_text = get_post_meta( $event->ID, '_event_end_time_text', true );
		$location      = get_post_meta( $event->ID, '_event_location', true );
		$rec_type      = get_post_meta( $event->ID, '_event_rec_type', true ) ?: 'none';
		$end_date_str  = get_post_meta( $event->ID, '_event_end_date', true );

		$raw_exceptions = get_post_meta( $event->ID, '_event_exceptions', true );
		$exceptions     = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $raw_exceptions ) ) ) );

		$base_start = new DateTime( $start_date_str );
		$rec_end    = $end_date_str ? new DateTime( $end_date_str ) : null;

		if ( 'none' === $rec_type ) {
			if ( $base_start >= $win_start && $base_start <= $win_end && ! in_array( $base_start->format( 'Y-m-d' ), $exceptions, true ) ) {
				$occurrences[] = create_occurrence_object( $event, $base_start->format( 'Y-m-d' ), $start_time, $end_time_text, $location );
			}
		} elseif ( 'weekly' === $rec_type ) {
			$weekly_days = get_post_meta( $event->ID, '_event_weekly_days', true ) ?: array();
			if ( ! empty( $weekly_days ) ) {
				$scan = clone $base_start;
				if ( $scan < $win_start ) {
					$scan = clone $win_start;
				}

				while ( $scan <= $win_end ) {
					if ( $rec_end && $scan > $rec_end ) {
						break;
					}
					$day_slug = strtolower( $scan->format( 'D' ) );
					$date_str = $scan->format( 'Y-m-d' );

					if ( $scan >= $base_start && in_array( $day_slug, $weekly_days, true ) && ! in_array( $date_str, $exceptions, true ) ) {
						$occurrences[] = create_occurrence_object( $event, $date_str, $start_time, $end_time_text, $location );
					}
					$scan->modify( '+1 day' );
				}
			}
		} elseif ( 'monthly' === $rec_type ) {
			$monthly_type = get_post_meta( $event->ID, '_event_monthly_type', true );
			$scan_month   = clone $win_start;
			$scan_month->modify( 'first day of this month' );

			while ( $scan_month <= $win_end ) {
				$target_date = null;

				if ( 'day_num' === $monthly_type ) {
					$day_num   = (int) get_post_meta( $event->ID, '_event_monthly_day_num', true );
					$days_in_m = (int) $scan_month->format( 't' );
					if ( $day_num <= $days_in_m ) {
						$target_date = new DateTime( $scan_month->format( 'Y-m-' ) . sprintf( '%02d', $day_num ) );
					}
				} else {
					$nth_pos   = get_post_meta( $event->ID, '_event_monthly_nth_pos', true );
					$nth_day   = get_post_meta( $event->ID, '_event_monthly_nth_day', true );
					$calc_str  = "{$nth_pos} {$nth_day} of " . $scan_month->format( 'F Y' );
					$target_date = new DateTime( $calc_str );
				}

				if ( $target_date && $target_date >= $base_start && $target_date >= $win_start && $target_date <= $win_end ) {
					if ( ! $rec_end || $target_date <= $rec_end ) {
						$d_str = $target_date->format( 'Y-m-d' );
						if ( ! in_array( $d_str, $exceptions, true ) ) {
							$occurrences[] = create_occurrence_object( $event, $d_str, $start_time, $end_time_text, $location );
						}
					}
				}
				$scan_month->modify( '+1 month' );
			}
		}
	}

	// Sort primarily by Date (ASC), secondarily by Start Time (ASC).
	usort(
		$occurrences,
		function ( $a, $b ) {
			$cmp = strcmp( $a['date'], $b['date'] );
			if ( 0 !== $cmp ) {
				return $cmp;
			}
			return strcmp( $a['start_time'], $b['start_time'] );
		}
	);

	set_transient( $cache_key, $occurrences, CUSTOM_EVENTS_OCCURRENCE_TTL );

	return $occurrences;
}

/**
 * Build the occurrence array for one event/date.
 *
 * @param WP_Post $post         Event post.
 * @param string  $date         Occurrence date Y-m-d.
 * @param string  $start_time   HH:MM.
 * @param string  $end_time_text Flexible end-time text.
 * @param string  $location     Raw location HTML.
 * @return array
 */
function create_occurrence_object( $post, $date, $start_time, $end_time_text, $location ) {
	return array(
		'post_id'       => $post->ID,
		'title'         => get_the_title( $post->ID ),
		'permalink'     => get_permalink( $post->ID ),
		'date'          => $date,
		'start_time'    => $start_time,
		'end_time_text' => $end_time_text,
		'location'      => $location,
	);
}