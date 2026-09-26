<?php
/**
 * Session detail template with Join Video Call button.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id    = get_current_user_id();
$session_id = absint( wp_unslash( $_GET['session_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET session id for display; no state change.
$db         = zeko_mentor()->get_db();

if ( ! $session_id ) {
	echo '<p>' . esc_html__( 'Session not found.', 'zeko-mentor' ) . '</p>';
	return;
}

$session = $db->get_session( $session_id );
if ( ! $session ) {
	echo '<p>' . esc_html__( 'Session not found.', 'zeko-mentor' ) . '</p>';
	return;
}

$is_mentor = (int) $session['mentor_id'] === $user_id;
$is_mentee = (int) $session['mentee_id'] === $user_id;

if ( ! $is_mentor && ! $is_mentee ) {
	echo '<p>' . esc_html__( 'You do not have access to this session.', 'zeko-mentor' ) . '</p>';
	return;
}

$mentor_userdata = get_userdata( (int) $session['mentor_id'] );
$mentee_userdata = get_userdata( (int) $session['mentee_id'] );
$mentor_name     = $mentor_userdata ? $mentor_userdata->display_name : 'User #' . $session['mentor_id'];
$mentee_name     = $mentee_userdata ? $mentee_userdata->display_name : 'User #' . $session['mentee_id'];

$session_datetime = strtotime( $session['session_date'] . ' ' . $session['start_time'] );
$now              = time();
$can_join         = $session['meeting_url'] && ( $now >= ( $session_datetime - 15 * MINUTE_IN_SECONDS ) ) && $now <= ( $session_datetime + ( (int) $session['duration_minutes'] + 15 ) * MINUTE_IN_SECONDS );
$can_complete     = $is_mentor && 'confirmed' === $session['status'];
$can_cancel       = ( $is_mentor || $is_mentee ) && in_array( $session['status'], array( 'pending', 'confirmed' ), true );

$status_colors = array(
	'pending'   => '#f59e0b',
	'confirmed' => '#3b82f6',
	'completed' => '#10b981',
	'cancelled' => '#ef4444',
);
?>

<div class="zeko-session-detail" style="max-width:720px;margin:0 auto;">
	<h2><?php echo esc_html( $session['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' ) ); ?></h2>

	<div style="display:flex;gap:8px;margin-bottom:20px;">
		<span style="background:<?php echo esc_attr( $status_colors[ $session['status'] ] ?? '#666' ); ?>;color:#fff;padding:4px 12px;border-radius:16px;font-size:13px;font-weight:600;">
			<?php echo esc_html( ucfirst( $session['status'] ) ); ?>
		</span>
		<?php if ( $session['payment_status'] ) : ?>
			<span style="background:#e2e8f0;padding:4px 12px;border-radius:16px;font-size:13px;">
				<?php echo esc_html( ucfirst( $session['payment_status'] ) ); ?>
			</span>
		<?php endif; ?>
	</div>

	<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:20px;">
		<table style="width:100%;border-collapse:collapse;">
			<tr style="border-bottom:1px solid #f3f4f6;">
				<td style="padding:10px 0;color:#6b7280;width:140px;"><?php esc_html_e( 'Date', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;"><?php echo esc_html( gmdate( 'l, F j, Y', strtotime( $session['session_date'] ) ) ); ?></td>
			</tr>
			<tr style="border-bottom:1px solid #f3f4f6;">
				<td style="padding:10px 0;color:#6b7280;"><?php esc_html_e( 'Time', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;">
					<?php echo esc_html( $session['start_time'] . ' – ' . $session['end_time'] ); ?>
					<span style="color:#999;margin-left:8px;">(<?php echo esc_html( $session['duration_minutes'] ); ?> <?php esc_html_e( 'min', 'zeko-mentor' ); ?>)</span>
				</td>
			</tr>
			<tr style="border-bottom:1px solid #f3f4f6;">
				<td style="padding:10px 0;color:#6b7280;"><?php esc_html_e( 'Type', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;"><?php echo esc_html( ucfirst( $session['session_type'] ) ); ?></td>
			</tr>
			<tr style="border-bottom:1px solid #f3f4f6;">
				<td style="padding:10px 0;color:#6b7280;"><?php esc_html_e( 'Mentor', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;"><?php echo esc_html( $mentor_name ); ?></td>
			</tr>
			<tr style="border-bottom:1px solid #f3f4f6;">
				<td style="padding:10px 0;color:#6b7280;"><?php esc_html_e( 'Mentee', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;"><?php echo esc_html( $mentee_name ); ?></td>
			</tr>
			<tr>
				<td style="padding:10px 0;color:#6b7280;"><?php esc_html_e( 'Amount', 'zeko-mentor' ); ?></td>
				<td style="padding:10px 0;font-weight:600;color:#7c3aed;">
					<?php echo esc_html( $session['amount'] . ' ' . $session['currency'] ); ?>
				</td>
			</tr>
		</table>
	</div>

	<?php if ( $session['meeting_url'] ) : ?>
		<div style="background:#eff6ff;border:2px solid #3b82f6;border-radius:8px;padding:20px;text-align:center;margin-bottom:20px;">
			<?php if ( $can_join ) : ?>
				<p style="margin:0 0 12px;color:#1e40af;font-weight:600;"><?php esc_html_e( 'Your session is ready!', 'zeko-mentor' ); ?></p>
				<a href="<?php echo esc_url( $session['meeting_url'] ); ?>" target="_blank" rel="noopener"
					style="display:inline-block;background:#3b82f6;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;">
					📹 <?php esc_html_e( 'Join Video Call', 'zeko-mentor' ); ?>
				</a>
				<p style="margin:12px 0 0;color:#6b7280;font-size:13px;">
					<?php esc_html_e( 'The call will open in a new tab. Please allow camera/microphone access.', 'zeko-mentor' ); ?>
				</p>
			<?php else : ?>
				<p style="margin:0 0 8px;color:#1e40af;"><?php esc_html_e( 'Meeting link available 15 minutes before the session.', 'zeko-mentor' ); ?></p>
				<input type="text" readonly value="<?php echo esc_attr( $session['meeting_url'] ); ?>"
						style="width:100%;max-width:400px;padding:8px;border:1px solid #d1d5db;border-radius:4px;text-align:center;font-size:13px;color:#6b7280;"
						onclick="this.select();" />
				<p style="margin:8px 0 0;color:#999;font-size:12px;"><?php esc_html_e( 'Click to select and copy', 'zeko-mentor' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $session['notes'] ) : ?>
		<div style="background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;">
			<strong style="color:#374151;"><?php esc_html_e( 'Notes', 'zeko-mentor' ); ?></strong>
			<p style="margin:8px 0 0;color:#555;"><?php echo esc_html( $session['notes'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $session['mentor_notes'] && $is_mentor ) : ?>
		<div style="background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;">
			<strong style="color:#374151;"><?php esc_html_e( 'Private Notes', 'zeko-mentor' ); ?></strong>
			<p style="margin:8px 0 0;color:#555;"><?php echo esc_html( $session['mentor_notes'] ); ?></p>
		</div>
	<?php endif; ?>

	<div style="display:flex;gap:8px;margin-top:20px;">
		<?php if ( $can_complete ) : ?>
			<button class="button button-primary zm-complete-session" data-session-id="<?php echo esc_attr( $session_id ); ?>">
				<?php esc_html_e( 'Mark as Completed', 'zeko-mentor' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $can_cancel ) : ?>
			<button class="button zm-cancel-session" data-session-id="<?php echo esc_attr( $session_id ); ?>" style="color:#ef4444;">
				<?php esc_html_e( 'Cancel Session', 'zeko-mentor' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( 'completed' === $session['status'] && $is_mentee && ! $session['rating'] ) : ?>
			<div class="zm-review-form" data-session-id="<?php echo esc_attr( $session_id ); ?>" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-top:16px;width:100%;">
				<h4 style="margin:0 0 12px;"><?php esc_html_e( 'Leave a Review', 'zeko-mentor' ); ?></h4>
				<div class="zm-star-rating" style="margin-bottom:12px;">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<span class="star" data-value="<?php echo esc_attr( $i ); ?>" style="cursor:pointer;font-size:24px;color:#e5e7eb;">★</span>
					<?php endfor; ?>
					<input type="hidden" name="rating" value="0" />
				</div>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Review title...', 'zeko-mentor' ); ?>" style="width:100%;margin-bottom:8px;padding:8px;border:1px solid #d1d5db;border-radius:4px;" />
				<textarea name="review_text" rows="3" placeholder="<?php esc_attr_e( 'Share your experience...', 'zeko-mentor' ); ?>" style="width:100%;margin-bottom:8px;padding:8px;border:1px solid #d1d5db;border-radius:4px;"></textarea>
				<button class="button button-primary zm-submit-review"><?php esc_html_e( 'Submit Review', 'zeko-mentor' ); ?></button>
			</div>
		<?php endif; ?>
	</div>
</div>
