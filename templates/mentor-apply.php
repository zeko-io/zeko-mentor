<?php
/**
 * Become-a-mentor application form template.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id       = get_current_user_id();
$db            = zeko_mentor()->get_db();
$existing      = $db->get_profile( $user_id );
$session_rates = $db->get_session_rates( $user_id );

$expertise_options = array(
	'technology' => __( 'Technology', 'zeko-mentor' ),
	'business'   => __( 'Business', 'zeko-mentor' ),
	'design'     => __( 'Design', 'zeko-mentor' ),
	'marketing'  => __( 'Marketing', 'zeko-mentor' ),
	'leadership' => __( 'Leadership', 'zeko-mentor' ),
	'finance'    => __( 'Finance', 'zeko-mentor' ),
	'sales'      => __( 'Sales', 'zeko-mentor' ),
	'hr'         => __( 'Human Resources', 'zeko-mentor' ),
	'legal'      => __( 'Legal', 'zeko-mentor' ),
	'education'  => __( 'Education', 'zeko-mentor' ),
	'science'    => __( 'Science', 'zeko-mentor' ),
	'creative'   => __( 'Creative', 'zeko-mentor' ),
);

$saved_expertise = json_decode( $existing['expertise_areas'] ?? '[]', true );

$response_times = array(
	''              => __( 'Select...', 'zeko-mentor' ),
	'within-hour'   => __( 'Within 1 hour', 'zeko-mentor' ),
	'within-day'    => __( 'Within 24 hours', 'zeko-mentor' ),
	'within-2-days' => __( 'Within 2 days', 'zeko-mentor' ),
	'within-week'   => __( 'Within a week', 'zeko-mentor' ),
);
?>

<div class="zeko-mentor-apply-form">
	<h2><?php esc_html_e( 'Become a Mentor', 'zeko-mentor' ); ?></h2>
	<p class="zeko-apply-subtitle"><?php esc_html_e( 'Share your expertise and help others grow. Fill out the form below to apply.', 'zeko-mentor' ); ?></p>

	<div class="zeko-mentor-apply-notice"></div>

	<div class="zm-apply-form zeko-apply-card">
		<div class="zeko-form-group">
			<label><?php esc_html_e( 'Headline', 'zeko-mentor' ); ?> <span class="zeko-optional"><?php esc_html_e( '(short tagline shown on your profile)', 'zeko-mentor' ); ?></span></label>
			<input type="text" name="headline" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. Senior Product Manager helping you break into tech leadership', 'zeko-mentor' ); ?>" value="<?php echo esc_attr( $existing['headline'] ?? '' ); ?>" />
		</div>

		<div class="zeko-form-group">
			<label><?php esc_html_e( 'Areas of Expertise', 'zeko-mentor' ); ?> <span class="zeko-required">*</span></label>
			<div class="zm-expertise-grid">
				<?php foreach ( $expertise_options as $key => $label ) : ?>
					<label class="zm-expertise-option">
						<input type="checkbox" name="expertise[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $saved_expertise, true ) ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="zeko-form-group">
			<label for="zm-bio"><?php esc_html_e( 'Bio / About You', 'zeko-mentor' ); ?> <span class="zeko-required">*</span></label>
			<textarea id="zm-bio" name="bio" rows="5" placeholder="<?php esc_attr_e( 'Tell us about your experience and what you can help with...', 'zeko-mentor' ); ?>"><?php echo esc_textarea( $existing['bio'] ?? '' ); ?></textarea>
			<?php if ( class_exists( 'Zeko_AI_Writer_UI' ) ) : ?>
				<?php
				echo wp_kses_post(
					(string) Zeko_AI_Writer_UI::button(
						array(
							'preset' => 'mentor_bio',
							'target' => '#zm-bio',
						)
					)
				);
				?>
			<?php endif; ?>
		</div>

		<div class="zeko-form-row">
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Location', 'zeko-mentor' ); ?></label>
				<input type="text" name="location" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Lagos, Nigeria', 'zeko-mentor' ); ?>" value="<?php echo esc_attr( $existing['location'] ?? '' ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Languages', 'zeko-mentor' ); ?></label>
				<input type="text" name="languages" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. English, French', 'zeko-mentor' ); ?>" value="<?php echo esc_attr( $existing['languages'] ?? '' ); ?>" />
			</div>
		</div>

		<div class="zeko-form-row">
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Years of Experience', 'zeko-mentor' ); ?></label>
				<input type="number" name="years_experience" min="0" max="60" value="<?php echo esc_attr( $existing['years_experience'] ?? 0 ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Response Time', 'zeko-mentor' ); ?></label>
				<select name="response_time">
					<?php foreach ( $response_times as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $existing['response_time'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="zeko-form-row">
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Hourly Rate', 'zeko-mentor' ); ?> <span class="zeko-required">*</span></label>
				<input type="number" name="hourly_rate" min="0" step="5" value="<?php echo esc_attr( $existing['hourly_rate'] ?? 25 ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Currency', 'zeko-mentor' ); ?></label>
				<select name="currency">
					<option value="USD" <?php selected( $existing['currency'] ?? 'USD', 'USD' ); ?>>USD</option>
					<option value="EUR" <?php selected( $existing['currency'] ?? '', 'EUR' ); ?>>EUR</option>
					<option value="GBP" <?php selected( $existing['currency'] ?? '', 'GBP' ); ?>>GBP</option>
					<option value="NGN" <?php selected( $existing['currency'] ?? '', 'NGN' ); ?>>NGN</option>
				</select>
			</div>
		</div>

		<div class="zeko-form-row">
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Video Rate', 'zeko-mentor' ); ?></label>
				<input type="number" name="session_rate_video" min="0" step="5" value="<?php echo esc_attr( $session_rates['video'] ?? ( $existing['hourly_rate'] ?? 25 ) ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Audio Rate', 'zeko-mentor' ); ?></label>
				<input type="number" name="session_rate_audio" min="0" step="5" value="<?php echo esc_attr( $session_rates['audio'] ?? ( $existing['hourly_rate'] ?? 25 ) ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'Chat Rate', 'zeko-mentor' ); ?></label>
				<input type="number" name="session_rate_chat" min="0" step="5" value="<?php echo esc_attr( $session_rates['chat'] ?? ( $existing['hourly_rate'] ?? 25 ) ); ?>" />
			</div>
		</div>
		<p class="zeko-form-hint"><?php esc_html_e( 'Optional per-session prices. Leave a field at 0 to use your hourly rate for that session type.', 'zeko-mentor' ); ?></p>

		<div class="zeko-form-group">
			<label><?php esc_html_e( 'Max Concurrent Mentees', 'zeko-mentor' ); ?></label>
			<input type="number" name="max_mentees" min="1" max="20" value="<?php echo esc_attr( $existing['max_mentees'] ?? 5 ); ?>" />
		</div>

		<h4 class="zeko-section-title"><?php esc_html_e( 'Social Links', 'zeko-mentor' ); ?> <span class="zeko-optional"><?php esc_html_e( '(optional)', 'zeko-mentor' ); ?></span></h4>

		<div class="zeko-form-row">
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'LinkedIn', 'zeko-mentor' ); ?></label>
				<input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/..." value="<?php echo esc_attr( $existing['linkedin_url'] ?? '' ); ?>" />
			</div>
			<div class="zeko-form-group">
				<label><?php esc_html_e( 'GitHub', 'zeko-mentor' ); ?></label>
				<input type="url" name="github_url" placeholder="https://github.com/..." value="<?php echo esc_attr( $existing['github_url'] ?? '' ); ?>" />
			</div>
		</div>

		<div class="zeko-form-group">
			<label><?php esc_html_e( 'X / Twitter', 'zeko-mentor' ); ?></label>
			<input type="url" name="twitter_url" placeholder="https://x.com/..." value="<?php echo esc_attr( $existing['twitter_url'] ?? '' ); ?>" />
		</div>

		<button type="button" class="zeko-btn zeko-btn-primary zeko-btn-lg zm-submit-application">
			<?php esc_html_e( 'Submit Application', 'zeko-mentor' ); ?>
		</button>
	</div>
</div>
