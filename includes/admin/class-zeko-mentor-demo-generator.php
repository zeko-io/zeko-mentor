<?php
/**
 * Demo data generator for Zeko Mentor.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Demo_Generator. */
class Zeko_Mentor_Demo_Generator {

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * Mentor ids.
	 *
	 * @var array Mentor ids.
	 */
	private array $mentor_ids = array();
	/**
	 * Mentee ids.
	 *
	 * @var array Mentee ids.
	 */
	private array $mentee_ids = array();
	/**
	 * Program id.
	 *
	 * @var int Program id.
	 */
	private int $program_id = 0;

	private const DEMO_MENTOR_PREFIX = 'demo_mentor_';
	private const DEMO_MENTEE_PREFIX = 'demo_mentee_';

	private const EXPERTISE_AREAS = array(
		'technology',
		'business',
		'design',
		'marketing',
		'finance',
		'healthcare',
		'education',
		'creative_arts',
		'engineering',
		'science',
	);

	private const MENTOR_NAMES = array(
		'Alice Chen',
		'Bob Martinez',
		'Carol Okafor',
		'David Kim',
		'Elena Rossi',
	);

	private const MENTEE_NAMES = array(
		'Frank Adams',
		'Grace Lee',
		'Henry Nwosu',
		'Iris Tanaka',
		'Jack Wilson',
		'Karen Patel',
		'Leo Garcia',
		'Maria Schmidt',
		'Nathan Brown',
		'Olivia Dupont',
	);

	/**
	 * Construct.
	 *
	 * @param Zeko_Mentor_DB $db Db.
	 */
	public function __construct( Zeko_Mentor_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Seed.
	 */
	public function seed(): array {
		$this->create_mentors();
		$this->create_mentees();
		$this->create_availability();
		$this->create_matches();
		$this->create_sessions();
		$this->create_goals_and_progress();
		$this->create_reviews();
		$this->create_program();
		$this->create_messages();

		return array(
			'mentors'  => count( $this->mentor_ids ),
			'mentees'  => count( $this->mentee_ids ),
			'matches'  => $this->count_table( $this->db->get_table_matches() ),
			'sessions' => $this->count_table( $this->db->get_table_sessions() ),
			'goals'    => $this->count_table( $this->db->get_table_goals() ),
			'reviews'  => $this->count_table( $this->db->get_table_reviews() ),
			'programs' => $this->program_id ? 1 : 0,
			'messages' => $this->count_table( $this->db->get_table_messages() ),
			'profiles' => $this->count_table( $this->db->get_table_profiles() ),
		);
	}

	/**
	 * Clear.
	 */
	public function clear(): void {
		global $wpdb;

		$tables = array(
			$this->db->get_table_messages(),
			$this->db->get_table_notifications(),
			$this->db->get_table_reviews(),
			$this->db->get_table_progress(),
			$this->db->get_table_goals(),
			$this->db->get_table_program_members(),
			$this->db->get_table_programs(),
			$this->db->get_table_matches(),
			$this->db->get_table_sessions(),
			$this->db->get_table_availability(),
			$this->db->get_table_profiles(),
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		foreach ( $tables as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		$current_user_id = get_current_user_id();
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$demo_user_ids = get_users(
			array(
				'meta_key'   => 'zeko_demo_user',
				'meta_value' => '1',
				'fields'     => 'ID',
				'number'     => 100,
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$legacy_login_users = array();

		for ( $i = 1; $i <= 5; $i++ ) {
			$user = get_user_by( 'login', self::DEMO_MENTOR_PREFIX . $i );
			if ( $user ) {
				$legacy_login_users[] = (int) $user->ID;
			}
		}

		for ( $i = 1; $i <= 10; $i++ ) {
			$user = get_user_by( 'login', self::DEMO_MENTEE_PREFIX . $i );
			if ( $user ) {
				$legacy_login_users[] = (int) $user->ID;
			}
		}

		$demo_user_ids = array_unique( array_merge( $demo_user_ids, $legacy_login_users ) );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $demo_user_ids as $user_id ) {
			if ( $current_user_id !== (int) $user_id ) {
				wp_delete_user( (int) $user_id );
			}
		}

		$program = zeko_mentor_get_page_by_slug( 'demo-mentorship-program' );
		if ( $program ) {
			wp_delete_post( $program->ID, true );
		}

		$this->mentor_ids = array();
		$this->mentee_ids = array();
		$this->program_id = 0;
	}

	/**
	 * Create mentors.
	 */
	private function create_mentors(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$username = self::DEMO_MENTOR_PREFIX . ( $i + 1 );
			$email    = $username . '@zeko.test';
			$user_id  = username_exists( $username );

			if ( ! $user_id ) {
				$user_id = wp_create_user( $username, 'demo123', $email );
				$user    = new WP_User( $user_id );
				$user->set_role( 'author' );

				$first = explode( ' ', self::MENTOR_NAMES[ $i ] )[0];
				$last  = explode( ' ', self::MENTOR_NAMES[ $i ] )[1] ?? '';
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => self::MENTOR_NAMES[ $i ],
						'first_name'   => $first,
						'last_name'    => $last,
					)
				);
			}

			update_user_meta( $user_id, 'zeko_demo_user', 1 );

			$selected = array_rand( array_flip( self::EXPERTISE_AREAS ), wp_rand( 2, 4 ) );
			if ( is_string( $selected ) ) {
				$selected = array( $selected );
			}

			$this->db->save_profile(
				$user_id,
				array(
					'expertise_areas' => $selected,
					'bio'             => sprintf(
						'Experienced mentor with over %d years in %s. Passionate about helping others grow and succeed in their careers.',
						wp_rand( 5, 20 ),
						implode( ' and ', array_slice( $selected, 0, 2 ) )
					),
					'hourly_rate'     => wp_rand( 25, 150 ) + 0.99,
					'currency'        => 'USD',
					'is_active'       => 1,
					'max_mentees'     => wp_rand( 3, 8 ),
					'timezone'        => 'UTC',
				)
			);

			$this->mentor_ids[] = $user_id;
		}
	}

	/**
	 * Create mentees.
	 */
	private function create_mentees(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$username = self::DEMO_MENTEE_PREFIX . ( $i + 1 );
			$email    = $username . '@zeko.test';
			$user_id  = username_exists( $username );

			if ( ! $user_id ) {
				$user_id = wp_create_user( $username, 'demo123', $email );
				$user    = new WP_User( $user_id );
				$user->set_role( 'subscriber' );

				$first = explode( ' ', self::MENTEE_NAMES[ $i ] )[0];
				$last  = explode( ' ', self::MENTEE_NAMES[ $i ] )[1] ?? '';
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => self::MENTEE_NAMES[ $i ],
						'first_name'   => $first,
						'last_name'    => $last,
					)
				);
			}

			update_user_meta( $user_id, 'zeko_demo_user', 1 );

			$this->mentee_ids[] = $user_id;
		}
	}

	/**
	 * Create availability.
	 */
	private function create_availability(): void {
		$days       = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );
		$time_slots = array(
			array( '09:00', '12:00' ),
			array( '13:00', '17:00' ),
		);

		foreach ( $this->mentor_ids as $mentor_id ) {
			$slots = array();
			foreach ( $days as $day ) {
				$selected = $time_slots[ array_rand( $time_slots ) ];
				$slots[]  = array(
					'day_of_week' => $day,
					'start_time'  => $selected[0],
					'end_time'    => $selected[1],
					'timezone'    => 'UTC',
				);
			}
			$this->db->save_availability( $mentor_id, $slots );
		}
	}

	/**
	 * Create matches.
	 */
	private function create_matches(): void {
		foreach ( $this->mentee_ids as $mentee_id ) {
			$matched = array_rand( array_flip( $this->mentor_ids ), wp_rand( 1, 3 ) );
			if ( is_int( $matched ) ) {
				$matched = array( $matched );
			}
			foreach ( $matched as $mentor_id ) {
				$score = wp_rand( 65, 98 );
				$this->db->save_match( $mentee_id, $mentor_id, $score, array( 'Demo match' ) );
			}
		}
	}

	/**
	 * Create sessions.
	 */
	private function create_sessions(): void {
		$statuses      = array( 'pending', 'confirmed', 'completed', 'cancelled' );
		$session_types = array( 'video', 'audio', 'chat' );

		foreach ( $this->mentee_ids as $index => $mentee_id ) {
			$mentor_id = $this->mentor_ids[ $index % 5 ];
			$status    = $statuses[ $index % 4 ];
			$date      = 'completed' === $status
				? gmdate( 'Y-m-d', strtotime( '-' . wp_rand( 1, 30 ) . ' days' ) )
				: gmdate( 'Y-m-d', strtotime( '+' . wp_rand( 1, 14 ) . ' days' ) );

			$this->db->create_session(
				array(
					'mentor_id'        => $mentor_id,
					'mentee_id'        => $mentee_id,
					'session_date'     => $date,
					'start_time'       => sprintf( '%02d:00', wp_rand( 9, 16 ) ),
					'end_time'         => sprintf( '%02d:00', wp_rand( 10, 17 ) ),
					'duration_minutes' => 60,
					'status'           => $status,
					'session_type'     => $session_types[ $index % 3 ],
					'topic'            => $this->random_topic(),
					'amount'           => wp_rand( 25, 100 ) + 0.99,
					'currency'         => 'USD',
					'payment_status'   => 'pending',
				)
			);
		}
	}

	/**
	 * Create goals and progress.
	 */
	private function create_goals_and_progress(): void {
		$goal_titles = array(
			'Master React Hooks',
			'Build a Portfolio Site',
			'Learn Python for Data Science',
			'Improve Public Speaking',
			'Write 10 Blog Posts',
			'Launch a Side Project',
			'Get AWS Certified',
			'Learn Docker and Kubernetes',
		);

		foreach ( $this->mentee_ids as $mentee_id ) {
			$goal_count = wp_rand( 1, 3 );
			for ( $g = 0; $g < $goal_count; $g++ ) {
				$title   = $goal_titles[ array_rand( $goal_titles ) ];
				$status  = wp_rand( 0, 2 ) === 0 ? 'achieved' : 'active';
				$goal_id = $this->db->insert_goal(
					array(
						'user_id'     => $mentee_id,
						'title'       => $title . ' (Demo)',
						'description' => 'Working on ' . $title . ' with my mentor to build real-world skills.',
						'target_date' => gmdate( 'Y-m-d', strtotime( '+' . wp_rand( 15, 90 ) . ' days' ) ),
						'status'      => $status,
					)
				);

				if ( $goal_id && 'achieved' === $status ) {
					$this->db->insert_progress(
						array(
							'goal_id'   => $goal_id,
							'user_id'   => $mentee_id,
							'mentor_id' => $this->mentor_ids[ array_rand( $this->mentor_ids ) ],
							'note'      => 'Completed all milestones for this goal.',
							'rating'    => wp_rand( 4, 5 ),
						)
					);
				} elseif ( $goal_id ) {
					$this->db->insert_progress(
						array(
							'goal_id'   => $goal_id,
							'user_id'   => $mentee_id,
							'mentor_id' => $this->mentor_ids[ array_rand( $this->mentor_ids ) ],
							'note'      => 'Making good progress, completed 2 out of 5 milestones.',
							'rating'    => wp_rand( 3, 5 ),
						)
					);
				}
			}
		}
	}

	/**
	 * Create reviews.
	 */
	private function create_reviews(): void {
		$review_texts = array(
			'Excellent mentor! Very knowledgeable and patient.',
			'Helped me understand complex topics with ease.',
			'Great session, learned a lot in a short time.',
			'Very professional and well-prepared. Highly recommend.',
			'Provided valuable insights that I could apply immediately.',
		);

		$sessions = $this->db->get_user_sessions( $this->mentee_ids[0], 'mentee', 'completed', 50 );
		$reviewed = array();
		foreach ( $sessions as $session ) {
			if ( in_array( $session['session_id'], $reviewed, true ) ) {
				continue;
			}
			$reviewed[] = $session['session_id'];
			$this->db->insert_review(
				array(
					'session_id'  => $session['session_id'],
					'mentor_id'   => $session['mentor_id'],
					'reviewer_id' => $session['mentee_id'],
					'rating'      => wp_rand( 3, 5 ),
					'title'       => 'Great mentorship experience',
					'review_text' => $review_texts[ array_rand( $review_texts ) ],
					'is_public'   => 1,
				)
			);
			if ( count( $reviewed ) >= 12 ) {
				break;
			}
		}
	}

	/**
	 * Create program.
	 */
	private function create_program(): void {
		$program_id = $this->db->create_program(
			array(
				'mentor_id'       => $this->mentor_ids[0],
				'title'           => 'Web Development Bootcamp (Demo)',
				'description'     => 'A comprehensive 8-week program covering modern web development from HTML/CSS to full-stack JavaScript. Weekly group sessions, pair programming, and real-world projects.',
				'expertise_area'  => 'technology',
				'max_members'     => 10,
				'current_members' => 5,
				'duration_weeks'  => 8,
				'price'           => 199.99,
				'currency'        => 'USD',
				'status'          => 'active',
				'start_date'      => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
				'end_date'        => gmdate( 'Y-m-d', strtotime( '+63 days' ) ),
			)
		);

		if ( $program_id ) {
			$this->program_id = $program_id;
			for ( $i = 0; $i < 5; $i++ ) {
				$this->db->enroll_program( $program_id, $this->mentee_ids[ $i ] );
			}
		}
	}

	/**
	 * Create messages.
	 */
	private function create_messages(): void {
		$sample_messages = array(
			'Hi, I\'m excited to start our mentorship!',
			'Thanks for the session today, it was very helpful.',
			'Could you share some resources on the topics we discussed?',
			'I\'ve been practicing what we covered and seeing great progress.',
			'Looking forward to our next session!',
			'Would you recommend any specific courses or books?',
			'I\'ve completed the first milestone you suggested.',
			'Can we reschedule our session to next week?',
		);

		for ( $i = 0; $i < 20; $i++ ) {
			$sender   = $this->mentee_ids[ $i % 10 ];
			$receiver = $this->mentor_ids[ $i % 5 ];
			$msg      = $sample_messages[ array_rand( $sample_messages ) ];

			$this->db->insert_message(
				array(
					'session_id'  => 0,
					'sender_id'   => $sender,
					'receiver_id' => $receiver,
					'message'     => $msg,
					'is_read'     => wp_rand( 0, 1 ),
				)
			);
		}
	}

	/**
	 * Random topic.
	 */
	private function random_topic(): string {
		$topics = array(
			'Career development and growth strategy',
			'Technical skills review and improvement',
			'Project planning and architecture',
			'Interview preparation and practice',
			'Leadership and management skills',
			'Code review and best practices',
			'System design discussion',
			'Portfolio review and feedback',
		);
		return $topics[ array_rand( $topics ) ];
	}

	/**
	 * Count table.
	 *
	 * @param string $table Table.
	 */
	private function count_table( string $table ): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
	}
}
