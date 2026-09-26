<?php
/**
 * Mentor browse directory template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = get_current_user_id();
$mentors = zeko_mentor()->get_db()->get_mentors(
	array(
		'is_active' => 1,
		'limit'     => 20,
	)
);
$total   = zeko_mentor()->get_db()->get_mentor_count( array( 'is_active' => 1 ) );
?>

<div class="zeko-mentor-browse">
	<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
		<h2 style="margin:0;"><?php esc_html_e( 'Find a Mentor', 'zeko-mentor' ); ?></h2>
		<input type="text" class="zm-mentor-search" placeholder="<?php esc_attr_e( 'Search mentors...', 'zeko-mentor' ); ?>" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;width:280px;" />
	</div>

	<div class="zeko-mentor-filters">
		<h3><?php esc_html_e( 'Filters', 'zeko-mentor' ); ?></h3>
		<label><?php esc_html_e( 'Expertise', 'zeko-mentor' ); ?></label>
		<select class="zm-filter-expertise">
			<option value=""><?php esc_html_e( 'All', 'zeko-mentor' ); ?></option>
			<option value="technology"><?php esc_html_e( 'Technology', 'zeko-mentor' ); ?></option>
			<option value="business"><?php esc_html_e( 'Business', 'zeko-mentor' ); ?></option>
			<option value="design"><?php esc_html_e( 'Design', 'zeko-mentor' ); ?></option>
			<option value="marketing"><?php esc_html_e( 'Marketing', 'zeko-mentor' ); ?></option>
			<option value="career"><?php esc_html_e( 'Career', 'zeko-mentor' ); ?></option>
		</select>
		<label><?php esc_html_e( 'Max Rate', 'zeko-mentor' ); ?></label>
		<input type="number" class="zm-filter-max-rate" placeholder="<?php esc_attr_e( 'Any', 'zeko-mentor' ); ?>" min="0" step="5" />
		<label><?php esc_html_e( 'Min Rating', 'zeko-mentor' ); ?></label>
		<select class="zm-filter-min-rating">
			<option value="0"><?php esc_html_e( 'Any', 'zeko-mentor' ); ?></option>
			<option value="4"><?php esc_html_e( '4+ Stars', 'zeko-mentor' ); ?></option>
			<option value="4.5"><?php esc_html_e( '4.5+ Stars', 'zeko-mentor' ); ?></option>
		</select>
		<label>
			<input type="checkbox" class="zm-filter-verified" checked /> <?php esc_html_e( 'Verified only', 'zeko-mentor' ); ?>
		</label>
	</div>

	<p style="color:#6b7280;margin-bottom:16px;"><?php /* translators: %d: number of available mentors */ echo esc_html( sprintf( esc_html__( '%d mentors available', 'zeko-mentor' ), $total ) ); ?></p>

	<div class="zeko-mentor-grid">
		<?php if ( empty( $mentors ) ) : ?>
			<p><?php esc_html_e( 'No mentors found. Check back soon!', 'zeko-mentor' ); ?></p>
		<?php else : ?>
			<?php
			foreach ( $mentors as $mentor_data ) :
				$expertise   = json_decode( $mentor_data['expertise_areas'] ?? '[]', true );
				$avatar      = get_avatar_url( $mentor_data['user_id'], array( 'size' => 64 ) );
				$slug        = $mentor_data['mentor_slug'] ?? '';
				$profile_url = $slug ? home_url( '/mentors/' . $slug . '/' ) : '#';
				?>
				<div class="zeko-mentor-card">
					<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $mentor_data['mentor_name'] . __( ' profile photo', 'zeko-mentor' ) ); ?>" class="avatar" />
					<h3 class="mentor-name">
						<a href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( $mentor_data['mentor_name'] ); ?></a>
						<?php if ( ! empty( $mentor_data['is_verified'] ) ) : ?>
							<span class="zeko-verified-badge">✓ <?php esc_html_e( 'Verified', 'zeko-mentor' ); ?></span>
						<?php endif; ?>
					</h3>

					<?php if ( ! empty( $expertise ) ) : ?>
						<div class="zeko-expertise-tags">
							<?php foreach ( array_slice( $expertise, 0, 4 ) as $term_tag ) : ?>
								<span class="zeko-expertise-tag"><?php echo esc_html( $term_tag ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $mentor_data['bio'] ) : ?>
						<p style="color:#374151;font-size:14px;margin:8px 0;"><?php echo esc_html( wp_trim_words( $mentor_data['bio'], 20 ) ); ?></p>
					<?php endif; ?>

					<div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
						<div class="zeko-rating">
							★ <?php echo esc_html( number_format( (float) $mentor_data['avg_rating'], 1 ) ); ?>
							<span class="count">(<?php echo esc_html( $mentor_data['total_sessions'] ); ?>)</span>
						</div>
						<div class="zeko-rate">
							<?php echo esc_html( $mentor_data['hourly_rate'] ); ?>
							<span class="currency"><?php echo esc_html( $mentor_data['currency'] ); ?></span>
						</div>
					</div>

					<a href="<?php echo esc_url( $profile_url ); ?>" class="zeko-btn zeko-btn-primary" style="width:100%;justify-content:center;margin-top:12px;">
						<?php esc_html_e( 'View Profile & Book', 'zeko-mentor' ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
