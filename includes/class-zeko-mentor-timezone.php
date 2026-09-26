<?php
/**
 * Timezone utilities for Zeko Mentor.
 *
 * Handles user timezone detection, slot conversion, and display.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Timezone. */
class Zeko_Mentor_Timezone {

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
	}

	/**
	 * Get a user's timezone string.
	 *
	 * @param int $user_id User id.
	 */
	public function get_user_timezone( int $user_id ): string {
		$profile = $this->db->get_profile( $user_id );
		return ! empty( $profile['timezone'] ) ? $profile['timezone'] : 'UTC';
	}

	/**
	 * Save a user's timezone.
	 *
	 * @param int    $user_id User id.
	 * @param string $timezone Timezone.
	 */
	public function save_user_timezone( int $user_id, string $timezone ): void {
		$timezone = sanitize_text_field( $timezone );
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = 'UTC';
		}

		$existing = $this->db->get_profile( $user_id );
		if ( $existing ) {
			$this->db->save_profile(
				$user_id,
				array(
					'timezone'  => $timezone,
					'is_active' => $existing['is_active'],
				)
			);
		}

		// Also set in user meta for easy access.
		update_user_meta( $user_id, 'zeko_timezone', $timezone );
	}

	/**
	 * Convert a datetime from one timezone to another.
	 *
	 * @param string $datetime Datetime.
	 * @param string $from_tz From tz.
	 * @param string $to_tz To tz.
	 */
	public function convert_time( string $datetime, string $from_tz, string $to_tz ): string {
		try {
			$dt = new \DateTime( $datetime, new \DateTimeZone( $from_tz ) );
			$dt->setTimezone( new \DateTimeZone( $to_tz ) );
			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return $datetime;
		}
	}

	/**
	 * Convert a time string (H:i:s) from one TZ to another for a given date.
	 *
	 * @param string $time Time.
	 * @param string $date Date.
	 * @param string $from_tz From tz.
	 * @param string $to_tz To tz.
	 */
	public function convert_time_on_date( string $time, string $date, string $from_tz, string $to_tz ): string {
		return $this->convert_time( $date . ' ' . $time, $from_tz, $to_tz );
	}

	/**
	 * Display a session time in the user's timezone.
	 *
	 * @param array $session Session.
	 * @param int   $user_id User id.
	 */
	public function display_session_time( array $session, int $user_id ): string {
		$session_tz = ! empty( $session['timezone'] ) ? $session['timezone'] : 'UTC';
		$user_tz    = $this->get_user_timezone( $user_id );

		$start = $this->convert_time_on_date( $session['start_time'], $session['session_date'], $session_tz, $user_tz );
		$end   = $this->convert_time_on_date( $session['end_time'], $session['session_date'], $session_tz, $user_tz );

		return sprintf(
			'%s %s–%s (%s)',
			$session['session_date'],
			gmdate( 'H:i', strtotime( $start ) ),
			gmdate( 'H:i', strtotime( $end ) ),
			$user_tz
		);
	}

	/**
	 * Get available slots for a mentor, converted to the requested timezone.
	 *
	 * @param int    $mentor_id Mentor id.
	 * @param string $date Date.
	 * @param string $to_tz To tz.
	 */
	public function get_available_slots_in_tz( int $mentor_id, string $date, string $to_tz ): array {
		$slots   = $this->db->get_available_slots( $mentor_id, $date );
		$profile = $this->db->get_profile( $mentor_id );
		$from_tz = ! empty( $profile['timezone'] ) ? $profile['timezone'] : 'UTC';

		foreach ( $slots as &$slot ) {
			$slot['start_time_original'] = $slot['start_time'];
			$slot['end_time_original']   = $slot['end_time'];
			$slot['start_time']          = gmdate( 'H:i:s', strtotime( $this->convert_time_on_date( $slot['start_time'], $date, $from_tz, $to_tz ) ) );
			$slot['end_time']            = gmdate( 'H:i:s', strtotime( $this->convert_time_on_date( $slot['end_time'], $date, $from_tz, $to_tz ) ) );
		}
		unset( $slot );

		return $slots;
	}
}
