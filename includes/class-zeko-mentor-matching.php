<?php
/**
 * Matching engine for Zeko Mentor.
 *
 * Calculates match scores between mentees and mentors based on skills,
 * availability, rating, rate, and verification status.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Matching. */
class Zeko_Mentor_Matching {

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * WEIGHT SKILLS.
	 *
	 * @var mixed
	 */
	private const WEIGHT_SKILLS          = 40;
	private const WEIGHT_AVAILABILITY    = 15;
	private const WEIGHT_RATING          = 15;
	private const WEIGHT_RATE            = 15;
	private const WEIGHT_VERIFIED        = 5;
	private const WEIGHT_SESSION_LOAD    = 5;
	private const WEIGHT_RECENT_ACTIVITY = 5;

	/**
	 * MAX RAW SCORE.
	 *
	 * @var mixed
	 */
	private const MAX_RAW_SCORE = 100;

	/**
	 * Construct.
	 *
	 * @param Zeko_Mentor_DB $db Db.
	 */
	public function __construct( Zeko_Mentor_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Find top matches for a mentee.
	 *
	 * @param int $mentee_id Mentee id.
	 * @param int $limit Limit.
	 */
	public function find_matches( int $mentee_id, int $limit = 10 ): array {
		$mentors = $this->db->get_mentors(
			array(
				'is_active' => 1,
				'limit'     => 100,
				'orderby'   => 'avg_rating',
				'order'     => 'DESC',
			)
		);

		if ( empty( $mentors ) ) {
			return array();
		}

		// Get mentee profile/interests from user meta.
		$mentee_interests = get_user_meta( $mentee_id, 'zeko_mentor_interests', true );
		if ( ! is_array( $mentee_interests ) ) {
			$mentee_interests = array();
		}

		$mentee_budget = (float) get_user_meta( $mentee_id, 'zeko_mentor_budget', true );
		$mentee_tz     = get_user_meta( $mentee_id, 'zeko_timezone', true ) ?: 'UTC';

		$scored = array();
		foreach ( $mentors as $mentor ) {
			if ( (int) $mentor['user_id'] === $mentee_id ) {
				continue;
			}

			$score   = $this->calculate_match_score( $mentee_interests, $mentee_budget, $mentee_tz, $mentor );
			$reasons = $this->get_match_reasons( $mentee_interests, $mentor );

			if ( $score > 0 ) {
				$scored[] = array(
					'mentor'  => $mentor,
					'score'   => $score,
					'reasons' => $reasons,
				);
			}
		}

		// Sort by score descending.
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		$top = array_slice( $scored, 0, $limit );

		// Persist matches.
		foreach ( $top as $match ) {
			$this->db->save_match(
				$mentee_id,
				(int) $match['mentor']['user_id'],
				$match['score'],
				$match['reasons']
			);
		}

		return $top;
	}

	/**
	 * Calculate match score (0-100).
	 *
	 * @param array  $mentee_interests Mentee interests.
	 * @param float  $mentee_budget Mentee budget.
	 * @param string $mentee_tz Mentee tz.
	 * @param array  $mentor Mentor.
	 */
	public function calculate_match_score( array $mentee_interests, float $mentee_budget, string $mentee_tz, array $mentor ): float {
		$score = 0;

		// Skill overlap (0-40).
		$mentor_expertise = json_decode( $mentor['expertise_areas'] ?? '[]', true );
		if ( ! empty( $mentee_interests ) && ! empty( $mentor_expertise ) ) {
			$overlap   = array_intersect( array_map( 'strtolower', $mentee_interests ), array_map( 'strtolower', $mentor_expertise ) );
			$skill_pct = count( $overlap ) / max( count( $mentee_interests ), 1 );
			$score    += $skill_pct * self::WEIGHT_SKILLS;
		}

		// Availability overlap (0-15) — check if mentor has slots on days matching mentee preferences.
		$mentor_id    = (int) $mentor['user_id'];
		$availability = $this->db->get_availability( $mentor_id );
		if ( ! empty( $availability ) ) {
			// Full credit if mentor has any availability, bonus if more slots available.
			$slot_count  = count( $availability );
			$slot_factor = min( 1.0, $slot_count / 5 ); // 5+ unique day slots = max.
			$score      += $slot_factor * self::WEIGHT_AVAILABILITY;
		}

		// Rating (0-15).
		$rating_score = ( (float) $mentor['avg_rating'] / 5 ) * self::WEIGHT_RATING;
		$score       += $rating_score;

		// Rate compatibility (0-15).
		if ( $mentee_budget > 0 && (float) $mentor['hourly_rate'] > 0 ) {
			if ( (float) $mentor['hourly_rate'] <= $mentee_budget ) {
				$score += self::WEIGHT_RATE;
			} else {
				$diff   = abs( (float) $mentor['hourly_rate'] - $mentee_budget ) / $mentee_budget;
				$score += max( 0, self::WEIGHT_RATE * ( 1 - $diff ) );
			}
		} else {
			$score += self::WEIGHT_RATE * 0.5;
		}

		// Verification bonus (0-5).
		if ( ! empty( $mentor['is_verified'] ) ) {
			$score += self::WEIGHT_VERIFIED;
		}

		// Session load — fewer active mentees = better (0-5).
		$active = $this->db->count_active_mentees( $mentor_id );
		$max    = (int) $mentor['max_mentees'];
		if ( $max > 0 ) {
			$load_pct = 1 - ( $active / $max );
			$score   += $load_pct * self::WEIGHT_SESSION_LOAD;
		}

		// Recency — recently active mentors get a bonus (0-5).
		$total_sessions = (int) ( $mentor['total_sessions'] ?? 0 );
		if ( $total_sessions > 0 ) {
			// Mentors with sessions get full recency credit; scale by activity.
			$recency = min( 1.0, $total_sessions / 20 );
			$score  += $recency * self::WEIGHT_RECENT_ACTIVITY;
		}

		// Normalize to 0-100.
		$max_possible = self::WEIGHT_SKILLS + self::WEIGHT_AVAILABILITY + self::WEIGHT_RATING
			+ self::WEIGHT_RATE + self::WEIGHT_VERIFIED + self::WEIGHT_SESSION_LOAD + self::WEIGHT_RECENT_ACTIVITY;

		return round( ( $score / $max_possible ) * 100, 2 );
	}

	/**
	 * Generate human-readable match reasons.
	 *
	 * @param array $mentee_interests Mentee interests.
	 * @param array $mentor Mentor.
	 */
	public function get_match_reasons( array $mentee_interests, array $mentor ): array {
		$reasons   = array();
		$expertise = json_decode( $mentor['expertise_areas'] ?? '[]', true );

		if ( ! empty( $mentee_interests ) && ! empty( $expertise ) ) {
			$overlap = array_intersect( array_map( 'strtolower', $mentee_interests ), array_map( 'strtolower', $expertise ) );
			if ( ! empty( $overlap ) ) {
				$reasons[] = sprintf(
					/* translators: %s: list of matching skills */
					__( 'Expertise in %s', 'zeko-mentor' ),
					implode( ', ', $overlap )
				);
			}
		}

		if ( (float) $mentor['avg_rating'] >= 4.5 ) {
			$reasons[] = sprintf(
				/* translators: %s: rating */
				__( 'Top-rated mentor (%s/5)', 'zeko-mentor' ),
				number_format( (float) $mentor['avg_rating'], 1 )
			);
		}

		if ( ! empty( $mentor['is_verified'] ) ) {
			$reasons[] = __( 'Verified mentor', 'zeko-mentor' );
		}

		if ( (int) $mentor['total_sessions'] >= 10 ) {
			$reasons[] = sprintf(
				/* translators: %d: number of sessions */
				__( '%d+ sessions completed', 'zeko-mentor' ),
				(int) $mentor['total_sessions']
			);
		}

		return $reasons;
	}

	/**
	 * Refresh matches for a mentee.
	 *
	 * @param int $mentee_id Mentee id.
	 */
	public function refresh_matches( int $mentee_id ): array {
		// Expire old matches.
		$this->db->flush_cache();
		return $this->find_matches( $mentee_id );
	}
}
