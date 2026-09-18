<?php
/**
 * Curly Events shortcodes: [event_list], [event_calendar], [event_pagination].
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'event_list', 'custom_events_list_shortcode' );
add_shortcode( 'event_calendar', 'custom_events_calendar_shortcode' );
add_shortcode( 'event_pagination', 'custom_events_pagination_shortcode' );

/**
 * [event_list limit="5"] — upcoming events, with a recent-past fallback.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function custom_events_list_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 5 ), $atts, 'event_list' );

	custom_events_maybe_enqueue_assets();

	$today             = date( 'Y-m-d' );
	$future_limit_date = date( 'Y-m-d', strtotime( '+90 days' ) );

	$occurrences = get_virtual_event_occurrences( $today, $future_limit_date );

	ob_start();
	?>
	<div class="evt-list-wrapper">
	<?php
	if ( ! empty( $occurrences ) ) {
		$occurrences = array_slice( $occurrences, 0, (int) $atts['limit'] );
		foreach ( $occurrences as $occ ) {
			render_single_event_item( $occ );
		}
	} else {
		$past_limit_date = date( 'Y-m-d', strtotime( '-180 days' ) );
		$yesterday       = date( 'Y-m-d', strtotime( '-1 day' ) );
		$past_occurrences = get_virtual_event_occurrences( $past_limit_date, $yesterday );

		echo '<h3 class="evt-fallback-heading">Recent Past Events</h3>';
		if ( ! empty( $past_occurrences ) ) {
			$past_occurrences = array_slice( array_reverse( $past_occurrences ), 0, 3 );
			foreach ( $past_occurrences as $occ ) {
				render_single_event_item( $occ );
			}
		} else {
			echo '<p>No recent events found.</p>';
		}
	}
	echo '</div>';

	return ob_get_clean();
}

/**
 * Render one list item from an occurrence array.
 *
 * @param array $occ Occurrence.
 */
function render_single_event_item( $occ ) {
	$formatted_time = '';
	if ( ! empty( $occ['start_time'] ) && '00:00' !== $occ['start_time'] ) {
		$formatted_time = date( 'g:i A', strtotime( $occ['start_time'] ) );
		if ( ! empty( $occ['end_time_text'] ) ) {
			$formatted_time .= ' - ' . esc_html( $occ['end_time_text'] );
		}
	} elseif ( ! empty( $occ['end_time_text'] ) ) {
		$formatted_time = esc_html( $occ['end_time_text'] );
	}
	?>
	<div class="evt-item">
		<h4><a href="<?php echo esc_url( $occ['permalink'] ); ?>"><?php echo esc_html( $occ['title'] ); ?></a></h4>
		<div class="evt-meta-line">
			<strong>Date:</strong> <?php echo date( 'F j, Y', strtotime( $occ['date'] ) ); ?>
			<?php if ( ! empty( $formatted_time ) ) : ?> | <strong>Time:</strong> <?php echo $formatted_time; ?><?php endif; ?>
		</div>
		<?php if ( ! empty( $occ['location'] ) ) : ?>
			<div class="evt-location-box">
				<strong>Location:</strong>
				<div><?php echo wp_kses_post( $occ['location'] ); ?></div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * [event_calendar] — monthly grid for the current month (or ?cal_m/?cal_y).
 *
 * @return string
 */
function custom_events_calendar_shortcode() {
	$req_month = isset( $_GET['cal_m'] ) ? (int) $_GET['cal_m'] : (int) date( 'n' );
	$req_year  = isset( $_GET['cal_y'] ) ? (int) $_GET['cal_y'] : (int) date( 'Y' );
	return custom_events_render_calendar( $req_month, $req_year );
}

/**
 * Render the calendar grid for a given month/year.
 *
 * Also used by the REST endpoint (calendar AJAX widget). Prev/next links carry
 * cal-nav data attrs so the front-end can swap the month in place; the hrefs
 * still work standalone (no JS).
 *
 * @param int $req_month 1-12.
 * @param int $req_year  4-digit year.
 * @return string
 */
function custom_events_render_calendar( $req_month, $req_year ) {
	if ( $req_month < 1 ) {
		$req_month = 1;
	}
	if ( $req_month > 12 ) {
		$req_month = 12;
	}
	if ( $req_year < 2000 || $req_year > 2100 ) {
		$req_year = (int) date( 'Y' );
	}

	custom_events_maybe_enqueue_assets();

	$current_date      = new DateTime( "$req_year-$req_month-01" );
	$start_of_calendar = clone $current_date;
	$start_of_calendar->modify( 'first day of this month' )->modify( 'last sunday' );

	$end_of_calendar = clone $current_date;
	$end_of_calendar->modify( 'last day of this month' )->modify( 'next saturday' );

	$occurrences = get_virtual_event_occurrences(
		$start_of_calendar->format( 'Y-m-d' ),
		$end_of_calendar->format( 'Y-m-d' )
	);

	$events_by_date = array();
	foreach ( $occurrences as $occ ) {
		$events_by_date[ $occ['date'] ][] = $occ;
	}

	$prev_date = clone $current_date;
	$prev_date->modify( '-1 month' );
	$next_date = clone $current_date;
	$next_date->modify( '+1 month' );

	$calendar_base = get_post_type_archive_link( 'events' ) ?: home_url( '/events/' );
	$prev_url      = add_query_arg( array( 'cal_m' => $prev_date->format( 'n' ), 'cal_y' => $prev_date->format( 'Y' ) ), $calendar_base );
	$next_url      = add_query_arg( array( 'cal_m' => $next_date->format( 'n' ), 'cal_y' => $next_date->format( 'Y' ) ), $calendar_base );

	$notice = custom_events_render_notice( $req_month, $req_year );
	$agenda = custom_events_render_agenda( $req_month, $req_year );

	ob_start();
	?>
	<div class="evt-cal-wrapper">
		<div class="evt-cal-header">
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $prev_url ); ?>" data-cal-m="<?php echo $prev_date->format( 'n' ); ?>" data-cal-y="<?php echo $prev_date->format( 'Y' ); ?>">&laquo; Previous</a>
			<h2><?php echo $current_date->format( 'F Y' ); ?></h2>
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $next_url ); ?>" data-cal-m="<?php echo $next_date->format( 'n' ); ?>" data-cal-y="<?php echo $next_date->format( 'Y' ); ?>">Next &raquo;</a>
		</div>

		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="evt-cal-grid">
			<div class="evt-cal-day-head">Sun</div>
			<div class="evt-cal-day-head">Mon</div>
			<div class="evt-cal-day-head">Tue</div>
			<div class="evt-cal-day-head">Wed</div>
			<div class="evt-cal-day-head">Thu</div>
			<div class="evt-cal-day-head">Fri</div>
			<div class="evt-cal-day-head">Sat</div>

			<?php
			$runner = clone $start_of_calendar;
			while ( $runner <= $end_of_calendar ) {
				$d_str         = $runner->format( 'Y-m-d' );
				$is_current    = $runner->format( 'n' ) == $req_month;
				$cell_class    = $is_current ? 'evt-cal-cell' : 'evt-cal-cell other-month';

				echo '<div class="' . esc_attr( $cell_class ) . '">';
				echo '<div class="evt-cal-date-num">' . esc_html( $runner->format( 'j' ) ) . '</div>';

				if ( isset( $events_by_date[ $d_str ] ) ) {
					foreach ( $events_by_date[ $d_str ] as $ev ) {
						$time_disp = ( ! empty( $ev['start_time'] ) && '00:00' !== $ev['start_time'] ) ? date( 'g:ia', strtotime( $ev['start_time'] ) ) . ' ' : '';
						echo '<a class="evt-cal-event-link" href="' . esc_url( $ev['permalink'] ) . '">';
						if ( $time_disp ) {
							echo '<span class="evt-cal-time">' . esc_html( $time_disp ) . '</span>';
						}
						echo esc_html( $ev['title'] );
						echo '</a>';
					}
				}

				echo '</div>';
				$runner->modify( '+1 day' );
			}
			?>
		</div>

		<?php echo $agenda; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Broad occurrence index used by the calendar notices.
 *
 * Covers the 180-day recent-past fallback through the 3-year horizon, so a
 * single cached occurrence window answers "does this month have events?" and
 * "what is the next / most recent event?". Memoized per request on top of the
 * recurrence engine's transient cache.
 *
 * @return array[] Occurrences sorted by date, then start time.
 */
function custom_events_occurrence_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}

	$start = date( 'Y-m-d', strtotime( '-180 days', current_time( 'timestamp' ) ) );
	$index = get_virtual_event_occurrences( $start, custom_events_virtual_horizon_end() );
	if ( ! is_array( $index ) ) {
		$index = array();
	}

	return $index;
}

/**
 * Contact page URL for the "no upcoming events" message.
 *
 * Uses the Events > Settings value when set, else /contact/ on the current
 * site. Overridable in code via the `curly_events_contact_url` filter.
 *
 * @return string
 */
function custom_events_contact_url() {
	$url = (string) get_option( CURLY_EVENTS_OPTION_CONTACT_URL, '' );
	if ( '' === trim( $url ) ) {
		$url = home_url( '/contact/' );
	}
	return apply_filters( 'curly_events_contact_url', $url );
}

/**
 * Anchor text for the "no upcoming events" contact link.
 *
 * Uses the Events > Settings value when set, else the default phrase.
 * Overridable in code via the `curly_events_contact_text` filter.
 *
 * @return string
 */
function custom_events_contact_text() {
	$text = (string) get_option( CURLY_EVENTS_OPTION_CONTACT_TEXT, CURLY_EVENTS_DEFAULT_CONTACT_TEXT );
	if ( '' === trim( $text ) ) {
		$text = CURLY_EVENTS_DEFAULT_CONTACT_TEXT;
	}
	return apply_filters( 'curly_events_contact_text', $text );
}

/**
 * First indexed occurrence strictly after a date, or null.
 *
 * @param string $date Y-m-d.
 * @return array|null
 */
function custom_events_next_occurrence_after( $date ) {
	foreach ( custom_events_occurrence_index() as $occ ) {
		if ( strcmp( $occ['date'], $date ) > 0 ) {
			return $occ;
		}
	}
	return null;
}

/**
 * Most recent indexed occurrence strictly before a date, or null.
 *
 * @param string $date Y-m-d.
 * @return array|null
 */
function custom_events_previous_occurrence_before( $date ) {
	$found = null;
	foreach ( custom_events_occurrence_index() as $occ ) {
		if ( strcmp( $occ['date'], $date ) < 0 ) {
			$found = $occ;
		}
	}
	return $found;
}

/**
 * Months that contain at least one indexed occurrence, keyed by Y-m.
 *
 * @return array[] Each entry: first (Y-m-d), last (Y-m-d), count.
 */
function custom_events_months_with_events() {
	$months = array();
	foreach ( custom_events_occurrence_index() as $occ ) {
		$mk = substr( $occ['date'], 0, 7 );
		if ( ! isset( $months[ $mk ] ) ) {
			$months[ $mk ] = array( 'first' => $occ['date'], 'last' => $occ['date'], 'count' => 0 );
		}
		if ( strcmp( $occ['date'], $months[ $mk ]['first'] ) < 0 ) {
			$months[ $mk ]['first'] = $occ['date'];
		}
		if ( strcmp( $occ['date'], $months[ $mk ]['last'] ) > 0 ) {
			$months[ $mk ]['last'] = $occ['date'];
		}
		$months[ $mk ]['count']++;
	}
	ksort( $months );
	return $months;
}

/**
 * Empty-month notice for the desktop grid.
 *
 * Returns '' when the displayed month has events. Otherwise points to the
 * nearest month ahead (preferred) or behind with events, or the final
 * no-upcoming-events message. Mirrors the Curly Linseed calendar notice.
 *
 * @param int $req_month 1-12.
 * @param int $req_year  4-digit year.
 * @return string
 */
function custom_events_render_notice( $req_month, $req_year ) {
	$month_key = sprintf( '%04d-%02d', $req_year, $req_month );
	$months    = custom_events_months_with_events();

	if ( ! empty( $months[ $month_key ]['count'] ) ) {
		return '';
	}

	$forward  = '';
	$backward = '';
	foreach ( array_keys( $months ) as $mk ) {
		if ( strcmp( $mk, $month_key ) > 0 && ( '' === $forward || strcmp( $mk, $forward ) < 0 ) ) {
			$forward = $mk;
		}
		if ( strcmp( $mk, $month_key ) < 0 && ( '' === $backward || strcmp( $mk, $backward ) > 0 ) ) {
			$backward = $mk;
		}
	}

	$month_label   = date( 'F Y', strtotime( $month_key . '-01' ) );
	$calendar_base = get_post_type_archive_link( 'events' ) ?: home_url( '/events/' );

	ob_start();
	?>
	<div class="evt-cal-notice">
		<?php if ( '' !== $forward ) :
			$target       = $months[ $forward ];
			$f_month      = (int) substr( $forward, 5, 2 );
			$f_year       = (int) substr( $forward, 0, 4 );
			$jump_url     = add_query_arg( array( 'cal_m' => $f_month, 'cal_y' => $f_year ), $calendar_base );
			$date_label   = custom_events_format_date( $target['first'] );
			$target_month = date( 'F', strtotime( $forward . '-01' ) );
			?>
			<p><?php echo esc_html( sprintf( 'No events scheduled in %s — the next events start %s.', $month_label, $date_label ) ); ?></p>
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $jump_url ); ?>" data-cal-m="<?php echo esc_attr( $f_month ); ?>" data-cal-y="<?php echo esc_attr( $f_year ); ?>">Jump to <?php echo esc_html( $target_month ); ?> &raquo;</a>
		<?php elseif ( '' !== $backward ) :
			$b_month        = (int) substr( $backward, 5, 2 );
			$b_year         = (int) substr( $backward, 0, 4 );
			$jump_url       = add_query_arg( array( 'cal_m' => $b_month, 'cal_y' => $b_year ), $calendar_base );
			$target_month   = date( 'F Y', strtotime( $backward . '-01' ) );
			?>
			<p><?php echo esc_html( sprintf( 'No events scheduled in %s — the most recent events were in %s.', $month_label, $target_month ) ); ?></p>
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $jump_url ); ?>" data-cal-m="<?php echo esc_attr( $b_month ); ?>" data-cal-y="<?php echo esc_attr( $b_year ); ?>">Jump back to <?php echo esc_html( date( 'F', strtotime( $backward . '-01' ) ) ); ?> &raquo;</a>
		<?php else : ?>
			<p>There are no upcoming events scheduled at the moment.</p>
			<p>Stay tuned — <a href="<?php echo esc_url( custom_events_contact_url() ); ?>"><?php echo esc_html( custom_events_contact_text() ); ?></a>.</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Mobile agenda: stacked, day-by-day list for the displayed month.
 *
 * The current month uses a rolling window of today through +30 days (so it
 * spills into the following month); any other month shows only that month's
 * days. Only days with events are rendered - never blank days.
 *
 * @param int $req_month 1-12.
 * @param int $req_year  4-digit year.
 * @return string
 */
function custom_events_render_agenda( $req_month, $req_year ) {
	$is_current = ( (int) $req_month === (int) current_time( 'n' ) && (int) $req_year === (int) current_time( 'Y' ) );

	if ( $is_current ) {
		$range_start = current_time( 'Y-m-d' );
		$end_date    = date_create( $range_start, wp_timezone() );
		$end_date->modify( '+30 days' );
		$range_end = $end_date->format( 'Y-m-d' );
	} else {
		$first       = new DateTime( sprintf( '%04d-%02d-01', $req_year, $req_month ) );
		$range_start = $first->format( 'Y-m-d' );
		$range_end   = $first->modify( 'last day of this month' )->format( 'Y-m-d' );
	}

	$occurrences = get_virtual_event_occurrences( $range_start, $range_end );

	$by_date = array();
	foreach ( $occurrences as $occ ) {
		$by_date[ $occ['date'] ][] = $occ;
	}

	ob_start();
	?>
	<div class="evt-cal-agenda">
		<?php if ( ! empty( $by_date ) ) : ?>
			<?php foreach ( $by_date as $d_str => $day_occurrences ) : ?>
				<div class="evt-agenda-day">
					<div class="evt-agenda-date"><?php echo esc_html( date( 'l, F j', strtotime( $d_str ) ) ); ?></div>
					<?php
					foreach ( $day_occurrences as $occ ) :
						$time = '';
						if ( ! empty( $occ['start_time'] ) && '00:00' !== $occ['start_time'] ) {
							$time = date( 'g:i A', strtotime( $occ['start_time'] ) );
						}
						if ( ! empty( $occ['end_time_text'] ) ) {
							$time = '' !== $time ? $time . ' – ' . $occ['end_time_text'] : $occ['end_time_text'];
						}
						?>
						<a class="evt-agenda-event" href="<?php echo esc_url( $occ['permalink'] ); ?>">
							<?php if ( '' !== $time ) : ?><span class="evt-agenda-time"><?php echo esc_html( $time ); ?></span><?php endif; ?>
							<span class="evt-agenda-title"><?php echo esc_html( $occ['title'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<?php echo custom_events_render_agenda_empty( $is_current, $req_month, $req_year, $range_start, $range_end ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Notice shown in the mobile agenda when its window has no events.
 *
 * Links to the next event after the window, else the most recent event before
 * it, else the final no-upcoming-events message.
 *
 * @param bool   $is_current  Whether the agenda is the rolling current window.
 * @param int    $req_month   Displayed month 1-12.
 * @param int    $req_year    Displayed 4-digit year.
 * @param string $range_start Window start Y-m-d.
 * @param string $range_end   Window end Y-m-d.
 * @return string
 */
function custom_events_render_agenda_empty( $is_current, $req_month, $req_year, $range_start, $range_end ) {
	if ( $is_current ) {
		$lead = 'No events in the next 30 days.';
	} else {
		$lead = sprintf( 'No events scheduled in %s.', date( 'F Y', strtotime( sprintf( '%04d-%02d-01', $req_year, $req_month ) ) ) );
	}

	ob_start();
	?>
	<div class="evt-cal-notice">
		<?php
		$next = custom_events_next_occurrence_after( $range_end );
		if ( $next ) :
			?>
			<p><?php echo esc_html( $lead . ' The next event is ' . $next['title'] . ' on ' . custom_events_format_date( $next['date'] ) . '.' ); ?></p>
			<a class="evt-cal-btn" href="<?php echo esc_url( $next['permalink'] ); ?>">View event &raquo;</a>
		<?php else :
			$recent = custom_events_previous_occurrence_before( $range_start );
			if ( $recent ) :
				?>
				<p><?php echo esc_html( $lead . ' The most recent event was ' . $recent['title'] . ' on ' . custom_events_format_date( $recent['date'] ) . '.' ); ?></p>
				<a class="evt-cal-btn" href="<?php echo esc_url( $recent['permalink'] ); ?>">View event &raquo;</a>
			<?php else : ?>
				<p>There are no upcoming events scheduled at the moment.</p>
				<p>Stay tuned — <a href="<?php echo esc_url( custom_events_contact_url() ); ?>"><?php echo esc_html( custom_events_contact_text() ); ?></a>.</p>
			<?php endif;
		endif;
		?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [event_pagination] — numbered page links for the virtual events loop.
 *
 * @return string
 */
function custom_events_pagination_shortcode() {
	$occurrences = get_virtual_event_occurrences(
		current_time( 'Y-m-d' ),
		custom_events_virtual_horizon_end()
	);
	$pages = (int) ceil( count( $occurrences ) / CUSTOM_EVENTS_VIRTUAL_PER_PAGE );
	if ( $pages <= 1 ) {
		return '';
	}

	custom_events_maybe_enqueue_assets();

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$big   = 999999999;
	$base  = str_replace( $big, '%#%', get_pagenum_link( $big ) );

	$links = paginate_links(
		array(
			'base'      => $base,
			'format'    => '?paged=%#%',
			'current'   => $paged,
			'total'     => $pages,
			'prev_text' => '&laquo; Prev',
			'next_text' => 'Next &raquo;',
			'mid_size'  => 2,
			'end_size'  => 1,
			'type'      => 'plain',
		)
	);
	if ( ! $links ) {
		return '';
	}

	ob_start();
	?>
	<nav class="evt-pagination"><?php echo $links; ?></nav>
	<?php
	return ob_get_clean();
}