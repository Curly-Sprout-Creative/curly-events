<?php
/**
 * Plugin Name:       Curly Events
 * Plugin URI:        https://github.com/Curly-Sprout-Creative/curly-events
 * Description:       Custom "events" post type with native meta boxes, recurrence (weekly/monthly/none), virtual occurrence engine, [event_list] / [event_calendar] / [event_pagination] shortcodes, and an Oxygen 6 Post Loop bridge. Requires Oxygen 6 built with the documented Events Post Loop wiring.
 * Version:           1.0.3
 * Author:            Curly Sprout Creative
 * License:           GPL-2.0-or-later
 * Text Domain:       curly-events
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CURLY_EVENTS_VERSION', '1.0.3' );
define( 'CURLY_EVENTS_FILE', __FILE__ );
define( 'CURLY_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CURLY_EVENTS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Load order matters: constants and the recurrence engine underpin the
 * shortcodes, the loop bridge, and the REST endpoint.
 */
require_once CURLY_EVENTS_DIR . 'includes/constants.php';
require_once CURLY_EVENTS_DIR . 'includes/cpt.php';
require_once CURLY_EVENTS_DIR . 'includes/admin.php';
require_once CURLY_EVENTS_DIR . 'includes/recurrence.php';
require_once CURLY_EVENTS_DIR . 'includes/shortcodes.php';
require_once CURLY_EVENTS_DIR . 'includes/loop-bridge.php';
require_once CURLY_EVENTS_DIR . 'includes/rest.php';

/**
 * Plugin update checker (GitHub Releases, public repo — no auth needed).
 */
require_once CURLY_EVENTS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$curly_events_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Curly-Sprout-Creative/curly-events/',
		__FILE__,
		'curly-events'
	);
}