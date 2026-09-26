<?php
/**
 * Database schema and query layer for Zeko Mentor.
 *
 * 10 custom tables for mentor profiles, availability, sessions, programs,
 * goals, progress, reviews, matches, and messages.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_DB. */
class Zeko_Mentor_DB {

	/**
	 * Db version.
	 *
	 * @var string Db version.
	 */
	private string $db_version = '1.5.0';

	/**
	 * Wpdb.
	 *
	 * @var \wpdb Wpdb.
	 */
	private \wpdb $wpdb;

	/**
	 * Sanitize rich-text (Quill) content with the narrow ecosystem
	 * allow-list; falls back to wp_kses_post() when zeko-core is absent.
	 *
	 * @param mixed $html Html.
	 */
	private static function sanitize_rich( $html ): string {
		if ( class_exists( 'Zeko_Core_Sanitize' ) ) {
			return Zeko_Core_Sanitize::rich_text( (string) $html );
		}
		return wp_kses_post( (string) $html );
	}

	/**
	 * Table profiles.
	 *
	 * @var string Table profiles.
	 */
	private string $table_profiles;
	/**
	 * Table availability.
	 *
	 * @var string Table availability.
	 */
	private string $table_availability;
	/**
	 * Table sessions.
	 *
	 * @var string Table sessions.
	 */
	private string $table_sessions;
	/**
	 * Table programs.
	 *
	 * @var string Table programs.
	 */
	private string $table_programs;
	/**
	 * Table program members.
	 *
	 * @var string Table program members.
	 */
	private string $table_program_members;
	/**
	 * Table goals.
	 *
	 * @var string Table goals.
	 */
	private string $table_goals;
	/**
	 * Table progress.
	 *
	 * @var string Table progress.
	 */
	private string $table_progress;
	/**
	 * Table reviews.
	 *
	 * @var string Table reviews.
	 */
	private string $table_reviews;
	/**
	 * Table matches.
	 *
	 * @var string Table matches.
	 */
	private string $table_matches;
	/**
	 * Table messages.
	 *
	 * @var string Table messages.
	 */
	private string $table_messages;
	/**
	 * Table notifications.
	 *
	 * @var string Table notifications.
	 */
	private string $table_notifications;

	/**
	 * Construct.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$p                           = $wpdb->prefix . 'zeko_';
		$this->table_profiles        = $p . 'mentor_profiles';
		$this->table_availability    = $p . 'mentor_availability';
		$this->table_sessions        = $p . 'mentor_sessions';
		$this->table_programs        = $p . 'mentor_programs';
		$this->table_program_members = $p . 'mentor_program_members';
		$this->table_goals           = $p . 'mentor_goals';
		$this->table_progress        = $p . 'mentor_progress';
		$this->table_reviews         = $p . 'mentor_reviews';
		$this->table_matches         = $p . 'mentor_matches';
		$this->table_messages        = $p . 'mentor_messages';
		$this->table_notifications   = $p . 'mentor_notifications';
	}

	/**
	 * Create or upgrade all tables via dbDelta.
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->get_charset_collate();
		$sql     = array();

		// ─── Mentor Profiles ────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_profiles} (
			profile_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			expertise_areas longtext DEFAULT NULL,
			bio text DEFAULT NULL,
			headline varchar(200) NOT NULL DEFAULT '',
			location varchar(100) NOT NULL DEFAULT '',
			languages varchar(200) NOT NULL DEFAULT '',
			years_experience int(11) NOT NULL DEFAULT 0,
			response_time varchar(50) NOT NULL DEFAULT '',
			linkedin_url varchar(255) NOT NULL DEFAULT '',
			github_url varchar(255) NOT NULL DEFAULT '',
			twitter_url varchar(255) NOT NULL DEFAULT '',
			hourly_rate decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			is_verified tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			max_mentees int(11) NOT NULL DEFAULT 5,
			total_sessions int(11) NOT NULL DEFAULT 0,
			avg_rating decimal(3,2) NOT NULL DEFAULT 0.00,
			profile_views int(11) NOT NULL DEFAULT 0,
			timezone varchar(50) NOT NULL DEFAULT 'UTC',
			verification_status varchar(20) NOT NULL DEFAULT 'none',
			verification_date datetime DEFAULT NULL,
			verification_notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (profile_id),
			UNIQUE KEY user_id (user_id),
			KEY is_verified (is_verified),
			KEY is_active (is_active),
			KEY avg_rating (avg_rating),
			KEY hourly_rate (hourly_rate),
			KEY verification_status (verification_status),
			KEY created_at (created_at),
			KEY timezone (timezone)
		) {$charset};";

		// ─── Availability Slots ─────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_availability} (
			availability_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			day_of_week tinyint(1) NOT NULL DEFAULT 0,
			start_time time NOT NULL,
			end_time time NOT NULL,
			timezone varchar(50) NOT NULL DEFAULT 'UTC',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (availability_id),
			KEY user_id (user_id),
			KEY day_of_week (day_of_week),
			KEY is_active (is_active)
		) {$charset};";

		// ─── Sessions ──────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_sessions} (
			session_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mentor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mentee_id bigint(20) unsigned NOT NULL DEFAULT 0,
			session_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			timezone varchar(50) NOT NULL DEFAULT 'UTC',
			duration_minutes int(11) NOT NULL DEFAULT 60,
			status varchar(20) NOT NULL DEFAULT 'pending',
			session_type varchar(20) NOT NULL DEFAULT 'video',
			meeting_url varchar(500) NOT NULL DEFAULT '',
			topic varchar(200) NOT NULL DEFAULT '',
			notes text DEFAULT NULL,
			mentor_notes text DEFAULT NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			payment_status varchar(20) NOT NULL DEFAULT 'pending',
			payment_ref varchar(100) NOT NULL DEFAULT '',
			transaction_code varchar(30) NOT NULL DEFAULT '',
			payout_ref varchar(100) NOT NULL DEFAULT '',
			rating tinyint(1) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (session_id),
			KEY mentor_id (mentor_id),
			KEY mentee_id (mentee_id),
			KEY session_date (session_date),
			KEY status (status),
			KEY payment_status (payment_status),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Programs ──────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_programs} (
			program_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mentor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(200) NOT NULL DEFAULT '',
			description longtext DEFAULT NULL,
			expertise_area varchar(100) NOT NULL DEFAULT '',
			max_members int(11) NOT NULL DEFAULT 20,
			current_members int(11) NOT NULL DEFAULT 0,
			duration_weeks int(11) NOT NULL DEFAULT 4,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'USD',
			status varchar(20) NOT NULL DEFAULT 'draft',
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (program_id),
			KEY mentor_id (mentor_id),
			KEY status (status),
			KEY expertise_area (expertise_area),
			KEY start_date (start_date)
		) {$charset};";

		// ─── Program Members ───────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_program_members} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			program_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			role varchar(20) NOT NULL DEFAULT 'mentee',
			status varchar(20) NOT NULL DEFAULT 'pending',
			enrolled_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY program_user (program_id, user_id),
			KEY user_id (user_id),
			KEY role (role),
			KEY status (status)
		) {$charset};";

		// ─── Goals ─────────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_goals} (
			goal_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			session_id bigint(20) unsigned DEFAULT NULL,
			program_id bigint(20) unsigned DEFAULT NULL,
			title varchar(200) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			target_date date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (goal_id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY program_id (program_id),
			KEY status (status)
		) {$charset};";

		// ─── Progress Entries ──────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_progress} (
			progress_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			goal_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mentor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note text DEFAULT NULL,
			rating tinyint(1) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (progress_id),
			KEY goal_id (goal_id),
			KEY user_id (user_id),
			KEY mentor_id (mentor_id),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Reviews ───────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_reviews} (
			review_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mentor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rating tinyint(1) NOT NULL DEFAULT 5,
			title varchar(100) NOT NULL DEFAULT '',
			review_text text DEFAULT NULL,
			is_public tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (review_id),
			UNIQUE KEY session_reviewer (session_id, reviewer_id),
			KEY session_id (session_id),
			KEY mentor_id (mentor_id),
			KEY reviewer_id (reviewer_id),
			KEY rating (rating),
			KEY is_public (is_public),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Matches ───────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_matches} (
			match_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mentee_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mentor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(5,2) NOT NULL DEFAULT 0.00,
			match_reasons longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'suggested',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (match_id),
			KEY mentee_id (mentee_id),
			KEY mentor_id (mentor_id),
			KEY score (score),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset};";

		// ─── Messages ──────────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_messages} (
			message_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned DEFAULT NULL,
			sender_id bigint(20) unsigned NOT NULL DEFAULT 0,
			receiver_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (message_id),
			KEY session_id (session_id),
			KEY sender_id (sender_id),
			KEY receiver_id (receiver_id),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) {$charset};";

		// ─── Notifications ──────────────────────────────────────────.

		$sql[] = "CREATE TABLE {$this->table_notifications} (
			notification_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(50) NOT NULL DEFAULT 'info',
			title varchar(200) NOT NULL DEFAULT '',
			message text NOT NULL,
			link varchar(500) NOT NULL DEFAULT '',
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (notification_id),
			KEY user_id (user_id),
			KEY user_unread (user_id, is_read),
			KEY is_read (is_read),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset};";

		// Execute all queries.
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		// ─── Schema Migrations for existing tables ──────────────.
		$this->run_migrations();
	}

	/**
	 * Run incremental schema migrations.
	 */
	private function run_migrations(): void {
		$installed = get_option( 'zeko_mentor_db_version', '0' );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// v1.0.0 → v1.1.0: Add timezone column to profiles.
		if ( version_compare( $installed, '1.1.0', '<' ) ) {
			$col = $this->wpdb->get_results( "SHOW COLUMNS FROM {$this->table_profiles} LIKE 'timezone'" );
			if ( empty( $col ) ) {
				$this->wpdb->query( "ALTER TABLE {$this->table_profiles} ADD COLUMN timezone varchar(50) NOT NULL DEFAULT 'UTC' AFTER profile_views" );
				$this->wpdb->query( "ALTER TABLE {$this->table_profiles} ADD KEY timezone_idx (timezone)" );
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			}
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// v1.2.0 → v1.3.0: Add payout_ref column to sessions.
		if ( version_compare( $installed, '1.3.0', '<' ) ) {
			$col = $this->wpdb->get_results( "SHOW COLUMNS FROM {$this->table_sessions} LIKE 'payout_ref'" );
			if ( empty( $col ) ) {
				$this->wpdb->query( "ALTER TABLE {$this->table_sessions} ADD COLUMN payout_ref varchar(100) NOT NULL DEFAULT '' AFTER payment_ref" );
				$this->wpdb->query( "ALTER TABLE {$this->table_sessions} ADD KEY payout_ref (payout_ref)" );
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			}

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			// Legacy sessions were paid out at booking time. Mark them as.
			// already paid so the deferred payout on completion cannot double-pay.
			$this->wpdb->query(
				"UPDATE {$this->table_sessions} SET payout_ref = 'booked' WHERE payment_status = 'paid' AND payout_ref = ''"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// v1.3.0 → v1.4.0: Add transaction_code column to sessions.
		if ( version_compare( $installed, '1.4.0', '<' ) ) {
			$col = $this->wpdb->get_results( "SHOW COLUMNS FROM {$this->table_sessions} LIKE 'transaction_code'" );
			if ( empty( $col ) ) {
				$this->wpdb->query( "ALTER TABLE {$this->table_sessions} ADD COLUMN transaction_code varchar(30) NOT NULL DEFAULT '' AFTER payment_ref" );
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			}
		}

		// v1.1.0 → v1.2.0: Add rich profile fields.
		if ( version_compare( $installed, '1.2.0', '<' ) ) {
			$cols = array(
				"headline varchar(200) NOT NULL DEFAULT '' AFTER bio",
				"location varchar(100) NOT NULL DEFAULT '' AFTER headline",
				"languages varchar(200) NOT NULL DEFAULT '' AFTER location",
				'years_experience int(11) NOT NULL DEFAULT 0 AFTER languages',
				"response_time varchar(50) NOT NULL DEFAULT '' AFTER years_experience",
				"linkedin_url varchar(255) NOT NULL DEFAULT '' AFTER response_time",
				"github_url varchar(255) NOT NULL DEFAULT '' AFTER linkedin_url",
				"twitter_url varchar(255) NOT NULL DEFAULT '' AFTER github_url",
			);
			foreach ( $cols as $col_def ) {
				$col_name = strtok( $col_def, ' ' );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
				$col = $this->wpdb->get_results( "SHOW COLUMNS FROM {$this->table_profiles} LIKE '{$col_name}'" );
				if ( empty( $col ) ) {
					$this->wpdb->query( "ALTER TABLE {$this->table_profiles} ADD COLUMN {$col_def}" );
					// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
				}
			}
			$this->flush_cache();
		}

		// v1.4.0 → v1.5.0: hot-path unread notification index.
		if ( version_compare( $installed, '1.5.0', '<' ) ) {
			$this->add_index_if_missing( $this->table_notifications, 'user_unread', '`user_id`, `is_read`' );
		}
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Add an index if it doesn't already exist.
	 *
	 * @param string $table Table.
	 * @param string $index_name Index name.
	 * @param string $columns Columns.
	 */
	private function add_index_if_missing( string $table, string $index_name, string $columns ): void {
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s',
				$table,
				$index_name
			)
		);
		if ( ! $exists ) {
			$this->wpdb->query( "ALTER TABLE {$table} ADD KEY `{$index_name}` ({$columns})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Drop all tables on uninstall.
	 */
	public function drop_tables(): void {
		$tables = array(
			$this->table_profiles,
			$this->table_availability,
			$this->table_sessions,
			$this->table_programs,
			$this->table_program_members,
			$this->table_goals,
			$this->table_progress,
			$this->table_reviews,
			$this->table_matches,
			$this->table_messages,
			$this->table_notifications,
		);
		foreach ( $tables as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
	}

	// ─── Table Getters ───────────────────────────────────────────.

	/**
	 * Table profiles.
	 */
	public function get_table_profiles(): string {
		return $this->table_profiles; }
	/**
	 * Table availability.
	 */
	public function get_table_availability(): string {
		return $this->table_availability; }
	/**
	 * Table sessions.
	 */
	public function get_table_sessions(): string {
		return $this->table_sessions; }
	/**
	 * Table programs.
	 */
	public function get_table_programs(): string {
		return $this->table_programs; }
	/**
	 * Table program members.
	 */
	public function get_table_program_members(): string {
		return $this->table_program_members; }
	/**
	 * Table goals.
	 */
	public function get_table_goals(): string {
		return $this->table_goals; }
	/**
	 * Table progress.
	 */
	public function get_table_progress(): string {
		return $this->table_progress; }
	/**
	 * Table reviews.
	 */
	public function get_table_reviews(): string {
		return $this->table_reviews; }
	/**
	 * Table matches.
	 */
	public function get_table_matches(): string {
		return $this->table_matches; }
	/**
	 * Table messages.
	 */
	public function get_table_messages(): string {
		return $this->table_messages; }
	/**
	 * Table notifications.
	 */
	public function get_table_notifications(): string {
		return $this->table_notifications; }

	// ─── Charset ─────────────────────────────────────────────────.

	/**
	 * Charset collate.
	 */
	private function get_charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	// ─── Object Cache Helpers ─────────────────────────────────────.

	/**
	 * Cache get.
	 *
	 * @param string $key Key.
	 */
	private function cache_get( string $key ) {
		return wp_cache_get( $key, 'zeko_mentor' );
	}

	/**
	 * Cache set.
	 *
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl Ttl.
	 */
	private function cache_set( string $key, $value, int $ttl = 300 ): void {
		wp_cache_set( $key, $value, 'zeko_mentor', $ttl );
	}

	/**
	 * Flush cache.
	 */
	public function flush_cache(): void {
		wp_cache_flush_group( 'zeko_mentor' );
	}

	/**
	 * Invalidate profile cache.
	 *
	 * @param int $user_id User id.
	 */
	private function invalidate_profile_cache( int $user_id ): void {
		wp_cache_delete( 'profile_' . $user_id, 'zeko_mentor' );
		wp_cache_delete( 'is_mentor_' . $user_id, 'zeko_mentor' );
		$this->flush_cache();
	}

	// ═══════════════════════════════════════════════════════════════.
	// PROFILE QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get a mentor profile by user ID.
	 *
	 * @param int $user_id User id.
	 */
	public function get_profile( int $user_id ): ?array {
		$cache_key = 'profile_' . $user_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_profiles} WHERE user_id = %d LIMIT 1", $user_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $row ?: array() );
		return $row ?: null;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a mentor profile by profile ID.
	 *
	 * @param int $profile_id Profile id.
	 */
	public function get_profile_by_id( int $profile_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_profiles} WHERE profile_id = %d LIMIT 1", $profile_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	/**
	 * Check if a user is an active mentor.
	 *
	 * @param int $user_id User id.
	 */
	public function is_mentor( int $user_id ): bool {
		$cache_key = 'is_mentor_' . $user_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_profiles} WHERE user_id = %d AND is_active = 1",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$result = $count > 0;
		$this->cache_set( $cache_key, $result ? 1 : 0 );
		return $result;
	}

	/**
	 * Supported session types.
	 */
	public function session_types(): array {
		return array( 'video', 'audio', 'chat' );
	}

	/**
	 * Get per-session-type rates for a mentor.
	 * Stored as user meta (JSON). Any type without an explicit rate falls
	 * back to the profile hourly_rate.
	 *
	 * @return array<string,float> Rate keyed by session type.
	 * @param int $user_id Mentor user ID.
	 */
	public function get_session_rates( int $user_id ): array {
		$saved   = get_user_meta( $user_id, 'zeko_mentor_session_rates', true );
		$saved   = is_array( $saved ) ? $saved : array();
		$profile = $this->get_profile( $user_id );
		$base    = (float) ( $profile['hourly_rate'] ?? 0 );
		$rates   = array();

		foreach ( $this->session_types() as $type ) {
			$rate           = isset( $saved[ $type ] ) ? (float) $saved[ $type ] : 0;
			$rates[ $type ] = $rate > 0 ? $rate : $base;
		}

		return $rates;
	}

	/**
	 * Save per-session-type rates for a mentor.
	 *
	 * @return bool
	 * @param int   $user_id Mentor user ID.
	 * @param array $rates Rate per session type.
	 */
	public function set_session_rates( int $user_id, array $rates ): bool {
		$clean = array();
		foreach ( $this->session_types() as $type ) {
			$rate = (float) ( $rates[ $type ] ?? 0 );
			if ( $rate > 0 ) {
				$clean[ $type ] = number_format( $rate, 2, '.', '' );
			}
		}

		if ( empty( $clean ) ) {
			$result = delete_user_meta( $user_id, 'zeko_mentor_session_rates' );
		} else {
			$result = (bool) update_user_meta( $user_id, 'zeko_mentor_session_rates', $clean );
		}

		if ( $result ) {
			/**
			 * Fires after a mentor's per-session-type rates change.
			 *
			 * @param int $user_id Mentor user ID.
			 */
			do_action( 'zeko_mentor_rates_updated', $user_id );
		}

		return $result;
	}

	/**
	 * Create or update a mentor profile.
	 *
	 * @param int   $user_id User id.
	 * @param array $data Data.
	 */
	public function save_profile( int $user_id, array $data ): int {
		$existing = $this->get_profile( $user_id );
		$new_rate = (float) ( $data['hourly_rate'] ?? 0 );
		$old_rate = $existing ? (float) ( $existing['hourly_rate'] ?? 0 ) : 0.0;

		$save_data = array(
			'user_id'          => $user_id,
			'expertise_areas'  => wp_json_encode( array_map( 'sanitize_text_field', $data['expertise_areas'] ?? array() ) ),
			'bio'              => self::sanitize_rich( $data['bio'] ?? '' ),
			'headline'         => sanitize_text_field( $data['headline'] ?? '' ),
			'location'         => sanitize_text_field( $data['location'] ?? '' ),
			'languages'        => sanitize_text_field( $data['languages'] ?? '' ),
			'years_experience' => absint( $data['years_experience'] ?? 0 ),
			'response_time'    => sanitize_text_field( $data['response_time'] ?? '' ),
			'linkedin_url'     => esc_url_raw( $data['linkedin_url'] ?? '' ),
			'github_url'       => esc_url_raw( $data['github_url'] ?? '' ),
			'twitter_url'      => esc_url_raw( $data['twitter_url'] ?? '' ),
			'hourly_rate'      => (float) ( $data['hourly_rate'] ?? 0 ),
			'currency'         => sanitize_text_field( $data['currency'] ?? 'USD' ),
			'is_active'        => ! empty( $data['is_active'] ) ? 1 : 0,
			'max_mentees'      => absint( $data['max_mentees'] ?? 5 ),
			'updated_at'       => current_time( 'mysql', true ),
		);

		if ( $existing ) {
			$this->wpdb->update(
				$this->table_profiles,
				$save_data,
				array( 'user_id' => $user_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
			$this->invalidate_profile_cache( $user_id );
			if ( abs( $new_rate - $old_rate ) > 0.001 ) {
				do_action( 'zeko_mentor_rates_updated', $user_id );
			}
			return (int) $existing['profile_id'];
		}

		$save_data['created_at'] = current_time( 'mysql', true );
		$this->wpdb->insert(
			$this->table_profiles,
			$save_data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s' )
		);
		$this->invalidate_profile_cache( $user_id );
		do_action( 'zeko_mentor_rates_updated', $user_id );
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get mentors with filters.
	 *
	 * @param array $args Args.
	 */
	public function get_mentors( array $args = array() ): array {
		$defaults = array(
			'expertise'   => '',
			'min_rating'  => 0,
			'max_rate'    => 0,
			'min_rate'    => 0,
			'is_verified' => null,
			'is_active'   => 1,
			'search'      => '',
			'orderby'     => 'avg_rating',
			'order'       => 'DESC',
			'limit'       => 20,
			'offset'      => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( null !== $args['is_active'] ) {
			$where[]  = 'p.is_active = %d';
			$values[] = (int) $args['is_active'];
		}
		if ( null !== $args['is_verified'] ) {
			$where[]  = 'p.is_verified = %d';
			$values[] = (int) $args['is_verified'];
		}
		if ( $args['min_rating'] > 0 ) {
			$where[]  = 'p.avg_rating >= %f';
			$values[] = (float) $args['min_rating'];
		}
		if ( $args['max_rate'] > 0 ) {
			$where[]  = 'p.hourly_rate <= %f';
			$values[] = (float) $args['max_rate'];
		}
		if ( $args['min_rate'] > 0 ) {
			$where[]  = 'p.hourly_rate >= %f';
			$values[] = (float) $args['min_rate'];
		}
		if ( $args['expertise'] ) {
			$where[]  = 'p.expertise_areas LIKE %s';
			$values[] = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['expertise'] ) ) . '%';
		}
		if ( $args['search'] ) {
			$where[]  = '(u.display_name LIKE %s OR p.bio LIKE %s)';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		$allowed_orderby = array( 'avg_rating', 'hourly_rate', 'total_sessions', 'created_at', 'profile_views' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? 'p.' . $args['orderby'] : 'p.avg_rating';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_sql = implode( ' AND ', $where );
		$prepare   = array_merge( $values, array( $limit, $offset ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT p.*, u.display_name AS mentor_name, u.user_email AS mentor_email, u.user_nicename AS mentor_slug
				FROM {$this->table_profiles} p
				LEFT JOIN {$this->wpdb->users} u ON p.user_id = u.ID
				WHERE {$where_sql}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				...$prepare
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Count mentors matching filters.
	 *
	 * @param array $args Args.
	 */
	public function get_mentor_count( array $args = array() ): int {
		$defaults = array(
			'expertise'   => '',
			'min_rating'  => 0,
			'max_rate'    => 0,
			'min_rate'    => 0,
			'is_verified' => null,
			'is_active'   => 1,
			'search'      => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( null !== $args['is_active'] ) {
			$where[]  = 'p.is_active = %d';
			$values[] = (int) $args['is_active'];
		}
		if ( null !== $args['is_verified'] ) {
			$where[]  = 'p.is_verified = %d';
			$values[] = (int) $args['is_verified'];
		}
		if ( $args['min_rating'] > 0 ) {
			$where[]  = 'p.avg_rating >= %f';
			$values[] = (float) $args['min_rating'];
		}
		if ( $args['max_rate'] > 0 ) {
			$where[]  = 'p.hourly_rate <= %f';
			$values[] = (float) $args['max_rate'];
		}
		if ( $args['min_rate'] > 0 ) {
			$where[]  = 'p.hourly_rate >= %f';
			$values[] = (float) $args['min_rate'];
		}
		if ( $args['expertise'] ) {
			$where[]  = 'p.expertise_areas LIKE %s';
			$values[] = '%' . $this->wpdb->esc_like( sanitize_text_field( $args['expertise'] ) ) . '%';
		}
		if ( $args['search'] ) {
			$where[]  = '(u.display_name LIKE %s OR p.bio LIKE %s)';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $values ) ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_profiles} p LEFT JOIN {$this->wpdb->users} u ON p.user_id = u.ID WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
					...$values
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_profiles} p LEFT JOIN {$this->wpdb->users} u ON p.user_id = u.ID WHERE {$where_sql}"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Get featured mentors (top-rated verified).
	 *
	 * @param int $limit Limit.
	 */
	public function get_featured_mentors( int $limit = 6 ): array {
		$cache_key = 'featured_mentors_' . $limit;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.*, u.display_name AS mentor_name
				FROM {$this->table_profiles} p
				LEFT JOIN {$this->wpdb->users} u ON p.user_id = u.ID
				WHERE p.is_active = 1 AND p.is_verified = 1
				ORDER BY p.avg_rating DESC, p.total_sessions DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $rows );
		return $rows;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Increment profile views.
	 *
	 * @param int $user_id User id.
	 */
	public function increment_profile_views( int $user_id ): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_profiles} SET profile_views = profile_views + 1 WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Update mentor avg_rating and total_sessions cache.
	 *
	 * @param int $user_id User id.
	 */
	public function update_mentor_stats( int $user_id ): void {
		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating
				FROM {$this->table_sessions}
				WHERE mentor_id = %d AND status = 'completed' AND rating IS NOT NULL",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$this->wpdb->update(
			$this->table_profiles,
			array(
				'total_sessions' => absint( $stats['total'] ?? 0 ),
				'avg_rating'     => (float) ( $stats['avg_rating'] ?? 0 ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%f', '%s' ),
			array( '%d' )
		);

		$this->invalidate_profile_cache( $user_id );
	}

	// ═══════════════════════════════════════════════════════════════.
	// AVAILABILITY QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get all availability slots for a mentor.
	 *
	 * @param int $user_id User id.
	 */
	public function get_availability( int $user_id ): array {
		$cache_key = 'availability_' . $user_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_availability} WHERE user_id = %d AND is_active = 1 ORDER BY day_of_week ASC, start_time ASC",
				$user_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $rows );
		return $rows;
	}

	/**
	 * Save availability slots (batch replace).
	 *
	 * @param int   $user_id User id.
	 * @param array $slots Slots.
	 */
	public function save_availability( int $user_id, array $slots ): void {
		$this->wpdb->delete(
			$this->table_availability,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		foreach ( $slots as $slot ) {
			$day = $slot['day_of_week'];
			if ( ! is_numeric( $day ) ) {
				$day = $this->day_name_to_number( $day );
			}
			$this->wpdb->insert(
				$this->table_availability,
				array(
					'user_id'     => $user_id,
					'day_of_week' => absint( $day ),
					'start_time'  => sanitize_text_field( $slot['start_time'] ),
					'end_time'    => sanitize_text_field( $slot['end_time'] ),
					'timezone'    => sanitize_text_field( $slot['timezone'] ?? 'UTC' ),
					'is_active'   => 1,
					'created_at'  => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);
		}

		wp_cache_delete( 'availability_' . $user_id, 'zeko_mentor' );
	}

	/**
	 * Day name to number.
	 *
	 * @param string $day Day.
	 */
	private function day_name_to_number( string $day ): int {
		$map = array(
			'Sunday'    => 0,
			'Monday'    => 1,
			'Tuesday'   => 2,
			'Wednesday' => 3,
			'Thursday'  => 4,
			'Friday'    => 5,
			'Saturday'  => 6,
		);
		return $map[ ucfirst( strtolower( $day ) ) ] ?? 0;
	}

	/**
	 * Get available slots for a specific date.
	 *
	 * @param int    $mentor_id Mentor id.
	 * @param string $date Date.
	 */
	public function get_available_slots( int $mentor_id, string $date ): array {
		$day_of_week = (int) gmdate( 'w', strtotime( $date ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$slots = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_availability}
				WHERE user_id = %d AND day_of_week = %d AND is_active = 1
				ORDER BY start_time ASC",
				$mentor_id,
				$day_of_week
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		// Get booked sessions for this date.
		$booked = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT start_time FROM {$this->table_sessions}
				WHERE mentor_id = %d AND session_date = %s AND status IN ('pending','confirmed')
				ORDER BY start_time ASC",
				$mentor_id,
				$date
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$available = array();
		foreach ( $slots as $slot ) {
			$is_booked = false;
			foreach ( $booked as $b ) {
				if ( $b === $slot['start_time'] ) {
					$is_booked = true;
					break;
				}
			}
			if ( ! $is_booked ) {
				$available[] = $slot;
			}
		}

		return $available;
	}

	// ═══════════════════════════════════════════════════════════════.
	// SESSION QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get a session by ID.
	 *
	 * @param int $session_id Session id.
	 */
	public function get_session( int $session_id ): ?array {
		$cache_key = 'session_' . $session_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_sessions} WHERE session_id = %d LIMIT 1", $session_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $row ?: array() );
		return $row ?: null;
	}

	/**
	 * Get paid sessions linked to a wallet transaction reference.
	 * Used by the shop bridge to undo session payments when a shop order is
	 * refunded (sessions carry the order's tx id in payment_ref).
	 *
	 * @return array Session rows (ARRAY_A).
	 * @param int $tx_id Zeko Pay transaction ID stored in payment_ref.
	 * @param int $mentee_id Optional mentee filter (buyer user ID).
	 */
	public function get_sessions_by_payment_ref( int $tx_id, int $mentee_id = 0 ): array {
		if ( $tx_id <= 0 ) {
			return array();
		}

		$where  = array( 'payment_ref = %d' );
		$values = array( $tx_id );

		if ( $mentee_id > 0 ) {
			$where[]  = 'mentee_id = %d';
			$values[] = $mentee_id;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_sessions} WHERE " . implode( ' AND ', $where ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Create a session.
	 *
	 * @param array $data Data.
	 */
	public function create_session( array $data ): int {
		$this->wpdb->insert(
			$this->table_sessions,
			array(
				'mentor_id'        => absint( $data['mentor_id'] ),
				'mentee_id'        => absint( $data['mentee_id'] ),
				'session_date'     => sanitize_text_field( $data['session_date'] ),
				'start_time'       => sanitize_text_field( $data['start_time'] ),
				'end_time'         => sanitize_text_field( $data['end_time'] ),
				'timezone'         => sanitize_text_field( $data['timezone'] ?? 'UTC' ),
				'duration_minutes' => absint( $data['duration_minutes'] ?? 60 ),
				'status'           => sanitize_text_field( $data['status'] ?? 'pending' ),
				'session_type'     => sanitize_text_field( $data['session_type'] ?? 'video' ),
				'meeting_url'      => esc_url_raw( $data['meeting_url'] ?? '' ),
				'topic'            => sanitize_text_field( $data['topic'] ?? '' ),
				'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
				'amount'           => (float) ( $data['amount'] ?? 0 ),
				'currency'         => sanitize_text_field( $data['currency'] ?? 'USD' ),
				'payment_status'   => sanitize_text_field( $data['payment_status'] ?? 'pending' ),
				'created_at'       => current_time( 'mysql', true ),
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update session status.
	 *
	 * @param int   $session_id Session id.
	 * @param array $data Data.
	 */
	public function update_session( int $session_id, array $data ): bool {
		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		$allowed = array( 'status', 'mentor_notes', 'notes', 'rating', 'meeting_url', 'payment_status', 'payment_ref', 'transaction_code', 'payout_ref', 'session_date', 'start_time', 'end_time' );
		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = in_array( $field, array( 'rating' ), true ) ? '%d' : '%s';
			}
		}

		$result = (bool) $this->wpdb->update(
			$this->table_sessions,
			$update,
			array( 'session_id' => $session_id ),
			$format,
			array( '%d' )
		);

		if ( $result ) {
			wp_cache_delete( 'session_' . $session_id, 'zeko_mentor' );
			$this->flush_cache();
		}
		return $result;
	}

	/**
	 * Delete a session row (used to roll back unpaid bookings).
	 *
	 * @param int $session_id Session id.
	 */
	public function delete_session( int $session_id ): bool {
		$deleted = (bool) $this->wpdb->delete(
			$this->table_sessions,
			array( 'session_id' => $session_id ),
			array( '%d' )
		);
		if ( $deleted ) {
			wp_cache_delete( 'session_' . $session_id, 'zeko_mentor' );
			$this->flush_cache();
		}
		return $deleted;
	}

	/**
	 * Get sessions for a user (as mentor or mentee).
	 *
	 * @param int    $user_id User id.
	 * @param string $role Role.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 */
	public function get_user_sessions( int $user_id, string $role = 'any', string $status = '', int $limit = 20, int $offset = 0 ): array {
		$where  = array( '(s.mentor_id = %d OR s.mentee_id = %d)' );
		$values = array( $user_id, $user_id );

		if ( 'mentor' === $role ) {
			$where  = array( 's.mentor_id = %d' );
			$values = array( $user_id );
		} elseif ( 'mentee' === $role ) {
			$where  = array( 's.mentee_id = %d' );
			$values = array( $user_id );
		}

		if ( $status ) {
			$where[]  = 's.status = %s';
			$values[] = $status;
		}

		$values[]  = $limit;
		$values[]  = $offset;
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT s.*,
					u_mn.display_name AS mentor_name,
					u_me.display_name AS mentee_name
				FROM {$this->table_sessions} s
				LEFT JOIN {$this->wpdb->users} u_mn ON s.mentor_id = u_mn.ID
				LEFT JOIN {$this->wpdb->users} u_me ON s.mentee_id = u_me.ID
				WHERE {$where_sql}
				ORDER BY s.session_date DESC, s.start_time DESC
				LIMIT %d OFFSET %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Count active mentees for a mentor.
	 *
	 * @param int $mentor_id Mentor id.
	 */
	public function count_active_mentees( int $mentor_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(DISTINCT mentee_id) FROM {$this->table_sessions}
				WHERE mentor_id = %d AND status IN ('pending','confirmed') AND session_date >= CURDATE()",
				$mentor_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ═══════════════════════════════════════════════════════════════.
	// REVIEW QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get reviews for a mentor.
	 *
	 * @param int $mentor_id Mentor id.
	 * @param int $limit Limit.
	 * @param int $offset Offset.
	 */
	public function get_mentor_reviews( int $mentor_id, int $limit = 20, int $offset = 0 ): array {
		$cache_key = 'reviews_' . $mentor_id . '_' . $limit . '_' . $offset;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.*, u.display_name AS reviewer_name
				FROM {$this->table_reviews} r
				LEFT JOIN {$this->wpdb->users} u ON r.reviewer_id = u.ID
				WHERE r.mentor_id = %d AND r.is_public = 1
				ORDER BY r.created_at DESC
				LIMIT %d OFFSET %d",
				$mentor_id,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $rows );
		return $rows;
	}

	/**
	 * Get rating distribution (count per star) for a mentor.
	 *
	 * @param int $mentor_id Mentor id.
	 */
	public function get_mentor_rating_distribution( int $mentor_id ): array {
		$cache_key = 'rating_dist_' . $mentor_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT rating, COUNT(*) AS cnt FROM {$this->table_reviews}
				WHERE mentor_id = %d AND is_public = 1 AND rating > 0
				GROUP BY rating",
				$mentor_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$distribution = array(
			5 => 0,
			4 => 0,
			3 => 0,
			2 => 0,
			1 => 0,
		);
		$total        = 0;
		foreach ( $rows as $row ) {
			$rating = (int) $row['rating'];
			if ( isset( $distribution[ $rating ] ) ) {
				$distribution[ $rating ] = (int) $row['cnt'];
				$total                  += (int) $row['cnt'];
			}
		}
		$this->cache_set(
			$cache_key,
			array(
				'distribution' => $distribution,
				'total'        => $total,
			)
		);
		return array(
			'distribution' => $distribution,
			'total'        => $total,
		);
	}

	/**
	 * Insert a review.
	 *
	 * @param array $data Data.
	 */
	public function insert_review( array $data ): int {
		$this->wpdb->insert(
			$this->table_reviews,
			array(
				'session_id'  => absint( $data['session_id'] ),
				'mentor_id'   => absint( $data['mentor_id'] ),
				'reviewer_id' => absint( $data['reviewer_id'] ),
				'rating'      => absint( $data['rating'] ),
				'title'       => sanitize_text_field( $data['title'] ?? '' ),
				'review_text' => self::sanitize_rich( $data['review_text'] ?? '' ),
				'is_public'   => 1,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	// ═══════════════════════════════════════════════════════════════.
	// GOAL QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get goals for a user.
	 *
	 * @param int    $user_id User id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_user_goals( int $user_id, string $status = '', int $limit = 20 ): array {
		$where  = 'user_id = %d';
		$values = array( $user_id );

		if ( $status ) {
			$where   .= ' AND status = %s';
			$values[] = $status;
		}
		$values[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_goals} WHERE {$where} ORDER BY created_at DESC LIMIT %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a goal.
	 *
	 * @param array $data Data.
	 */
	public function insert_goal( array $data ): int {
		$this->wpdb->insert(
			$this->table_goals,
			array(
				'user_id'     => absint( $data['user_id'] ),
				'session_id'  => absint( $data['session_id'] ?? 0 ) ?: null,
				'program_id'  => absint( $data['program_id'] ?? 0 ) ?: null,
				'title'       => sanitize_text_field( $data['title'] ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'target_date' => sanitize_text_field( $data['target_date'] ?? '' ) ?: null,
				'status'      => 'active',
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a single goal by ID.
	 *
	 * @param int $goal_id Goal id.
	 */
	public function get_goal( int $goal_id ): ?array {
		$cache_key = 'goal_' . $goal_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_goals} WHERE goal_id = %d LIMIT 1", $goal_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $row ?: array() );
		return $row ?: null;
	}

	/**
	 * Update goal status.
	 *
	 * @param int   $goal_id Goal id.
	 * @param array $data Data.
	 */
	public function update_goal( int $goal_id, array $data ): bool {
		$update = array();
		$format = array();

		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$format[]         = '%s';
		}
		if ( empty( $update ) ) {
			return false;
		}

		$result = (bool) $this->wpdb->update(
			$this->table_goals,
			$update,
			array( 'goal_id' => $goal_id ),
			$format,
			array( '%d' )
		);
		if ( $result ) {
			$this->flush_cache();
		}
		return $result;
	}

	// ═══════════════════════════════════════════════════════════════.
	// PROGRESS QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get progress entries for a goal.
	 *
	 * @param int $goal_id Goal id.
	 * @param int $limit Limit.
	 */
	public function get_goal_progress( int $goal_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT pr.*, u.display_name AS author_name
				FROM {$this->table_progress} pr
				LEFT JOIN {$this->wpdb->users} u ON pr.user_id = u.ID
				WHERE pr.goal_id = %d
				ORDER BY pr.created_at DESC
				LIMIT %d",
				$goal_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a progress entry.
	 *
	 * @param array $data Data.
	 */
	public function insert_progress( array $data ): int {
		$this->wpdb->insert(
			$this->table_progress,
			array(
				'goal_id'    => absint( $data['goal_id'] ),
				'user_id'    => absint( $data['user_id'] ),
				'mentor_id'  => absint( $data['mentor_id'] ?? 0 ),
				'note'       => self::sanitize_rich( $data['note'] ?? '' ),
				'rating'     => isset( $data['rating'] ) ? absint( $data['rating'] ) : null,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	// ═══════════════════════════════════════════════════════════════.
	// MATCH QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get matches for a mentee.
	 *
	 * @param int    $mentee_id Mentee id.
	 * @param string $status Status.
	 * @param int    $limit Limit.
	 */
	public function get_mentee_matches( int $mentee_id, string $status = '', int $limit = 20 ): array {
		$where  = 'mentee_id = %d';
		$values = array( $mentee_id );

		if ( $status ) {
			$where   .= ' AND status = %s';
			$values[] = $status;
		}
		$values[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT m.*, p.avg_rating, p.hourly_rate, p.expertise_areas, u.display_name AS mentor_name
				FROM {$this->table_matches} m
				LEFT JOIN {$this->table_profiles} p ON m.mentor_id = p.user_id
				LEFT JOIN {$this->wpdb->users} u ON m.mentor_id = u.ID
				WHERE {$where}
				ORDER BY m.score DESC
				LIMIT %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Insert or update a match.
	 *
	 * @param int   $mentee_id Mentee id.
	 * @param int   $mentor_id Mentor id.
	 * @param float $score Score.
	 * @param array $reasons Reasons.
	 */
	public function save_match( int $mentee_id, int $mentor_id, float $score, array $reasons ): int {
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT match_id FROM {$this->table_matches} WHERE mentee_id = %d AND mentor_id = %d LIMIT 1",
				$mentee_id,
				$mentor_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$data = array(
			'mentee_id'     => $mentee_id,
			'mentor_id'     => $mentor_id,
			'score'         => $score,
			'match_reasons' => wp_json_encode( $reasons ),
			'status'        => 'suggested',
			'created_at'    => current_time( 'mysql', true ),
			'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
		);

		if ( $existing ) {
			$this->wpdb->update(
				$this->table_matches,
				array(
					'score'         => $score,
					'match_reasons' => wp_json_encode( $reasons ),
					'status'        => 'suggested',
					'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
				),
				array( 'match_id' => (int) $existing ),
				array( '%f', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		$this->wpdb->insert(
			$this->table_matches,
			$data,
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update match status.
	 *
	 * @param int    $match_id Match id.
	 * @param string $status Status.
	 */
	public function update_match( int $match_id, string $status ): bool {
		$result = (bool) $this->wpdb->update(
			$this->table_matches,
			array( 'status' => $status ),
			array( 'match_id' => $match_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( $result ) {
			$this->flush_cache();
		}
		return $result;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get a single match by ID.
	 *
	 * @param int $match_id Match id.
	 */
	public function get_match( int $match_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_matches} WHERE match_id = %d LIMIT 1", $match_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $row ?: null;
	}

	// ═══════════════════════════════════════════════════════════════.
	// PROGRAM QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get a program by ID.
	 *
	 * @param int $program_id Program id.
	 */
	public function get_program( int $program_id ): ?array {
		$cache_key = 'program_' . $program_id;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table_programs} WHERE program_id = %d LIMIT 1", $program_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->cache_set( $cache_key, $row ?: array() );
		return $row ?: null;
	}

	/**
	 * Get programs with filters.
	 *
	 * @param array $args Args.
	 */
	public function get_programs( array $args = array() ): array {
		$defaults = array(
			'status'    => 'active',
			'mentor_id' => 0,
			'expertise' => '',
			'orderby'   => 'start_date',
			'order'     => 'DESC',
			'limit'     => 20,
			'offset'    => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['mentor_id'] ) {
			$where[]  = 'mentor_id = %d';
			$values[] = $args['mentor_id'];
		}
		if ( $args['expertise'] ) {
			$where[]  = 'expertise_area LIKE %s';
			$values[] = '%' . $this->wpdb->esc_like( $args['expertise'] ) . '%';
		}

		$values[]  = $args['limit'];
		$values[]  = $args['offset'];
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT p.*, u.display_name AS mentor_name
				FROM {$this->table_programs} p
				LEFT JOIN {$this->wpdb->users} u ON p.mentor_id = u.ID
				WHERE {$where_sql}
				ORDER BY p.start_date DESC
				LIMIT %d OFFSET %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Create a program.
	 *
	 * @param array $data Data.
	 */
	public function create_program( array $data ): int {
		$this->wpdb->insert(
			$this->table_programs,
			array(
				'mentor_id'      => absint( $data['mentor_id'] ),
				'title'          => sanitize_text_field( $data['title'] ),
				'description'    => self::sanitize_rich( $data['description'] ?? '' ),
				'expertise_area' => sanitize_text_field( $data['expertise_area'] ?? '' ),
				'max_members'    => absint( $data['max_members'] ?? 20 ),
				'duration_weeks' => absint( $data['duration_weeks'] ?? 4 ),
				'price'          => (float) ( $data['price'] ?? 0 ),
				'currency'       => sanitize_text_field( $data['currency'] ?? 'USD' ),
				'status'         => sanitize_text_field( $data['status'] ?? 'draft' ),
				'start_date'     => sanitize_text_field( $data['start_date'] ?? '' ) ?: null,
				'end_date'       => sanitize_text_field( $data['end_date'] ?? '' ) ?: null,
				'created_at'     => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Check if a user is enrolled in a program.
	 *
	 * @param int $program_id Program id.
	 * @param int $user_id User id.
	 */
	public function is_enrolled( int $program_id, int $user_id ): bool {
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_program_members}
				WHERE program_id = %d AND user_id = %d",
				$program_id,
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $count > 0;
	}

	/**
	 * Enroll a user in a program.
	 *
	 * @param int    $program_id Program id.
	 * @param int    $user_id User id.
	 * @param string $role Role.
	 */
	public function enroll_program( int $program_id, int $user_id, string $role = 'mentee' ): int {
		$this->wpdb->insert(
			$this->table_program_members,
			array(
				'program_id'  => $program_id,
				'user_id'     => $user_id,
				'role'        => $role,
				'status'      => 'active',
				'enrolled_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_programs} SET current_members = current_members + 1 WHERE program_id = %d",
				$program_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get active members (users enrolled) for a program.
	 *
	 * @param int $program_id Program id.
	 * @param int $limit Limit.
	 */
	public function get_program_members( int $program_id, int $limit = 50 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT pm.user_id, pm.role, pm.status, pm.enrolled_at, u.display_name
				FROM {$this->table_program_members} pm
				INNER JOIN {$this->wpdb->users} u ON pm.user_id = u.ID
				WHERE pm.program_id = %d AND pm.status = 'active'
				ORDER BY pm.enrolled_at ASC
				LIMIT %d",
				$program_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get programs the given user is enrolled in.
	 *
	 * @param int $user_id User id.
	 * @param int $limit Limit.
	 */
	public function get_user_programs( int $user_id, int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.*, pm.role AS member_role, pm.enrolled_at AS enrolled_at, u.display_name AS mentor_name
				FROM {$this->table_program_members} pm
				INNER JOIN {$this->table_programs} p ON pm.program_id = p.program_id
				LEFT JOIN {$this->wpdb->users} u ON p.mentor_id = u.ID
				WHERE pm.user_id = %d AND pm.status = 'active'
				ORDER BY pm.enrolled_at DESC
				LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Remove a user from a program.
	 *
	 * @param int $program_id Program id.
	 * @param int $user_id User id.
	 */
	public function remove_program_member( int $program_id, int $user_id ): bool {
		$deleted = $this->wpdb->delete(
			$this->table_program_members,
			array(
				'program_id' => $program_id,
				'user_id'    => $user_id,
			),
			array( '%d', '%d' )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( $deleted ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$this->table_programs} SET current_members = GREATEST(current_members - 1, 0) WHERE program_id = %d",
					$program_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$this->flush_cache();
		}

		return (bool) $deleted;
	}

	// ═══════════════════════════════════════════════════════════════.
	// MESSAGE QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Get messages between two users.
	 *
	 * @param int $user_id User id.
	 * @param int $other_id Other id.
	 * @param int $limit Limit.
	 */
	public function get_messages( int $user_id, int $other_id, int $limit = 50 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_messages}
				WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)
				ORDER BY created_at ASC
				LIMIT %d",
				$user_id,
				$other_id,
				$other_id,
				$user_id,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Insert a message.
	 *
	 * @param array $data Data.
	 */
	public function insert_message( array $data ): int {
		$this->wpdb->insert(
			$this->table_messages,
			array(
				'session_id'  => absint( $data['session_id'] ?? 0 ) ?: null,
				'sender_id'   => absint( $data['sender_id'] ),
				'receiver_id' => absint( $data['receiver_id'] ),
				'message'     => self::sanitize_rich( $data['message'] ),
				'is_read'     => 0,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s' )
		);
		$this->flush_cache();
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Mark messages as read.
	 *
	 * @param int $sender_id Sender id.
	 * @param int $receiver_id Receiver id.
	 */
	public function mark_messages_read( int $sender_id, int $receiver_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_messages,
			array( 'is_read' => 1 ),
			array(
				'sender_id'   => $sender_id,
				'receiver_id' => $receiver_id,
				'is_read'     => 0,
			),
			array( '%d' ),
			array( '%d', '%d', '%d' )
		);
	}

	// ═══════════════════════════════════════════════════════════════.
	// NOTIFICATION QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Create a notification.
	 *
	 * @param array $data Data.
	 */
	public function create_notification( array $data ): int {
		$this->wpdb->insert(
			$this->table_notifications,
			array(
				'user_id'    => absint( $data['user_id'] ),
				'type'       => sanitize_text_field( $data['type'] ?? 'info' ),
				'title'      => sanitize_text_field( $data['title'] ?? '' ),
				'message'    => self::sanitize_rich( $data['message'] ?? '' ),
				'link'       => esc_url_raw( $data['link'] ?? '' ),
				'is_read'    => 0,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get notifications for a user.
	 *
	 * @param int  $user_id User id.
	 * @param int  $limit Limit.
	 * @param bool $unread_only Unread only.
	 */
	public function get_notifications( int $user_id, int $limit = 20, bool $unread_only = false ): array {
		$where  = 'user_id = %d';
		$values = array( $user_id );

		if ( $unread_only ) {
			$where .= ' AND is_read = 0';
		}

		$values[] = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_notifications} WHERE {$where} ORDER BY created_at DESC LIMIT %d",
				...$values
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	/**
	 * Count unread notifications for a user.
	 *
	 * @param int $user_id User id.
	 */
	public function count_unread_notifications( int $user_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_notifications} WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	/**
	 * Mark a notification as read.
	 *
	 * @param int $notification_id Notification id.
	 * @param int $user_id User id.
	 */
	public function mark_notification_read( int $notification_id, int $user_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_notifications,
			array( 'is_read' => 1 ),
			array(
				'notification_id' => $notification_id,
				'user_id'         => $user_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Mark all notifications as read for a user.
	 *
	 * @param int $user_id User id.
	 */
	public function mark_all_notifications_read( int $user_id ): bool {
		return (bool) $this->wpdb->update(
			$this->table_notifications,
			array( 'is_read' => 1 ),
			array(
				'user_id' => $user_id,
				'is_read' => 0,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Delete notifications older than N days.
	 *
	 * @param int $days Days.
	 */
	public function cleanup_old_notifications( int $days = 90 ): void {
		$utoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_notifications} WHERE created_at < %s",
				$utoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}

	// ═══════════════════════════════════════════════════════════════.
	// iCAL QUERIES.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get upcoming sessions for iCal export.
	 * Returns sessions where the user is either the mentor or mentee,
	 * with full date/time info, meeting URL, topic, and participant names.
	 *
	 * @return array Session rows (ARRAY_A).
	 * @param int    $user_id User ID to query sessions for.
	 * @param string $since Only include sessions on or after this date (Y-m-d).
	 * @param int    $limit Maximum number of sessions to return.
	 */
	public function get_sessions_for_ical( int $user_id, string $since, int $limit = 100 ): array {
		$cache_key = 'ical_sessions_' . $user_id . '_' . $since;
		$cached    = $this->cache_get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT s.*,
					u_mn.display_name AS mentor_name,
					u_mn.user_email AS mentor_email,
					u_me.display_name AS mentee_name,
					u_me.user_email AS mentee_email
				FROM {$this->table_sessions} s
				LEFT JOIN {$this->wpdb->users} u_mn ON s.mentor_id = u_mn.ID
				LEFT JOIN {$this->wpdb->users} u_me ON s.mentee_id = u_me.ID
				WHERE (s.mentor_id = %d OR s.mentee_id = %d)
				  AND s.session_date >= %s
				  AND s.status IN ('pending','confirmed')
				ORDER BY s.session_date ASC, s.start_time ASC
				LIMIT %d",
				$user_id,
				$user_id,
				$since,
				$limit
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$this->cache_set( $cache_key, $rows, 60 );
		return $rows;
	}
}
