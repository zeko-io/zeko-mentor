<?php
/**
 * Mentor booking calendar template.
 *
 * @package Zeko_Mentor
 */

?>
<div class="zm-card zm-calendar-wrapper" data-mentor-id="<?php echo esc_attr( $mentor_id ?? '' ); ?>">
	<div class="zm-calendar-header">
		<button type="button" class="zm-btn zm-btn-sm zm-cal-prev">&larr;</button>
		<h3 class="zm-cal-title"></h3>
		<button type="button" class="zm-btn zm-btn-sm zm-cal-next">&rarr;</button>
	</div>
	<div class="zm-cal-grid"></div>
	<div class="zm-cal-legend" style="margin-top:12px;display:flex;gap:16px;font-size:0.85em;">
		<span><span style="display:inline-block;width:14px;height:14px;background:#dbeafe;border-radius:3px;vertical-align:middle;"></span> <?php esc_html_e( 'Has availability', 'zeko-mentor' ); ?></span>
		<span><span style="display:inline-block;width:14px;height:14px;background:#7c3aed;border-radius:50%;vertical-align:middle;"></span> <?php esc_html_e( 'Sessions booked', 'zeko-mentor' ); ?></span>
	</div>
	<?php if ( is_user_logged_in() && ! empty( $mentor_id ) && (int) get_current_user_id() === (int) $mentor_id ) : ?>
		<?php
			$public   = Zeko_Mentor::instance()->get_public();
			$ical_url = $public->get_ical_url( (int) $mentor_id );
		?>
		<div class="zm-cal-ical" style="margin-top:12px;">
			<a href="<?php echo esc_url( $ical_url ); ?>" class="zm-btn zm-btn-sm" title="<?php esc_attr_e( 'Subscribe your calendar to get automatic session updates', 'zeko-mentor' ); ?>">
				&#x1F4C5; <?php esc_html_e( 'Subscribe to Calendar', 'zeko-mentor' ); ?>
			</a>
			<span style="font-size:0.8em;color:#6b7280;margin-left:6px;"><?php esc_html_e( 'Adds this feed to your calendar app (Google Calendar, Outlook, Apple Calendar)', 'zeko-mentor' ); ?></span>
		</div>
	<?php endif; ?>
</div>
