<?php
/**
 * Email notifications for Zeko Mentor.
 *
 * Sends transactional emails for bookings, reviews, reminders, and matches.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Emails. */
class Zeko_Mentor_Emails {

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

		add_action( 'zeko_mentor_session_booked', array( $this, 'on_session_booked' ), 10, 3 );
		add_action( 'zeko_mentor_session_completed', array( $this, 'on_session_completed' ), 10, 1 );
		add_action( 'zeko_mentor_session_cancelled', array( $this, 'on_session_cancelled' ), 10, 1 );
		add_action( 'zeko_mentor_review_posted', array( $this, 'on_review_posted' ), 10, 2 );
		add_action( 'zeko_mentor_goal_achieved', array( $this, 'on_goal_achieved' ), 10, 1 );
	}

	/**
	 * Session booked — notify mentor.
	 *
	 * @param int $session_id Session id.
	 * @param int $mentor_id Mentor id.
	 * @param int $mentee_id Mentee id.
	 */
	public function on_session_booked( int $session_id, int $mentor_id, int $mentee_id ): void {
		$session = $this->db->get_session( $session_id );
		$mentor  = get_userdata( $mentor_id );
		$mentee  = get_userdata( $mentee_id );

		if ( ! $mentor || ! $mentee || ! $session ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: mentee name */
			__( 'New booking from %s', 'zeko-mentor' ),
			$mentee->display_name
		);

		$message = sprintf(
			/* translators: 1: mentee name, 2: session date, 3: session time, 4: topic */
			__( "Hi %1\$s,\n\nYou have a new mentorship session booked!\n\nMentee: %1\$s\nDate: %2\$s\nTime: %3\$s\nTopic: %4\$s\n\nPlease confirm or reschedule.", 'zeko-mentor' ),
			$mentee->display_name,
			$session['session_date'],
			$session['start_time'],
			$session['topic'] ?: __( 'Not specified', 'zeko-mentor' )
		);

		$this->send( $mentor->user_email, $subject, $message );
	}

	/**
	 * Session completed — notify mentee to review.
	 *
	 * @param int $session_id Session id.
	 */
	public function on_session_completed( int $session_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$mentee = get_userdata( (int) $session['mentee_id'] );
		$mentor = get_userdata( (int) $session['mentor_id'] );

		if ( ! $mentee || ! $mentor ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: mentor name */
			__( 'Session with %s completed — Leave a review!', 'zeko-mentor' ),
			$mentor->display_name
		);

		$message = sprintf(
			/* translators: 1: mentee name, 2: mentor name, 3: session topic */
			__( "Hi %1\$s,\n\nYour mentorship session with %2\$s has been completed.\n\nTopic: %3\$s\n\nWe'd love to hear your feedback! Please take a moment to leave a review.", 'zeko-mentor' ),
			$mentee->display_name,
			$mentor->display_name,
			$session['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' )
		);

		$this->send( $mentee->user_email, $subject, $message );
	}

	/**
	 * Session cancelled — notify the other party.
	 *
	 * @param int $session_id Session id.
	 */
	public function on_session_cancelled( int $session_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$canceller = get_current_user_id();
		$notify_id = ( $canceller === (int) $session['mentor_id'] ) ? (int) $session['mentee_id'] : (int) $session['mentor_id'];
		$notify    = get_userdata( $notify_id );

		if ( ! $notify ) {
			return;
		}

		$subject = __( 'Mentorship session cancelled', 'zeko-mentor' );
		$message = sprintf(
			/* translators: %s: notification user name */
			__( "Hi %1\$s,\n\nA mentorship session has been cancelled.\n\nDate: %2\$s\nTime: %3\$s\n\nIf you have questions, please reach out.", 'zeko-mentor' ),
			$notify->display_name,
			$session['session_date'],
			$session['start_time']
		);

		$this->send( $notify->user_email, $subject, $message );
	}

	/**
	 * Review posted — notify mentor.
	 *
	 * @param int $session_id Session id.
	 * @param int $reviewer_id Reviewer id.
	 */
	public function on_review_posted( int $session_id, int $reviewer_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$mentor   = get_userdata( (int) $session['mentor_id'] );
		$reviewer = get_userdata( $reviewer_id );

		if ( ! $mentor || ! $reviewer ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: reviewer name */
			__( 'New review from %s', 'zeko-mentor' ),
			$reviewer->display_name
		);

		$message = sprintf(
			/* translators: %s: mentor name */
			__( "Hi %s,\n\nYou've received a new review!\n\nCheck your mentor dashboard for details.", 'zeko-mentor' ),
			$mentor->display_name
		);

		$this->send( $mentor->user_email, $subject, $message );
	}

	/**
	 * Goal achieved — congratulate mentee.
	 *
	 * @param int $goal_id Goal id.
	 */
	public function on_goal_achieved( int $goal_id ): void {
		global $wpdb;
		$table = $this->db->get_table_goals();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$goal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE goal_id = %d LIMIT 1", $goal_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		if ( ! $goal ) {
			return;
		}

		$user = get_userdata( (int) $goal['user_id'] );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Congratulations — goal achieved!', 'zeko-mentor' );
		$message = sprintf(
			/* translators: 1: user name, 2: goal title */
			__( "Hi %1\$s,\n\nCongratulations! You've achieved your goal: \"%2\$s\"\n\nKeep up the great work on your mentorship journey!", 'zeko-mentor' ),
			$user->display_name,
			$goal['title']
		);

		$this->send( $user->user_email, $subject, $message );
	}

	/**
	 * Send an email.
	 * Plain-text messages are lifted into a consistent branded HTML shell.
	 *
	 * @return bool Whether the email was sent.
	 * @param string $to To.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 */
	private function send( string $to, string $subject, string $message ): bool {
		if ( zeko_mentor_is_demo_email( $to ) ) {
			return false;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $to, $subject, $this->wrap_template( $subject, $this->plain_body_to_html( $message ) ), $headers );
	}

	/**
	 * Convert plain-text message into escaped HTML line blocks.
	 *
	 * @return string HTML safe to print.
	 * @param string $text Plain text.
	 */
	private function plain_body_to_html( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$paragraphs = preg_split( '/\n{2,}/', trim( $text ) );
		$paragraphs = is_array( $paragraphs ) ? $paragraphs : array( trim( $text ) );

		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			$html .= '<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#334155;">' . nl2br( esc_html( $paragraph ) ) . '</p>';
		}
		return $html;
	}

	/**
	 * Wrap Mentor email content in a branded HTML document.
	 *
	 * @return string Full HTML email.
	 * @param string $heading Heading.
	 * @param string $body_html Inner HTML body.
	 */
	private function wrap_template( string $heading, string $body_html ): string {
		$brand      = '#4f46e5';
		$brand_dark = '#4338ca';
		$bg         = '#f1f5f9';
		$ink        = '#0f172a';
		$muted      = '#64748b';
		$border     = '#e2e8f0';
		$site_name  = get_bloginfo( 'name' );
		$site_url   = home_url( '/' );
		$tagline    = __( 'Grow with a mentor, one step at a time.', 'zeko-mentor' );

		return '<!DOCTYPE html>'
			. '<html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">'
			. '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
			. '<title>' . esc_html( $site_name ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . $bg . ';font-family:Arial,Helvetica,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $bg . ';"><tr><td align="center" style="padding:24px 16px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">'
			. '<tr><td style="background:' . $brand . ';height:5px;line-height:5px;font-size:0;">&nbsp;</td></tr>'
			. '<tr><td style="background:' . $brand_dark . ';padding:26px 30px;text-align:center;">'
			. '<h1 style="margin:0;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:0.3px;">' . esc_html( $site_name ) . '</h1>'
			. '<p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.9);">' . esc_html( $tagline ) . '</p>'
			. '</td></tr>'
			. '<tr><td style="background:#ffffff;padding:32px 30px;">'
			. '<h2 style="margin:0 0 18px;font-size:18px;font-weight:700;color:' . $ink . ';">' . esc_html( $heading ) . '</h2>'
			. $body_html
			. '</td></tr>'
			. '<tr><td style="background:#ffffff;border-top:1px solid ' . $border . ';padding:16px 30px;text-align:center;">'
			. '<p style="margin:0;font-size:12px;color:' . $muted . ';">&copy; ' . esc_html( gmdate( 'Y' ) ) . ' ' . esc_html( $site_name ) . ' &middot; <a href="' . esc_url( $site_url ) . '" style="color:' . $muted . ';">' . esc_html__( 'Visit site', 'zeko-mentor' ) . '</a></p>'
			. '</td></tr>'
			. '</table></td></tr></table>'
			. '</body></html>';
	}
}
