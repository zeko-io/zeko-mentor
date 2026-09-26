<?php
/**
 * Zeko Mentor — WordPress personal-data exporter, eraser and retention.
 *
 * Registers with Tools > Export Personal Data / Erase Personal Data so site
 * owners can fulfil data-protection requests for mentor profiles, availability,
 * sessions, goals, progress, reviews, matches, messages, notifications and
 * program memberships, and contributes age-based retention policies for
 * matches and notifications to the shared Zeko Core retention registry.
 *
 * Table schemas byte-verified 2026-09-23 against class-zeko-mentor-db.php DDL
 * (lines 76-320):
 *   {prefix}zeko_mentor_profiles         user_id / created_at, updated_at / bio, headline, location, languages, expertise_areas
 *   {prefix}zeko_mentor_availability     user_id / created_at / start_time, end_time, timezone
 *   {prefix}zeko_mentor_sessions         mentor_id, mentee_id / session_date, created_at, updated_at / topic, notes, meeting_url
 *   {prefix}zeko_mentor_programs         mentor_id / created_at, updated_at / title, description
 *   {prefix}zeko_mentor_program_members  program_id, user_id, role / enrolled_at, completed_at
 *   {prefix}zeko_mentor_goals            user_id / created_at / title, description
 *   {prefix}zeko_mentor_progress         user_id, mentor_id / created_at / note
 *   {prefix}zeko_mentor_reviews          reviewer_id, mentor_id, session_id / created_at / title, review_text
 *   {prefix}zeko_mentor_matches          mentee_id, mentor_id / created_at, expires_at / match_reasons
 *   {prefix}zeko_mentor_messages         sender_id, receiver_id / created_at / message
 *   {prefix}zeko_mentor_notifications    user_id / created_at / message
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the exporter, eraser and retention-table callbacks.
 */
function zeko_mentor_privacy_register(): void {
	add_filter( 'wp_privacy_personal_data_exporters', 'zeko_mentor_privacy_register_exporter' );
	add_filter( 'wp_privacy_personal_data_erasers', 'zeko_mentor_privacy_register_eraser' );
	add_filter( 'zeko_core_privacy_retention_tables', 'zeko_mentor_privacy_retention_tables' );
}
add_action( 'init', 'zeko_mentor_privacy_register', 11 );

/**
 * Register the personal-data exporter.
 *
 * @param array $exporters Exporters.
 */
function zeko_mentor_privacy_register_exporter( array $exporters ): array {
	$exporters['zeko-mentor'] = array(
		'exporter_friendly_name' => __( 'Zeko Mentor data', 'zeko-mentor' ),
		'callback'               => 'zeko_mentor_privacy_export',
	);
	return $exporters;
}

/**
 * Register the personal-data eraser.
 *
 * @param array $erasers Erasers.
 */
function zeko_mentor_privacy_register_eraser( array $erasers ): array {
	$erasers['zeko-mentor'] = array(
		'eraser_friendly_name' => __( 'Zeko Mentor data', 'zeko-mentor' ),
		'callback'             => 'zeko_mentor_privacy_erase',
	);
	return $erasers;
}

/**
 * Get a prepared DB instance (null when the plugin is not active).
 */
function zeko_mentor_privacy_db(): ?Zeko_Mentor_DB {
	if ( ! class_exists( 'Zeko_Mentor_DB' ) ) {
		return null;
	}
	return new Zeko_Mentor_DB();
}

/**
 * Whether a Zeko Mentor table exists (guards every touch of a table).
 *
 * @param string $table Table.
 */
function zeko_mentor_privacy_table_exists( string $table ): bool {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

/**
 * Export a user's Zeko Mentor data, 20 rows per table per page.
 *
 * @return array{data: array, done: bool}
 * @param string $email_address User who requested the export.
 * @param int    $page Export page (batching).
 */
function zeko_mentor_privacy_export( string $email_address, int $page = 1 ): array {
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$db = zeko_mentor_privacy_db();
	if ( ! $db ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	global $wpdb;

	$user_id   = (int) $user->ID;
	$per_page  = 20;
	$offset    = ( max( 1, (int) $page ) - 1 ) * $per_page;
	$data      = array();
	$sources   = 0;
	$exhausted = 0;

	// ─── Profile group: mentor profile + availability ──────────────────────.

	if ( zeko_mentor_privacy_table_exists( $db->get_table_profiles() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$db->get_table_profiles()} WHERE user_id = %d ORDER BY profile_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-mentor-profile',
				'group_label' => __( 'Zeko Mentor — Mentor profile', 'zeko-mentor' ),
				'item_id'     => 'zeko-mentor-profile-' . (int) $row->profile_id,
				'data'        => array(
					array(
						'name'  => __( 'Expertise areas', 'zeko-mentor' ),
						'value' => (string) $row->expertise_areas,
					),
					array(
						'name'  => __( 'Bio', 'zeko-mentor' ),
						'value' => (string) $row->bio,
					),
					array(
						'name'  => __( 'Headline', 'zeko-mentor' ),
						'value' => (string) $row->headline,
					),
					array(
						'name'  => __( 'Location', 'zeko-mentor' ),
						'value' => (string) $row->location,
					),
					array(
						'name'  => __( 'Languages', 'zeko-mentor' ),
						'value' => (string) $row->languages,
					),
					array(
						'name'  => __( 'Years of experience', 'zeko-mentor' ),
						'value' => (string) $row->years_experience,
					),
					array(
						'name'  => __( 'Response time', 'zeko-mentor' ),
						'value' => (string) $row->response_time,
					),
					array(
						'name'  => __( 'LinkedIn URL', 'zeko-mentor' ),
						'value' => (string) $row->linkedin_url,
					),
					array(
						'name'  => __( 'GitHub URL', 'zeko-mentor' ),
						'value' => (string) $row->github_url,
					),
					array(
						'name'  => __( 'Twitter URL', 'zeko-mentor' ),
						'value' => (string) $row->twitter_url,
					),
					array(
						'name'  => __( 'Hourly rate', 'zeko-mentor' ),
						'value' => (string) $row->hourly_rate,
					),
					array(
						'name'  => __( 'Currency', 'zeko-mentor' ),
						'value' => (string) $row->currency,
					),
					array(
						'name'  => __( 'Verified', 'zeko-mentor' ),
						'value' => (string) $row->is_verified,
					),
					array(
						'name'  => __( 'Active', 'zeko-mentor' ),
						'value' => (string) $row->is_active,
					),
					array(
						'name'  => __( 'Max mentees', 'zeko-mentor' ),
						'value' => (string) $row->max_mentees,
					),
					array(
						'name'  => __( 'Timezone', 'zeko-mentor' ),
						'value' => (string) $row->timezone,
					),
					array(
						'name'  => __( 'Verification status', 'zeko-mentor' ),
						'value' => (string) $row->verification_status,
					),
					array(
						'name'  => __( 'Verification notes', 'zeko-mentor' ),
						'value' => (string) $row->verification_notes,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_availability() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT availability_id, day_of_week, start_time, end_time, timezone, is_active, created_at FROM {$db->get_table_availability()} WHERE user_id = %d ORDER BY availability_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'group_id'    => 'zeko-mentor-profile',
				'group_label' => __( 'Zeko Mentor — Availability', 'zeko-mentor' ),
				'item_id'     => 'zeko-mentor-availability-' . (int) $row->availability_id,
				'data'        => array(
					array(
						'name'  => __( 'Day of week', 'zeko-mentor' ),
						'value' => (string) $row->day_of_week,
					),
					array(
						'name'  => __( 'Start time', 'zeko-mentor' ),
						'value' => (string) $row->start_time,
					),
					array(
						'name'  => __( 'End time', 'zeko-mentor' ),
						'value' => (string) $row->end_time,
					),
					array(
						'name'  => __( 'Timezone', 'zeko-mentor' ),
						'value' => (string) $row->timezone,
					),
					array(
						'name'  => __( 'Active', 'zeko-mentor' ),
						'value' => (string) $row->is_active,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				),
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	$profile_query = function ( string $group_id, string $group_label, string $item_prefix, int $item_key, array $pair_rows ) use ( &$data ) {
		$data[] = array(
			'group_id'    => $group_id,
			'group_label' => $group_label,
			'item_id'     => $item_prefix . (string) $item_key,
			'data'        => $pair_rows,
		);
	};

	// ─── Engagements group: sessions / goals / progress / matches / messages / memberships ───.

	$engagements_label = __( 'Zeko Mentor — Engagements', 'zeko-mentor' );

	if ( zeko_mentor_privacy_table_exists( $db->get_table_sessions() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, session_date, start_time, end_time, duration_minutes, status, session_type, meeting_url, topic, notes, amount, currency, payment_status, rating, created_at
				FROM {$db->get_table_sessions()} WHERE (mentor_id = %d OR mentee_id = %d) ORDER BY session_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-session-',
				(int) $row->session_id,
				array(
					array(
						'name'  => __( 'Session type', 'zeko-mentor' ),
						'value' => (string) $row->session_type,
					),
					array(
						'name'  => __( 'Date', 'zeko-mentor' ),
						'value' => (string) $row->session_date,
					),
					array(
						'name'  => __( 'Start time', 'zeko-mentor' ),
						'value' => (string) $row->start_time,
					),
					array(
						'name'  => __( 'End time', 'zeko-mentor' ),
						'value' => (string) $row->end_time,
					),
					array(
						'name'  => __( 'Duration (minutes)', 'zeko-mentor' ),
						'value' => (string) $row->duration_minutes,
					),
					array(
						'name'  => __( 'Status', 'zeko-mentor' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Topic', 'zeko-mentor' ),
						'value' => (string) $row->topic,
					),
					array(
						'name'  => __( 'Notes', 'zeko-mentor' ),
						'value' => (string) $row->notes,
					),
					array(
						'name'  => __( 'Meeting URL', 'zeko-mentor' ),
						'value' => (string) $row->meeting_url,
					),
					array(
						'name'  => __( 'Amount', 'zeko-mentor' ),
						'value' => (string) $row->amount,
					),
					array(
						'name'  => __( 'Currency', 'zeko-mentor' ),
						'value' => (string) $row->currency,
					),
					array(
						'name'  => __( 'Payment status', 'zeko-mentor' ),
						'value' => (string) $row->payment_status,
					),
					array(
						'name'  => __( 'Rating', 'zeko-mentor' ),
						'value' => (string) $row->rating,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_goals() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT goal_id, title, description, target_date, status, created_at FROM {$db->get_table_goals()} WHERE user_id = %d ORDER BY goal_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-goal-',
				(int) $row->goal_id,
				array(
					array(
						'name'  => __( 'Title', 'zeko-mentor' ),
						'value' => (string) $row->title,
					),
					array(
						'name'  => __( 'Description', 'zeko-mentor' ),
						'value' => (string) $row->description,
					),
					array(
						'name'  => __( 'Target date', 'zeko-mentor' ),
						'value' => (string) $row->target_date,
					),
					array(
						'name'  => __( 'Status', 'zeko-mentor' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_progress() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT progress_id, note, rating, created_at FROM {$db->get_table_progress()} WHERE user_id = %d ORDER BY progress_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-progress-',
				(int) $row->progress_id,
				array(
					array(
						'name'  => __( 'Note', 'zeko-mentor' ),
						'value' => (string) $row->note,
					),
					array(
						'name'  => __( 'Rating', 'zeko-mentor' ),
						'value' => (string) $row->rating,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_matches() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT match_id, mentee_id, mentor_id, score, match_reasons, status, created_at, expires_at FROM {$db->get_table_matches()} WHERE (mentee_id = %d OR mentor_id = %d) ORDER BY match_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-match-',
				(int) $row->match_id,
				array(
					array(
						'name'  => __( 'Mentee user ID', 'zeko-mentor' ),
						'value' => (string) $row->mentee_id,
					),
					array(
						'name'  => __( 'Mentor user ID', 'zeko-mentor' ),
						'value' => (string) $row->mentor_id,
					),
					array(
						'name'  => __( 'Score', 'zeko-mentor' ),
						'value' => (string) $row->score,
					),
					array(
						'name'  => __( 'Match reasons', 'zeko-mentor' ),
						'value' => (string) $row->match_reasons,
					),
					array(
						'name'  => __( 'Status', 'zeko-mentor' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
					array(
						'name'  => __( 'Expires at', 'zeko-mentor' ),
						'value' => (string) $row->expires_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_messages() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_id, session_id, sender_id, receiver_id, message, is_read, created_at FROM {$db->get_table_messages()} WHERE (sender_id = %d OR receiver_id = %d) ORDER BY message_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-message-',
				(int) $row->message_id,
				array(
					array(
						'name'  => __( 'Sender user ID', 'zeko-mentor' ),
						'value' => (string) $row->sender_id,
					),
					array(
						'name'  => __( 'Receiver user ID', 'zeko-mentor' ),
						'value' => (string) $row->receiver_id,
					),
					array(
						'name'  => __( 'Message', 'zeko-mentor' ),
						'value' => (string) $row->message,
					),
					array(
						'name'  => __( 'Read', 'zeko-mentor' ),
						'value' => (string) $row->is_read,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_program_members() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, program_id, role, status, enrolled_at, completed_at FROM {$db->get_table_program_members()} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-engagements',
				$engagements_label,
				'zeko-mentor-membership-',
				(int) $row->id,
				array(
					array(
						'name'  => __( 'Program ID', 'zeko-mentor' ),
						'value' => (string) $row->program_id,
					),
					array(
						'name'  => __( 'Role', 'zeko-mentor' ),
						'value' => (string) $row->role,
					),
					array(
						'name'  => __( 'Status', 'zeko-mentor' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Enrolled at', 'zeko-mentor' ),
						'value' => (string) $row->enrolled_at,
					),
					array(
						'name'  => __( 'Completed at', 'zeko-mentor' ),
						'value' => (string) $row->completed_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	// ─── Ledger group: reviews authored + programs created ─────────────────.

	$ledger_label = __( 'Zeko Mentor — Public ledger', 'zeko-mentor' );

	if ( zeko_mentor_privacy_table_exists( $db->get_table_reviews() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT review_id, session_id, mentor_id, reviewer_id, rating, title, review_text, is_public, created_at FROM {$db->get_table_reviews()} WHERE reviewer_id = %d ORDER BY review_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-ledger',
				$ledger_label,
				'zeko-mentor-review-',
				(int) $row->review_id,
				array(
					array(
						'name'  => __( 'Session ID', 'zeko-mentor' ),
						'value' => (string) $row->session_id,
					),
					array(
						'name'  => __( 'Mentor user ID', 'zeko-mentor' ),
						'value' => (string) $row->mentor_id,
					),
					array(
						'name'  => __( 'Rating', 'zeko-mentor' ),
						'value' => (string) $row->rating,
					),
					array(
						'name'  => __( 'Title', 'zeko-mentor' ),
						'value' => (string) $row->title,
					),
					array(
						'name'  => __( 'Review text', 'zeko-mentor' ),
						'value' => (string) $row->review_text,
					),
					array(
						'name'  => __( 'Public', 'zeko-mentor' ),
						'value' => (string) $row->is_public,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	if ( zeko_mentor_privacy_table_exists( $db->get_table_programs() ) ) {
		++$sources;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT program_id, title, description, expertise_area, max_members, current_members, duration_weeks, price, currency, status, start_date, end_date, created_at, updated_at FROM {$db->get_table_programs()} WHERE mentor_id = %d ORDER BY program_id ASC LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( (array) $rows as $row ) {
			$profile_query(
				'zeko-mentor-ledger',
				$ledger_label,
				'zeko-mentor-program-',
				(int) $row->program_id,
				array(
					array(
						'name'  => __( 'Title', 'zeko-mentor' ),
						'value' => (string) $row->title,
					),
					array(
						'name'  => __( 'Description', 'zeko-mentor' ),
						'value' => (string) $row->description,
					),
					array(
						'name'  => __( 'Expertise area', 'zeko-mentor' ),
						'value' => (string) $row->expertise_area,
					),
					array(
						'name'  => __( 'Max members', 'zeko-mentor' ),
						'value' => (string) $row->max_members,
					),
					array(
						'name'  => __( 'Current members', 'zeko-mentor' ),
						'value' => (string) $row->current_members,
					),
					array(
						'name'  => __( 'Duration (weeks)', 'zeko-mentor' ),
						'value' => (string) $row->duration_weeks,
					),
					array(
						'name'  => __( 'Price', 'zeko-mentor' ),
						'value' => (string) $row->price,
					),
					array(
						'name'  => __( 'Currency', 'zeko-mentor' ),
						'value' => (string) $row->currency,
					),
					array(
						'name'  => __( 'Status', 'zeko-mentor' ),
						'value' => (string) $row->status,
					),
					array(
						'name'  => __( 'Start date', 'zeko-mentor' ),
						'value' => (string) $row->start_date,
					),
					array(
						'name'  => __( 'End date', 'zeko-mentor' ),
						'value' => (string) $row->end_date,
					),
					array(
						'name'  => __( 'Created at', 'zeko-mentor' ),
						'value' => (string) $row->created_at,
					),
					array(
						'name'  => __( 'Updated at', 'zeko-mentor' ),
						'value' => (string) $row->updated_at,
					),
				)
			);
		}
		if ( count( $rows ) < $per_page ) {
			++$exhausted;
		}
	}

	return array(
		'data' => $data,
		'done' => $exhausted === $sources,
	);
}

/**
 * Erase a user's Zeko Mentor data in LIMIT 20 batches.
 * Directly-personal stores (profiles, availability, sessions, goals, progress,
 * matches, messages, notifications) are deleted. The public ledger outlives the
 * user: reviews keep their rating/review_text with reviewer_id scrubbed to 0;
 * created programs keep their content with mentor_id scrubbed to 0; and
 * program_members creator links (role mentor/creator) are scrubbed to 0 while
 * mentee memberships are deleted.
 *
 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
 * @param string $email_address User who requested erasure.
 * @param int    $_page page.
 */
function zeko_mentor_privacy_erase( string $email_address, int $_page = 1 ): array {
	$user = get_user_by( 'email', $email_address );
	if ( ! $user ) {
		return array(
			'items_removed'  => 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	$db = zeko_mentor_privacy_db();
	if ( ! $db ) {
		return array(
			'items_removed'  => 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	global $wpdb;

	$user_id   = (int) $user->ID;
	$per_page  = 20;
	$removed   = 0;
	$remaining = 0;

	// Directly-personal stores: DELETE.
	$deletions = array(
		$db->get_table_profiles()      => array( 'user_id = %d', array( $user_id ) ),
		$db->get_table_availability()  => array( 'user_id = %d', array( $user_id ) ),
		$db->get_table_sessions()      => array( '(mentor_id = %d OR mentee_id = %d)', array( $user_id, $user_id ) ),
		$db->get_table_goals()         => array( 'user_id = %d', array( $user_id ) ),
		$db->get_table_progress()      => array( 'user_id = %d', array( $user_id ) ),
		$db->get_table_matches()       => array( '(mentee_id = %d OR mentor_id = %d)', array( $user_id, $user_id ) ),
		$db->get_table_messages()      => array( '(sender_id = %d OR receiver_id = %d)', array( $user_id, $user_id ) ),
		$db->get_table_notifications() => array( 'user_id = %d', array( $user_id ) ),
	);

	foreach ( $deletions as $table => $spec ) {
		if ( ! zeko_mentor_privacy_table_exists( (string) $table ) ) {
			continue;
		}

		$where = $spec[0];
		$args  = $spec[1];
		$prep  = array_merge( $args, array( $per_page ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$removed   += (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE {$where} LIMIT %d", ...$prep )
		);
		$remaining += (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$args ) // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Program memberships: mentee rows are deleted, creator rows anonymized.
	if ( zeko_mentor_privacy_table_exists( $db->get_table_program_members() ) ) {
		$members_table = $db->get_table_program_members();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Capture the programs touched by this batch so their capacity counters.
		// can be recomputed after the mentee deletion.
		$touched = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT program_id FROM {$members_table} WHERE user_id = %d AND role NOT IN ('mentor','creator') ORDER BY program_id ASC LIMIT %d",
					$user_id,
					$per_page
				)
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( $touched ) {
			$placeholders = implode( ', ', array_fill( 0, count( $touched ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$removed += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$members_table} WHERE user_id = %d AND program_id IN ({$placeholders})",
					array_merge( array( $user_id ), $touched )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			foreach ( $touched as $program_id ) {
				$count = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT COUNT(*) FROM {$members_table} WHERE program_id = %d", $program_id )
				);
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$db->get_table_programs()} SET current_members = %d WHERE program_id = %d",
						$count,
						$program_id
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$remaining += (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$members_table} WHERE user_id = %d AND role NOT IN ('mentor','creator')",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Creator/mentor rows own the program; keep the row but detach the user.
		$removed += (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$members_table} SET user_id = 0 WHERE user_id = %d AND role IN ('mentor','creator')",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// Public ledger — created programs stay usable with the creator link scrubbed.
	$programs_anonymized = 0;
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( zeko_mentor_privacy_table_exists( $db->get_table_programs() ) ) {
		$programs_anonymized = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_programs()} SET mentor_id = 0 WHERE mentor_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$removed += $programs_anonymized;
	}

	// Public ledger — reviews keep rating/review_text with the author scrubbed.
	$reviews_anonymized = 0;
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	if ( zeko_mentor_privacy_table_exists( $db->get_table_reviews() ) ) {
		$reviews_anonymized = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$db->get_table_reviews()} SET reviewer_id = 0 WHERE reviewer_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$removed += $reviews_anonymized;
	}

	$messages = array();
	if ( 0 !== $reviews_anonymized ) {
		$messages[] = __( 'Your published reviews remain visible on mentor profiles, with your name removed.', 'zeko-mentor' );
	}
	if ( 0 !== $programs_anonymized ) {
		$messages[] = __( 'Programs you created remain visible with the creator link removed.', 'zeko-mentor' );
	}
	if ( 0 === $removed ) {
		$messages[] = __( 'No Zeko Mentor data was found for this user.', 'zeko-mentor' );
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => 0,
		'messages'       => $messages,
		'done'           => 0 === $remaining,
	);
}

/**
 * Age-based retention configs for the shared Zeko Core retention registry.
 * The filter is fired by Zeko Core (if/when present); registering this
 * callback is harmless when the filter never fires. No cron is scheduled here —
 * processing runs under the one shared zeko_core_privacy_retention_daily job.
 * Config shape matches the core contract (byte-verified against
 * class-zeko-core-privacy-exporters.php:730-743): table / user_col / type_col /
 * date_col / types / days. Match statuses and notification types are enumerated
 * as allow-lists so every row ages out after a year.
 *
 * @param array $tables Tables.
 */
function zeko_mentor_privacy_retention_tables( array $tables ): array {
	global $wpdb;
	$prefix = $wpdb->prefix;

	// Match suggestions lose value once stale; age out after a year (byte-.
	// verified match status values: suggested/accepted/rejected/dismissed —.
	// class-zeko-mentor-db.php:1445/1456, class-zeko-mentor-rest.php:764/788,.
	// class-zeko-mentor-ajax.php:708/726).
	$tables[] = array(
		'table'    => $prefix . 'zeko_mentor_matches',
		'user_col' => 'mentee_id',
		'type_col' => 'status',
		'date_col' => 'created_at',
		'types'    => array( 'suggested', 'accepted', 'rejected', 'dismissed' ),
		'days'     => 365,
	);

	// In-app notifications age out after a year (byte-verified notification.
	// type values: session/review/match/info/goal/program —.
	// class-zeko-mentor-notifications.php:53/87/119/155/189/209/237 and.
	// class-zeko-mentor-ajax.php:817/832/903).
	$tables[] = array(
		'table'    => $prefix . 'zeko_mentor_notifications',
		'user_col' => 'user_id',
		'type_col' => 'type',
		'date_col' => 'created_at',
		'types'    => array( 'session', 'review', 'match', 'info', 'goal', 'program' ),
		'days'     => 365,
	);

	return $tables;
}
