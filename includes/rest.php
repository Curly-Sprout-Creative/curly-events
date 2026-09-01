<?php
/**
 * Curly Events: calendar REST endpoint + front-end asset enqueue.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public REST endpoint serving one month of calendar HTML for the Events Index
 * widget. The page renders no calendar until the user opens it; the toggle
 * lazy-loads it and prev/next swap months in place (no reload). The default
 * month is the month of the first event card on the current page.
 *
 * Mitigation: the expensive part (occurrence computation) is transient-cached,
 * so repeated hits are cheap; month/year are clamped before use.
 */
function custom_events_calendar_rest_route() {
	register_rest_route(
		'curly-events/v1',
		'/calendar',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'custom_events_calendar_rest_callback',
		)
	);
}
add_action( 'rest_api_init', 'custom_events_calendar_rest_route' );

/**
 * REST callback for the calendar widget.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function custom_events_calendar_rest_callback( $request ) {
	$month = (int) $request->get_param( 'm' );
	$year  = (int) $request->get_param( 'y' );
	if ( ! $month ) {
		$month = (int) date( 'n' );
	}
	if ( ! $year ) {
		$year = (int) date( 'Y' );
	}
	return rest_ensure_response(
		array(
			'html' => custom_events_render_calendar( $month, $year ),
		)
	);
}

/**
 * Enqueue the events stylesheet + script when event content may be on the page.
 *
 * Idempotent; also called from the shortcode callbacks to cover widget/sidebar
 * usage and pages where the shortcode is not in the main query.
 */
function custom_events_maybe_enqueue_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_enqueue_style(
		'curly-events',
		CURLY_EVENTS_URL . 'assets/css/events.css',
		array(),
		CURLY_EVENTS_VERSION
	);

	wp_enqueue_script(
		'curly-events',
		CURLY_EVENTS_URL . 'assets/js/events.js',
		array(),
		CURLY_EVENTS_VERSION,
		true
	);

	wp_localize_script(
		'curly-events',
		'curlyEvents',
		array(
			'restUrl' => esc_url_raw( rest_url( 'curly-events/v1/calendar' ) ),
		)
	);
}

/**
 * Front-end page hook: enqueue on events archives, single events, or any page
 * whose content uses an events shortcode.
 */
function custom_events_enqueue_on_query() {
	if ( is_post_type_archive( 'events' ) || is_singular( 'events' ) ) {
		custom_events_maybe_enqueue_assets();
		return;
	}

	if ( is_singular() ) {
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'event_list' ) ||
			$post && has_shortcode( $post->post_content, 'event_calendar' ) ||
			$post && has_shortcode( $post->post_content, 'event_pagination' ) ) {
			custom_events_maybe_enqueue_assets();
		}
	}
}
add_action( 'wp_enqueue_scripts', 'custom_events_enqueue_on_query' );