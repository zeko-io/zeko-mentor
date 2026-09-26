<?php
/**
 * Uninstall script for Zeko Mentor.
 *
 * Runs when the plugin is deleted via WordPress admin.
 * Cleans up mentor-owned tables, user meta, plugin options, and scheduled
 * cron events. Shared ecosystem data is kept.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix;

// Drop mentor-owned tables.
$tables = array(
	$prefix . 'zeko_mentor_profiles',
	$prefix . 'zeko_mentor_availability',
	$prefix . 'zeko_mentor_sessions',
	$prefix . 'zeko_mentor_programs',
	$prefix . 'zeko_mentor_program_members',
	$prefix . 'zeko_mentor_goals',
	$prefix . 'zeko_mentor_progress',
	$prefix . 'zeko_mentor_reviews',
	$prefix . 'zeko_mentor_matches',
	$prefix . 'zeko_mentor_messages',
	$prefix . 'zeko_mentor_notifications',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete mentor-owned user meta (session state, rates, ical tokens,
// program-payment markers). Never a bare `zeko_%` wildcard, which would
// wipe other ecosystem modules' meta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	'DELETE FROM ' . $wpdb->usermeta . " WHERE meta_key LIKE 'zeko_mentor\_%'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
);

// Delete plugin options.
$options = array(
	'zeko_mentor_settings',
	'zeko_mentor_db_version',
	'zeko_mentor_page_ids',
	'zeko_mentor_pages_created',
	'zeko_mentor_primary_menu_done',
	'zeko_mentor_plugin_version',
	'zeko_mentor_flush_rewrites',
	'zeko_mentor_profile_routes_flushed',
	'zeko_mentor_menu_created',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clear all scheduled cron events.
$cron_hooks = array(
	'zeko_mentor_session_reminders',
	'zeko_mentor_match_refresh',
	'zeko_mentor_analytics_aggregate',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
