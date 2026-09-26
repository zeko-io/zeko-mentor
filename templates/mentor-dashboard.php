<?php
/**
 * Mentor/Mentee dashboard template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id   = get_current_user_id();
$db        = zeko_mentor()->get_db();
$is_mentor = $db->is_mentor( $user_id );
?>

<div class="zeko-mentor-dashboard-wrapper">
	<?php if ( $is_mentor ) : ?>
		<h2><?php esc_html_e( 'Mentor Dashboard', 'zeko-mentor' ); ?></h2>
		<p style="color:#6b7280;"><?php esc_html_e( 'Manage your sessions, availability, and mentees.', 'zeko-mentor' ); ?></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'My Mentorship', 'zeko-mentor' ); ?></h2>
		<p style="color:#6b7280;"><?php esc_html_e( 'Track your sessions, goals, and mentor matches.', 'zeko-mentor' ); ?></p>
	<?php endif; ?>

	<!-- Dashboard content rendered by ecosystem class -->
	<?php do_action( 'zeko_dashboard_tab_content_mentor' ); ?>
</div>
