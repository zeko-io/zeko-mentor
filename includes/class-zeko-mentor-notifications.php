<?php
/**
 * Creates in-app notifications for key mentor events.
 *
 * Listens to existing action hooks and writes to the notifications table.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Notifications. */
class Zeko_Mentor_Notifications {

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

		// Session events — hooks pass ( $session_id, ... ) from AJAX handlers.
		add_action( 'zeko_mentor_session_booked', array( $this, 'on_session_booked' ), 10, 3 );
		add_action( 'zeko_mentor_session_cancelled', array( $this, 'on_session_cancelled' ), 10, 1 );
		add_action( 'zeko_mentor_session_completed', array( $this, 'on_session_completed' ), 10, 1 );

		// Review events — hooks pass ( $session_id, $reviewer_id ).
		add_action( 'zeko_mentor_review_posted', array( $this, 'on_review_posted' ), 10, 2 );

		// Match events.
		add_action( 'zeko_mentor_match_accepted', array( $this, 'on_match_accepted' ), 10, 3 );

		// Application events.
		add_action( 'zeko_mentor_application_verified', array( $this, 'on_application_verified' ), 10, 2 );

		// Goal events — hooks pass ( $goal_id ).
		add_action( 'zeko_mentor_goal_achieved', array( $this, 'on_goal_achieved_by_id' ), 10, 1 );
	}

	/**
	 * Session booked — notify the mentor.
	 * Hook signature: do_action( 'zeko_mentor_session_booked', $session_id, $mentor_id, $mentee_id )
	 *
	 * @param int $session_id Session id.
	 * @param int $mentor_id Mentor id.
	 * @param int $mentee_id Mentee id.
	 */
	public function on_session_booked( int $session_id, int $mentor_id, int $mentee_id ): void {
		$session     = $this->db->get_session( $session_id );
		$mentee_name = $this->get_user_name( $mentee_id );
		$topic       = ! empty( $session['topic'] ) ? $session['topic'] : __( 'a mentorship session', 'zeko-mentor' );
		$date        = ! empty( $session['session_date'] ) ? $session['session_date'] : '';

		$this->create(
			array(
				'user_id' => $mentor_id,
				'type'    => 'session',
				'title'   => __( 'New Session Booked', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: 1: mentee name, 2: session topic, 3: date */
					__( '%1$s booked "%2$s" on %3$s.', 'zeko-mentor' ),
					$mentee_name,
					$topic,
					$date
				),
				'link'    => $this->get_session_url( $session_id ),
			)
		);
	}

	/**
	 * Session cancelled — notify the other party.
	 * Hook signature: do_action( 'zeko_mentor_session_cancelled', $session_id )
	 *
	 * @param int $session_id Session id.
	 */
	public function on_session_cancelled( int $session_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$cancelled_by = get_current_user_id();
		$other_id     = $cancelled_by === (int) $session['mentor_id']
			? (int) $session['mentee_id']
			: (int) $session['mentor_id'];
		$by_name      = $this->get_user_name( $cancelled_by );
		$topic        = ! empty( $session['topic'] ) ? $session['topic'] : __( 'Session', 'zeko-mentor' );

		$this->create(
			array(
				'user_id' => $other_id,
				'type'    => 'session',
				'title'   => __( 'Session Cancelled', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: 1: canceller name, 2: session topic */
					__( '%1$s cancelled "%2$s".', 'zeko-mentor' ),
					$by_name,
					$topic
				),
				'link'    => $this->get_session_url( $session_id ),
			)
		);
	}

	/**
	 * Session completed — notify the other party.
	 * Hook signature: do_action( 'zeko_mentor_session_completed', $session_id )
	 *
	 * @param int $session_id Session id.
	 */
	public function on_session_completed( int $session_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$completed_by = get_current_user_id();
		$mentor_id    = (int) $session['mentor_id'];
		$mentee_id    = (int) $session['mentee_id'];
		$other_id     = $completed_by === $mentor_id ? $mentee_id : $mentor_id;
		$topic        = ! empty( $session['topic'] ) ? $session['topic'] : __( 'Session', 'zeko-mentor' );

		$this->create(
			array(
				'user_id' => $other_id,
				'type'    => 'session',
				'title'   => __( 'Session Completed', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: %s: session topic */
					__( '"%s" has been marked as completed.', 'zeko-mentor' ),
					$topic
				),
				'link'    => $this->get_session_url( $session_id ),
			)
		);
	}

	/**
	 * Review posted — notify the mentor.
	 * Hook signature: do_action( 'zeko_mentor_review_posted', $session_id, $reviewer_id )
	 *
	 * @param int $session_id Session id.
	 * @param int $reviewer_id Reviewer id.
	 */
	public function on_review_posted( int $session_id, int $reviewer_id ): void {
		$session = $this->db->get_session( $session_id );
		if ( ! $session ) {
			return;
		}

		$reviewer_name = $this->get_user_name( $reviewer_id );

		// Find the rating from the latest review for this session.
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$rating = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rating FROM {$this->db->get_table_reviews()} WHERE session_id = %d ORDER BY review_id DESC LIMIT 1",
				$session_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$this->create(
			array(
				'user_id' => (int) $session['mentor_id'],
				'type'    => 'review',
				'title'   => __( 'New Review Received', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: 1: reviewer name, 2: rating */
					__( '%1$s left a %2$d-star review.', 'zeko-mentor' ),
					$reviewer_name,
					$rating
				),
			)
		);
	}

	/**
	 * Match accepted — notify the mentor.
	 * Hook can pass ( $match_id, $mentee_id ) or ( $match_array, $mentee_id, $mentor_id ).
	 *
	 * @param mixed $match_or_id Match or id.
	 * @param int   $mentee_id Mentee id.
	 * @param int   $mentor_id Mentor id.
	 */
	public function on_match_accepted( $match_or_id, int $mentee_id, int $mentor_id = 0 ): void {
		if ( is_array( $match_or_id ) ) {
			$match     = $match_or_id;
			$mentor_id = (int) ( $match['mentor_id'] ?? $mentor_id );
		} else {
			$match_id = (int) $match_or_id;
			$match    = $this->db->get_match( $match_id ) ?? array();
			if ( empty( $match ) ) {
				return;
			}
			$mentor_id = (int) $match['mentor_id'];
		}

		$mentee_name = $this->get_user_name( $mentee_id );

		$this->create(
			array(
				'user_id' => $mentor_id,
				'type'    => 'match',
				'title'   => __( 'New Mentee Match', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: %s: mentee name */
					__( '%s accepted your match suggestion.', 'zeko-mentor' ),
					$mentee_name
				),
			)
		);
	}

	/**
	 * Application verified — notify the applicant.
	 *
	 * @param int    $user_id User id.
	 * @param string $status Status.
	 */
	public function on_application_verified( int $user_id, string $status ): void {
		$is_approved = 'approved' === $status;

		$this->create(
			array(
				'user_id' => $user_id,
				'type'    => 'info',
				'title'   => $is_approved
					? __( 'Mentor Application Approved!', 'zeko-mentor' )
					: __( 'Mentor Application Update', 'zeko-mentor' ),
				'message' => $is_approved
					? __( 'Congratulations! Your mentor application has been approved. You can now start accepting sessions.', 'zeko-mentor' )
					: __( 'Thank you for your application. Unfortunately, we were unable to approve it at this time.', 'zeko-mentor' ),
			)
		);
	}

	/**
	 * Goal achieved by ID — fetch goal data and notify.
	 * Hook signature: do_action( 'zeko_mentor_goal_achieved', $goal_id )
	 *
	 * @param int $goal_id Goal id.
	 */
	public function on_goal_achieved_by_id( int $goal_id ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$goal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->db->get_table_goals()} WHERE goal_id = %d LIMIT 1", $goal_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! $goal ) {
			return;
		}

		$this->create(
			array(
				'user_id' => (int) $goal['user_id'],
				'type'    => 'goal',
				'title'   => __( 'Goal Achieved!', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: %s: goal title */
					__( 'You achieved "%s". Well done!', 'zeko-mentor' ),
					$goal['title']
				),
			)
		);
	}

	// ─── Helpers ───────────────────────────────────────────────.

	/**
	 * Create a notification via the DB layer.
	 *
	 * @param array $data Data.
	 */
	private function create( array $data ): int {
		return $this->db->create_notification( $data );
	}

	/**
	 * Get a user's display name.
	 *
	 * @param int $user_id User id.
	 */
	private function get_user_name( int $user_id ): string {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'User', 'zeko-mentor' );
	}

	/**
	 * Get the frontend URL for a session.
	 *
	 * @param int $session_id Session id.
	 */
	private function get_session_url( int $session_id ): string {
		$page_id = zeko_mentor_get_page_id( 'mentor-session' );
		if ( $page_id ) {
			return add_query_arg( 'session_id', $session_id, get_permalink( $page_id ) );
		}
		return home_url( '/mentor-session/?session_id=' . $session_id );
	}
}

/**
 * Get a Zeko Mentor page ID by slug.
 *
 * @param string $slug Slug.
 */
function zeko_mentor_get_page_id( string $slug ): int {
	return zeko_mentor_get_page_id_by_slug( $slug );
}
