<?php
/**
 * Single program template — public detail page + member area for enrolled users.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$program_id = absint( wp_unslash( $_GET['program'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET program id for display; no state change.
$db         = zeko_mentor()->get_db();
$program    = $db->get_program( $program_id );

if ( ! $program ) {
	echo '<p>' . esc_html__( 'Program not found.', 'zeko-mentor' ) . '</p>';
	return;
}

$user_id      = get_current_user_id();
$is_logged_in = is_user_logged_in();
$is_enrolled  = $is_logged_in && $db->is_enrolled( $program_id, $user_id );
$is_mentor    = $is_logged_in && (int) $program['mentor_id'] === $user_id;
$has_access   = $is_enrolled || $is_mentor;
$is_full      = (int) $program['current_members'] >= (int) $program['max_members'];
$is_free      = (float) $program['price'] <= 0;
$members      = $db->get_program_members( $program_id );

$mentor_user = get_userdata( (int) $program['mentor_id'] );
$mentor_name = $program['mentor_name'] ?? ( $mentor_user ? $mentor_user->display_name : '' );
$mentor_slug = $mentor_user ? $mentor_user->user_nicename : '';
$mentor_url  = $mentor_slug ? home_url( '/mentors/' . $mentor_slug . '/' ) : '';
$price_label = $is_free ? __( 'Free', 'zeko-mentor' ) : number_format_i18n( (float) $program['price'], 2 ) . ' ' . $program['currency'];
$enrolled_at = '';

if ( $is_enrolled ) {
	foreach ( $members as $mentor_data ) {
		if ( (int) $mentor_data['user_id'] === $user_id ) {
			$enrolled_at = $mentor_data['enrolled_at'];
			break;
		}
	}
}
?>

<div class="zeko-program-single">

	<div class="zeko-program-single-hero">
		<div>
			<h1 class="zeko-program-single-title"><?php echo esc_html( $program['title'] ); ?></h1>
			<?php if ( $mentor_name ) : ?>
				<p class="zeko-program-single-mentor">
					<?php esc_html_e( 'Led by', 'zeko-mentor' ); ?>
					<?php if ( $mentor_url ) : ?>
						<a href="<?php echo esc_url( $mentor_url ); ?>"><?php echo esc_html( $mentor_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $mentor_name ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $program['expertise_area'] ) : ?>
				<span class="zeko-expertise-tag"><?php echo esc_html( $program['expertise_area'] ); ?></span>
			<?php endif; ?>
		</div>
		<div class="zeko-program-single-hero-side">
			<?php if ( 'active' !== $program['status'] ) : ?>
				<span class="zeko-program-status status-full"><?php esc_html_e( 'Inactive', 'zeko-mentor' ); ?></span>
			<?php elseif ( $is_full ) : ?>
				<span class="zeko-program-status status-full"><?php esc_html_e( 'Full', 'zeko-mentor' ); ?></span>
			<?php else : ?>
				<span class="zeko-program-status status-enrolled"><?php esc_html_e( 'Open for enrollment', 'zeko-mentor' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="zeko-program-single-grid">

		<div class="zeko-program-single-main">
			<?php if ( $program['description'] ) : ?>
				<div class="zeko-program-section">
					<h3 class="zeko-section-title"><?php esc_html_e( 'About this program', 'zeko-mentor' ); ?></h3>
					<div class="zeko-program-description">
						<?php echo wp_kses_post( wpautop( $program['description'] ) ); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="zeko-program-single-side">
			<div class="zeko-program-facts-card">
				<h4 class="zeko-program-facts-title"><?php esc_html_e( 'Program Details', 'zeko-mentor' ); ?></h4>
				<ul class="zeko-program-facts">
					<li><span><?php esc_html_e( 'Duration', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['duration_weeks'] ); ?> <?php esc_html_e( 'weeks', 'zeko-mentor' ); ?></strong></li>
					<li><span><?php esc_html_e( 'Members', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['current_members'] . '/' . $program['max_members'] ); ?></strong></li>
					<li><span><?php esc_html_e( 'Price', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $price_label ); ?></strong></li>
					<?php if ( $program['start_date'] ) : ?>
						<li><span><?php esc_html_e( 'Starts', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['start_date'] ); ?></strong></li>
					<?php endif; ?>
					<?php if ( $program['end_date'] ) : ?>
						<li><span><?php esc_html_e( 'Ends', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['end_date'] ); ?></strong></li>
					<?php endif; ?>
				</ul>
			</div>

			<div class="zeko-program-facts-card zeko-enroll-card">
				<h4 class="zeko-program-facts-title"><?php esc_html_e( 'Enrollment', 'zeko-mentor' ); ?></h4>

				<?php if ( ! $is_logged_in ) : ?>
					<p class="zeko-program-desc"><?php esc_html_e( 'Log in to join this program and access the member area.', 'zeko-mentor' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( home_url( '/programs/' . $program_id . '/' ) ) ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Log in to Join', 'zeko-mentor' ); ?></a>
				<?php elseif ( $is_mentor ) : ?>
					<p class="zeko-program-desc"><?php esc_html_e( 'You run this program. The member area below is available to you.', 'zeko-mentor' ); ?></p>
					<span class="zeko-verified-badge">&#10003; <?php esc_html_e( 'Program Owner', 'zeko-mentor' ); ?></span>
				<?php elseif ( $is_enrolled ) : ?>
					<p class="zeko-program-desc"><?php esc_html_e( 'You are enrolled in this program.', 'zeko-mentor' ); ?></p>
					<span class="zeko-verified-badge">&#10003; <?php esc_html_e( 'Enrolled', 'zeko-mentor' ); ?></span>
				<?php elseif ( $is_full ) : ?>
					<button type="button" class="zeko-btn zeko-btn-secondary" disabled><?php esc_html_e( 'Program Full', 'zeko-mentor' ); ?></button>
				<?php else : ?>
					<p class="zeko-program-desc">
						<?php
						echo $is_free
							? esc_html__( 'This program is free to join.', 'zeko-mentor' )
							/* translators: %s: program price */
							: esc_html( sprintf( __( 'Join for %s — paid once from your Zeko wallet.', 'zeko-mentor' ), $price_label ) );
						?>
					</p>
					<button type="button" class="zeko-btn zeko-btn-primary zm-join-program" data-program-id="<?php echo esc_attr( $program_id ); ?>">
						<?php /* translators: %s: program price */ echo $is_free ? esc_html__( 'Join Program', 'zeko-mentor' ) : esc_html( sprintf( __( 'Join for %s', 'zeko-mentor' ), $price_label ) ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( $has_access ) : ?>
		<div class="zeko-member-area">
			<div class="zeko-member-area-header">
				<h3 class="zeko-section-title"><?php esc_html_e( 'Program Access', 'zeko-mentor' ); ?></h3>
				<?php if ( $is_enrolled ) : ?>
					<span class="zeko-program-status status-enrolled"><?php esc_html_e( 'Active member', 'zeko-mentor' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="zeko-program-single-grid">
				<div class="zeko-program-section">
					<h4 class="zeko-program-facts-title"><?php esc_html_e( 'Your cohort', 'zeko-mentor' ); ?></h4>
					<ul class="zeko-program-facts">
						<?php if ( $program['start_date'] ) : ?>
							<li><span><?php esc_html_e( 'Start date', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['start_date'] ); ?></strong></li>
						<?php endif; ?>
						<?php if ( $program['end_date'] ) : ?>
							<li><span><?php esc_html_e( 'End date', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['end_date'] ); ?></strong></li>
						<?php endif; ?>
						<li><span><?php esc_html_e( 'Duration', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( $program['duration_weeks'] ); ?> <?php esc_html_e( 'weeks', 'zeko-mentor' ); ?></strong></li>
						<?php if ( $enrolled_at ) : ?>
							<li><span><?php esc_html_e( 'Enrolled on', 'zeko-mentor' ); ?></span><strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $enrolled_at ) ); ?></strong></li>
						<?php endif; ?>
					</ul>

					<?php if ( $mentor_name ) : ?>
						<h4 class="zeko-program-facts-title" style="margin-top:20px;"><?php esc_html_e( 'Your mentor', 'zeko-mentor' ); ?></h4>
						<div class="zeko-program-mentor-card">
							<?php echo get_avatar( (int) $program['mentor_id'], 48, '', $mentor_name, array( 'class' => 'zeko-avatar' ) ); ?>
							<div>
								<strong><?php echo esc_html( $mentor_name ); ?></strong>
								<div class="zeko-program-mentor-actions">
									<a href="<?php echo esc_url( home_url( '/mentor-inbox/' ) ); ?>" class="zeko-btn zeko-btn-sm zeko-btn-secondary"><?php esc_html_e( 'Send Message', 'zeko-mentor' ); ?></a>
									<?php if ( $mentor_url ) : ?>
										<a href="<?php echo esc_url( $mentor_url ); ?>" class="zeko-btn zeko-btn-sm zeko-btn-outline"><?php esc_html_e( 'View Profile', 'zeko-mentor' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="zeko-program-section">
					<h4 class="zeko-program-facts-title"><?php esc_html_e( 'Members', 'zeko-mentor' ); ?></h4>
					<?php if ( empty( $members ) ) : ?>
						<p class="zeko-empty-state"><?php esc_html_e( 'No members yet.', 'zeko-mentor' ); ?></p>
					<?php else : ?>
						<ul class="zeko-member-list">
							<?php foreach ( $members as $mentor_data ) : ?>
								<li class="zeko-member-list-item">
									<?php echo get_avatar( (int) $mentor_data['user_id'], 40, '', $mentor_data['display_name'], array( 'class' => 'zeko-avatar' ) ); ?>
									<div>
										<strong><?php echo esc_html( $mentor_data['display_name'] ); ?></strong>
										<span class="zeko-member-meta"><?php esc_html_e( 'Joined', 'zeko-mentor' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $mentor_data['enrolled_at'] ) ); ?></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $is_enrolled ) : ?>
				<div class="zeko-leave-area">
					<p class="zeko-program-desc"><?php esc_html_e( 'Changed your mind? Leaving refunds your program fee if paid.', 'zeko-mentor' ); ?></p>
					<button type="button" class="zeko-btn zeko-btn-danger-outline zm-leave-program" data-program-id="<?php echo esc_attr( $program_id ); ?>"><?php esc_html_e( 'Leave Program', 'zeko-mentor' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
