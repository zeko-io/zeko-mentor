<?php
/**
 * Programs directory template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db           = zeko_mentor()->get_db();
$programs     = $db->get_programs(
	array(
		'status' => 'active',
		'limit'  => 20,
	)
);
$user_id      = get_current_user_id();
$is_logged_in = is_user_logged_in();
?>

<div class="zeko-programs-browse">
	<h2><?php esc_html_e( 'Mentorship Programs', 'zeko-mentor' ); ?></h2>
	<p class="zeko-programs-subtitle"><?php esc_html_e( 'Join group mentoring cohorts to learn alongside peers.', 'zeko-mentor' ); ?></p>

	<?php if ( empty( $programs ) ) : ?>
		<div class="zeko-empty-state">
			<p><?php esc_html_e( 'No active programs right now. Check back soon!', 'zeko-mentor' ); ?></p>
		</div>
	<?php else : ?>
		<div class="zeko-programs-grid">
			<?php
			foreach ( $programs as $p ) :
				$enrolled = $is_logged_in && $db->is_enrolled( (int) $p['program_id'], $user_id );
				$is_full  = (int) $p['current_members'] >= (int) $p['max_members'];
				$is_free  = (float) $p['price'] <= 0;
				?>
				<div class="zeko-program-card">
					<?php $program_url = home_url( '/programs/' . (int) $p['program_id'] . '/' ); ?>
					<div class="zeko-program-card-header">
						<h3><a href="<?php echo esc_url( $program_url ); ?>" class="zeko-program-card-title"><?php echo esc_html( $p['title'] ); ?></a></h3>
						<?php if ( $enrolled ) : ?>
							<span class="zeko-program-status status-enrolled"><?php esc_html_e( 'Enrolled', 'zeko-mentor' ); ?></span>
						<?php elseif ( $is_full ) : ?>
							<span class="zeko-program-status status-full"><?php esc_html_e( 'Full', 'zeko-mentor' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="zeko-program-mentor"><?php esc_html_e( 'by', 'zeko-mentor' ); ?> <?php echo esc_html( $p['mentor_name'] ); ?></p>

					<?php if ( $p['description'] ) : ?>
						<p class="zeko-program-desc"><?php echo esc_html( wp_trim_words( $p['description'], 25 ) ); ?></p>
					<?php endif; ?>

					<div class="zeko-program-meta">
						<span>📅 <?php echo esc_html( $p['duration_weeks'] ); ?> <?php esc_html_e( 'weeks', 'zeko-mentor' ); ?></span>
						<span>👥 <?php echo esc_html( $p['current_members'] . '/' . $p['max_members'] ); ?></span>
						<span>💰 <?php echo $is_free ? esc_html__( 'Free', 'zeko-mentor' ) : esc_html( $p['price'] . ' ' . $p['currency'] ); ?></span>
					</div>

					<div class="zeko-program-card-footer">
						<?php if ( $enrolled ) : ?>
							<a href="<?php echo esc_url( $program_url ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Open Program', 'zeko-mentor' ); ?></a>
						<?php elseif ( $is_full ) : ?>
							<button type="button" class="zeko-btn zeko-btn-secondary" disabled><?php esc_html_e( 'Program Full', 'zeko-mentor' ); ?></button>
						<?php elseif ( ! $is_logged_in ) : ?>
							<a href="<?php echo esc_url( wp_login_url( $program_url ) ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Log in to Join', 'zeko-mentor' ); ?></a>
						<?php else : ?>
							<button type="button" class="zeko-btn zeko-btn-primary zm-join-program" data-program-id="<?php echo esc_attr( $p['program_id'] ); ?>">
								<?php /* translators: 1: program price. 2: currency */ echo $is_free ? esc_html__( 'Join Program', 'zeko-mentor' ) : esc_html( sprintf( __( 'Join for %1$s %2$s', 'zeko-mentor' ), $p['price'], $p['currency'] ) ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
