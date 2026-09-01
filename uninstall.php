<?php
/**
 * Uninstall handler for Curly Events.
 *
 * Clears cached occurrence transients only. Event content (posts + meta) is
 * preserved by design — re-activating the plugin restores everything.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_curly_events_occ_%'
	    OR option_name LIKE '_transient_timeout_curly_events_occ_%'"
);