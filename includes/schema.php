<?php
/**
 * Event structured data (schema.org).
 *
 * A class listing is the one thing on an events site that search engines can
 * render as a rich result: "Event" entities get date, time and venue shown
 * directly in results, which for a studio running recurring classes is the
 * highest-value markup on the site.
 *
 * Emits one Event per single event page, built from the same stored meta the
 * recurrence engine uses — so the schema cannot drift from what the page shows.
 * Recurring events carry an `eventSchedule` (weekly/monthly) alongside the next
 * occurrence's start date, matching Google's guidance for repeating events.
 *
 * Venue address: the plugin has no address fields of its own, so when Curly
 * Site Tools' LocalBusiness schema is configured its address is reused as the
 * venue's address. Anything else falls back to the location name alone. Use the
 * `custom_events_event_schema` filter to override or suppress the output.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a flexible end-time string ("10:30am", "6:30 pm", "18:30") to HH:MM.
 *
 * @param string $text Stored `_event_end_time_text` value.
 * @return string|null HH:MM, or null when the text is not a time.
 */
function custom_events_schema_parse_end_time( $text ) {
	if ( ! preg_match( '/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', (string) $text, $matches ) ) {
		return null;
	}

	$hour   = (int) $matches[1];
	$minute = isset( $matches[2] ) && '' !== $matches[2] ? (int) $matches[2] : 0;
	$meriam = isset( $matches[3] ) ? strtolower( $matches[3] ) : '';

	if ( '' !== $meriam ) {
		$hour = $hour % 12;
		if ( 'pm' === $meriam ) {
			$hour += 12;
		}
	}

	if ( $hour > 23 || $minute > 59 ) {
		return null;
	}

	return sprintf( '%02d:%02d', $hour, $minute );
}

/**
 * The venue address, reused from Curly Site Tools when it is configured.
 *
 * @return array PostalAddress entity, or an empty array.
 */
function custom_events_schema_venue_address() {
	$option = static function ( $name ) {
		return function_exists( 'curly_site_tools_schema_option' ) ? curly_site_tools_schema_option( $name ) : '';
	};

	$address = array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $option( 'CURLY_SITE_TOOLS_SCHEMA_STREET' ),
			'addressLocality' => $option( 'CURLY_SITE_TOOLS_SCHEMA_CITY' ),
			'addressRegion'   => $option( 'CURLY_SITE_TOOLS_SCHEMA_REGION' ),
			'postalCode'      => $option( 'CURLY_SITE_TOOLS_SCHEMA_POSTAL' ),
			'addressCountry'  => $option( 'CURLY_SITE_TOOLS_SCHEMA_COUNTRY' ),
		)
	);

	// Only an address with a street is worth emitting.
	return empty( $address['streetAddress'] ) ? array() : $address;
}

/**
 * Build the recurrence Schedule entity for a repeating event.
 *
 * @param int    $post_id   Event ID.
 * @param string $start_iso ISO start date (Y-m-d) of the series.
 * @param string $end_iso   ISO end date (Y-m-d) of the series, or ''.
 * @param string $start     Start time HH:MM, or ''.
 * @param string $end       End time HH:MM, or ''.
 * @return array|null Schedule entity, or null when the event does not repeat.
 */
function custom_events_schema_schedule( $post_id, $start_iso, $end_iso, $start, $end ) {
	$rec_type = get_post_meta( $post_id, '_event_rec_type', true ) ?: 'none';
	if ( 'none' === $rec_type ) {
		return null;
	}

	$schedule = array(
		'@type'           => 'Schedule',
		'startDate'       => $start_iso,
	);

	if ( 'weekly' === $rec_type ) {
		$days   = get_post_meta( $post_id, '_event_weekly_days', true ) ?: array();
		$names  = array(
			'sun' => 'Sunday',
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
		);
		$by_day = array();
		foreach ( (array) $days as $day ) {
			$url = function_exists( 'curly_site_tools_schema_day_url' ) ? curly_site_tools_schema_day_url( $day ) : '';
			if ( '' === $url ) {
				$key = substr( strtolower( (string) $day ), 0, 3 );
				$url = isset( $names[ $key ] ) ? 'https://schema.org/' . $names[ $key ] : '';
			}
			if ( $url ) {
				$by_day[] = $url;
			}
		}
		if ( ! $by_day ) {
			return null;
		}
		$schedule['repeatFrequency'] = 'P1W';
		$schedule['byDay']           = array_values( array_unique( $by_day ) );
	} elseif ( 'monthly' === $rec_type ) {
		$schedule['repeatFrequency'] = 'P1M';
		$monthly_type                = get_post_meta( $post_id, '_event_monthly_type', true );
		if ( 'day_num' === $monthly_type ) {
			$day_num = (int) get_post_meta( $post_id, '_event_monthly_day_num', true );
			if ( $day_num > 0 ) {
				$schedule['byMonthDay'] = $day_num;
			}
		}
	} else {
		return null;
	}

	// The series' own end date bounds the schedule; without one it is open-ended.
	if ( '' !== $end_iso ) {
		$schedule['endDate'] = $end_iso;
	}
	if ( '' !== $start ) {
		$schedule['startTime'] = $start . ':00';
	}
	if ( '' !== $end ) {
		$schedule['endTime'] = $end . ':00';
	}

	return $schedule;
}

/**
 * Build the Event entity for a single event post.
 *
 * @param int $post_id Event post ID.
 * @return array|null Event entity, or null when the event cannot be described.
 */
function custom_events_schema_entity( $post_id ) {
	$start_date = get_post_meta( $post_id, '_event_start_date', true );
	if ( ! $start_date ) {
		return null;
	}

	$permalink = get_permalink( $post_id );
	$timezone  = wp_timezone();

	// Prefer the next upcoming occurrence, so the start date in the results is
	// the class a visitor can still attend; fall back to the series start.
	$occurrence_date = $start_date;
	if ( function_exists( 'custom_events_get_next_occurrence' ) ) {
		$next = custom_events_get_next_occurrence( $post_id );
		if ( is_array( $next ) && ! empty( $next['date'] ) ) {
			$occurrence_date = $next['date'];
		}
	}

	$start_time = (string) get_post_meta( $post_id, '_event_start_time', true );
	$end_time   = custom_events_schema_parse_end_time( get_post_meta( $post_id, '_event_end_time_text', true ) );

	$start = new DateTime( $start_date, $timezone );
	$event = array(
		'@type'               => 'Event',
		'@id'                 => $permalink . '#event',
		'name'                => get_the_title( $post_id ),
		'url'                 => $permalink,
		'mainEntityOfPage'    => array(
			'@type' => 'WebPage',
			'@id'   => $permalink,
		),
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
	);

	// Start: include the time only when the event actually has one.
	if ( '' !== $start_time ) {
		$start = new DateTime( $occurrence_date . ' ' . $start_time, $timezone );
	}
	$event['startDate'] = ( '' !== $start_time ) ? $start->format( 'c' ) : $start->format( 'Y-m-d' );

	// End: derive from the free-text end time, and only when it lands after the
	// start (the field is prose, so it is often unusable — that is fine).
	if ( null !== $end_time ) {
		$end = new DateTime( $start->format( 'Y-m-d' ) . ' ' . $end_time, $timezone );
		if ( $end > $start ) {
			$event['endDate'] = $end->format( 'c' );
		}
	}

	// Venue.
	$location_name = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, '_event_location', true ) ) );
	if ( '' !== $location_name ) {
		$place = array(
			'@type' => 'Place',
			'name'  => $location_name,
		);
		$address = custom_events_schema_venue_address();
		if ( $address ) {
			$place['address'] = $address;
		}
		$event['location'] = $place;
	}

	// Description: the excerpt when the editor wrote one, else the opening of
	// the event copy.
	$description = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
	if ( '' === $description ) {
		$description = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
		$description = trim( preg_replace( '/\s+/', ' ', $description ) );
		if ( strlen( $description ) > 300 ) {
			$description = rtrim( substr( $description, 0, 297 ) ) . '…';
		}
	}
	if ( '' !== $description ) {
		$event['description'] = $description;
	}

	// Image: the featured image, when the event has one.
	$image_id = (int) get_post_thumbnail_id( $post_id );
	if ( $image_id ) {
		$image = wp_get_attachment_image_src( $image_id, 'full' );
		if ( $image ) {
			$event['image'] = $image[0];
		}
	}

	$series_end = (string) get_post_meta( $post_id, '_event_end_date', true );
	$schedule   = custom_events_schema_schedule( $post_id, $start_date, $series_end, $start_time, (string) $end_time );
	if ( $schedule ) {
		$event['eventSchedule'] = $schedule;
	}

	$event['organizer'] = array(
		'@type' => 'Organization',
		'@id'   => home_url( '/' ) . '#/schema/Organization',
		'name'  => get_bloginfo( 'name' ),
	);

	/**
	 * Filter the Event entity before it is printed.
	 *
	 * Return null to suppress the schema for this event entirely.
	 *
	 * @param array|null $event   Schema.org Event entity.
	 * @param int        $post_id Event post ID.
	 */
	return apply_filters( 'custom_events_event_schema', $event, $post_id );
}

/**
 * Print the Event entity on single event pages.
 */
function custom_events_print_event_schema() {
	if ( ! is_singular( 'events' ) ) {
		return;
	}

	if ( ! get_option( CURLY_EVENTS_OPTION_SCHEMA_ENABLED, 1 ) ) {
		return;
	}

	$entity = custom_events_schema_entity( get_queried_object_id() );
	if ( ! $entity ) {
		return;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array( $entity ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	);
}
add_action( 'wp_head', 'custom_events_print_event_schema', 20 );
