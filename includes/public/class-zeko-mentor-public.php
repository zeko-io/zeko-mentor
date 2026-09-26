<?php
/**
 * Public frontend handler for Zeko Mentor.
 *
 * Registers shortcodes, template rendering, and availability management.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Public. */
class Zeko_Mentor_Public {

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * Construct.
	 *
	 * @param Zeko_Mentor_DB $db Db.
	 */
	public function __construct( Zeko_Mentor_DB $db ) {
		$this->db = $db;

		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_profile_template' ) );
		add_action( 'template_redirect', array( $this, 'handle_program_template' ) );
		add_action( 'template_redirect', array( $this, 'handle_ical_feed' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'zeko_mentor_browse', array( $this, 'shortcode_browse' ) );
		add_shortcode( 'zeko_mentor_profile', array( $this, 'shortcode_profile' ) );
		add_shortcode( 'zeko_mentor_dashboard', array( $this, 'shortcode_dashboard' ) );
		add_shortcode( 'zeko_mentor_programs', array( $this, 'shortcode_programs' ) );
		add_shortcode( 'zeko_mentor_matches', array( $this, 'shortcode_matches' ) );
		add_shortcode( 'zeko_mentor_apply', array( $this, 'shortcode_apply' ) );
		add_shortcode( 'zeko_mentor_session', array( $this, 'shortcode_session' ) );
		add_shortcode( 'zeko_mentor_inbox', array( $this, 'shortcode_inbox' ) );
		add_shortcode( 'zeko_mentor_availability', array( $this, 'shortcode_availability' ) );
		add_shortcode( 'zeko_mentor_calendar', array( $this, 'shortcode_calendar' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets(): void {
		$load = (bool) get_query_var( 'zeko_mentor_profile', '' );
		$load = $load || (bool) get_query_var( 'zeko_mentor_program', '' );

		if ( ! $load && ( is_singular() || is_page() ) ) {
			global $post;
			$load = $post && (
				has_shortcode( $post->post_content, 'zeko_mentor_browse' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_profile' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_dashboard' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_programs' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_matches' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_apply' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_session' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_inbox' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_availability' )
				|| has_shortcode( $post->post_content, 'zeko_mentor_calendar' )
			);
		}

		if ( $load ) {
			wp_enqueue_style(
				'zeko-mentor',
				'assets/css/zeko-mentor.css',
				array( 'zeko-core' ),
				ZEKO_MENTOR_VERSION
			);
			wp_enqueue_script(
				'zeko-mentor',
				ZEKO_MENTOR_PLUGIN_URL . 'assets/js/zeko-mentor.js',
				array( 'jquery' ),
				ZEKO_MENTOR_VERSION,
				true
			);
			$timezone = '';
			if ( is_user_logged_in() ) {
				$user_tz = get_user_meta( get_current_user_id(), 'zeko_timezone', true );
				if ( $user_tz ) {
					$timezone = $user_tz;
				}
			}
			wp_localize_script(
				'zeko-mentor',
				'zekoMentor',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'zeko_mentor_nonce' ),
					'timezone' => $timezone,
					'i18n'     => array(
						'confirm'   => __( 'Are you sure?', 'zeko-mentor' ),
						'booked'    => __( 'Session booked successfully!', 'zeko-mentor' ),
						'cancelled' => __( 'Session cancelled.', 'zeko-mentor' ),
						'error'     => __( 'Something went wrong. Please try again.', 'zeko-mentor' ),
						'pickDate'  => __( 'Please choose a date first.', 'zeko-mentor' ),
						'pickTime'  => __( 'Please select an available time slot.', 'zeko-mentor' ),
						'noSlots'   => __( 'No available times on this date.', 'zeko-mentor' ),
						'enrolled'  => __( 'Enrolled', 'zeko-mentor' ),
					),
				)
			);
		}
	}

	/**
	 * [zeko_mentor_browse] — Mentor directory.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_browse( $atts ): string {
		unset( $atts );
		if ( ! current_user_can( 'read' ) ) {
			return '<p>' . esc_html__( 'Please log in to browse mentors.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-browse.php';
		if ( ! file_exists( $template ) ) {
			return '<p>Mentor browse template not found.</p>';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_profile] — Single mentor profile.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_profile( $atts ): string {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'zeko_mentor_profile' );
		$user_id = absint( $atts['id'] ) ?: absint( wp_unslash( $_GET['mentor'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET profile id for display; no state change.

		if ( ! $user_id ) {
			return '<p>' . esc_html__( 'Mentor not found.', 'zeko-mentor' ) . '</p>';
		}

		$profile = $this->db->get_profile( $user_id );
		if ( ! $profile ) {
			return '<p>' . esc_html__( 'Mentor profile not found.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-profile.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	// ── Rewrite rules & routing ─────────────────────────────.

	/**
	 * Register rewrite rule for /mentors/{slug}/ profile pages.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^mentors/([^/]+)/?$',
			'index.php?zeko_mentor_profile=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^programs/([0-9]+)/?$',
			'index.php?zeko_mentor_program=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^mentor/ical/([a-f0-9]+)/?$',
			'index.php?zeko_mentor_ical=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Vars.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'zeko_mentor_profile';
		$vars[] = 'zeko_mentor_program';
		$vars[] = 'zeko_mentor_ical';
		return $vars;
	}

	/**
	 * Handle /mentors/{slug}/ requests via template_redirect.
	 */
	public function handle_profile_template(): void {
		$slug = get_query_var( 'zeko_mentor_profile', '' );
		if ( ! $slug ) {
			return;
		}

		$user = get_user_by( 'slug', $slug );
		if ( ! $user ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			include get_query_template( '404' );
			exit;
		}

		$profile = $this->db->get_profile( $user->ID );
		if ( ! $profile ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			include get_query_template( '404' );
			exit;
		}

		$_GET['mentor'] = (string) $user->ID;

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-profile.php';
		status_header( 200 );
		get_header();
		include $template;
		get_footer();
		exit;
	}

	/**
	 * Handle /programs/{id}/ requests via template_redirect.
	 */
	public function handle_program_template(): void {
		$program_id = absint( get_query_var( 'zeko_mentor_program', 0 ) );
		if ( ! $program_id ) {
			return;
		}

		$program = $this->db->get_program( $program_id );
		if ( ! $program ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			include get_query_template( '404' );
			exit;
		}

		$_GET['program'] = (string) $program_id;

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/program-single.php';
		status_header( 200 );
		get_header();
		include $template;
		get_footer();
		exit;
	}

	/**
	 * Handle /mentor/ical/{token}/ requests.
	 */
	public function handle_ical_feed(): void {
		$token = get_query_var( 'zeko_mentor_ical', '' );
		if ( ! $token ) {
			return;
		}
		$this->serve_ical( $token );
	}

	/**
	 * Validate the iCal token and output the VCALENDAR feed.
	 *
	 * @param string $token Random 32-char hex token from the URL.
	 */
	private function serve_ical( string $token ): void {
		if ( strlen( $token ) !== 32 || ! ctype_xdigit( $token ) ) {
			status_header( 404 );
			exit;
		}

		$user_id = $this->resolve_ical_token( $token );
		if ( ! $user_id ) {
			status_header( 404 );
			exit;
		}

		$user     = get_userdata( $user_id );
		$since    = gmdate( 'Y-m-d' );
		$sessions = $this->db->get_sessions_for_ical( $user_id, $since );

		$site_name = get_bloginfo( 'name' );
		$cal_name  = sprintf(
			/* translators: %s: site name */
			__( '%s — Mentorship Sessions', 'zeko-mentor' ),
			$site_name
		);

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mentorship-sessions.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		echo "BEGIN:VCALENDAR\r\n";
		echo "VERSION:2.0\r\n";
		echo 'PRODID:-//' . esc_attr( $site_name ) . "//Zeko Mentor//EN\r\n";
		echo "CALSCALE:GREGORIAN\r\n";
		echo "METHOD:PUBLISH\r\n";
		echo 'X-WR-CALNAME:' . esc_attr( $cal_name ) . "\r\n";

		$now_utc = gmdate( 'Ymd\THis\Z' );

		foreach ( $sessions as $session ) {
			$session_date = $session['session_date'];
			$start_time   = $session['start_time'];
			$end_time     = $session['end_time'];
			$timezone     = $session['timezone'] ?: 'UTC';
			$mentor_name  = $session['mentor_name'] ?: __( 'Mentor', 'zeko-mentor' );
			$mentee_name  = $session['mentee_name'] ?: __( 'Mentee', 'zeko-mentor' );
			$topic        = $session['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' );
			$meeting_url  = $session['meeting_url'] ?? '';

			$dt_start = $this->ical_datetime( $session_date, $start_time, $timezone );
			$dt_end   = $this->ical_datetime( $session_date, $end_time, $timezone );

			$uid = sprintf(
				'session-%d-%s@%s',
				$session['session_id'],
				md5( home_url() ),
				'session'
			);

			$description = sprintf(
				/* translators: 1: topic, 2: mentor name, 3: mentee name, 4: meeting URL */
				__( "Topic: %1\$s\nMentor: %2\$s\nMentee: %3\$s\nMeeting: %4\$s", 'zeko-mentor' ),
				$topic,
				$mentor_name,
				$mentee_name,
				$meeting_url
			);

			echo "BEGIN:VEVENT\r\n";
			echo 'UID:' . esc_attr( $uid ) . "\r\n";
			echo 'DTSTAMP:' . esc_attr( $now_utc ) . "\r\n";
			echo 'DTSTART;TZID=' . esc_attr( $timezone ) . ':' . esc_attr( $dt_start ) . "\r\n";
			echo 'DTEND;TZID=' . esc_attr( $timezone ) . ':' . esc_attr( $dt_end ) . "\r\n";
			echo 'SUMMARY:' . esc_attr( $topic ) . "\r\n";
			if ( $meeting_url ) {
				echo 'LOCATION:' . esc_attr( $meeting_url ) . "\r\n";
			}
			echo 'DESCRIPTION:' . esc_attr( $description ) . "\r\n";
			echo 'STATUS:' . ( 'confirmed' === $session['status'] ? 'CONFIRMED' : 'TENTATIVE' ) . "\r\n";
			echo "END:VEVENT\r\n";
		}

		echo "END:VCALENDAR\r\n";
		exit;
	}

	/**
	 * Convert a date, time, and timezone into an iCal local datetime string.
	 *
	 * @return string iCal datetime string (Ymd\THis).
	 * @param string $date Y-m-d date string.
	 * @param string $time H:i:s time string.
	 * @param string $timezone IANA timezone identifier.
	 */
	private function ical_datetime( string $date, string $time, string $timezone ): string {
		$dt_string = $date . ' ' . substr( $time, 0, 8 );
		$dt        = new \DateTime( $dt_string, new \DateTimeZone( 'UTC' ) );
		$dt->setTimezone( new \DateTimeZone( $timezone ) );
		return $dt->format( 'Ymd\THis' );
	}

	/**
	 * Resolve an iCal token to a user ID.
	 *
	 * @return int User ID or 0 if not found.
	 * @param string $token 32-char hex token.
	 */
	private function resolve_ical_token( string $token ): int {
		global $wpdb;
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'zeko_mentor_ical_token' AND meta_value = %s LIMIT 1",
				$token
			)
		);
		return $user_id ? (int) $user_id : 0;
	}

	/**
	 * Get or create the iCal subscription token for a user.
	 *
	 * @return string 32-char hex token.
	 * @param int $user_id User ID.
	 */
	public function get_ical_token( int $user_id ): string {
		$token = get_user_meta( $user_id, 'zeko_mentor_ical_token', true );
		if ( $token && strlen( $token ) === 32 && ctype_xdigit( $token ) ) {
			return $token;
		}
		$token = bin2hex( random_bytes( 16 ) );
		update_user_meta( $user_id, 'zeko_mentor_ical_token', $token );
		return $token;
	}

	/**
	 * Get the full iCal feed URL for a user.
	 *
	 * @return string Full URL to the .ics feed.
	 * @param int $user_id User ID.
	 */
	public function get_ical_url( int $user_id ): string {
		$token = $this->get_ical_token( $user_id );
		return home_url( '/mentor/ical/' . $token . '/' );
	}

	/**
	 * [zeko_mentor_dashboard] — Mentee/Mentor dashboard.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_dashboard( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-dashboard.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_programs] — Program directory.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_programs( $_atts ): string {
		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/program-list.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_matches] — Match suggestion page for mentees.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_matches( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your matches.', 'zeko-mentor' ) . '</p>';
		}

		$user_id  = get_current_user_id();
		$matches  = $this->db->get_mentee_matches( $user_id, '', 20 );
		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-matches.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_apply] — Become-a-mentor application form.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_apply( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to apply as a mentor.', 'zeko-mentor' ) . '</p>';
		}

		$user_id  = get_current_user_id();
		$existing = $this->db->get_profile( $user_id );

		if ( $existing && 'approved' === ( $existing['verification_status'] ?? '' ) ) {
			return '<div class="zeko-mentor-apply-notice" style="background:#d1fae5;border:1px solid #6ee7b7;padding:16px;border-radius:8px;">
				<p style="color:#065f46;margin:0;">✅ ' . esc_html__( 'You are already a verified mentor!', 'zeko-mentor' ) . '</p>
			</div>';
		}

		if ( $existing && 'pending' === ( $existing['verification_status'] ?? '' ) ) {
			return '<div class="zeko-mentor-apply-notice" style="background:#fef3c7;border:1px solid #fcd34d;padding:16px;border-radius:8px;">
				<p style="color:#92400e;margin:0;">⏳ ' . esc_html__( 'Your application is under review. We\'ll notify you once approved.', 'zeko-mentor' ) . '</p>
			</div>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-apply.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_session] — Session detail page with video call button.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_session( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view session details.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/session-detail.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_inbox] — Full messaging inbox with threads.
	 *
	 * @param _ $_atts atts.
	 */
	public function shortcode_inbox( $_atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view messages.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-inbox.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_availability] — Mentor availability management UI.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_availability( $atts ): string {
		unset( $atts );
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to manage availability.', 'zeko-mentor' ) . '</p>';
		}

		$user_id = get_current_user_id();
		if ( ! $this->db->is_mentor( $user_id ) ) {
			return '<p>' . esc_html__( 'Only mentors can manage availability.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-availability.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [zeko_mentor_calendar] — Visual month/mini calendar for browsing slots.
	 *
	 * @param mixed $atts Atts.
	 */
	public function shortcode_calendar( $atts ): string {
		$atts      = shortcode_atts( array( 'id' => 0 ), $atts, 'zeko_mentor_calendar' );
		$mentor_id = absint( $atts['id'] );

		$profile = $mentor_id ? $this->db->get_profile( $mentor_id ) : null;
		if ( $mentor_id && ! $profile ) {
			return '<p>' . esc_html__( 'Mentor not found.', 'zeko-mentor' ) . '</p>';
		}

		$template = ZEKO_MENTOR_PLUGIN_PATH . 'templates/mentor-calendar.php';
		ob_start();
		include $template;
		return ob_get_clean();
	}
}
