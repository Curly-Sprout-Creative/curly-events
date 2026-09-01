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

	ob_start();
	?>
	<div class="evt-cal-wrapper">
		<div class="evt-cal-header">
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $prev_url ); ?>" data-cal-m="<?php echo $prev_date->format( 'n' ); ?>" data-cal-y="<?php echo $prev_date->format( 'Y' ); ?>">&laquo; Previous</a>
			<h2><?php echo $current_date->format( 'F Y' ); ?></h2>
			<a class="evt-cal-btn cal-nav" href="<?php echo esc_url( $next_url ); ?>" data-cal-m="<?php echo $next_date->format( 'n' ); ?>" data-cal-y="<?php echo $next_date->format( 'Y' ); ?>">Next &raquo;</a>
		</div>

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