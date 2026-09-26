<?php
/**
 * Mentor availability management template.
 *
 * @package Zeko_Mentor
 */

?>
<div class="zm-card">
	<h2><?php esc_html_e( 'Manage Your Availability', 'zeko-mentor' ); ?></h2>
	<p class="zm-text-muted"><?php esc_html_e( 'Set your weekly recurring availability slots below.', 'zeko-mentor' ); ?></p>

	<div class="zm-availability-grid">
		<?php
		$days = array(
			'Monday'    => __( 'Monday', 'zeko-mentor' ),
			'Tuesday'   => __( 'Tuesday', 'zeko-mentor' ),
			'Wednesday' => __( 'Wednesday', 'zeko-mentor' ),
			'Thursday'  => __( 'Thursday', 'zeko-mentor' ),
			'Friday'    => __( 'Friday', 'zeko-mentor' ),
			'Saturday'  => __( 'Saturday', 'zeko-mentor' ),
			'Sunday'    => __( 'Sunday', 'zeko-mentor' ),
		);
		foreach ( $days as $day_en => $day_label ) :
			?>
		<div class="zm-availability-day" data-day="<?php echo esc_attr( $day_en ); ?>">
			<h4><?php echo esc_html( $day_label ); ?></h4>
			<div class="zm-av-slots"></div>
			<button type="button" class="zm-btn zm-btn-sm zm-btn-secondary zm-av-add-slot">
				+ <?php esc_html_e( 'Add Slot', 'zeko-mentor' ); ?>
			</button>
		</div>
		<?php endforeach; ?>
	</div>

	<input type="hidden" id="zm-av-data" value="" />
	<button type="button" class="zm-btn zm-btn-primary" id="zm-av-save">
		<?php esc_html_e( 'Save Availability', 'zeko-mentor' ); ?>
	</button>
	<span id="zm-av-status" style="margin-left:12px;font-size:0.9em;"></span>
</div>
