<?php
/**
 * AJAX handler for Zeko Mentor.
 *
 * Central dispatcher for all AJAX actions.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_AJAX. */
class Zeko_Mentor_AJAX {

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * Sanitize rich-text (Quill) content with the narrow ecosystem
	 * allow-list; falls back to $this->sanitize_rich() when zeko-core is absent.
	 *
	 * @param mixed $html Html.
	 */
	private function sanitize_rich( $html ): string {
		if ( class_exists( 'Zeko_Core_Sanitize' ) ) {
			return Zeko_Core_Sanitize::rich_text( (string) $html );
		}
		return $this->sanitize_rich( $html );
	}

	/**
	 * Rate limits.
	 *
	 * @var array Rate limits.
	 */
	private static array $rate_limits = array(
		'book_session'       => array(
			'limit'  => 5,
			'window' => 60,
		),
		'submit_review'      => array(
			'limit'  => 3,
			'window' => 60,
		),
		'save_goal'          => array(
			'limit'  => 5,
			'window' => 60,
		),
		'save_availability'  => array(
			'limit'  => 10,
			'window' => 60,
		),
		'session_reschedule' => array(
			'limit'  => 5,
			'window' => 60,
		),
		'get_calendar_data'  => array(
			'limit'  => 30,
			'window' => 60,
		),
	);

	/**
	 * Construct.
	 *
	 * @param Zeko_Mentor_DB $db Db.
	 */
	public function __construct( Zeko_Mentor_DB $db ) {
		$this->db = $db;

		add_action( 'wp_ajax_zeko_mentor_book_session', array( $this, 'handle_book_session' ) );
		add_action( 'wp_ajax_zeko_mentor_cancel_session', array( $this, 'handle_cancel_session' ) );
		add_action( 'wp_ajax_zeko_mentor_complete_session', array( $this, 'handle_complete_session' ) );
		add_action( 'wp_ajax_zeko_mentor_submit_review', array( $this, 'handle_submit_review' ) );
		add_action( 'wp_ajax_zeko_mentor_save_goal', array( $this, 'handle_save_goal' ) );
		add_action( 'wp_ajax_zeko_mentor_update_goal', array( $this, 'handle_update_goal' ) );
		add_action( 'wp_ajax_zeko_mentor_log_progress', array( $this, 'handle_log_progress' ) );
		add_action( 'wp_ajax_zeko_mentor_save_availability', array( $this, 'handle_save_availability' ) );
		add_action( 'wp_ajax_zeko_mentor_get_available_slots', array( $this, 'handle_get_available_slots' ) );
		add_action( 'wp_ajax_zeko_mentor_save_profile', array( $this, 'handle_save_profile' ) );
		add_action( 'wp_ajax_zeko_mentor_dismiss_match', array( $this, 'handle_dismiss_match' ) );
		add_action( 'wp_ajax_zeko_mentor_accept_match', array( $this, 'handle_accept_match' ) );
		add_action( 'wp_ajax_zeko_mentor_join_program', array( $this, 'handle_join_program' ) );
		add_action( 'wp_ajax_zeko_mentor_leave_program', array( $this, 'handle_leave_program' ) );
		add_action( 'wp_ajax_zeko_mentor_search', array( $this, 'handle_mentor_search' ) );
		add_action( 'wp_ajax_zeko_mentor_send_message', array( $this, 'handle_send_message' ) );
		add_action( 'wp_ajax_zeko_mentor_apply_as_mentor', array( $this, 'handle_apply_as_mentor' ) );
		add_action( 'wp_ajax_zeko_mentor_verify_application', array( $this, 'handle_verify_application' ) );
		add_action( 'wp_ajax_nopriv_zeko_mentor_apply_as_mentor', array( $this, 'handle_apply_as_mentor' ) );

		// Notifications.
		add_action( 'wp_ajax_zm_get_notifications', array( $this, 'handle_get_notifications' ) );
		add_action( 'wp_ajax_zm_get_unread_count', array( $this, 'handle_get_unread_count' ) );
		add_action( 'wp_ajax_zm_mark_notification_read', array( $this, 'handle_mark_notification_read' ) );
		add_action( 'wp_ajax_zm_mark_all_notifications_read', array( $this, 'handle_mark_all_notifications_read' ) );

		// Timezone.
		add_action( 'wp_ajax_zeko_mentor_save_timezone', array( $this, 'handle_save_timezone' ) );
		add_action( 'wp_ajax_nopriv_zeko_mentor_save_timezone', array( $this, 'handle_save_timezone' ) );

		// Reschedule.
		add_action( 'wp_ajax_zeko_mentor_reschedule_session', array( $this, 'handle_reschedule_session' ) );

		// Get messages for inbox.
		add_action( 'wp_ajax_zeko_mentor_get_messages', array( $this, 'handle_get_messages' ) );
		add_action( 'wp_ajax_zeko_mentor_get_conversations', array( $this, 'handle_get_conversations' ) );

		// Calendar data.
		add_action( 'wp_ajax_zeko_mentor_get_calendar_data', array( $this, 'handle_get_calendar_data' ) );
		add_action( 'wp_ajax_nopriv_zeko_mentor_get_calendar_data', array( $this, 'handle_get_calendar_data' ) );

		// Demo data.
		add_action( 'wp_ajax_zeko_mentor_generate_demo_data', array( $this, 'handle_generate_demo_data' ) );
		add_action( 'wp_ajax_zeko_mentor_clear_demo_data', array( $this, 'handle_clear_demo_data' ) );
	}

	/**
	 * Verify nonce and rate limit.
	 *
	 * @param string $action Action.
	 */
	private function verify_request( string $action ): bool {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zeko_mentor_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return false;
		}
		if ( ! $this->check_rate_limit( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'zeko-mentor' ) ) );
			return false;
		}
		return true;
	}

	/**
	 * Simple rate limiter using transients.
	 *
	 * @param string $action Action name.
	 * @param string $identifier Optional explicit bucket ('u{id}' / 'ip{hash}').
	 */
	private function check_rate_limit( string $action, string $identifier = '' ): bool {
		if ( ! isset( self::$rate_limits[ $action ] ) ) {
			return true;
		}
		$config = self::$rate_limits[ $action ];
		$bucket = ( '' !== $identifier ) ? $identifier : 'u' . get_current_user_id();
		$key    = 'zeko_mentor_rate_' . $action . '_' . $bucket;
		$count  = (int) get_transient( $key );
		if ( $count >= $config['limit'] ) {
			return false;
		}
		set_transient( $key, $count + 1, $config['window'] );
		return true;
	}

	/**
	 * Get the client IP address for IP-scoped rate limits.
	 */
	private function client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return sanitize_text_field( $ip );
	}

	/**
	 * Handle session booking.
	 */
	public function handle_book_session(): void {
		if ( ! $this->verify_request( 'book_session' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$mentor_id    = absint( $_POST['mentor_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$mentee_id    = get_current_user_id();
		$session_date = sanitize_text_field( wp_unslash( $_POST['session_date'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$start_time   = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$end_time     = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$topic        = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$session_type = sanitize_text_field( wp_unslash( $_POST['session_type'] ?? 'video' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$session_type = in_array( $session_type, array( 'video', 'audio', 'chat' ), true ) ? $session_type : 'video';

		if ( ! $mentor_id || ! $session_date || ! $start_time || ! $end_time ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a date and an available time slot.', 'zeko-mentor' ) ) );
			return;
		}

		$profile = $this->db->get_profile( $mentor_id );
		if ( ! $profile || ! $profile['is_active'] ) {
			wp_send_json_error( array( 'message' => __( 'Mentor is not available.', 'zeko-mentor' ) ) );
			return;
		}

		// Check capacity.
		$active_mentees = $this->db->count_active_mentees( $mentor_id );
		if ( $active_mentees >= (int) $profile['max_mentees'] ) {
			wp_send_json_error( array( 'message' => __( 'Mentor has reached maximum mentee capacity.', 'zeko-mentor' ) ) );
			return;
		}

		// Validate requested time falls within an available slot.
		$available_slots = $this->db->get_available_slots( $mentor_id, $session_date );
		$valid_slot      = false;
		if ( ! empty( $available_slots ) ) {
			$request_start = strtotime( $start_time );
			$request_end   = strtotime( $end_time );
			foreach ( $available_slots as $slot ) {
				$slot_start = strtotime( $slot['start_time'] );
				$slot_end   = strtotime( $slot['end_time'] );
				if ( $request_start >= $slot_start && $request_end <= $slot_end ) {
					$valid_slot = true;
					break;
				}
			}
		}
		if ( ! $valid_slot ) {
			wp_send_json_error( array( 'message' => __( 'Selected time is not within the mentor\'s available hours.', 'zeko-mentor' ) ) );
			return;
		}

		// Generate meeting URL.
		$meeting_url = '';
		if ( 'video' === $session_type ) {
			$meeting_url = 'https://meet.jit.si/ZekoMentor-' . $mentor_id . '-' . $mentee_id . '-' . wp_generate_password( 8, false );
		}

		// Per-type session price (falls back to the base hourly rate).
		$rates  = method_exists( $this->db, 'get_session_rates' ) ? $this->db->get_session_rates( $mentor_id ) : array();
		$amount = (float) ( $rates[ $session_type ] ?? $profile['hourly_rate'] );

		$session_id = $this->db->create_session(
			array(
				'mentor_id'        => $mentor_id,
				'mentee_id'        => $mentee_id,
				'session_date'     => $session_date,
				'start_time'       => $start_time,
				'end_time'         => $end_time,
				'duration_minutes' => (int) ( ( strtotime( $end_time ) - strtotime( $start_time ) ) / 60 ),
				'session_type'     => $session_type,
				'meeting_url'      => $meeting_url,
				'topic'            => $topic,
				'amount'           => $amount,
				'currency'         => $profile['currency'],
				'payment_status'   => 'pending',
			)
		);

		// Prefer checkout via the Zeko Shop (invoice + receipt + loyalty for.
		// the mentee, deferred payout for the mentor). Fall back to a direct.
		// wallet charge when no linked session product is available.
		$payment_via_shop = false;
		$checkout_url     = '';

		if ( $amount > 0 && class_exists( 'Zeko_Shop' ) && class_exists( 'Zeko_Shop_DB' ) ) {
			$shop_db = Zeko_Shop::instance()->get_db();
			$product = $shop_db->get_product_by_external( 'session', $mentor_id, $session_type );

			if ( $product && 'active' === $product['status'] ) {
				$shop_db->add_to_cart( $mentee_id, (int) $product['product_id'], 1 );

				// Track the pending session so checkout can mark it paid.
				$pending                                   = get_user_meta( $mentee_id, 'zeko_mentor_pending_sessions', true );
				$pending                                   = is_array( $pending ) ? $pending : array();
				$pending[ (int) $product['product_id'] ][] = $session_id;
				update_user_meta( $mentee_id, 'zeko_mentor_pending_sessions', $pending );

				$payment_via_shop = true;
				$checkout_url     = function_exists( 'zeko_shop_page_url' )
					? zeko_shop_page_url( 'checkout' )
					: home_url( '/checkout/' );
			}
		}

		if ( ! $payment_via_shop && $amount > 0 && class_exists( 'Zeko_Pay_Integrations' ) ) {
			$payment = Zeko_Pay_Integrations::instance()->mentor_book_session(
				$mentee_id,
				$mentor_id,
				$session_id,
				$amount
			);

			if ( $payment['success'] ) {
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
				// Direct charge failed — never report a successful booking for.
				// an unpaid session. Roll the phantom session back.
				$this->db->delete_session( $session_id );
				$message = $payment['message'] ?? __( 'Payment failed. Please check your wallet balance.', 'zeko-mentor' );
				wp_send_json_error(
					array(
						'message'  => $message,
						'redirect' => home_url( '/wallet/' ),
					)
				);
			}
		}

		do_action( 'zeko_mentor_session_booked', $session_id, $mentor_id, $mentee_id );

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$mentee_id,
				'session_booked',
				__( 'Session booked', 'zeko-mentor' ),
				$session_id,
				array( 'module' => 'mentor' )
			);
		}

		if ( $payment_via_shop ) {
			wp_send_json_success(
				array(
					'message'         => __( 'Session requested! Complete checkout to confirm your booking.', 'zeko-mentor' ),
					'session_id'      => $session_id,
					'redirect'        => $checkout_url,
					'pending_payment' => true,
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Session booked successfully!', 'zeko-mentor' ),
				'session_id' => $session_id,
			)
		);
	}

	/**
	 * Handle session cancellation.
	 */
	public function handle_cancel_session(): void {
		if ( ! $this->verify_request( 'cancel_session' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$session_id = absint( $_POST['session_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$session    = $this->db->get_session( $session_id );
		$user_id    = get_current_user_id();

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'zeko-mentor' ) ) );
			return;
		}

		if ( (int) $session['mentee_id'] !== $user_id && (int) $session['mentor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->update_session( $session_id, array( 'status' => 'cancelled' ) );
		do_action( 'zeko_mentor_session_cancelled', $session_id );

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'session_cancelled',
				__( 'Session cancelled', 'zeko-mentor' ),
				$session_id,
				array( 'module' => 'mentor' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Session cancelled.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle session completion (mentor only).
	 */
	public function handle_complete_session(): void {
		if ( ! $this->verify_request( 'complete_session' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$session_id = absint( $_POST['session_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$session    = $this->db->get_session( $session_id );
		$user_id    = get_current_user_id();

		if ( ! $session || (int) $session['mentor_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->update_session( $session_id, array( 'status' => 'completed' ) );

		// Demo sessions never trigger payouts, stats, or completion mailouts.
		if ( ! zeko_mentor_is_demo_user( (int) $session['mentor_id'] ) && ! zeko_mentor_is_demo_user( (int) $session['mentee_id'] ) ) {
			$this->db->update_mentor_stats( (int) $session['mentor_id'] );
			do_action( 'zeko_mentor_session_completed', $session_id );
		}

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'session_completed',
				__( 'Session completed', 'zeko-mentor' ),
				$session_id,
				array( 'module' => 'mentor' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Session marked as completed.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle review submission.
	 */
	public function handle_submit_review(): void {
		if ( ! $this->verify_request( 'submit_review' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$session_id  = absint( $_POST['session_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$rating      = absint( $_POST['rating'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$review_text = $this->sanitize_rich( wp_unslash( $_POST['review_text'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; content allow-listed by Zeko_Core_Sanitize::rich_text().
		$user_id     = get_current_user_id();

		$session = $this->db->get_session( $session_id );
		if ( ! $session || (int) $session['mentee_id'] !== $user_id || 'completed' !== $session['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Cannot review this session.', 'zeko-mentor' ) ) );
			return;
		}

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Rating must be 1-5.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->insert_review(
			array(
				'session_id'  => $session_id,
				'mentor_id'   => $session['mentor_id'],
				'reviewer_id' => $user_id,
				'rating'      => $rating,
				'title'       => $title,
				'review_text' => $review_text,
			)
		);

		$this->db->update_session( $session_id, array( 'rating' => $rating ) );
		$this->db->update_mentor_stats( (int) $session['mentor_id'] );

		do_action( 'zeko_mentor_review_posted', $session_id, $user_id );

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'review_posted',
				__( 'Review posted', 'zeko-mentor' ),
				$session_id,
				array( 'module' => 'mentor' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Review submitted!', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle save availability.
	 */
	public function handle_save_availability(): void {
		if ( ! $this->verify_request( 'save_availability' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $this->db->is_mentor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Only mentors can set availability.', 'zeko-mentor' ) ) );
			return;
		}

		$slots = json_decode( wp_unslash( $_POST['slots'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; each slot field is sanitized in Zeko_Mentor_DB::save_availability().
		if ( ! is_array( $slots ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid slots data.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->save_availability( $user_id, $slots );

		wp_send_json_success( array( 'message' => __( 'Availability saved.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle get available slots.
	 */
	public function handle_get_available_slots(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zeko_mentor_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return;
		}

		$mentor_id = absint( $_POST['mentor_id'] ?? 0 );
		$date      = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );

		if ( ! $mentor_id || ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'zeko-mentor' ) ) );
			return;
		}

		$slots = $this->db->get_available_slots( $mentor_id, $date );

		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * Handle save profile.
	 */
	public function handle_save_profile(): void {
		if ( ! $this->verify_request( 'save_profile' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = get_current_user_id();

		$this->db->save_profile(
			$user_id,
			array(
				'expertise_areas'  => json_decode( wp_unslash( $_POST['expertise_areas'] ?? '[]' ), true ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; each area is sanitized in Zeko_Mentor_DB::save_profile().
				'bio'              => $this->sanitize_rich( wp_unslash( $_POST['bio'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; content allow-listed by Zeko_Core_Sanitize::rich_text().
				'headline'         => sanitize_text_field( wp_unslash( $_POST['headline'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'location'         => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'languages'        => sanitize_text_field( wp_unslash( $_POST['languages'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'years_experience' => absint( wp_unslash( $_POST['years_experience'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'response_time'    => sanitize_text_field( wp_unslash( $_POST['response_time'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'linkedin_url'     => esc_url_raw( wp_unslash( $_POST['linkedin_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'github_url'       => esc_url_raw( wp_unslash( $_POST['github_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'twitter_url'      => esc_url_raw( wp_unslash( $_POST['twitter_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'hourly_rate'      => (float) sanitize_text_field( wp_unslash( $_POST['hourly_rate'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'currency'         => sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'USD' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'max_mentees'      => absint( $_POST['max_mentees'] ?? 5 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
			)
		);

		wp_send_json_success( array( 'message' => __( 'Profile saved.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle goal creation.
	 */
	public function handle_save_goal(): void {
		if ( ! $this->verify_request( 'save_goal' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$goal_id = $this->db->insert_goal(
			array(
				'user_id'     => get_current_user_id(),
				'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'target_date' => sanitize_text_field( wp_unslash( $_POST['target_date'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Goal created!', 'zeko-mentor' ),
				'goal_id' => $goal_id,
			)
		);
	}

	/**
	 * Handle goal status update.
	 */
	public function handle_update_goal(): void {
		if ( ! $this->verify_request( 'update_goal' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$goal_id = absint( $_POST['goal_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$status  = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$user_id = get_current_user_id();

		$goal = $this->db->get_goal( $goal_id );
		if ( ! $goal ) {
			wp_send_json_error( array( 'message' => __( 'Goal not found.', 'zeko-mentor' ) ) );
			return;
		}
		if ( ! $this->can_manage_goal( $goal, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		if ( ! $this->db->update_goal( $goal_id, array( 'status' => $status ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Goal could not be updated.', 'zeko-mentor' ) ) );
			return;
		}

		if ( 'achieved' === $status ) {
			do_action( 'zeko_mentor_goal_achieved', $goal_id );
		}

		wp_send_json_success( array( 'message' => __( 'Goal updated.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle progress logging (mentor).
	 */
	public function handle_log_progress(): void {
		if ( ! $this->verify_request( 'log_progress' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$goal_id = absint( $_POST['goal_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$user_id = get_current_user_id();

		$goal = $this->db->get_goal( $goal_id );
		if ( ! $goal ) {
			wp_send_json_error( array( 'message' => __( 'Goal not found.', 'zeko-mentor' ) ) );
			return;
		}
		if ( ! $this->can_manage_goal( $goal, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->insert_progress(
			array(
				'goal_id'   => $goal_id,
				'user_id'   => (int) $goal['user_id'],
				'mentor_id' => $user_id,
				'note'      => $this->sanitize_rich( wp_unslash( $_POST['note'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; content allow-listed by Zeko_Core_Sanitize::rich_text().
				'rating'    => isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
			)
		);

		wp_send_json_success( array( 'message' => __( 'Progress logged.', 'zeko-mentor' ) ) );
	}

	/**
	 * Whether the current user may view/update a goal: the goal owner, or the
	 * mentor attached to the goal's linked session or program.
	 *
	 * @param array $goal Goal.
	 * @param int   $user_id User id.
	 */
	private function can_manage_goal( array $goal, int $user_id ): bool {
		if ( (int) $goal['user_id'] === $user_id ) {
			return true;
		}
		if ( ! empty( $goal['session_id'] ) ) {
			$session = $this->db->get_session( (int) $goal['session_id'] );
			if ( $session && (int) $session['mentor_id'] === $user_id ) {
				return true;
			}
		}
		if ( ! empty( $goal['program_id'] ) ) {
			$program = $this->db->get_program( (int) $goal['program_id'] );
			if ( $program && (int) $program['mentor_id'] === $user_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle match accept/dismiss.
	 */
	public function handle_dismiss_match(): void {
		if ( ! $this->verify_request( 'dismiss_match' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}
		$match_id = absint( $_POST['match_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$match    = $this->db->get_match( $match_id );
		if ( ! $this->is_match_participant( $match ) ) {
			wp_send_json_error( array( 'message' => __( 'Match not found.', 'zeko-mentor' ) ) );
			return;
		}
		$this->db->update_match( $match_id, 'rejected' );
		wp_send_json_success( array( 'message' => __( 'Match dismissed.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle accept match.
	 */
	public function handle_accept_match(): void {
		if ( ! $this->verify_request( 'accept_match' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}
		$match_id = absint( $_POST['match_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$match    = $this->db->get_match( $match_id );
		if ( ! $this->is_match_participant( $match ) ) {
			wp_send_json_error( array( 'message' => __( 'Match not found.', 'zeko-mentor' ) ) );
			return;
		}
		$this->db->update_match( $match_id, 'accepted' );
		wp_send_json_success( array( 'message' => __( 'Match accepted!', 'zeko-mentor' ) ) );
	}

	/**
	 * Whether the current user is one of the two match participants.
	 *
	 * @return bool True when the current user is the mentee or mentor of the match.
	 * @param ?array $match Match row or null when not found.
	 */
	private function is_match_participant( ?array $match ): bool {
		if ( ! $match ) {
			return false;
		}
		$user_id = get_current_user_id();
		return (int) $match['mentee_id'] === $user_id || (int) $match['mentor_id'] === $user_id;
	}

	/**
	 * Handle program enrollment.
	 */
	public function handle_join_program(): void {
		if ( ! $this->verify_request( 'join_program' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$program_id = absint( $_POST['program_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$program    = $this->db->get_program( $program_id );
		$user_id    = get_current_user_id();

		if ( ! $program || 'active' !== $program['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Program not available.', 'zeko-mentor' ) ) );
			return;
		}

		if ( $this->db->is_enrolled( $program_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already enrolled in this program.', 'zeko-mentor' ) ) );
			return;
		}

		if ( (int) $program['current_members'] >= (int) $program['max_members'] ) {
			wp_send_json_error( array( 'message' => __( 'This program is full.', 'zeko-mentor' ) ) );
			return;
		}

		$amount = (float) ( $program['price'] ?? 0 );

		if ( $amount > 0 && class_exists( 'Zeko_Shop' ) && class_exists( 'Zeko_Shop_DB' ) ) {
			$shop_db = Zeko_Shop::instance()->get_db();
			$product = $shop_db->get_product_by_external( 'program', (int) $program_id );

			if ( $product && 'active' === $product['status'] ) {
				$shop_db->add_to_cart( $user_id, (int) $product['product_id'], 1 );

				$checkout_url = function_exists( 'zeko_shop_page_url' )
					? zeko_shop_page_url( 'checkout' )
					: home_url( '/checkout/' );

				wp_send_json_success(
					array(
						'message'  => __( 'Added to cart. Complete checkout to enroll.', 'zeko-mentor' ),
						'redirect' => $checkout_url,
					)
				);
			}
		}

		if ( $amount > 0 && class_exists( 'Zeko_Pay_Integrations' ) ) {
			$pay_result = Zeko_Pay_Integrations::instance()->mentor_pay_program(
				$user_id,
				$program_id,
				(int) $program['mentor_id'],
				$amount
			);
			if ( empty( $pay_result['success'] ) ) {
				wp_send_json_error( array( 'message' => $pay_result['message'] ?? __( 'Payment failed. Please check your wallet balance and try again.', 'zeko-mentor' ) ) );
				return;
			}
			update_user_meta( $user_id, 'zeko_mentor_program_payment_' . $program_id, $pay_result['tx_id'] ?? '' );
		}

		$this->db->enroll_program( $program_id, $user_id );

		$program_url = home_url( '/programs/' . $program_id . '/' );
		$this->db->create_notification(
			array(
				'user_id' => $user_id,
				'type'    => 'program',
				'title'   => __( 'Enrolled in program', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: %s: program title */
					__( 'You joined "%s". Access the program member area here.', 'zeko-mentor' ),
					$program['title']
				),
				'link'    => $program_url,
			)
		);

		$current_user = wp_get_current_user();
		$this->db->create_notification(
			array(
				'user_id' => (int) $program['mentor_id'],
				'type'    => 'program',
				'title'   => __( 'New program member', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: 1: member name, 2: program title */
					__( '%1$s joined your program "%2$s".', 'zeko-mentor' ),
					$current_user->display_name,
					$program['title']
				),
				'link'    => $program_url,
			)
		);

		do_action( 'zeko_mentor_program_enrolled', $program_id, $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'Enrolled in program!', 'zeko-mentor' ),
				'link'    => $program_url,
			)
		);
	}

	/**
	 * Handle leaving a program (with refund).
	 */
	public function handle_leave_program(): void {
		if ( ! $this->verify_request( 'leave_program' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$program_id = absint( $_POST['program_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$user_id    = get_current_user_id();
		$program    = $this->db->get_program( $program_id );

		if ( ! $program ) {
			wp_send_json_error( array( 'message' => __( 'Program not found.', 'zeko-mentor' ) ) );
			return;
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

				if ( class_exists( 'Zeko_Shop' ) && method_exists( 'Zeko_Shop', 'instance' ) ) {
					$programs_bridge = Zeko_Shop::instance()->get_programs();
					if ( $programs_bridge ) {
						$programs_bridge->mark_program_orders_refunded( $program_id, $user_id );
					}
				}
			}
		}

		$this->db->remove_program_member( $program_id, $user_id );

		$current_user = wp_get_current_user();
		$this->db->create_notification(
			array(
				'user_id' => (int) $program['mentor_id'],
				'type'    => 'program',
				'title'   => __( 'Member left program', 'zeko-mentor' ),
				'message' => sprintf(
				/* translators: 1: member name, 2: program title */
					__( '%1$s left your program "%2$s".', 'zeko-mentor' ),
					$current_user->display_name,
					$program['title']
				),
				'link'    => home_url( '/programs/' . $program_id . '/' ),
			)
		);

		do_action( 'zeko_mentor_program_left', $program_id, $user_id );

		wp_send_json_success( array( 'message' => __( 'Left the program.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle mentor search (live filter).
	 */
	public function handle_mentor_search(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zeko_mentor_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return;
		}

		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = 12;
		$offset   = ( $page - 1 ) * $per_page;

		$mentors = $this->db->get_mentors(
			array(
				'search' => $search,
				'limit'  => $per_page,
				'offset' => $offset,
			)
		);

		$total = $this->db->get_mentor_count( array( 'search' => $search ) );

		wp_send_json_success(
			array(
				'mentors' => $mentors,
				'total'   => $total,
				'pages'   => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Handle sending a mentorship message.
	 */
	public function handle_send_message(): void {
		if ( ! $this->verify_request( 'send_message' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$receiver_id = absint( $_POST['receiver_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$message     = $this->sanitize_rich( wp_unslash( $_POST['message'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; content allow-listed by Zeko_Core_Sanitize::rich_text().
		$session_id  = absint( $_POST['session_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.

		if ( ! $receiver_id || ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->insert_message(
			array(
				'sender_id'   => get_current_user_id(),
				'receiver_id' => $receiver_id,
				'session_id'  => $session_id ?: null,
				'message'     => $message,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Message sent.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle mentor application (become-a-mentor form).
	 */
	public function handle_apply_as_mentor(): void {
		if ( ! $this->verify_request( 'apply_as_mentor' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id         = get_current_user_id();
		$existing        = $this->db->get_profile( $user_id );
		$expertise_areas = json_decode( wp_unslash( $_POST['expertise_areas'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request() at handler start; each area is sanitized in Zeko_Mentor_DB::save_profile().
		$bio             = sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$hourly_rate     = (float) sanitize_text_field( wp_unslash( $_POST['hourly_rate'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$currency        = sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'USD' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$max_mentees     = absint( $_POST['max_mentees'] ?? 5 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.

		if ( empty( $expertise_areas ) || empty( $bio ) || $hourly_rate <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Please fill all required fields.', 'zeko-mentor' ) ) );
			return;
		}

		if ( $existing && 'approved' === ( $existing['verification_status'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already a verified mentor.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->save_profile(
			$user_id,
			array(
				'expertise_areas'     => $expertise_areas,
				'bio'                 => $bio,
				'headline'            => sanitize_text_field( wp_unslash( $_POST['headline'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'location'            => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'languages'           => sanitize_text_field( wp_unslash( $_POST['languages'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'years_experience'    => absint( wp_unslash( $_POST['years_experience'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'response_time'       => sanitize_text_field( wp_unslash( $_POST['response_time'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'linkedin_url'        => esc_url_raw( wp_unslash( $_POST['linkedin_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'github_url'          => esc_url_raw( wp_unslash( $_POST['github_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'twitter_url'         => esc_url_raw( wp_unslash( $_POST['twitter_url'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'hourly_rate'         => $hourly_rate,
				'currency'            => $currency,
				'max_mentees'         => $max_mentees,
				'is_active'           => 0,
				'verification_status' => 'pending',
			)
		);

		$this->db->set_session_rates(
			$user_id,
			array(
				'video' => (float) sanitize_text_field( wp_unslash( $_POST['session_rate_video'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'audio' => (float) sanitize_text_field( wp_unslash( $_POST['session_rate_audio'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
				'chat'  => (float) sanitize_text_field( wp_unslash( $_POST['session_rate_chat'] ?? 0 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
			)
		);

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'mentor_applied',
				__( 'Mentor application submitted', 'zeko-mentor' ),
				$user_id,
				array( 'module' => 'mentor' )
			);
		}

		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			'New Mentor Application',
			sprintf(
				"A new mentor application has been submitted by %s (%s).\n\nExpertise: %s\nBio: %s\nRate: %s %s\n\nPlease review in wp-admin.",
				get_userdata( $user_id )->display_name,
				get_userdata( $user_id )->user_email,
				implode( ', ', $expertise_areas ),
				$bio,
				$hourly_rate,
				$currency
			)
		);

		do_action( 'zeko_mentor_application_submitted', $user_id );

		wp_send_json_success( array( 'message' => __( 'Application submitted! We will review it shortly.', 'zeko-mentor' ) ) );
	}

	/**
	 * Handle admin verifying (approving/rejecting) a mentor application.
	 */
	public function handle_verify_application(): void {
		if ( ! $this->verify_request( 'verify_application' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$action  = sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.

		if ( ! $user_id || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'zeko-mentor' ) ) );
			return;
		}

		$status    = 'approve' === $action ? 'approved' : 'rejected';
		$is_active = 'approve' === $action ? 1 : 0;

		$this->db->save_profile(
			$user_id,
			array(
				'verification_status' => $status,
				'verification_date'   => gmdate( 'Y-m-d H:i:s' ),
				'is_active'           => $is_active,
			)
		);

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'mentor_' . $status,
				/* translators: %s: verification status */
				sprintf( __( 'Mentor verification %s', 'zeko-mentor' ), $status ),
				$user_id,
				array( 'module' => 'mentor' )
			);
		}

		$user_data = get_userdata( $user_id );
		if ( $user_data && ! zeko_mentor_is_demo_user( $user_id ) ) {
			wp_mail(
				$user_data->user_email,
				'approve' === $action ? 'Mentor Application Approved!' : 'Mentor Application Update',
				'approve' === $action
					? 'Congratulations! Your mentor application has been approved. You can now start accepting sessions.'
					: 'Thank you for your application. Unfortunately, we were unable to approve it at this time.'
			);
		}

		do_action( 'zeko_mentor_application_verified', $user_id, $status );

		/* translators: %s: application status */
		wp_send_json_success( array( 'message' => sprintf( __( 'Application %s.', 'zeko-mentor' ), $status ) ) );
	}

	// ═══════════════════════════════════════════════════════════════.
	// NOTIFICATION HANDLERS.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get notifications for the current user.
	 */
	public function handle_get_notifications(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id       = get_current_user_id();
		$notifications = $this->db->get_notifications( $user_id, 20 );

		// Add human-readable time_ago.
		foreach ( $notifications as &$n ) {
			$n['time_ago'] = $this->time_ago( $n['created_at'] );
		}
		unset( $n );

		wp_send_json_success( array( 'notifications' => $notifications ) );
	}

	/**
	 * Get unread notification count for the current user.
	 */
	public function handle_get_unread_count(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'count' => 0 ) );
			return;
		}

		$count = $this->db->count_unread_notifications( get_current_user_id() );
		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * Mark a single notification as read.
	 */
	public function handle_mark_notification_read(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zm_notifications_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return;
		}

		$notification_id = absint( $_POST['notification_id'] ?? 0 );
		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->mark_notification_read( $notification_id, get_current_user_id() );
		wp_send_json_success( array( 'message' => __( 'Marked as read.', 'zeko-mentor' ) ) );
	}

	/**
	 * Mark all notifications as read for the current user.
	 */
	public function handle_mark_all_notifications_read(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->mark_all_notifications_read( get_current_user_id() );
		wp_send_json_success( array( 'message' => __( 'All marked as read.', 'zeko-mentor' ) ) );
	}

	/**
	 * Convert a datetime to a human-readable "time ago" string.
	 *
	 * @param string $datetime Datetime.
	 */
	private function time_ago( string $datetime ): string {
		$now  = new \DateTime();
		$ago  = new \DateTime( $datetime );
		$diff = $now->diff( $ago );

		if ( $diff->y > 0 ) {
			/* translators: %d: number of years */
			return sprintf( _n( '%d year ago', '%d years ago', $diff->y, 'zeko-mentor' ), $diff->y );
		}
		if ( $diff->m > 0 ) {
			/* translators: %d: number of months */
			return sprintf( _n( '%d month ago', '%d months ago', $diff->m, 'zeko-mentor' ), $diff->m );
		}
		if ( $diff->d > 0 ) {
			/* translators: %d: number of days */
			return sprintf( _n( '%d day ago', '%d days ago', $diff->d, 'zeko-mentor' ), $diff->d );
		}
		if ( $diff->h > 0 ) {
			/* translators: %d: number of hours */
			return sprintf( _n( '%d hour ago', '%d hours ago', $diff->h, 'zeko-mentor' ), $diff->h );
		}
		if ( $diff->i > 0 ) {
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d minute ago', '%d minutes ago', $diff->i, 'zeko-mentor' ), $diff->i );
		}

		return __( 'Just now', 'zeko-mentor' );
	}

	// ═══════════════════════════════════════════════════════════════.
	// TIMEZONE HANDLER.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Handle save timezone.
	 */
	public function handle_save_timezone(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zeko_mentor_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$timezone = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) );
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid timezone.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'zeko_timezone', $timezone );

		// Also update profile if it exists.
		$profile = $this->db->get_profile( $user_id );
		if ( $profile ) {
			$this->db->save_profile(
				$user_id,
				array(
					'timezone'  => $timezone,
					'is_active' => $profile['is_active'],
				)
			);
		}

		wp_send_json_success( array( 'message' => __( 'Timezone saved.', 'zeko-mentor' ) ) );
	}

	// ═══════════════════════════════════════════════════════════════.
	// RESCHEDULE HANDLER.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Handle reschedule session.
	 */
	public function handle_reschedule_session(): void {
		if ( ! $this->verify_request( 'session_reschedule' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$session_id = absint( $_POST['session_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$session    = $this->db->get_session( $session_id );
		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		if ( (int) $session['mentor_id'] !== $user_id && (int) $session['mentee_id'] !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}

		if ( ! in_array( $session['status'], array( 'pending', 'confirmed' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Session cannot be rescheduled.', 'zeko-mentor' ) ) );
			return;
		}

		$new_date  = sanitize_text_field( wp_unslash( $_POST['session_date'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$new_start = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.
		$new_end   = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.

		if ( ! $new_date || ! $new_start || ! $new_end ) {
			wp_send_json_error( array( 'message' => __( 'Missing date/time fields.', 'zeko-mentor' ) ) );
			return;
		}

		$this->db->update_session(
			$session_id,
			array(
				'status'       => $session['status'],
				'session_date' => $new_date,
				'start_time'   => $new_start,
				'end_time'     => $new_end,
			)
		);

		do_action( 'zeko_mentor_session_rescheduled', $session_id );

		if ( class_exists( 'Zeko_Core_Activity' ) ) {
			\Zeko_Core_Activity::get_instance()->log(
				$user_id,
				'session_rescheduled',
				__( 'Session rescheduled', 'zeko-mentor' ),
				$session_id,
				array( 'module' => 'mentor' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Session rescheduled successfully.', 'zeko-mentor' ) ) );
	}

	// ═══════════════════════════════════════════════════════════════.
	// MESSAGING INBOX HANDLERS.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Get conversations (users the current user has exchanged messages with).
	 */
	public function handle_get_conversations(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		global $wpdb;
		$messages_table = $this->db->get_table_messages();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.other_id,
					MAX(c.created_at) AS last_message_at,
					(SELECT m2.message FROM {$messages_table} m2
					 WHERE (m2.sender_id = %d AND m2.receiver_id = c.other_id)
					    OR (m2.receiver_id = %d AND m2.sender_id = c.other_id)
					 ORDER BY m2.created_at DESC LIMIT 1
					) AS last_message,
					(SELECT COUNT(*) FROM {$messages_table} m3
					 WHERE m3.receiver_id = %d AND m3.sender_id = c.other_id AND m3.is_read = 0
					) AS unread_count
				FROM (
					SELECT sender_id AS other_id, created_at FROM {$messages_table} WHERE receiver_id = %d
					UNION
					SELECT receiver_id AS other_id, created_at FROM {$messages_table} WHERE sender_id = %d
				) c
				GROUP BY c.other_id
				ORDER BY last_message_at DESC
				LIMIT 50",
				$user_id,
				$user_id,
				$user_id,
				$user_id,
				$user_id
			),
			ARRAY_A
		) ?: array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// Add display names.
		foreach ( $conversations as &$c ) {
			$ud          = get_userdata( (int) $c['other_id'] );
			$c['name']   = $ud ? $ud->display_name : 'User';
			$c['avatar'] = get_avatar_url( (int) $c['other_id'], array( 'size' => 48 ) );
		}
		unset( $c );

		wp_send_json_success( array( 'conversations' => $conversations ) );
	}

	/**
	 * Get messages for a conversation with a specific user.
	 */
	public function handle_get_messages(): void {
		if ( ! $this->verify_request( 'get_messages' ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'zeko-mentor' ) ) );
			return;
		}

		$user_id  = get_current_user_id();
		$other_id = absint( $_POST['other_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request() at handler start.

		if ( ! $other_id || $other_id === $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'zeko-mentor' ) ) );
			return;
		}

		$messages = $this->db->get_messages( $user_id, $other_id, 100 );

		// Mark incoming messages as read.
		$this->db->mark_messages_read( $other_id, $user_id );

		foreach ( $messages as &$m ) {
			$m['is_mine'] = (int) $m['sender_id'] === $user_id;
		}
		unset( $m );

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Get calendar data for a mentor (availability + sessions for a month).
	 */
	public function handle_get_calendar_data(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'zeko_mentor_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'zeko-mentor' ) ) );
			return;
		}

		$bucket = is_user_logged_in() ? 'u' . get_current_user_id() : 'ip' . md5( $this->client_ip() );
		if ( ! $this->check_rate_limit( 'get_calendar_data', $bucket ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'zeko-mentor' ) ) );
			return;
		}

		$mentor_id  = absint( $_POST['mentor_id'] ?? 0 );
		$year_month = sanitize_text_field( wp_unslash( $_POST['year_month'] ?? '' ) );

		if ( ! $mentor_id || ! $year_month ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'zeko-mentor' ) ) );
			return;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}$/', $year_month ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid year_month format.', 'zeko-mentor' ) ) );
			return;
		}

		// Get availability for the mentor.
		$availability = $this->db->get_availability( $mentor_id );

		// Build array of date strings that have availability.
		$days_with_avail = array();
		$year            = (int) substr( $year_month, 0, 4 );
		$month           = (int) substr( $year_month, 5, 2 );
		$days_in_month   = (int) gmdate( 't', strtotime( $year_month . '-01' ) );

		foreach ( $availability as $slot ) {
			for ( $d = 1; $d <= $days_in_month; $d++ ) {
				$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
				$dow      = (int) gmdate( 'w', strtotime( $date_str ) );
				if ( $dow === (int) $slot['day_of_week'] ) {
					$days_with_avail[] = $date_str;
				}
			}
		}
		$days_with_avail = array_unique( $days_with_avail );
		sort( $days_with_avail );

		// Get sessions for that month.
		$all_sessions  = $this->db->get_user_sessions( $mentor_id, 'mentor', 'confirmed', 500 );
		$session_dates = array();
		$prefix        = $year_month . '-';
		foreach ( $all_sessions as $s ) {
			if ( str_starts_with( $s['session_date'], $prefix ) ) {
				$session_dates[] = array(
					'session_date' => $s['session_date'],
					'start_time'   => $s['start_time'],
					'end_time'     => $s['end_time'],
				);
			}
		}

		wp_send_json_success(
			array(
				'has_availability' => array_values( $days_with_avail ),
				'sessions'         => $session_dates,
			)
		);
	}

	// ═══════════════════════════════════════════════════════════════.
	// DEMO DATA HANDLERS.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Generate demo data.
	 */
	public function handle_generate_demo_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ?? '' ) ), 'zeko_mentor_demo_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'zeko-mentor' ) ) );
			return;
		}

		require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/admin/class-zeko-mentor-demo-generator.php';
		$generator = new Zeko_Mentor_Demo_Generator( $this->db );

		try {
			$counts = $generator->seed();
			wp_send_json_success(
				array(
					'message' => __( 'Demo data generated successfully.', 'zeko-mentor' ),
					'counts'  => $counts,
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
					/* translators: %s: error message */
						__( 'Demo generation failed: %s', 'zeko-mentor' ),
						$e->getMessage()
					),
				)
			);
		}
	}

	/**
	 * Clear demo data.
	 */
	public function handle_clear_demo_data(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'zeko-mentor' ) ) );
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ?? '' ) ), 'zeko_mentor_demo_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'zeko-mentor' ) ) );
			return;
		}

		require_once ZEKO_MENTOR_PLUGIN_PATH . 'includes/admin/class-zeko-mentor-demo-generator.php';
		$generator = new Zeko_Mentor_Demo_Generator( $this->db );

		try {
			$generator->clear();
			wp_send_json_success(
				array(
					'message' => __( 'All demo data cleared.', 'zeko-mentor' ),
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
					/* translators: %s: error message */
						__( 'Failed to clear demo data: %s', 'zeko-mentor' ),
						$e->getMessage()
					),
				)
			);
		}
	}
}
