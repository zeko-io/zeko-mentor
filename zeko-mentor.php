<?php
/**
 * Plugin Name:       Zeko Mentor
 * Plugin URI:        https://ozconsultz.com/zeko-mentor
 * Description:       World-class mentorship platform with smart matching, session booking, video calls, and deep ecosystem integration.
 * Version:           1.3.1
 * Author:            Zeko Team
 * Author URI:        https://ozconsultz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zeko-mentor
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1.2
 *
 * @package Zeko_ZEKO_MENTOR
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ZEKO_MENTOR_VERSION' ) ) {
	define( 'ZEKO_MENTOR_VERSION', '1.3.1' );
}

if ( ! defined( 'ZEKO_MENTOR_PLUGIN_PATH' ) ) {
	define( 'ZEKO_MENTOR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ZEKO_MENTOR_PLUGIN_URL' ) ) {
	define( 'ZEKO_MENTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ZEKO_MENTOR_PLUGIN_BASENAME' ) ) {
	define( 'ZEKO_MENTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'ZEKO_MENTOR_DB_VERSION' ) ) {
	define( 'ZEKO_MENTOR_DB_VERSION', '1.5.0' );
}

require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/class-zeko-mentor.php';

require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/privacy/class-zeko-mentor-privacy.php';

/**
 * Get a page by its slug without using the deprecated get_page_by_path().
 * Delegates to the ecosystem lookup (Zeko Core) when present; the local
 * WP_Query is the fallback so mentor still works without it.
 *
 * @param string $slug Slug.
 * @param string $post_type Post type.
 */
function zeko_mentor_get_page_by_slug( string $slug, string $post_type = 'page' ) {
	if ( class_exists( 'Zeko_Core_Helpers' ) && method_exists( 'Zeko_Core_Helpers', 'get_page_by_slug' ) ) {
		return Zeko_Core_Helpers::get_instance()->get_page_by_slug( $slug, $post_type );
	}

	$query = new WP_Query(
		array(
			'post_type'      => $post_type,
			'name'           => sanitize_title( $slug ),
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);
	return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * Get a page ID by slug.
 *
 * @param string $slug Slug.
 * @param string $post_type Post type.
 */
function zeko_mentor_get_page_id_by_slug( string $slug, string $post_type = 'page' ): int {
	$page = zeko_mentor_get_page_by_slug( $slug, $post_type );
	return $page ? (int) $page->ID : 0;
}

/**
 * Boot the plugin on plugins_loaded.
 */
function zeko_mentor_init() {
	return Zeko_Mentor::instance();
}
add_action( 'plugins_loaded', 'zeko_mentor_init' );

/**
 * Helper to access the singleton.
 *
 * @return Zeko_Mentor
 */
function zeko_mentor() {
	return Zeko_Mentor::instance();
}

/**
 * Activation: create schema + pages + cron + flush rewrites.
 */
function zeko_mentor_activate() {
	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/db/class-zeko-mentor-db.php';
	$db = new Zeko_Mentor_DB();
	$db->create_tables();

	zeko_mentor_create_shortcode_pages();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( 'zeko_mentor_session_reminders' ) ) {
		wp_schedule_event( time(), 'hourly', 'zeko_mentor_session_reminders' );
	}
	if ( ! wp_next_scheduled( 'zeko_mentor_match_refresh' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'zeko_mentor_match_refresh' );
	}
	if ( ! wp_next_scheduled( 'zeko_mentor_analytics_aggregate' ) ) {
		wp_schedule_event( time(), 'daily', 'zeko_mentor_analytics_aggregate' );
	}
}

/**
 * Deactivation: clear cron + flush rewrites.
 */
function zeko_mentor_deactivate() {
	wp_clear_scheduled_hook( 'zeko_mentor_session_reminders' );
	wp_clear_scheduled_hook( 'zeko_mentor_match_refresh' );
	wp_clear_scheduled_hook( 'zeko_mentor_analytics_aggregate' );
	flush_rewrite_rules();
}

/**
 * Whether a user is demo-generated data.
 * Matches on the zeko_demo_user meta tag as well as the @zeko.test demo
 * email domain so demo accounts are excluded from real mailouts, payouts,
 * and public claims.
 *
 * @return bool True when the user is demo-generated.
 * @param int $user_id User ID.
 */
function zeko_mentor_is_demo_user( int $user_id ): bool {
	if ( $user_id <= 0 ) {
		return false;
	}

	if ( get_user_meta( $user_id, 'zeko_demo_user', true ) ) {
		return true;
	}

	$user = get_userdata( $user_id );
	if ( $user && 'zeko.test' === strtolower( (string) wp_parse_url( (string) $user->user_email, PHP_URL_HOST ) ) ) {
		return true;
	}

	return false;
}

/**
 * Whether an email address belongs to a demo account.
 *
 * @return bool True when the address is demo-generated.
 * @param string $email Email address.
 */
function zeko_mentor_is_demo_email( string $email ): bool {
	if ( '' === $email ) {
		return false;
	}

	if ( 'zeko.test' === strtolower( (string) wp_parse_url( $email, PHP_URL_HOST ) ) ) {
		return true;
	}

	$user = get_user_by( 'email', $email );
	return $user && (bool) get_user_meta( (int) $user->ID, 'zeko_demo_user', true );
}

/**
 * Cron: send session reminders 24h before session.
 */
function zeko_mentor_send_session_reminders() {
	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/db/class-zeko-mentor-db.php';
	$db = new Zeko_Mentor_DB();

	$settings = get_option( 'zeko_mentor_settings', array() );
	$hours    = absint( $settings['reminder_hours'] ?? 24 );
	$reminder = $settings['session_reminder'] ?? 1;

	if ( ! $reminder ) {
		return;
	}

	global $wpdb;
	$table = $db->get_table_sessions();

	$now  = current_time( 'mysql', true );
	$then = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + ( $hours * HOUR_IN_SECONDS ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$sessions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE status IN ('confirmed','pending')
			  AND CONCAT(session_date, ' ', start_time) BETWEEN %s AND %s",
			gmdate( 'Y-m-d H:i:s', strtotime( $then ) - HOUR_IN_SECONDS ),
			$then
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	if ( empty( $sessions ) ) {
		return;
	}

	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/class-zeko-mentor-emails.php';
	$emails = new Zeko_Mentor_Emails( $db );

	foreach ( $sessions as $s ) {
		$mentor_userdata = get_userdata( (int) $s['mentor_id'] );
		$mentee_userdata = get_userdata( (int) $s['mentee_id'] );

		if ( zeko_mentor_is_demo_user( (int) $s['mentor_id'] ) ) {
			$mentor_userdata = null;
		}

		if ( $mentor_userdata ) {
			wp_mail(
				$mentor_userdata->user_email,
				sprintf(
					/* translators: %s: session topic */
					__( 'Upcoming Session Reminder: %s', 'zeko-mentor' ),
					$s['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' )
				),
				sprintf(
					/* translators: 1: mentee name, 2: date, 3: time, 4: topic, 5: meeting URL */
					__( "You have an upcoming session with %1\$s on %2\$s at %3\$s.\n\nTopic: %4\$s\nMeeting URL: %5\$s", 'zeko-mentor' ),
					$mentee_userdata ? $mentee_userdata->display_name : __( 'your mentee', 'zeko-mentor' ),
					$s['session_date'],
					$s['start_time'],
					$s['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' ),
					$s['meeting_url']
				)
			);
		}

		if ( zeko_mentor_is_demo_user( (int) $s['mentee_id'] ) ) {
			$mentee_userdata = null;
		}

		if ( $mentee_userdata ) {
			wp_mail(
				$mentee_userdata->user_email,
				sprintf(
					/* translators: %s: session topic */
					__( 'Upcoming Session Reminder: %s', 'zeko-mentor' ),
					$s['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' )
				),
				sprintf(
					/* translators: 1: mentor name, 2: date, 3: time, 4: topic, 5: meeting URL */
					__( "You have an upcoming session with %1\$s on %2\$s at %3\$s.\n\nTopic: %4\$s\nMeeting URL: %5\$s", 'zeko-mentor' ),
					$mentor_userdata ? $mentor_userdata->display_name : __( 'your mentor', 'zeko-mentor' ),
					$s['session_date'],
					$s['start_time'],
					$s['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' ),
					$s['meeting_url']
				)
			);
		}

		do_action( 'zeko_mentor_session_reminder_sent', $s );
	}
}

/**
 * Cron: refresh match suggestions for all mentees.
 */
function zeko_mentor_refresh_matches() {
	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/db/class-zeko-mentor-db.php';
	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/class-zeko-mentor-matching.php';

	$db       = new Zeko_Mentor_DB();
	$matching = new Zeko_Mentor_Matching( $db );

	global $wpdb;
	$matches_table = $db->get_table_matches();

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$mentee_ids = $wpdb->get_col(
		"SELECT DISTINCT mentee_id FROM {$matches_table} WHERE status IN ('suggested','accepted') ORDER BY mentee_id LIMIT 100"
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	foreach ( $mentee_ids as $mentee_id ) {
		$suggestions = $matching->find_matches( (int) $mentee_id, 5 );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( $suggestions as $s ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT match_id FROM {$matches_table} WHERE mentee_id = %d AND mentor_id = %d AND status NOT IN ('rejected','dismissed')",
					$mentee_id,
					$s['mentor_id']
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			if ( ! $existing ) {
				$db->save_match( (int) $mentee_id, (int) $s['mentor_id'], (float) $s['score'], $s['reasons'] );
			}
		}
	}

	do_action( 'zeko_mentor_matches_refreshed', count( $mentee_ids ) );
}

/**
 * Cron: aggregate daily analytics.
 */
function zeko_mentor_aggregate_analytics() {
	global $wpdb;
	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/db/class-zeko-mentor-db.php';
	$db = new Zeko_Mentor_DB();

	$today          = gmdate( 'Y-m-d' );
	$sessions_table = $db->get_table_sessions();

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	$stats = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
				SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as revenue
			 FROM {$sessions_table} WHERE session_date = %s",
			$today
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

	$option_key = 'zeko_mentor_analytics_' . $today;
	update_option( $option_key, $stats );

	$all_keys = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'zeko_mentor_analytics_%' ORDER BY option_name DESC LIMIT 90"
	);
	if ( count( $all_keys ) > 90 ) {
		$to_delete = array_slice( $all_keys, 90 );
		foreach ( $to_delete as $key ) {
			delete_option( $key );
		}
	}

	do_action( 'zeko_mentor_analytics_aggregated', $today, $stats );
}

/**
 * Uninstall: drop tables + delete options.
 */
function zeko_mentor_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}

	require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/db/class-zeko-mentor-db.php';
	$db = new Zeko_Mentor_DB();
	$db->drop_tables();

	// Remove shortcode pages — ownership-verified only (marker or a legacy.
	// page matching a mentor slug whose content carries a mentor shortcode).
	// A user's unrelated page that merely shares a slug is never deleted.
	if ( function_exists( 'zeko_delete_plugin_pages' ) ) {
		zeko_delete_plugin_pages(
			'mentor',
			array(
				'mentors',
				'mentor-dashboard',
				'mentor-programs',
				'mentor-matches',
				'become-a-mentor',
				'mentor-session',
				'mentor-inbox',
				'manage-availability',
				'mentor-calendar',
			)
		);
	}

	// Remove nav menu items added by this plugin.
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) ) {
		$items = wp_get_nav_menu_items( $locations['primary'] );
		if ( $items ) {
			foreach ( $items as $item ) {
				if ( __( 'Mentorship', 'zeko-mentor' ) === $item->title || __( 'Find a Mentor', 'zeko-mentor' ) === $item->title || __( 'My Mentorship', 'zeko-mentor' ) === $item->title ) {
					wp_delete_post( $item->ID, true );
				}
			}
		}
	}
	unset( $locations['zeko-mentor'] );
	set_theme_mod( 'nav_menu_locations', $locations );

	// Remove plugin options.
	delete_option( 'zeko_mentor_db_version' );
	delete_option( 'zeko_mentor_settings' );
	delete_option( 'zeko_mentor_pages_created' );
	delete_option( 'zeko_mentor_flush_rewrites' );
	delete_option( 'zeko_mentor_menu_created' );
	delete_option( 'zeko_mentor_primary_menu_done' );

	// Remove analytics options.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zeko_mentor_analytics_%'" );

	// Remove mentor-specific user meta only (never a blanket zeko_% wipe — that would delete.
	// pay wallet bindings, rewards points, learn enrollments, shop data, and shared keys.
	// like zeko_timezone which zeko-love also uses).
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'zeko_mentor_%'" );
}

/**
 * Create default shortcode pages.
 */
function zeko_mentor_create_shortcode_pages() {
	$pages = array(
		'mentors'             => array(
			'title'   => __( 'Find a Mentor', 'zeko-mentor' ),
			'content' => '[zeko_mentor_browse]',
		),
		'mentor-dashboard'    => array(
			'title'   => __( 'My Mentorship', 'zeko-mentor' ),
			'content' => '[zeko_mentor_dashboard]',
		),
		'mentor-programs'     => array(
			'title'   => __( 'Mentorship Programs', 'zeko-mentor' ),
			'content' => '[zeko_mentor_programs]',
		),
		'mentor-matches'      => array(
			'title'   => __( 'My Mentor Matches', 'zeko-mentor' ),
			'content' => '[zeko_mentor_matches]',
		),
		'become-a-mentor'     => array(
			'title'   => __( 'Become a Mentor', 'zeko-mentor' ),
			'content' => '[zeko_mentor_apply]',
		),
		'mentor-session'      => array(
			'title'   => __( 'Session Details', 'zeko-mentor' ),
			'content' => '[zeko_mentor_session]',
		),
		'mentor-inbox'        => array(
			'title'   => __( 'Messages', 'zeko-mentor' ),
			'content' => '[zeko_mentor_inbox]',
		),
		'manage-availability' => array(
			'title'   => __( 'Availability', 'zeko-mentor' ),
			'content' => '[zeko_mentor_availability]',
		),
		'mentor-calendar'     => array(
			'title'   => __( 'Calendar', 'zeko-mentor' ),
			'content' => '[zeko_mentor_calendar]',
		),
	);

	foreach ( $pages as $slug => $page ) {
		$existing = zeko_mentor_get_page_by_slug( $slug );
		if ( ! $existing ) {
			$result = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $slug,
				)
			);
			if ( is_wp_error( $result ) ) {
				error_log( 'Zeko Mentor: Failed to create page "' . $slug . '": ' . $result->get_error_message() );
			} elseif ( function_exists( 'zeko_mark_plugin_page' ) ) {
					zeko_mark_plugin_page( $result, 'mentor' );
			}
		}
	}
}

/**
 * Ensure pages exist on admin_init.
 */
function zeko_mentor_maybe_create_pages() {
	$pages_created = get_option( 'zeko_mentor_pages_created', false );
	if ( ! $pages_created ) {
		zeko_mentor_create_shortcode_pages();
		update_option( 'zeko_mentor_pages_created', true );
	}
}

register_activation_hook( __FILE__, 'zeko_mentor_activate' );
register_deactivation_hook( __FILE__, 'zeko_mentor_deactivate' );
register_uninstall_hook( __FILE__, 'zeko_mentor_uninstall' );
add_action( 'admin_init', 'zeko_mentor_maybe_create_pages' );
