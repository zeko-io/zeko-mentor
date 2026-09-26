<?php
/**
 * REST API handler for Zeko Mentor.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_REST. */
class Zeko_Mentor_REST {

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		$namespace = 'zeko-mentor/v1';

		register_rest_route(
			$namespace,
			'/mentors',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mentors' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'expertise'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'min_rating'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'max_rate'    => array(
						'type'    => 'number',
						'default' => 0,
					),
					'is_verified' => array( 'type' => 'boolean' ),
					'orderby'     => array(
						'type'    => 'string',
						'default' => 'avg_rating',
						'enum'    => array( 'avg_rating', 'hourly_rate', 'total_sessions', 'created_at', 'profile_views' ),
					),
					'order'       => array(
						'type'    => 'string',
						'default' => 'DESC',
						'enum'    => array( 'ASC', 'DESC' ),
					),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/mentors/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mentor' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/mentors/(?P<id>\d+)/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mentor_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/sessions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sessions' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
					'args'                => array(
						'role'     => array(
							'type'    => 'string',
							'default' => 'any',
							'enum'    => array( 'mentor', 'mentee', 'any' ),
						),
						'status'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_session' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/sessions/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_session' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/goals',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_goals' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
					'args'                => array(
						'status'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_goal' ),
					'permission_callback' => array( $this, 'is_logged_in' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/programs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_programs' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'status'    => array(
						'type'    => 'string',
						'default' => 'active',
					),
					'expertise' => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/programs/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_program' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/programs/(?P<id>\d+)/enroll',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enroll_program' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/programs/(?P<id>\d+)/unenroll',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'unenroll_program' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/matches',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_matches' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'status'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/matches/(?P<id>\d+)/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept_match' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			$namespace,
			'/matches/(?P<id>\d+)/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss_match' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);
	}

	/**
	 * Logged in.
	 */
	public function is_logged_in(): bool {
		return is_user_logged_in();
	}

	// ─── Mentors ──────────────────────────────────────────────.

	/**
	 * Mentors.
	 *
	 * @param mixed $request Request.
	 */
	public function get_mentors( $request ) {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$args = array(
			'search'      => $request->get_param( 'search' ),
			'expertise'   => $request->get_param( 'expertise' ),
			'min_rating'  => (float) $request->get_param( 'min_rating' ),
			'max_rate'    => (float) $request->get_param( 'max_rate' ),
			'is_verified' => $request->get_param( 'is_verified' ),
			'orderby'     => $request->get_param( 'orderby' ),
			'order'       => $request->get_param( 'order' ),
			'limit'       => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
		);

		$mentors = $this->db->get_mentors( $args );
		$total   = $this->db->get_mentor_count( $args );

		$data = array_map( array( $this, 'prepare_mentor_response' ), $mentors );

		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Mentor.
	 *
	 * @param mixed $request Request.
	 */
	public function get_mentor( $request ) {
		$profile = $this->db->get_profile_by_id( absint( $request['id'] ) );

		if ( ! $profile ) {
			return new \WP_REST_Response( array( 'message' => 'Mentor not found.' ), 404 );
		}

		$this->db->increment_profile_views( (int) $profile['user_id'] );

		$data = $this->prepare_mentor_response( $profile );

		$user = get_userdata( (int) $profile['user_id'] );
		if ( $user ) {
			$data['display_name'] = $user->display_name;
			$data['avatar']       = get_avatar_url( $user->ID, array( 'size' => 256 ) );
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Mentor availability.
	 *
	 * @param mixed $request Request.
	 */
	public function get_mentor_availability( $request ) {
		$mentor_id = absint( $request['id'] );
		$profile   = $this->db->get_profile_by_id( $mentor_id );

		if ( ! $profile ) {
			return new \WP_REST_Response( array( 'message' => 'Mentor not found.' ), 404 );
		}

		$date = sanitize_text_field( $request->get_param( 'date' ) );
		if ( ! $date ) {
			$date = current_time( 'Y-m-d' );
		}

		$slots = $this->db->get_available_slots( (int) $profile['user_id'], $date );

		return new \WP_REST_Response( $slots, 200 );
	}

	// ─── Sessions ─────────────────────────────────────────────.

	/**
	 * Sessions.
	 *
	 * @param mixed $request Request.
	 */
	public function get_sessions( $request ) {
		$user_id  = get_current_user_id();
		$role     = $request->get_param( 'role' );
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$sessions = $this->db->get_user_sessions(
			$user_id,
			$role,
			$status,
			$per_page,
			( $page - 1 ) * $per_page
		);

		return new \WP_REST_Response( $sessions, 200 );
	}

	/**
	 * Create session.
	 *
	 * @param mixed $request Request.
	 */
	public function create_session( $request ) {
		$params  = $request->get_json_params();
		$user_id = get_current_user_id();

		$mentor_id    = absint( $params['mentor_id'] ?? 0 );
		$session_date = sanitize_text_field( $params['session_date'] ?? '' );
		$start_time   = sanitize_text_field( $params['start_time'] ?? '' );
		$end_time     = sanitize_text_field( $params['end_time'] ?? '' );
		$topic        = sanitize_text_field( $params['topic'] ?? '' );
		$session_type = sanitize_text_field( $params['session_type'] ?? 'video' );
		$session_type = in_array( $session_type, array( 'video', 'audio', 'chat' ), true ) ? $session_type : 'video';

		if ( ! $mentor_id || ! $session_date || ! $start_time || ! $end_time ) {
			return new \WP_REST_Response( array( 'message' => 'mentor_id, session_date, start_time, and end_time are required.' ), 400 );
		}

		$profile = $this->db->get_profile( $mentor_id );

		if ( ! $profile || ! (int) $profile['is_active'] ) {
			return new \WP_REST_Response( array( 'message' => 'Mentor not available.' ), 404 );
		}

		$active_mentees = $this->db->count_active_mentees( $mentor_id );
		if ( $active_mentees >= (int) $profile['max_mentees'] ) {
			return new \WP_REST_Response( array( 'message' => 'Mentor has reached maximum capacity.' ), 409 );
		}

		$slots      = $this->db->get_available_slots( $mentor_id, $session_date );
		$slot_found = false;
		foreach ( $slots as $slot ) {
			if ( $slot['start_time'] === $start_time ) {
				$slot_found = true;
				break;
			}
		}

		if ( ! $slot_found ) {
			return new \WP_REST_Response( array( 'message' => 'Selected time slot is not available.' ), 409 );
		}

		$meeting_url = '';
		if ( 'video' === $session_type ) {
			$room_name   = 'zeko-session-' . substr( md5( $session_date . $start_time . $mentor_id . $user_id ), 0, 12 );
			$meeting_url = 'https://meet.jit.si/' . $room_name;
		}

		$rates    = method_exists( $this->db, 'get_session_rates' ) ? $this->db->get_session_rates( $mentor_id ) : array();
		$amount   = (float) ( $rates[ $session_type ] ?? $profile['hourly_rate'] );
		$currency = sanitize_text_field( $profile['currency'] ?? 'USD' );

		// Create session FIRST, so we have a real session_id for payment.
		$session_id = $this->db->create_session(
			array(
				'mentor_id'      => $mentor_id,
				'mentee_id'      => $user_id,
				'session_date'   => $session_date,
				'start_time'     => $start_time,
				'end_time'       => $end_time,
				'topic'          => $topic,
				'session_type'   => $session_type,
				'meeting_url'    => $meeting_url,
				'amount'         => $amount,
				'currency'       => $currency,
				'payment_status' => 'pending',
				'status'         => 'pending',
			)
		);

		if ( ! $session_id ) {
			return new \WP_REST_Response( array( 'message' => 'Failed to create session.' ), 500 );
		}

		// Prefer checkout via the Zeko Shop (invoice + receipt + loyalty for.
		// the mentee, deferred payout for the mentor). Fall back to a direct.
		// wallet charge when no linked session product is available.
		$payment_via_shop = false;
		$checkout_url     = '';

		if ( $amount > 0 && class_exists( 'Zeko_Shop' ) && class_exists( 'Zeko_Shop_DB' ) ) {
			$shop_db = Zeko_Shop::instance()->get_db();
			$product = $shop_db->get_product_by_external( 'session', $mentor_id, $session_type );

			if ( $product && 'active' === $product['status'] ) {
				$shop_db->add_to_cart( $user_id, (int) $product['product_id'], 1 );

				// Track the pending session so checkout can mark it paid.
				$pending                                   = get_user_meta( $user_id, 'zeko_mentor_pending_sessions', true );
				$pending                                   = is_array( $pending ) ? $pending : array();
				$pending[ (int) $product['product_id'] ][] = $session_id;
				update_user_meta( $user_id, 'zeko_mentor_pending_sessions', $pending );

				$payment_via_shop = true;
				$checkout_url     = function_exists( 'zeko_shop_page_url' )
					? zeko_shop_page_url( 'checkout' )
					: home_url( '/checkout/' );
			}
		}

		if ( ! $payment_via_shop && $amount > 0 && class_exists( 'Zeko_Pay_Integrations' ) ) {
			$payment = Zeko_Pay_Integrations::instance()->mentor_book_session(
				$user_id,
				$mentor_id,
				$session_id,
				$amount
			);
			if ( ! empty( $payment['success'] ) ) {
				$this->db->update_session(
					$session_id,
					array(
						'payment_status'   => 'paid',
						'payment_ref'      => (string) ( $payment['tx_id'] ?? '' ),
						'transaction_code' => (string) ( $payment['tx_code'] ?? '' ),
						'payout_ref'       => 'booked',
					)
				);
			} else {
				// Direct charge failed — never return a successful booking for.
				// an unpaid session. Roll the phantom session back.
				$this->db->delete_session( $session_id );
				$message = $payment['message'] ?? __( 'Payment failed. Please check your wallet balance.', 'zeko-mentor' );
				return new \WP_REST_Response(
					array(
						'message'  => $message,
						'redirect' => home_url( '/wallet/' ),
					),
					402
				);
			}
		}

		do_action( 'zeko_mentor_session_booked', $session_id, $mentor_id, $user_id );

		$session = $this->db->get_session( $session_id );

		if ( $payment_via_shop ) {
			$session['pending_payment'] = true;
			$session['redirect']        = $checkout_url;
		}

		return new \WP_REST_Response( $session, 201 );
	}

	/**
	 * Update session.
	 *
	 * @param mixed $request Request.
	 */
	public function update_session( $request ) {
		$session_id = absint( $request['id'] );
		$user_id    = get_current_user_id();
		$session    = $this->db->get_session( $session_id );

		if ( ! $session ) {
			return new \WP_REST_Response( array( 'message' => 'Session not found.' ), 404 );
		}

		if ( (int) $session['mentor_id'] !== $user_id && (int) $session['mentee_id'] !== $user_id ) {
			return new \WP_REST_Response( array( 'message' => 'Permission denied.' ), 403 );
		}

		$params = $request->get_json_params();
		$update = array();

		if ( isset( $params['status'] ) ) {
			$allowed_statuses = array( 'pending', 'confirmed', 'completed', 'cancelled', 'no_show' );
			$status           = sanitize_text_field( $params['status'] );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return new \WP_REST_Response( array( 'message' => 'Invalid status.' ), 400 );
			}

			$is_mentor = (int) $session['mentor_id'] === $user_id || current_user_can( 'manage_options' );

			// Completion/no-show trigger the mentor payout — only the mentor.
			// (or an admin) may confirm them, never the mentee.
			if ( in_array( $status, array( 'completed', 'no_show' ), true ) && ! $is_mentor ) {
				return new \WP_REST_Response( array( 'message' => 'Only the mentor can confirm session completion.' ), 403 );
			}

			// Scheduling transitions are the mentor's decision.
			if ( in_array( $status, array( 'pending', 'confirmed' ), true ) && ! $is_mentor ) {
				return new \WP_REST_Response( array( 'message' => 'Only the mentor can update scheduling status.' ), 403 );
			}

			$update['status'] = $status;
		}

		if ( isset( $params['notes'] ) ) {
			$update['notes'] = sanitize_textarea_field( $params['notes'] );
		}

		if ( isset( $params['mentor_notes'] ) && (int) $session['mentor_id'] === $user_id ) {
			$update['mentor_notes'] = sanitize_textarea_field( $params['mentor_notes'] );
		}

		if ( empty( $update ) ) {
			return new \WP_REST_Response( array( 'message' => 'No valid fields to update.' ), 400 );
		}

		$this->db->update_session( $session_id, $update );

		if ( isset( $update['status'] ) && 'completed' === $update['status'] ) {
			// Demo sessions never trigger payouts, stats, or completion mailouts.
			if ( ! zeko_mentor_is_demo_user( (int) $session['mentor_id'] ) && ! zeko_mentor_is_demo_user( (int) $session['mentee_id'] ) ) {
				$this->db->update_mentor_stats( (int) $session['mentor_id'] );
				do_action( 'zeko_mentor_session_completed', $session_id );
			}
		}

		if ( isset( $update['status'] ) && 'cancelled' === $update['status'] ) {
			do_action( 'zeko_mentor_session_cancelled', $session_id );
		}

		$updated = $this->db->get_session( $session_id );

		return new \WP_REST_Response( $updated, 200 );
	}

	// ─── Goals ────────────────────────────────────────────────.

	/**
	 * Goals.
	 *
	 * @param mixed $request Request.
	 */
	public function get_goals( $request ) {
		$user_id  = get_current_user_id();
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$goals = $this->db->get_user_goals( $user_id, $status, $per_page );

		return new \WP_REST_Response( $goals, 200 );
	}

	/**
	 * Create goal.
	 *
	 * @param mixed $request Request.
	 */
	public function create_goal( $request ) {
		$params  = $request->get_json_params();
		$user_id = get_current_user_id();

		$title = sanitize_text_field( $params['title'] ?? '' );
		if ( ! $title ) {
			return new \WP_REST_Response( array( 'message' => 'Title is required.' ), 400 );
		}

		$goal_id = $this->db->insert_goal(
			array(
				'user_id'     => $user_id,
				'title'       => $title,
				'description' => sanitize_textarea_field( $params['description'] ?? '' ),
				'target_date' => sanitize_text_field( $params['target_date'] ?? '' ),
			)
		);

		return new \WP_REST_Response(
			array(
				'goal_id' => $goal_id,
				'message' => 'Goal created.',
			),
			201
		);
	}

	// ─── Programs ─────────────────────────────────────────────.

	/**
	 * Programs.
	 *
	 * @param mixed $request Request.
	 */
	public function get_programs( $request ) {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$args = array(
			'status'    => $request->get_param( 'status' ),
			'expertise' => $request->get_param( 'expertise' ),
			'limit'     => $per_page,
			'offset'    => ( $page - 1 ) * $per_page,
		);

		$programs = $this->db->get_programs( $args );

		return new \WP_REST_Response( $programs, 200 );
	}

	/**
	 * Program.
	 *
	 * @param mixed $request Request.
	 */
	public function get_program( $request ) {
		$program = $this->db->get_program( absint( $request['id'] ) );

		if ( ! $program ) {
			return new \WP_REST_Response( array( 'message' => 'Program not found.' ), 404 );
		}

		return new \WP_REST_Response( $program, 200 );
	}

	/**
	 * Enroll program.
	 *
	 * @param mixed $request Request.
	 */
	public function enroll_program( $request ) {
		$program_id = absint( $request['id'] );
		$user_id    = get_current_user_id();
		$program    = $this->db->get_program( $program_id );

		if ( ! $program ) {
			return new \WP_REST_Response( array( 'message' => 'Program not found.' ), 404 );
		}

		if ( 'active' !== $program['status'] ) {
			return new \WP_REST_Response( array( 'message' => 'Program is not accepting enrollments.' ), 409 );
		}

		if ( (int) $program['current_members'] >= (int) $program['max_members'] ) {
			return new \WP_REST_Response( array( 'message' => 'Program is full.' ), 409 );
		}

		$amount = (float) ( $program['price'] ?? 0 );

		if ( $amount > 0 && class_exists( 'Zeko_Pay_Integrations' ) ) {
			$pay_result = Zeko_Pay_Integrations::instance()->mentor_pay_program(
				$user_id,
				$program_id,
				(int) $program['mentor_id'],
				$amount
			);
			if ( empty( $pay_result['success'] ) ) {
				return new \WP_REST_Response( array( 'message' => 'Payment failed.' ), 402 );
			}
			update_user_meta( $user_id, 'zeko_mentor_program_payment_' . $program_id, $pay_result['tx_id'] ?? '' );
		}

		$this->db->enroll_program( $program_id, $user_id );

		do_action( 'zeko_mentor_program_enrolled', $program_id, $user_id );

		return new \WP_REST_Response( array( 'message' => 'Enrolled successfully.' ), 201 );
	}

	/**
	 * Unenroll program.
	 *
	 * @param mixed $request Request.
	 */
	public function unenroll_program( $request ) {
		$program_id = absint( $request['id'] );
		$user_id    = get_current_user_id();
		$program    = $this->db->get_program( $program_id );

		if ( ! $program ) {
			return new \WP_REST_Response( array( 'message' => 'Program not found.' ), 404 );
		}

		$tx_id = (int) get_user_meta( $user_id, 'zeko_mentor_program_payment_' . $program_id, true );

		if ( $tx_id > 0 && class_exists( 'Zeko_Pay_Integrations' ) ) {
			$pay_result = Zeko_Pay_Integrations::instance()->mentor_refund_program(
				$program_id,
				$tx_id,
				$user_id,
				(int) $program['mentor_id'],
				(float) $program['price']
			);
			if ( ! empty( $pay_result['success'] ) ) {
				delete_user_meta( $user_id, 'zeko_mentor_program_payment_' . $program_id );
			}
		}

		$this->db->remove_program_member( $program_id, $user_id );

		return new \WP_REST_Response( array( 'message' => 'Unenrolled successfully.' ), 200 );
	}

	// ─── Matches ──────────────────────────────────────────────.

	/**
	 * Matches.
	 *
	 * @param mixed $request Request.
	 */
	public function get_matches( $request ) {
		$user_id  = get_current_user_id();
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$matches = $this->db->get_mentee_matches( $user_id, $status, $per_page );

		$data = array_map( array( $this, 'prepare_match_response' ), $matches );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Accept match.
	 *
	 * @param mixed $request Request.
	 */
	public function accept_match( $request ) {
		$match_id = absint( $request['id'] );
		$user_id  = get_current_user_id();

		$match = $this->db->get_mentee_matches( $user_id );
		$found = false;
		foreach ( $match as $m ) {
			if ( (int) $m['match_id'] === $match_id ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return new \WP_REST_Response( array( 'message' => 'Match not found.' ), 404 );
		}

		$this->db->update_match( $match_id, 'accepted' );

		do_action( 'zeko_mentor_match_accepted', $match_id, $user_id );

		return new \WP_REST_Response( array( 'message' => 'Match accepted.' ), 200 );
	}

	/**
	 * Dismiss match.
	 *
	 * @param mixed $request Request.
	 */
	public function dismiss_match( $request ) {
		$match_id = absint( $request['id'] );
		$user_id  = get_current_user_id();

		$match = $this->db->get_mentee_matches( $user_id );
		$found = false;
		foreach ( $match as $m ) {
			if ( (int) $m['match_id'] === $match_id ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return new \WP_REST_Response( array( 'message' => 'Match not found.' ), 404 );
		}

		$this->db->update_match( $match_id, 'dismissed' );

		return new \WP_REST_Response( array( 'message' => 'Match dismissed.' ), 200 );
	}

	// ─── Helpers ──────────────────────────────────────────────.

	/**
	 * Prepare mentor response.
	 *
	 * @param array $profile Profile.
	 */
	private function prepare_mentor_response( array $profile ): array {
		$profile['expertise_list'] = json_decode( $profile['expertise_areas'] ?? '[]', true );
		$profile['mentor_name']    = '';

		$user = get_userdata( (int) $profile['user_id'] );
		if ( $user ) {
			$profile['mentor_name'] = $user->display_name;
			$profile['avatar']      = get_avatar_url( $user->ID, array( 'size' => 256 ) );
		}

		return $profile;
	}

	/**
	 * Prepare match response.
	 *
	 * @param array $match Match.
	 */
	private function prepare_match_response( array $match ): array {
		$match['match_reasons_list'] = json_decode( $match['match_reasons'] ?? '[]', true );
		$match['expertise_list']     = json_decode( $match['expertise_areas'] ?? '[]', true );

		return $match;
	}
}
