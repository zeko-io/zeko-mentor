<?php
/**
 * Match suggestion page template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id  = get_current_user_id();
$db       = zeko_mentor()->get_db();
$matches  = $db->get_mentee_matches( $user_id, '', 20 );
$pending  = array_filter(
	$matches,
	function ( $mentor_data ) {
		return 'suggested' === $mentor_data['status'];
	}
);
$accepted = array_filter(
	$matches,
	function ( $mentor_data ) {
		return 'accepted' === $mentor_data['status'];
	}
);
?>

<div class="zeko-mentor-matches-page">
	<h2><?php esc_html_e( 'My Mentor Matches', 'zeko-mentor' ); ?></h2>

	<?php if ( ! empty( $pending ) ) : ?>
		<h3><?php esc_html_e( 'Suggestions', 'zeko-mentor' ); ?></h3>
		<p style="color:#6b7280;"><?php esc_html_e( 'Accept or dismiss these mentor matches based on your goals.', 'zeko-mentor' ); ?></p>

		<?php
		foreach ( $pending as $mentor_data ) :
			$ud   = get_userdata( (int) $mentor_data['mentor_id'] );
			$name = $ud ? $ud->display_name : 'Mentor #' . $mentor_data['mentor_id'];
			?>
			<div class="zeko-match-card" style="background:#fff;border:1px solid #e2e8f0;padding:16px;margin-bottom:12px;border-radius:8px;">
				<div style="display:flex;justify-content:space-between;align-items:center;">
					<div style="flex:1;">
						<h4 style="margin:0;"><?php echo esc_html( $name ); ?></h4>
						<?php if ( ! empty( $mentor_data['reasons'] ) ) : ?>
							<div style="margin-top:8px;">
								<?php foreach ( json_decode( $mentor_data['reasons'], true ) ?: array() as $reason ) : ?>
									<span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:12px;font-size:12px;margin-right:4px;margin-bottom:4px;display:inline-block;">
										<?php echo esc_html( $reason ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div style="margin-top:8px;color:#666;font-size:13px;">
							<span>★ <?php echo esc_html( number_format( (float) $mentor_data['avg_rating'], 1 ) ); ?></span>
							<span style="margin-left:12px;"><?php echo esc_html( $mentor_data['hourly_rate'] . ' ' . $mentor_data['currency'] ); ?>/hr</span>
							<?php if ( $mentor_data['is_verified'] ) : ?>
								<span style="color:#10b981;margin-left:12px;">✓ <?php esc_html_e( 'Verified', 'zeko-mentor' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div style="text-align:right;">
						<div style="font-size:24px;font-weight:700;color:#7c3aed;"><?php echo esc_html( $mentor_data['score'] ); ?>%</div>
						<div style="font-size:12px;color:#666;"><?php esc_html_e( 'match', 'zeko-mentor' ); ?></div>
						<div style="margin-top:12px;">
							<button class="button button-primary zeko-mentor-match-accept" data-match="<?php echo esc_attr( $mentor_data['match_id'] ); ?>" style="margin-right:4px;">
								<?php esc_html_e( 'Accept', 'zeko-mentor' ); ?>
							</button>
							<button class="button zeko-mentor-match-dismiss" data-match="<?php echo esc_attr( $mentor_data['match_id'] ); ?>">
								<?php esc_html_e( 'Dismiss', 'zeko-mentor' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $accepted ) ) : ?>
		<h3 style="margin-top:24px;"><?php esc_html_e( 'Accepted', 'zeko-mentor' ); ?></h3>
		<?php
		foreach ( $accepted as $mentor_data ) :
			$ud          = get_userdata( (int) $mentor_data['mentor_id'] );
			$name        = $ud ? $ud->display_name : 'Mentor #' . $mentor_data['mentor_id'];
			$profile_url = ( $ud && $ud->user_nicename ) ? home_url( '/mentors/' . $ud->user_nicename . '/' ) : home_url( '/mentors/' );
			?>
			<div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;margin-bottom:8px;border-radius:6px;">
				<strong style="color:#065f46;"><?php echo esc_html( $name ); ?></strong>
				<span style="color:#10b981;margin-left:8px;">✓ <?php esc_html_e( 'Accepted', 'zeko-mentor' ); ?></span>
				<span style="margin-left:8px;">
					<a href="<?php echo esc_url( $profile_url ); ?>" class="button button-small"><?php esc_html_e( 'View Profile', 'zeko-mentor' ); ?></a>
				</span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( empty( $pending ) && empty( $accepted ) ) : ?>
		<div style="text-align:center;padding:40px;color:#6b7280;">
			<p><?php esc_html_e( 'No match suggestions yet.', 'zeko-mentor' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/mentors/' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Browse Mentors', 'zeko-mentor' ); ?></a>
		</div>
	<?php endif; ?>
</div>
