<?php
/**
 * Ecosystem integration for Zeko Mentor.
 *
 * Hooks into Zeko theme dashboard, activity feed, profile, and payments.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Ecosystem. */
class Zeko_Mentor_Ecosystem {

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * Construct.
	 *
	 * @param Zeko_Mentor_DB $db Db.
	 */
	public function __construct( Zeko_Mentor_DB $db ) {
		$this->db = $db;

		// Dashboard tab.
		add_filter( 'zeko_dashboard_tabs', array( $this, 'add_dashboard_tab' ) );
		add_action( 'zeko_dashboard_tab_content_mentor', array( $this, 'render_dashboard_tab' ) );

		// Activity feed.
		add_filter( 'zeko_activity_feed_items', array( $this, 'inject_activity_items' ) );

		// Profile sections.
		add_action( 'zeko_profile_view_sections', array( $this, 'render_profile_section' ) );
		add_action( 'zeko_theme_profile_stats', array( $this, 'render_profile_stats' ) );

		// Navigation menu.
		add_action( 'admin_init', array( $this, 'maybe_create_nav_menu_items' ) );

		// Nav items registry.
		add_filter( 'zeko_nav_items', array( $this, 'register_nav_items' ) );

		// Notification source registry.
		add_filter( 'zeko_register_notification_sources', array( $this, 'register_notification_source' ) );
	}

	/**
	 * Register Mentor as a notification source for the core bell.
	 *
	 * @param array $sources Sources.
	 */
	public function register_notification_source( array $sources ): array {
		global $wpdb;
		$sources['mentor'] = array(
			'table'       => $wpdb->prefix . 'zeko_mentor_notifications',
			'pk_column'   => 'notification_id',
			'type_column' => 'type',
			'has_title'   => true,
			'has_message' => true,
			'has_link'    => true,
			'icon'        => 'groups',
			'label'       => __( 'Mentorship', 'zeko-mentor' ),
		);
		return $sources;
	}

	/**
	 * Add "My Mentorship" tab to dashboard.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_dashboard_tab( array $tabs ): array {
		$tabs['mentor'] = array(
			'label'    => __( 'My Mentorship', 'zeko-mentor' ),
			'icon'     => 'dashicons-welcome-learn-more',
			'priority' => 35,
		);
		return $tabs;
	}

	/**
	 * Render dashboard tab content — supports multi-role (user can be both mentor and mentee).
	 */
	public function render_dashboard_tab(): void {
		$user_id      = get_current_user_id();
		$is_mentor    = $this->db->is_mentor( $user_id );
		$has_sessions = ! empty( $this->db->get_user_sessions( $user_id, 'mentee', '', 1 ) );
		$has_goals    = ! empty( $this->db->get_user_goals( $user_id, 'active', 1 ) );
		$is_both      = $is_mentor && ( $has_sessions || $has_goals );

		if ( $is_both ) {
			$this->render_both_role_dashboard( $user_id );
		} elseif ( $is_mentor ) {
			$this->render_mentor_dashboard( $user_id );
		} else {
			$this->render_mentee_dashboard( $user_id );
		}
	}

	/**
	 * Render combined mentor + mentee view for multi-role users.
	 *
	 * @param int $user_id User id.
	 */
	private function render_both_role_dashboard( int $user_id ): void {
		$mentor_sessions = $this->db->get_user_sessions( $user_id, 'mentor', '', 5 );
		$mentee_sessions = $this->db->get_user_sessions( $user_id, 'mentee', '', 5 );
		$goals           = $this->db->get_user_goals( $user_id, '', 5 );
		$matches         = $this->db->get_mentee_matches( $user_id, 'suggested', 3 );
		$profile         = $this->db->get_profile( $user_id );
		?>
		<div class="zeko-mentor-dashboard">
			<div class="zeko-dashboard-banner banner-info">
				<strong>&#10022; <?php esc_html_e( 'You are both a Mentor and Mentee', 'zeko-mentor' ); ?></strong>
				<span style="margin-left:8px;opacity:0.8;"><?php esc_html_e( 'Switch between views below.', 'zeko-mentor' ); ?></span>
			</div>

			<div class="zeko-dashboard-tabs">
				<button class="zeko-dashboard-tab active" data-tab="mentor"><?php esc_html_e( 'As Mentor', 'zeko-mentor' ); ?></button>
				<button class="zeko-dashboard-tab" data-tab="mentee"><?php esc_html_e( 'As Mentee', 'zeko-mentor' ); ?></button>
			</div>

			<div class="zeko-dashboard-tab-panel active" data-tab-panel="mentor">
				<div class="zeko-mentor-stats-row">
					<div class="zeko-stat-card">
						<span class="stat-value" style="color:var(--zm-primary);"><?php echo esc_html( $profile['total_sessions'] ?? 0 ); ?></span>
						<p class="stat-label"><?php esc_html_e( 'Sessions as Mentor', 'zeko-mentor' ); ?></p>
					</div>
					<div class="zeko-stat-card">
						<span class="stat-value" style="color:var(--zm-primary);"><?php echo esc_html( number_format( (float) ( $profile['avg_rating'] ?? 0 ), 1 ) ); ?></span>
						<p class="stat-label"><?php esc_html_e( 'Avg Rating', 'zeko-mentor' ); ?></p>
					</div>
				</div>

				<h3 class="zeko-section-title"><?php esc_html_e( 'Recent Sessions', 'zeko-mentor' ); ?></h3>
				<?php if ( ! empty( $mentor_sessions ) ) : ?>
					<?php foreach ( $mentor_sessions as $s ) : ?>
						<div class="zeko-session-card">
							<div class="session-info">
								<h4><?php echo esc_html( $s['topic'] ?: __( 'Session', 'zeko-mentor' ) ); ?></h4>
								<span class="session-meta"><?php echo esc_html( $s['session_date'] . ' ' . $s['start_time'] ); ?></span>
							</div>
							<span class="session-meta"><?php echo esc_html( $s['mentee_name'] ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="zeko-empty-state"><?php esc_html_e( 'No mentor sessions yet.', 'zeko-mentor' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="zeko-dashboard-tab-panel" data-tab-panel="mentee">
				<div class="zeko-mentor-stats-row">
					<div class="zeko-stat-card">
						<span class="stat-value" style="color:var(--zm-info);"><?php echo esc_html( count( $mentee_sessions ) ); ?></span>
						<p class="stat-label"><?php esc_html_e( 'Sessions as Mentee', 'zeko-mentor' ); ?></p>
					</div>
					<div class="zeko-stat-card">
						<span class="stat-value" style="color:var(--zm-success);"><?php echo esc_html( count( $goals ) ); ?></span>
						<p class="stat-label"><?php esc_html_e( 'Active Goals', 'zeko-mentor' ); ?></p>
					</div>
				</div>

				<?php if ( ! empty( $mentee_sessions ) ) : ?>
					<h3 class="zeko-section-title"><?php esc_html_e( 'My Sessions', 'zeko-mentor' ); ?></h3>
					<?php foreach ( $mentee_sessions as $s ) : ?>
						<div class="zeko-session-card">
							<div class="session-info">
								<h4><?php echo esc_html( $s['topic'] ?: __( 'Session', 'zeko-mentor' ) ); ?></h4>
								<span class="session-meta"><?php echo esc_html( $s['session_date'] ); ?></span>
							</div>
							<span class="session-meta"><?php echo esc_html( $s['mentor_name'] ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( ! empty( $goals ) ) : ?>
					<h3 class="zeko-section-title"><?php esc_html_e( 'My Goals', 'zeko-mentor' ); ?></h3>
					<?php
					foreach ( $goals as $g ) :
						$progress_items = $this->db->get_goal_progress( (int) $g['goal_id'], 10 );
						$pct            = $g['target_date'] && strtotime( $g['target_date'] ) > time()
							? min( 100, round( ( count( $progress_items ) / max( 1, (int) ceil( ( strtotime( $g['target_date'] ) - strtotime( $g['created_at'] ) ) / WEEK_IN_SECONDS ) ) ) * 100 ) )
							: ( 'achieved' === $g['status'] ? 100 : 0 );
						?>
						<div class="zeko-goal-card">
							<div class="goal-header">
								<div>
									<h4 class="goal-title"><?php echo esc_html( $g['title'] ); ?></h4>
									<?php if ( $g['target_date'] ) : ?>
										<span class="goal-meta"><?php esc_html_e( 'Due:', 'zeko-mentor' ); ?> <?php echo esc_html( $g['target_date'] ); ?></span>
									<?php endif; ?>
								</div>
								<span class="goal-status status-<?php echo esc_attr( $g['status'] ?: 'active' ); ?>">
									<?php echo esc_html( ucfirst( $g['status'] ?: 'active' ) ); ?>
								</span>
							</div>
							<div class="zeko-progress-bar">
								<div class="zeko-progress-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div>
							</div>
							<div style="margin-top:8px;">
								<button class="zeko-btn zeko-btn-sm zeko-btn-secondary zeko-mentor-log-progress" data-goal="<?php echo esc_attr( $g['goal_id'] ); ?>"><?php esc_html_e( 'Log Progress', 'zeko-mentor' ); ?></button>
								<?php if ( 'achieved' !== $g['status'] ) : ?>
									<button class="zeko-btn zeko-btn-sm zeko-btn-success zeko-mentor-goal-achieved" data-goal="<?php echo esc_attr( $g['goal_id'] ); ?>"><?php esc_html_e( 'Mark Achieved', 'zeko-mentor' ); ?></button>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $progress_items ) ) : ?>
								<div class="zeko-progress-log">
									<div class="zeko-progress-log-title"><?php esc_html_e( 'Progress Log', 'zeko-mentor' ); ?></div>
									<?php foreach ( $progress_items as $pi ) : ?>
										<div class="zeko-progress-entry">
											<span class="entry-date"><?php echo esc_html( $pi['created_at'] ); ?></span>
											<span><?php echo esc_html( wp_trim_words( $pi['note'] ?? '', 15 ) ); ?></span>
											<?php if ( ! empty( $pi['rating'] ) ) : ?>
												<span class="entry-rating">&#9733; <?php echo esc_html( $pi['rating'] ); ?></span>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php
							// Zeko Learn integration: suggest related courses.
							if ( class_exists( 'Zeko_Learn_DB' ) ) {
								$learn_db = new Zeko_Learn_DB();
								$topic    = sanitize_text_field( $g['title'] );
								$courses  = $learn_db->get_courses(
									array(
										'search' => $topic,
										'limit'  => 2,
									)
								);
								if ( ! empty( $courses ) ) :
									?>
									<div class="zeko-learn-card">
										<div class="zeko-learn-card-title">&#128218; <?php esc_html_e( 'Related Courses', 'zeko-mentor' ); ?></div>
										<?php foreach ( $courses as $c ) : ?>
											<div style="margin-top:6px;">
												<a href="<?php echo esc_url( home_url( '/learn/' . ( $c['slug'] ?? '' ) ) ); ?>">
													<?php echo esc_html( $c['title'] ); ?>
												</a>
												<?php if ( ! empty( $c['skill_level'] ) ) : ?>
													<span style="color:#666;font-size:11px;margin-left:6px;">(<?php echo esc_html( ucfirst( $c['skill_level'] ) ); ?>)</span>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php } ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( ! empty( $matches ) ) : ?>
					<h3 class="zeko-section-title"><?php esc_html_e( 'Suggested Mentors', 'zeko-mentor' ); ?></h3>
					<?php foreach ( $matches as $m ) : ?>
						<div class="zeko-match-card">
							<div class="zeko-match-header">
								<div>
									<strong><?php echo esc_html( $m['mentor_name'] ); ?></strong>
									<span class="zeko-rating" style="margin-left:8px;">&#9733; <?php echo esc_html( number_format( (float) $m['avg_rating'], 1 ) ); ?></span>
								</div>
								<div style="display:flex;align-items:center;gap:8px;">
									<span class="zeko-match-score"><?php echo esc_html( $m['score'] ); ?>% match</span>
									<button class="zeko-btn zeko-btn-sm zeko-btn-primary zeko-mentor-match-accept" data-match="<?php echo esc_attr( $m['match_id'] ); ?>"><?php esc_html_e( 'Accept', 'zeko-mentor' ); ?></button>
									<button class="zeko-btn zeko-btn-sm zeko-btn-outline zeko-mentor-match-dismiss" data-match="<?php echo esc_attr( $m['match_id'] ); ?>"><?php esc_html_e( 'Dismiss', 'zeko-mentor' ); ?></button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php $this->render_my_programs( $user_id ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render mentor view.
	 *
	 * @param int $user_id User id.
	 */
	private function render_mentor_dashboard( int $user_id ): void {
		$sessions = $this->db->get_user_sessions( $user_id, 'mentor', '', 5 );
		$profile  = $this->db->get_profile( $user_id );
		$reviews  = $this->db->get_mentor_reviews( $user_id, 3 );
		?>
		<div class="zeko-mentor-dashboard">
			<div class="zeko-mentor-stats-row">
				<div class="zeko-stat-card">
					<span class="stat-value"><?php echo esc_html( $profile['total_sessions'] ?? 0 ); ?></span>
					<p class="stat-label"><?php esc_html_e( 'Sessions', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card">
					<span class="stat-value"><?php echo esc_html( number_format( (float) ( $profile['avg_rating'] ?? 0 ), 1 ) ); ?></span>
					<p class="stat-label"><?php esc_html_e( 'Avg Rating', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card">
					<span class="stat-value"><?php echo esc_html( $this->db->count_active_mentees( $user_id ) ); ?></span>
					<p class="stat-label"><?php esc_html_e( 'Active Mentees', 'zeko-mentor' ); ?></p>
				</div>
			</div>

			<h3 class="zeko-section-title"><?php esc_html_e( 'Upcoming Sessions', 'zeko-mentor' ); ?></h3>
			<?php if ( empty( $sessions ) ) : ?>
				<div class="zeko-empty-state">
					<p><?php esc_html_e( 'No upcoming sessions.', 'zeko-mentor' ); ?></p>
				</div>
			<?php else : ?>
				<?php foreach ( $sessions as $s ) : ?>
					<div class="zeko-session-card">
						<div class="session-info">
							<h4><?php echo esc_html( $s['topic'] ?: __( 'Session', 'zeko-mentor' ) ); ?></h4>
							<span class="session-meta"><?php echo esc_html( $s['session_date'] . ' ' . $s['start_time'] ); ?></span>
						</div>
						<span class="session-meta"><?php echo esc_html( $s['mentee_name'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $reviews ) ) : ?>
				<h3 class="zeko-section-title"><?php esc_html_e( 'Recent Reviews', 'zeko-mentor' ); ?></h3>
				<?php foreach ( $reviews as $r ) : ?>
					<div class="zeko-review-card">
						<div class="zeko-review-header">
							<span class="zeko-review-author"><?php echo esc_html( $r['reviewer_name'] ); ?></span>
							<span class="zeko-rating">&#9733; <?php echo esc_html( $r['rating'] ); ?>/5</span>
						</div>
						<p class="zeko-review-text"><?php echo esc_html( $r['review_text'] ); ?></p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render mentee view with progress UI, match suggestions, and Learn integration.
	 *
	 * @param int $user_id User id.
	 */
	private function render_mentee_dashboard( int $user_id ): void {
		$sessions = $this->db->get_user_sessions( $user_id, 'mentee', '', 5 );
		$goals    = $this->db->get_user_goals( $user_id, '', 5 );
		$matches  = $this->db->get_mentee_matches( $user_id, 'suggested', 3 );
		?>
		<div class="zeko-mentor-dashboard">
			<h3 class="zeko-section-title"><?php esc_html_e( 'Upcoming Sessions', 'zeko-mentor' ); ?></h3>
			<?php if ( empty( $sessions ) ) : ?>
				<div class="zeko-empty-state">
					<p><?php esc_html_e( 'No upcoming sessions.', 'zeko-mentor' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/mentors/' ) ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Find a Mentor', 'zeko-mentor' ); ?></a>
				</div>
			<?php else : ?>
				<?php foreach ( $sessions as $s ) : ?>
					<div class="zeko-session-card">
						<div class="session-info">
							<h4><?php echo esc_html( $s['topic'] ?: __( 'Session', 'zeko-mentor' ) ); ?></h4>
							<span class="session-meta"><?php echo esc_html( $s['session_date'] . ' ' . $s['start_time'] ); ?></span>
						</div>
						<span class="session-meta"><?php echo esc_html( $s['mentor_name'] ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $matches ) ) : ?>
				<h3 class="zeko-section-title"><?php esc_html_e( 'Suggested Mentors', 'zeko-mentor' ); ?></h3>
				<?php foreach ( $matches as $m ) : ?>
					<div class="zeko-match-card">
						<div class="zeko-match-header">
							<div>
								<strong><?php echo esc_html( $m['mentor_name'] ); ?></strong>
								<span class="zeko-rating" style="margin-left:8px;">&#9733; <?php echo esc_html( number_format( (float) $m['avg_rating'], 1 ) ); ?></span>
								<span class="zeko-match-score" style="margin-left:8px;"><?php echo esc_html( $m['score'] ); ?>% match</span>
							</div>
							<div>
								<button class="zeko-btn zeko-btn-sm zeko-btn-primary zeko-mentor-match-accept" data-match="<?php echo esc_attr( $m['match_id'] ); ?>"><?php esc_html_e( 'Accept', 'zeko-mentor' ); ?></button>
								<button class="zeko-btn zeko-btn-sm zeko-btn-outline zeko-mentor-match-dismiss" data-match="<?php echo esc_attr( $m['match_id'] ); ?>"><?php esc_html_e( 'Dismiss', 'zeko-mentor' ); ?></button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $goals ) ) : ?>
				<h3 class="zeko-section-title"><?php esc_html_e( 'My Goals', 'zeko-mentor' ); ?></h3>
				<?php
				foreach ( $goals as $g ) :
					$progress_items = $this->db->get_goal_progress( (int) $g['goal_id'], 10 );
					$pct            = 'achieved' === $g['status'] ? 100 : 0;
					if ( $g['target_date'] && strtotime( $g['target_date'] ) > strtotime( $g['created_at'] ) && ! empty( $progress_items ) ) {
						$total_weeks = max( 1, (int) ceil( ( strtotime( $g['target_date'] ) - strtotime( $g['created_at'] ) ) / WEEK_IN_SECONDS ) );
						$pct         = min( 100, round( ( count( $progress_items ) / $total_weeks ) * 100 ) );
					}
					?>
					<div class="zeko-goal-card">
						<div class="goal-header">
							<div>
								<h4 class="goal-title"><?php echo esc_html( $g['title'] ); ?></h4>
								<?php if ( $g['target_date'] ) : ?>
									<span class="goal-meta"><?php esc_html_e( 'Due:', 'zeko-mentor' ); ?> <?php echo esc_html( $g['target_date'] ); ?></span>
								<?php endif; ?>
							</div>
							<span class="goal-status status-<?php echo esc_attr( $g['status'] ?: 'active' ); ?>">
								<?php echo esc_html( ucfirst( $g['status'] ?: 'active' ) ); ?>
							</span>
						</div>
						<div class="zeko-progress-bar">
							<div class="zeko-progress-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></div>
						</div>
						<div style="margin-top:8px;">
							<button class="zeko-btn zeko-btn-sm zeko-btn-secondary zeko-mentor-log-progress" data-goal="<?php echo esc_attr( $g['goal_id'] ); ?>"><?php esc_html_e( 'Log Progress', 'zeko-mentor' ); ?></button>
							<?php if ( 'achieved' !== $g['status'] ) : ?>
								<button class="zeko-btn zeko-btn-sm zeko-btn-success zeko-mentor-goal-achieved" data-goal="<?php echo esc_attr( $g['goal_id'] ); ?>"><?php esc_html_e( 'Mark Achieved', 'zeko-mentor' ); ?></button>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $progress_items ) ) : ?>
							<div class="zeko-progress-log">
								<div class="zeko-progress-log-title"><?php esc_html_e( 'Progress Log', 'zeko-mentor' ); ?></div>
								<?php foreach ( $progress_items as $pi ) : ?>
									<div class="zeko-progress-entry">
										<span class="entry-date"><?php echo esc_html( $pi['created_at'] ); ?></span>
										<span><?php echo esc_html( wp_trim_words( $pi['note'] ?? '', 15 ) ); ?></span>
										<?php if ( ! empty( $pi['rating'] ) ) : ?>
											<span class="entry-rating">&#9733; <?php echo esc_html( $pi['rating'] ); ?></span>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php
						if ( class_exists( 'Zeko_Learn_DB' ) ) {
							$learn_db = new Zeko_Learn_DB();
							$topic    = sanitize_text_field( $g['title'] );
							$courses  = $learn_db->get_courses(
								array(
									'search' => $topic,
									'limit'  => 2,
								)
							);
							if ( ! empty( $courses ) ) :
								?>
								<div class="zeko-learn-card">
									<div class="zeko-learn-card-title">&#128218; <?php esc_html_e( 'Related Courses', 'zeko-mentor' ); ?></div>
									<?php foreach ( $courses as $c ) : ?>
										<div style="margin-top:6px;">
											<a href="<?php echo esc_url( home_url( '/learn/' . ( $c['slug'] ?? '' ) ) ); ?>">
												<?php echo esc_html( $c['title'] ); ?>
											</a>
											<?php if ( ! empty( $c['skill_level'] ) ) : ?>
												<span style="color:#666;font-size:11px;margin-left:6px;">(<?php echo esc_html( ucfirst( $c['skill_level'] ) ); ?>)</span>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php } ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php $this->render_my_programs( $user_id ); ?>
		</div>
		<?php
	}

	/**
	 * Render the "My Programs" section with links to program member areas.
	 *
	 * @param int $user_id User id.
	 */
	private function render_my_programs( int $user_id ): void {
		$programs = $this->db->get_user_programs( $user_id );
		?>
		<h3 class="zeko-section-title"><?php esc_html_e( 'My Programs', 'zeko-mentor' ); ?></h3>
		<?php if ( empty( $programs ) ) : ?>
			<div class="zeko-empty-state">
				<p><?php esc_html_e( 'You are not enrolled in any programs yet.', 'zeko-mentor' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/mentor-programs/' ) ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Browse Programs', 'zeko-mentor' ); ?></a>
			</div>
		<?php else : ?>
			<div class="zeko-programs-grid">
				<?php foreach ( $programs as $p ) : ?>
					<div class="zeko-program-card">
						<div class="zeko-program-card-header">
							<h3><a href="<?php echo esc_url( home_url( '/programs/' . (int) $p['program_id'] . '/' ) ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
							<span class="zeko-program-status status-enrolled"><?php esc_html_e( 'Enrolled', 'zeko-mentor' ); ?></span>
						</div>
						<p class="zeko-program-mentor"><?php esc_html_e( 'by', 'zeko-mentor' ); ?> <?php echo esc_html( $p['mentor_name'] ); ?></p>
						<div class="zeko-program-meta">
							<span>📅 <?php echo esc_html( $p['duration_weeks'] ); ?> <?php esc_html_e( 'weeks', 'zeko-mentor' ); ?></span>
							<span>👥 <?php echo esc_html( $p['current_members'] . '/' . $p['max_members'] ); ?></span>
							<span>✅ <?php esc_html_e( 'Member since', 'zeko-mentor' ); ?> <?php echo esc_html( mysql2date( get_option( 'date_format' ), $p['enrolled_at'] ) ); ?></span>
						</div>
						<div class="zeko-program-card-footer">
							<a href="<?php echo esc_url( home_url( '/programs/' . (int) $p['program_id'] . '/' ) ); ?>" class="zeko-btn zeko-btn-primary"><?php esc_html_e( 'Open Program', 'zeko-mentor' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}

	/**
	 * Inject mentor activities into the global feed.
	 *
	 * @param array $items Items.
	 */
	public function inject_activity_items( array $items ): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $items;
		}

		// Recent completed sessions.
		$sessions = $this->db->get_user_sessions( $user_id, '', 'completed', 3 );
		foreach ( $sessions as $s ) {
			$items[] = array(
				'module'    => 'mentor',
				'action'    => 'session_completed',
				'message'   => sprintf(
					/* translators: 1: mentor/mentee name, 2: session topic */
					__( 'Completed mentorship session "%1$s" with %2$s', 'zeko-mentor' ),
					$s['topic'] ?: __( 'Mentorship Session', 'zeko-mentor' ),
					( get_current_user_id() === (int) $s['mentor_id'] ) ? $s['mentee_name'] : $s['mentor_name']
				),
				'timestamp' => $s['updated_at'],
				'link'      => '',
			);
		}

		return $items;
	}

	/**
	 * Render mentor card on user profile.
	 *
	 * @param int $user_id User id.
	 */
	public function render_profile_section( int $user_id ): void {
		$profile = $this->db->get_profile( $user_id );
		if ( ! $profile || ! $profile['is_active'] ) {
			return;
		}

		$expertise = json_decode( $profile['expertise_areas'] ?? '[]', true );
		?>
		<div class="zeko-mentor-profile-card" style="background:var(--zm-gray-50);border:1px solid var(--zm-gray-200);border-radius:var(--zm-radius-lg);padding:20px;margin:16px 0;">
			<h3 style="margin-top:0;color:var(--zm-primary);display:flex;align-items:center;gap:8px;">
				&#9733; <?php esc_html_e( 'Mentor', 'zeko-mentor' ); ?>
				<?php if ( $profile['is_verified'] ) : ?>
					<span class="zeko-verified-badge"><?php esc_html_e( 'Verified', 'zeko-mentor' ); ?></span>
				<?php endif; ?>
			</h3>
			<?php if ( $profile['bio'] ) : ?>
				<p style="color:var(--zm-gray-700);line-height:1.6;"><?php echo esc_html( wp_trim_words( $profile['bio'], 30 ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $expertise ) ) : ?>
				<div class="zeko-expertise-tags">
					<?php foreach ( $expertise as $tag ) : ?>
						<span class="zeko-expertise-tag"><?php echo esc_html( $tag ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div style="display:flex;align-items:center;gap:16px;margin-top:12px;">
				<span class="zeko-rate"><?php echo esc_html( $profile['hourly_rate'] . ' ' . $profile['currency'] ); ?></span>
				<span class="zeko-rating">&#9733; <?php echo esc_html( number_format( (float) $profile['avg_rating'], 1 ) ); ?> <span class="count">(<?php echo esc_html( $profile['total_sessions'] ); ?> <?php esc_html_e( 'sessions', 'zeko-mentor' ); ?>)</span></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render mentor stats on profile.
	 *
	 * @param int $user_id User id.
	 */
	public function render_profile_stats( int $user_id ): void {
		$profile = $this->db->get_profile( $user_id );
		if ( ! $profile ) {
			return;
		}
		?>
		<div class="zeko-profile-stat">
			<strong><?php echo esc_html( $profile['total_sessions'] ); ?></strong>
			<span><?php esc_html_e( 'Sessions', 'zeko-mentor' ); ?></span>
		</div>
		<?php
	}

	// ═══════════════════════════════════════════════════════════════.
	// NAVIGATION MENU.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Register Mentorship nav items via the core registry.
	 *
	 * @return array
	 * @param array $locations keyed by location slug.
	 */
	public function register_nav_items( array $locations ): array {
		$children = array();

		foreach (
			array(
				'Find a Mentor'   => 'mentors',
				'My Mentorship'   => 'mentor-dashboard',
				'Programs'        => 'mentor-programs',
				'My Matches'      => 'mentor-matches',
				'Become a Mentor' => 'become-a-mentor',
				'Messages'        => 'mentor-inbox',
				'Availability'    => 'manage-availability',
				'Calendar'        => 'mentor-calendar',
			) as $title => $slug
		) {
			$page       = zeko_mentor_get_page_by_slug( $slug );
			$children[] = array(
				'title' => $title,
				'url'   => $page ? get_permalink( $page->ID ) : home_url( '/' . $slug . '/' ),
			);
		}

		$locations['primary'][] = array(
			'title'    => __( 'Mentorship', 'zeko-mentor' ),
			'url'      => home_url( '/mentorship/' ),
			'order'    => 8,
			'children' => $children,
		);
		return $locations;
	}

	/**
	 * Append Zeko Mentor pages as sub-items under "Mentorship" in the primary nav.
	 * Follows the same pattern as the Finance module in theme functions.php.
	 */
	public function maybe_create_nav_menu_items(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$done = get_option( 'zeko_mentor_primary_menu_done', false );
		if ( $done ) {
			return;
		}

		$locations = get_nav_menu_locations();
		if ( empty( $locations['primary'] ) ) {
			return;
		}

		$menu_id = $locations['primary'];
		$items   = wp_get_nav_menu_items( $menu_id );
		if ( ! $items ) {
			return;
		}

		$mentorship_parent_id = 0;
		$max_order            = 0;

		foreach ( $items as $item ) {
			$order = (int) $item->menu_order;
			if ( $order > $max_order ) {
				$max_order = $order;
			}
			if ( 'Mentorship' === $item->title ) {
				$mentorship_parent_id = $item->ID;
			}
		}

		$children = array(
			'Find a Mentor'   => 'mentors',
			'My Mentorship'   => 'mentor-dashboard',
			'Programs'        => 'mentor-programs',
			'My Matches'      => 'mentor-matches',
			'Become a Mentor' => 'become-a-mentor',
			'Messages'        => 'mentor-inbox',
			'Availability'    => 'manage-availability',
			'Calendar'        => 'mentor-calendar',
		);

		if ( ! $mentorship_parent_id ) {
			$parent_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => __( 'Mentorship', 'zeko-mentor' ),
					'menu-item-url'    => home_url( '/mentorship/' ),
					'menu-item-status' => 'publish',
					'menu-item-type'   => 'custom',
					'menu-item-order'  => $max_order + 1,
				)
			);

			foreach ( $children as $title => $slug ) {
				$page = zeko_mentor_get_page_by_slug( $slug );
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $title,
						'menu-item-url'       => $page ? get_permalink( $page->ID ) : home_url( '/' . $slug . '/' ),
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
						'menu-item-parent-id' => $parent_id,
					)
				);
			}
		} else {
			$existing_titles = array();
			foreach ( $items as $item ) {
				if ( (int) $item->menu_item_parent === $mentorship_parent_id ) {
					$existing_titles[ $item->title ] = true;
				}
			}

			foreach ( $children as $title => $slug ) {
				if ( isset( $existing_titles[ $title ] ) ) {
					continue;
				}
				$page = zeko_mentor_get_page_by_slug( $slug );
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $title,
						'menu-item-url'       => $page ? get_permalink( $page->ID ) : home_url( '/' . $slug . '/' ),
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
						'menu-item-parent-id' => $mentorship_parent_id,
					)
				);
			}
		}

		update_option( 'zeko_mentor_primary_menu_done', true );
	}

	// ═══════════════════════════════════════════════════════════════.
	// NOTIFICATION BELL.
	// ═══════════════════════════════════════════════════════════════.

	/**
	 * Enqueue notification bell assets on frontend.
	 */
	public function enqueue_notification_assets(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'zeko-mentor-notifications',
			ZEKO_MENTOR_PLUGIN_URL . 'assets/css/zm-notifications.css',
			array(),
			ZEKO_MENTOR_VERSION
		);

		wp_enqueue_script(
			'zeko-mentor-notifications',
			ZEKO_MENTOR_PLUGIN_URL . 'assets/js/zm-notifications.js',
			array( 'jquery' ),
			ZEKO_MENTOR_VERSION,
			true
		);

		wp_localize_script(
			'zeko-mentor-notifications',
			'zmNotifications',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'zm_notifications_nonce' ),
				'pollInterval' => 30000,
				'i18n'         => array(
					'noNotifications' => __( 'No notifications yet.', 'zeko-mentor' ),
					'markAllRead'     => __( 'Mark all as read', 'zeko-mentor' ),
					'viewAll'         => __( 'View all', 'zeko-mentor' ),
				),
			)
		);
	}

	/**
	 * Add notification bell to the WP admin bar.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar.
	 */
	public function add_notification_admin_bar( WP_Admin_Bar $admin_bar ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$unread      = $this->db->count_unread_notifications( $user_id );
		$count_class = $unread > 0 ? 'zm-bell-unread' : '';
		$label       = $unread > 0
			? sprintf( '<span class="ab-icon dashicons dashicons-bell"></span><span class="zm-bell-count">%d</span>', $unread )
			: '<span class="ab-icon dashicons dashicons-bell"></span>';

		$admin_bar->add_node(
			array(
				'id'    => 'zeko-mentor-notifications',
				'title' => $label,
				'href'  => esc_url( home_url( '/mentor-dashboard/' ) ),
				'meta'  => array(
					'class' => 'zm-admin-bell ' . $count_class,
					'title' => sprintf(
					/* translators: %d: number of unread notifications */
						_n( '%d unread notification', '%d unread notifications', $unread, 'zeko-mentor' ),
						$unread
					),
				),
			)
		);
	}
}
