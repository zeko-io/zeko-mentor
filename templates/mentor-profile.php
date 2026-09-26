<?php
/**
 * Single mentor profile template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = absint( wp_unslash( $_GET['mentor'] ?? $atts['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET profile id for display; no state change.
if ( ! $user_id ) {
	return;
}

$db      = zeko_mentor()->get_db();
$profile = $db->get_profile( $user_id );
if ( ! $profile ) {
	return;
}

$user            = get_userdata( $user_id );
$expertise       = json_decode( $profile['expertise_areas'] ?? '[]', true );
$avatar          = get_avatar_url( $user_id, array( 'size' => 160 ) );
$reviews         = $db->get_mentor_reviews( $user_id, 5 );
$rating_dist     = $db->get_mentor_rating_distribution( $user_id );
$availability    = $db->get_availability( $user_id );
$session_rates   = $db->get_session_rates( $user_id );
$is_logged_in    = is_user_logged_in();
$current_user_id = get_current_user_id();
$is_own          = ( $current_user_id === $user_id );
$avg_rating      = number_format( (float) ( $profile['avg_rating'] ?? 0 ), 1 );
$review_total    = (int) $rating_dist['total'];

$response_labels = array(
	'within-hour'   => __( 'Within 1 hour', 'zeko-mentor' ),
	'within-day'    => __( 'Within 24 hours', 'zeko-mentor' ),
	'within-2-days' => __( 'Within 2 days', 'zeko-mentor' ),
	'within-week'   => __( 'Within a week', 'zeko-mentor' ),
);
$response_time   = $response_labels[ $profile['response_time'] ?? '' ] ?? '';

$socials = array();
foreach ( array( 'linkedin', 'github', 'twitter' ) as $soc ) {
	if ( ! empty( $profile[ $soc . '_url' ] ?? '' ) ) {
		$socials[ $soc ] = $profile[ $soc . '_url' ];
	}
}

$languages = array_filter( array_map( 'trim', explode( ',', $profile['languages'] ?? '' ) ) );

$db->increment_profile_views( $user_id );
?>

<div class="zeko-mentor-profile">
	<div class="zeko-mentor-profile-hero">
		<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $user->display_name . __( ' profile photo', 'zeko-mentor' ) ); ?>" class="avatar-large" />
		<div class="profile-hero-info">
			<h2>
				<?php echo esc_html( $user->display_name ); ?>
				<?php if ( $profile['is_verified'] ) : ?>
					<span class="zeko-verified-badge">✓ <?php esc_html_e( 'Verified Mentor', 'zeko-mentor' ); ?></span>
				<?php endif; ?>
			</h2>

			<?php if ( ! empty( $profile['headline'] ?? '' ) ) : ?>
				<p class="profile-hero-headline"><?php echo esc_html( $profile['headline'] ); ?></p>
			<?php endif; ?>

			<div class="profile-hero-metrics">
				<div class="zeko-rating">★ <?php echo esc_html( $avg_rating ); ?> <span class="count">(<?php echo esc_html( $review_total ); ?> <?php esc_html_e( 'reviews', 'zeko-mentor' ); ?>)</span></div>
				<div class="zeko-rate">
					<?php echo esc_html( $profile['hourly_rate'] ); ?>
					<span class="currency"><?php echo esc_html( $profile['currency'] ); ?></span>
					<span class="per-session">/ <?php esc_html_e( 'session', 'zeko-mentor' ); ?></span>
				</div>
			</div>

			<div class="profile-hero-meta">
				<?php if ( ! empty( $profile['location'] ?? '' ) ) : ?>
					<span class="profile-meta-item">📍 <?php echo esc_html( $profile['location'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $languages ) ) : ?>
					<span class="profile-meta-item">🌐 <?php echo esc_html( implode( ', ', $languages ) ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $user->user_registered ) ) : ?>
					<span class="profile-meta-item">🕒 <?php esc_html_e( 'Member since', 'zeko-mentor' ); ?> <?php echo esc_html( date_i18n( 'Y', strtotime( $user->user_registered ) ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $expertise ) ) : ?>
				<div class="zeko-expertise-tags">
					<?php foreach ( $expertise as $term_tag ) : ?>
						<span class="zeko-expertise-tag"><?php echo esc_html( $term_tag ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $socials ) ) : ?>
			<div class="profile-hero-socials">
				<?php foreach ( $socials as $network => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="profile-social-link" aria-label="<?php echo esc_attr( $network ); ?>">
						<?php echo esc_html( ucfirst( $network ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="zeko-mentor-stats-row">
		<div class="zeko-stat-card">
			<span class="stat-value"><?php echo esc_html( $profile['total_sessions'] ?? 0 ); ?></span>
			<p class="stat-label"><?php esc_html_e( 'Sessions Completed', 'zeko-mentor' ); ?></p>
		</div>
		<div class="zeko-stat-card">
			<span class="stat-value">★ <?php echo esc_html( $avg_rating ); ?></span>
			<p class="stat-label"><?php esc_html_e( 'Average Rating', 'zeko-mentor' ); ?></p>
		</div>
		<div class="zeko-stat-card">
			<span class="stat-value"><?php echo esc_html( $response_time ?: __( '—', 'zeko-mentor' ) ); ?></span>
			<p class="stat-label"><?php esc_html_e( 'Response Time', 'zeko-mentor' ); ?></p>
		</div>
		<div class="zeko-stat-card">
			<span class="stat-value"><?php echo esc_html( number_format_i18n( (int) ( $profile['profile_views'] ?? 0 ) ) ); ?></span>
			<p class="stat-label"><?php esc_html_e( 'Profile Views', 'zeko-mentor' ); ?></p>
		</div>
	</div>

	<div class="zeko-profile-content">
		<div class="zeko-profile-main">
			<?php if ( $profile['bio'] ) : ?>
				<section class="profile-section">
					<h3 class="zeko-section-title"><?php esc_html_e( 'About', 'zeko-mentor' ); ?></h3>
					<div class="zeko-profile-bio">
						<?php echo wp_kses_post( wpautop( $profile['bio'] ) ); ?>
					</div>
					<?php if ( (int) ( $profile['years_experience'] ?? 0 ) > 0 ) : ?>
						<div class="profile-facts">
							<span class="profile-fact">💼 <strong><?php echo esc_html( $profile['years_experience'] ); ?></strong> <?php esc_html_e( 'years of experience', 'zeko-mentor' ); ?></span>
							<?php if ( ! empty( $profile['location'] ?? '' ) ) : ?>
								<span class="profile-fact">📍 <strong><?php echo esc_html( $profile['location'] ); ?></strong></span>
							<?php endif; ?>
							<?php if ( ! empty( $languages ) ) : ?>
								<span class="profile-fact">🌐 <?php echo esc_html( implode( ', ', $languages ) ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="profile-section">
				<h3 class="zeko-section-title"><?php esc_html_e( 'What to Expect', 'zeko-mentor' ); ?></h3>
				<div class="profile-expect-grid">
					<div class="profile-expect-item">
						<span class="expect-icon">📹</span>
						<strong><?php esc_html_e( 'Video Call', 'zeko-mentor' ); ?></strong>
						<span><?php esc_html_e( 'Face-to-face mentoring over a secure video link.', 'zeko-mentor' ); ?></span>
					</div>
					<div class="profile-expect-item">
						<span class="expect-icon">🎧</span>
						<strong><?php esc_html_e( 'Audio Only', 'zeko-mentor' ); ?></strong>
						<span><?php esc_html_e( 'A focused conversation without the video.', 'zeko-mentor' ); ?></span>
					</div>
					<div class="profile-expect-item">
						<span class="expect-icon">💬</span>
						<strong><?php esc_html_e( 'Chat', 'zeko-mentor' ); ?></strong>
						<span><?php esc_html_e( 'Asynchronous support through the built-in inbox.', 'zeko-mentor' ); ?></span>
					</div>
					<div class="profile-expect-item">
						<span class="expect-icon">📅</span>
						<strong><?php esc_html_e( 'Flexible Slots', 'zeko-mentor' ); ?></strong>
						<span><?php esc_html_e( 'Pick the time that works best for you.', 'zeko-mentor' ); ?></span>
					</div>
				</div>
			</section>

			<?php if ( $review_total > 0 || ! empty( $reviews ) ) : ?>
				<section class="profile-section">
					<h3 class="zeko-section-title"><?php esc_html_e( 'Reviews', 'zeko-mentor' ); ?></h3>

					<?php if ( $review_total > 0 ) : ?>
						<div class="profile-reviews-summary">
							<div class="summary-score">
								<span class="summary-big"><?php echo esc_html( $avg_rating ); ?></span>
								<div class="zeko-rating"><?php echo esc_html( str_repeat( '★', (int) round( (float) ( $profile['avg_rating'] ?? 0 ) ) ) ); ?></div>
								<span class="summary-count"><?php echo esc_html( $review_total ); ?> <?php esc_html_e( 'reviews', 'zeko-mentor' ); ?></span>
							</div>
							<div class="summary-bars">
								<?php
								$max_count = max( array_merge( array( 1 ), array_values( $rating_dist['distribution'] ) ) );
								foreach ( array( 5, 4, 3, 2, 1 ) as $star ) :
									$cnt = $rating_dist['distribution'][ $star ];
									$pct = ( $cnt / $max_count ) * 100;
									?>
									<div class="summary-bar-row">
										<span class="summary-bar-label"><?php echo esc_html( $star ); ?> ★</span>
										<span class="summary-bar-track"><span class="summary-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></span></span>
										<span class="summary-bar-count"><?php echo esc_html( $cnt ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php foreach ( $reviews as $r ) : ?>
						<div class="zeko-review-card">
							<div class="zeko-review-header">
								<span class="zeko-review-author"><?php echo esc_html( $r['reviewer_name'] ); ?></span>
								<div class="zeko-rating"><?php echo esc_html( str_repeat( '★', (int) $r['rating'] ) ); ?></div>
							</div>
							<?php if ( $r['title'] ) : ?>
								<div class="zeko-review-title"><?php echo esc_html( $r['title'] ); ?></div>
							<?php endif; ?>
							<p class="zeko-review-text"><?php echo esc_html( $r['review_text'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>
		</div>

		<div class="zeko-profile-sidebar">
			<?php if ( $is_logged_in && ! $is_own ) : ?>
				<div class="zeko-booking-form">
					<h3><?php esc_html_e( 'Book a Session', 'zeko-mentor' ); ?></h3>
					<p class="booking-form-hint"><?php /* translators: %s: hourly rate with currency */ printf( esc_html__( '%s per session · pick a date and time', 'zeko-mentor' ), esc_html( $profile['hourly_rate'] . ' ' . $profile['currency'] ) ); ?></p>
					<?php if ( is_array( $session_rates ) && count( $session_rates ) > 1 ) : ?>
						<ul class="zeko-session-rates">
							<?php
							$rate_labels = array(
								'video' => __( 'Video', 'zeko-mentor' ),
								'audio' => __( 'Audio', 'zeko-mentor' ),
								'chat'  => __( 'Chat', 'zeko-mentor' ),
							);
							foreach ( $session_rates as $item_type => $rate ) :
								?>
								<li><span><?php echo esc_html( $rate_labels[ $item_type ] ?? ucfirst( $item_type ) ); ?>:</span> <?php echo esc_html( $rate . ' ' . $profile['currency'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<div class="zm-booking-form" data-mentor-id="<?php echo esc_attr( $user_id ); ?>">
						<div class="zeko-form-group">
							<label><?php esc_html_e( 'Date', 'zeko-mentor' ); ?></label>
							<input type="date" name="session_date" class="zm-date-picker" data-mentor-id="<?php echo esc_attr( $user_id ); ?>" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
						</div>

						<div class="zm-slots-container"></div>
						<input type="hidden" name="start_time" />
						<input type="hidden" name="end_time" />

						<div class="zeko-form-group">
							<label><?php esc_html_e( 'Topic', 'zeko-mentor' ); ?></label>
							<input type="text" name="topic" placeholder="<?php esc_attr_e( 'What do you want to discuss?', 'zeko-mentor' ); ?>" />
						</div>

						<div class="zeko-form-group">
							<label><?php esc_html_e( 'Session Type', 'zeko-mentor' ); ?></label>
							<select name="session_type">
								<option value="video"><?php esc_html_e( 'Video Call', 'zeko-mentor' ); ?></option>
								<option value="audio"><?php esc_html_e( 'Audio Only', 'zeko-mentor' ); ?></option>
								<option value="chat"><?php esc_html_e( 'Chat', 'zeko-mentor' ); ?></option>
							</select>
						</div>

						<button type="button" class="zeko-btn zeko-btn-primary zeko-btn-block zm-book-session">
							<?php esc_html_e( 'Book Session', 'zeko-mentor' ); ?>
						</button>
					</div>
				</div>
			<?php elseif ( ! $is_logged_in ) : ?>
				<div class="zeko-sidebar-card">
					<h4 class="zeko-section-title"><?php esc_html_e( 'Book a Session', 'zeko-mentor' ); ?></h4>
					<p><?php esc_html_e( 'Log in to book a session with this mentor.', 'zeko-mentor' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="zeko-btn zeko-btn-primary zeko-btn-block"><?php esc_html_e( 'Log In', 'zeko-mentor' ); ?></a>
				</div>
			<?php elseif ( $is_own ) : ?>
				<div class="zeko-sidebar-card">
					<h4 class="zeko-section-title"><?php esc_html_e( 'This is you', 'zeko-mentor' ); ?></h4>
					<p><?php esc_html_e( 'This is your public mentor profile. Bookings appear in your dashboard.', 'zeko-mentor' ); ?></p>
					<?php $dashboard_id = zeko_mentor_get_page_id_by_slug( 'mentor-dashboard' ); ?>
					<?php if ( $dashboard_id ) : ?>
						<a href="<?php echo esc_url( get_permalink( $dashboard_id ) ); ?>" class="zeko-btn zeko-btn-secondary zeko-btn-block"><?php esc_html_e( 'Go to Dashboard', 'zeko-mentor' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $availability ) ) : ?>
				<div class="zeko-sidebar-card">
					<h4 class="zeko-section-title"><?php esc_html_e( 'Availability', 'zeko-mentor' ); ?></h4>
					<?php
					$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
					foreach ( $availability as $slot ) {
						printf(
							'<div class="zeko-availability-item"><span>%s</span><span class="zeko-availability-time">%s - %s</span></div>',
							esc_html( $days[ (int) $slot['day_of_week'] ] ),
							esc_html( $slot['start_time'] ),
							esc_html( $slot['end_time'] )
						);
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
