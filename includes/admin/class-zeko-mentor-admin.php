<?php
/**
 * Admin handler for Zeko Mentor.
 *
 * Admin pages, settings, mentor management.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor_Admin. */
class Zeko_Mentor_Admin {

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

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_footer', array( $this, 'render_demo_js' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'zeko_mentor_settings',
			'zeko_mentor_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Input.
	 */
	public function sanitize_settings( $input ): array {
		return array(
			'platform_fee_pct'           => (float) ( $input['platform_fee_pct'] ?? 10 ),
			'default_duration'           => absint( $input['default_duration'] ?? 60 ),
			'auto_verify_threshold'      => absint( $input['auto_verify_threshold'] ?? 10 ),
			'reminder_hours'             => absint( $input['reminder_hours'] ?? 24 ),
			'session_reminder'           => ! empty( $input['session_reminder'] ) ? 1 : 0,
			'match_refresh_days'         => absint( $input['match_refresh_days'] ?? 7 ),
			'notify_session_booked'      => ! empty( $input['notify_session_booked'] ) ? 1 : 0,
			'notify_session_cancel'      => ! empty( $input['notify_session_cancel'] ) ? 1 : 0,
			'notify_session_complete'    => ! empty( $input['notify_session_complete'] ) ? 1 : 0,
			'notify_new_review'          => ! empty( $input['notify_new_review'] ) ? 1 : 0,
			'notify_new_match'           => ! empty( $input['notify_new_match'] ) ? 1 : 0,
			'notify_new_message'         => ! empty( $input['notify_new_message'] ) ? 1 : 0,
			'notifications_cleanup_days' => absint( $input['notifications_cleanup_days'] ?? 90 ),
		);
	}

	/**
	 * Get settings.
	 */
	public static function get_settings(): array {
		$defaults = array(
			'platform_fee_pct'           => 10,
			'default_duration'           => 60,
			'auto_verify_threshold'      => 10,
			'reminder_hours'             => 24,
			'session_reminder'           => 1,
			'match_refresh_days'         => 7,
			'notify_session_booked'      => 1,
			'notify_session_cancel'      => 1,
			'notify_session_complete'    => 1,
			'notify_new_review'          => 1,
			'notify_new_match'           => 1,
			'notify_new_message'         => 1,
			'notifications_cleanup_days' => 90,
		);
		return wp_parse_args( get_option( 'zeko_mentor_settings', array() ), $defaults );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Zeko Mentor', 'zeko-mentor' ),
			__( 'Mentor', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			30
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Mentors', 'zeko-mentor' ),
			__( 'Mentors', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Sessions', 'zeko-mentor' ),
			__( 'Sessions', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-sessions',
			array( $this, 'render_sessions' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Programs', 'zeko-mentor' ),
			__( 'Programs', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-programs',
			array( $this, 'render_programs' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Analytics', 'zeko-mentor' ),
			__( 'Analytics', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-analytics',
			array( $this, 'render_analytics' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Applications', 'zeko-mentor' ),
			__( 'Applications', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-applications',
			array( $this, 'render_applications' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Settings', 'zeko-mentor' ),
			__( 'Settings', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'zeko-mentor',
			__( 'Demo Data', 'zeko-mentor' ),
			__( 'Demo Data', 'zeko-mentor' ),
			'manage_options',
			'zeko-mentor-demo',
			array( $this, 'render_demo_data' )
		);
	}

	/**
	 * Render admin dashboard with analytics overview.
	 */
	public function render_dashboard(): void {
		global $wpdb;
		$profiles_table = $this->db->get_table_profiles();
		$sessions_table = $this->db->get_table_sessions();
		$programs_table = $this->db->get_table_programs();
		$reviews_table  = $this->db->get_table_reviews();

		$mentor_count       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$profiles_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$verified_count     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles_table} WHERE is_verified = %d", 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$active_count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles_table} WHERE is_active = %d", 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$total_sessions     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$completed_sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE status = %s", 'completed' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$total_revenue      = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$sessions_table} WHERE payment_status = %s", 'paid' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$avg_rating         = (float) $wpdb->get_var( "SELECT COALESCE(AVG(avg_rating), 0) FROM {$profiles_table} WHERE avg_rating > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$total_programs     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$programs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$mentors = $this->db->get_mentors(
			array(
				'limit'     => 5,
				'is_active' => null,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zeko Mentor — Dashboard', 'zeko-mentor' ); ?></h1>

			<div class="zeko-mentor-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#7c3aed;"><?php echo esc_html( $mentor_count ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Total Mentors', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#10b981;"><?php echo esc_html( $active_count ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Active', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#3b82f6;"><?php echo esc_html( $total_sessions ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Total Sessions', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#f59e0b;"><?php echo esc_html( number_format( (float) $avg_rating, 1 ) ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Avg Rating', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#10b981;"><?php echo esc_html( '$' . number_format( $total_revenue, 2 ) ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Revenue', 'zeko-mentor' ); ?></p>
				</div>
				<div class="zeko-stat-card" style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#8b5cf6;"><?php echo esc_html( $total_programs ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Programs', 'zeko-mentor' ); ?></p>
				</div>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
				<div>
					<h2><?php esc_html_e( 'Recent Mentors', 'zeko-mentor' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Expertise', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Rate', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Rating', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Status', 'zeko-mentor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $mentors ) ) : ?>
								<tr><td colspan="5"><?php esc_html_e( 'No mentors found.', 'zeko-mentor' ); ?></td></tr>
							<?php else : ?>
								<?php
								foreach ( $mentors as $m ) :
									$userdata = get_userdata( (int) $m['user_id'] );
									$name     = $userdata ? $userdata->display_name : 'User #' . $m['user_id'];
									?>
									<tr>
										<td><?php echo esc_html( $name ); ?></td>
										<td><?php echo esc_html( implode( ', ', json_decode( $m['expertise_areas'] ?? '[]', true ) ) ); ?></td>
										<td><?php echo esc_html( $m['hourly_rate'] . ' ' . $m['currency'] ); ?></td>
										<td>★ <?php echo esc_html( number_format( (float) $m['avg_rating'], 1 ) ); ?></td>
										<td>
											<?php if ( $m['is_verified'] ) : ?>
												<span style="color:#10b981;">✓ <?php esc_html_e( 'Verified', 'zeko-mentor' ); ?></span>
											<?php elseif ( $m['is_active'] ) : ?>
												<span style="color:#3b82f6;"><?php esc_html_e( 'Active', 'zeko-mentor' ); ?></span>
											<?php else : ?>
												<span style="color:#ef4444;"><?php esc_html_e( 'Inactive', 'zeko-mentor' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div>
					<h2><?php esc_html_e( 'Quick Stats', 'zeko-mentor' ); ?></h2>
					<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;">
						<table style="width:100%;border-collapse:collapse;">
							<tr style="border-bottom:1px solid #e2e8f0;">
								<td style="padding:12px 0;"><?php esc_html_e( 'Session Completion Rate', 'zeko-mentor' ); ?></td>
								<td style="padding:12px 0;text-align:right;font-weight:600;color:#10b981;">
									<?php
									$rate = $total_sessions > 0 ? round( ( $completed_sessions / $total_sessions ) * 100 ) : 0;
									echo esc_html( $rate . '%' );
									?>
								</td>
							</tr>
							<tr style="border-bottom:1px solid #e2e8f0;">
								<td style="padding:12px 0;"><?php esc_html_e( 'Revenue per Session', 'zeko-mentor' ); ?></td>
								<td style="padding:12px 0;text-align:right;font-weight:600;">
									<?php echo esc_html( '$' . ( $completed_sessions > 0 ? number_format( $total_revenue / $completed_sessions, 2 ) : '0.00' ) ); ?>
								</td>
							</tr>
							<tr style="border-bottom:1px solid #e2e8f0;">
								<td style="padding:12px 0;"><?php esc_html_e( 'Verified Ratio', 'zeko-mentor' ); ?></td>
								<td style="padding:12px 0;text-align:right;font-weight:600;">
									<?php echo esc_html( $mentor_count > 0 ? round( ( $verified_count / $mentor_count ) * 100 ) . '%' : '0%' ); ?>
								</td>
							</tr>
							<tr>
								<td style="padding:12px 0;"><?php esc_html_e( 'Active Mentors', 'zeko-mentor' ); ?></td>
								<td style="padding:12px 0;text-align:right;font-weight:600;">
									<?php echo esc_html( $mentor_count > 0 ? round( ( $active_count / $mentor_count ) * 100 ) . '%' : '0%' ); ?>
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render sessions management page with data table and filters.
	 */
	public function render_sessions(): void {
		$current_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page; value binds via $wpdb->prepare().
		$current_date   = sanitize_text_field( wp_unslash( $_GET['date'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page; value binds via $wpdb->prepare().
		$page           = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page.
		$per_page       = 20;
		$offset         = ( $page - 1 ) * $per_page;

		$session_table = $this->db->get_table_sessions();
		$profiles      = $this->db->get_table_profiles();
		global $wpdb;

		$where  = 'WHERE 1=1';
		$values = array();

		if ( $current_status ) {
			$where   .= ' AND s.status = %s';
			$values[] = $current_status;
		}
		if ( $current_date ) {
			$where   .= ' AND s.session_date = %s';
			$values[] = $current_date;
		}

		if ( ! empty( $values ) ) {
			$count_sql = "SELECT COUNT(*) FROM {$session_table} s {$where}";
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$session_table} s" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT s.*, mp.user_id AS mentor_name_id, me.user_id AS mentee_name_id
				 FROM {$session_table} s
				 LEFT JOIN {$profiles} mp ON s.mentor_id = mp.user_id
				 LEFT JOIN {$profiles} me ON s.mentee_id = me.user_id
				 {$where}
				 ORDER BY s.created_at DESC LIMIT %d OFFSET %d",
				...array_merge( $values, array( $per_page, $offset ) )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT s.*, mp.user_id AS mentor_name_id, me.user_id AS mentee_name_id
				 FROM {$session_table} s
				 LEFT JOIN {$profiles} mp ON s.mentor_id = mp.user_id
				 LEFT JOIN {$profiles} me ON s.mentee_id = me.user_id
				 ORDER BY s.created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		}

		$sessions = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$pages    = ceil( $total / $per_page );

		$status_options = array(
			''          => __( 'All', 'zeko-mentor' ),
			'pending'   => __( 'Pending', 'zeko-mentor' ),
			'confirmed' => __( 'Confirmed', 'zeko-mentor' ),
			'completed' => __( 'Completed', 'zeko-mentor' ),
			'cancelled' => __( 'Cancelled', 'zeko-mentor' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Session Management', 'zeko-mentor' ); ?></h1>

			<div class="tablenav top" style="margin-bottom:16px;">
				<form method="get">
					<input type="hidden" name="page" value="zeko-mentor-sessions" />
					<select name="status">
						<?php foreach ( $status_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="date" name="date" value="<?php echo esc_attr( $current_date ); ?>" />
					<?php submit_button( __( 'Filter', 'zeko-mentor' ), 'button', 'filter_action', false ); ?>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Mentor', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Mentee', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Date & Time', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Topic', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Payment', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'zeko-mentor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $sessions ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No sessions found.', 'zeko-mentor' ); ?></td></tr>
					<?php else : ?>
						<?php
						foreach ( $sessions as $s ) :
							$mentor_userdata = get_userdata( (int) $s['mentor_id'] );
							$mentee_userdata = get_userdata( (int) $s['mentee_id'] );
							$mentor_name     = $mentor_userdata ? $mentor_userdata->display_name : 'User #' . $s['mentor_id'];
							$mentee_name     = $mentee_userdata ? $mentee_userdata->display_name : 'User #' . $s['mentee_id'];
							$status_colors   = array(
								'pending'   => '#f59e0b',
								'confirmed' => '#3b82f6',
								'completed' => '#10b981',
								'cancelled' => '#ef4444',
							);
							$payment_colors  = array(
								'pending'  => '#f59e0b',
								'paid'     => '#10b981',
								'refunded' => '#ef4444',
							);
							?>
							<tr>
								<td>#<?php echo esc_html( $s['session_id'] ); ?></td>
								<td><?php echo esc_html( $mentor_name ); ?></td>
								<td><?php echo esc_html( $mentee_name ); ?></td>
								<td><?php echo esc_html( $s['session_date'] . ' ' . $s['start_time'] . '–' . $s['end_time'] ); ?></td>
								<td><?php echo esc_html( $s['topic'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $s['session_type'] ) ); ?></td>
								<td>
									<span style="color:<?php echo esc_attr( $status_colors[ $s['status'] ] ?? '#666' ); ?>;font-weight:600;">
										<?php echo esc_html( ucfirst( $s['status'] ) ); ?>
									</span>
								</td>
								<td>
									<span style="color:<?php echo esc_attr( $payment_colors[ $s['payment_status'] ] ?? '#666' ); ?>;">
										<?php echo esc_html( ucfirst( $s['payment_status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $s['amount'] . ' ' . $s['currency'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom" style="margin-top:16px;">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render programs management page with data table and filters.
	 */
	public function render_programs(): void {
		$current_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page; value used only for list filtering.
		$current_expert = sanitize_text_field( wp_unslash( $_GET['expertise'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page; value used only for list filtering.
		$page           = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET list filter on manage_options page.
		$per_page       = 20;
		$offset         = ( $page - 1 ) * $per_page;

		$programs = $this->db->get_programs(
			array(
				'status'    => $current_status,
				'expertise' => $current_expert,
				'limit'     => $per_page,
				'offset'    => $offset,
			)
		);

		$expertise_options = array(
			''           => __( 'All Areas', 'zeko-mentor' ),
			'technology' => __( 'Technology', 'zeko-mentor' ),
			'business'   => __( 'Business', 'zeko-mentor' ),
			'design'     => __( 'Design', 'zeko-mentor' ),
			'marketing'  => __( 'Marketing', 'zeko-mentor' ),
			'leadership' => __( 'Leadership', 'zeko-mentor' ),
			'finance'    => __( 'Finance', 'zeko-mentor' ),
		);
		$status_options    = array(
			''       => __( 'All', 'zeko-mentor' ),
			'active' => __( 'Active', 'zeko-mentor' ),
			'draft'  => __( 'Draft', 'zeko-mentor' ),
			'closed' => __( 'Closed', 'zeko-mentor' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Program Management', 'zeko-mentor' ); ?></h1>

			<div class="tablenav top" style="margin-bottom:16px;">
				<form method="get">
					<input type="hidden" name="page" value="zeko-mentor-programs" />
					<select name="status">
						<?php foreach ( $status_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="expertise">
						<?php foreach ( $expertise_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_expert, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'zeko-mentor' ), 'button', 'filter_action', false ); ?>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Title', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Mentor', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Expertise', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Members', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Price', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zeko-mentor' ); ?></th>
						<th><?php esc_html_e( 'Dates', 'zeko-mentor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $programs ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No programs found.', 'zeko-mentor' ); ?></td></tr>
					<?php else : ?>
						<?php
						foreach ( $programs as $p ) :
							$mentor_userdata = get_userdata( (int) $p['mentor_id'] );
							$mentor_name     = $mentor_userdata ? $mentor_userdata->display_name : 'User #' . $p['mentor_id'];
							$status_colors   = array(
								'active' => '#10b981',
								'draft'  => '#6b7280',
								'closed' => '#ef4444',
							);
							?>
							<tr>
								<td>#<?php echo esc_html( $p['program_id'] ); ?></td>
								<td><?php echo esc_html( $p['title'] ); ?></td>
								<td><?php echo esc_html( $mentor_name ); ?></td>
								<td><?php echo esc_html( ucfirst( $p['expertise_area'] ) ); ?></td>
								<td><?php echo esc_html( $p['current_members'] . '/' . $p['max_members'] ); ?></td>
								<td><?php echo esc_html( $p['duration_weeks'] . ' weeks' ); ?></td>
								<td><?php echo esc_html( $p['price'] . ' ' . $p['currency'] ); ?></td>
								<td>
									<span style="color:<?php echo esc_attr( $status_colors[ $p['status'] ] ?? '#666' ); ?>;font-weight:600;">
										<?php echo esc_html( ucfirst( $p['status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php echo esc_html( ( $p['start_date'] ?: '—' ) . ' → ' . ( $p['end_date'] ?: '—' ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render settings page with tabbed sections.
	 */
	public function render_settings(): void {
		$settings   = self::get_settings();
		$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'general' ) );
		$tabs       = array(
			'general'       => __( 'General', 'zeko-mentor' ),
			'notifications' => __( 'Notifications', 'zeko-mentor' ),
			'pages'         => __( 'Pages', 'zeko-mentor' ),
			'advanced'      => __( 'Advanced', 'zeko-mentor' ),
		);

		// Handle recreate pages action.
		if ( isset( $_GET['zeko_mentor_action'] ) && 'recreate_pages' === $_GET['zeko_mentor_action'] ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'zeko_mentor_recreate_pages' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'zeko-mentor' ) );
			}
			zeko_mentor_create_shortcode_pages();
			$page_ids = array();
			$slugs    = array( 'mentors', 'mentor-dashboard', 'mentor-programs', 'mentor-matches', 'become-a-mentor', 'mentor-session' );
			foreach ( $slugs as $slug ) {
				$page = zeko_mentor_get_page_by_slug( $slug );
				if ( $page ) {
					$page_ids[ $slug ] = $page->ID;
				}
			}
			update_option( 'zeko_mentor_page_ids', $page_ids );
			update_option( 'zeko_mentor_pages_created', true );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Pages recreated successfully.', 'zeko-mentor' ) . '</p></div>';
		}

		// Handle export action.
		if ( isset( $_GET['zeko_mentor_action'] ) && 'export_data' === $_GET['zeko_mentor_action'] ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'zeko_mentor_export_data' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'zeko-mentor' ) );
			}
			$this->handle_export_data();
			exit;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zeko Mentor Settings', 'zeko-mentor' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=zeko-mentor-settings&tab=' . $key ) ); ?>"
						class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content" style="margin-top: 16px;">
				<?php if ( 'general' === $active_tab ) : ?>
					<form method="post" action="options.php">
						<?php settings_fields( 'zeko_mentor_settings' ); ?>
						<table class="form-table">
							<tr>
								<th><label for="platform_fee_pct"><?php esc_html_e( 'Platform Fee (%)', 'zeko-mentor' ); ?></label></th>
								<td><input type="number" id="platform_fee_pct" name="zeko_mentor_settings[platform_fee_pct]" value="<?php echo esc_attr( $settings['platform_fee_pct'] ); ?>" min="0" max="50" step="0.5" class="small-text" /></td>
							</tr>
							<tr>
								<th><label for="default_duration"><?php esc_html_e( 'Default Session Duration (minutes)', 'zeko-mentor' ); ?></label></th>
								<td><input type="number" id="default_duration" name="zeko_mentor_settings[default_duration]" value="<?php echo esc_attr( $settings['default_duration'] ); ?>" min="15" max="180" step="15" class="small-text" /></td>
							</tr>
							<tr>
								<th><label for="reminder_hours"><?php esc_html_e( 'Session Reminder (hours before)', 'zeko-mentor' ); ?></label></th>
								<td><input type="number" id="reminder_hours" name="zeko_mentor_settings[reminder_hours]" value="<?php echo esc_attr( $settings['reminder_hours'] ); ?>" min="1" max="72" class="small-text" /></td>
							</tr>
							<tr>
								<th><label for="session_reminder"><?php esc_html_e( 'Enable Session Reminders', 'zeko-mentor' ); ?></label></th>
								<td><input type="checkbox" id="session_reminder" name="zeko_mentor_settings[session_reminder]" value="1" <?php checked( $settings['session_reminder'] ); ?> /></td>
							</tr>
							<tr>
								<th><label for="match_refresh_days"><?php esc_html_e( 'Match Refresh Interval (days)', 'zeko-mentor' ); ?></label></th>
								<td><input type="number" id="match_refresh_days" name="zeko_mentor_settings[match_refresh_days]" value="<?php echo esc_attr( $settings['match_refresh_days'] ); ?>" min="1" max="30" class="small-text" /></td>
							</tr>
						</table>
						<?php submit_button(); ?>
					</form>

				<?php elseif ( 'notifications' === $active_tab ) : ?>
					<form method="post" action="options.php">
						<?php settings_fields( 'zeko_mentor_settings' ); ?>
						<h2><?php esc_html_e( 'In-App Notification Preferences', 'zeko-mentor' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Control which events generate in-app notifications for users.', 'zeko-mentor' ); ?></p>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Session Booked', 'zeko-mentor' ); ?></th>
								<td><input type="checkbox" name="zeko_mentor_settings[notify_session_booked]" value="1" <?php checked( $settings['notify_session_booked'] ); ?> /> <?php esc_html_e( 'Notify mentors when a session is booked', 'zeko-mentor' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Session Cancelled', 'zeko-mentor' ); ?></th>
								<td><input type="checkbox" name="zeko_mentor_settings[notify_session_cancel]" value="1" <?php checked( $settings['notify_session_cancel'] ); ?> /> <?php esc_html_e( 'Notify the other party when a session is cancelled', 'zeko-mentor' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Session Completed', 'zeko-mentor' ); ?></th>
								<td><input type="checkbox" name="zeko_mentor_settings[notify_session_complete]" value="1" <?php checked( $settings['notify_session_complete'] ); ?> /> <?php esc_html_e( 'Notify the other party when a session is completed', 'zeko-mentor' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'New Review', 'zeko-mentor' ); ?></th>
								<td><input type="checkbox" name="zeko_mentor_settings[notify_new_review]" value="1" <?php checked( $settings['notify_new_review'] ); ?> /> <?php esc_html_e( 'Notify mentors when they receive a review', 'zeko-mentor' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Match Accepted', 'zeko-mentor' ); ?></th>
								<td><input type="checkbox" name="zeko_mentor_settings[notify_new_match]" value="1" <?php checked( $settings['notify_new_match'] ); ?> /> <?php esc_html_e( 'Notify mentors when a mentee accepts a match', 'zeko-mentor' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Cleanup Old Notifications', 'zeko-mentor' ); ?></th>
								<td>
									<input type="number" name="zeko_mentor_settings[notifications_cleanup_days]" value="<?php echo esc_attr( $settings['notifications_cleanup_days'] ); ?>" min="30" max="365" class="small-text" />
									<span class="description"><?php esc_html_e( 'days (notifications older than this are auto-deleted)', 'zeko-mentor' ); ?></span>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
					</form>

				<?php elseif ( 'pages' === $active_tab ) : ?>
					<h2><?php esc_html_e( 'Plugin Pages', 'zeko-mentor' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These pages are created automatically. You can recreate them if they were deleted.', 'zeko-mentor' ); ?></p>
					<?php
					$page_ids = get_option( 'zeko_mentor_page_ids', array() );
					$slugs    = array(
						'mentors'          => __( 'Find a Mentor', 'zeko-mentor' ),
						'mentor-dashboard' => __( 'My Mentorship', 'zeko-mentor' ),
						'mentor-programs'  => __( 'Mentorship Programs', 'zeko-mentor' ),
						'mentor-matches'   => __( 'My Matches', 'zeko-mentor' ),
						'become-a-mentor'  => __( 'Become a Mentor', 'zeko-mentor' ),
						'mentor-session'   => __( 'Session Details', 'zeko-mentor' ),
					);
					?>
					<table class="wp-list-table widefat fixed striped" style="max-width:700px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Page', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Shortcode', 'zeko-mentor' ); ?></th>
								<th><?php esc_html_e( 'Status', 'zeko-mentor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $slugs as $slug => $label ) :
								$page = zeko_mentor_get_page_by_slug( $slug );
								?>
								<tr>
									<td>
										<?php if ( $page ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>"><?php echo esc_html( $label ); ?></a>
										<?php else : ?>
											<span style="color:#ef4444;"><?php echo esc_html( $label ); ?></span>
										<?php endif; ?>
									</td>
									<td><code>[zeko_mentor_<?php echo esc_html( str_replace( array( 'mentor-', 'become-a-' ), '', $slug ) ); ?>]</code></td>
									<td>
										<?php if ( $page && 'publish' === $page->post_status ) : ?>
											<span style="color:#10b981;">&#10003; <?php esc_html_e( 'Published', 'zeko-mentor' ); ?></span>
										<?php elseif ( $page ) : ?>
											<span style="color:#f59e0b;"><?php echo esc_html( ucfirst( $page->post_status ) ); ?></span>
										<?php else : ?>
											<span style="color:#ef4444;"><?php esc_html_e( 'Missing', 'zeko-mentor' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p style="margin-top:16px;">
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=zeko-mentor-settings&tab=pages&zeko_mentor_action=recreate_pages' ), 'zeko_mentor_recreate_pages' ) ); ?>"
							class="button button-secondary"
							onclick="return confirm('<?php esc_attr_e( 'This will create any missing pages. Existing pages will not be modified. Continue?', 'zeko-mentor' ); ?>');">
							<?php esc_html_e( 'Recreate Missing Pages', 'zeko-mentor' ); ?>
						</a>
					</p>

				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<h2><?php esc_html_e( 'Advanced', 'zeko-mentor' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Export Data', 'zeko-mentor' ); ?></th>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=zeko-mentor-settings&tab=advanced&zeko_mentor_action=export_data' ), 'zeko_mentor_export_data' ) ); ?>"
									class="button button-secondary">
									<?php esc_html_e( 'Export as CSV', 'zeko-mentor' ); ?>
								</a>
								<p class="description"><?php esc_html_e( 'Export all mentor profiles, sessions, reviews, and goals as CSV files.', 'zeko-mentor' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Database Version', 'zeko-mentor' ); ?></th>
							<td><code><?php echo esc_html( ZEKO_MENTOR_DB_VERSION ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Plugin Version', 'zeko-mentor' ); ?></th>
							<td><code><?php echo esc_html( ZEKO_MENTOR_VERSION ); ?></code></td>
						</tr>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle CSV export of mentor data.
	 */
	private function handle_export_data(): void {
		global $wpdb;
		$type   = sanitize_text_field( wp_unslash( $_GET['export_type'] ?? 'sessions' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in render_settings() before handle_export_data() is invoked; value is allow-listed against $tables keys.
		$tables = array(
			'sessions' => array(
				'table'    => $this->db->get_table_sessions(),
				'filename' => 'mentor-sessions.csv',
			),
			'profiles' => array(
				'table'    => $this->db->get_table_profiles(),
				'filename' => 'mentor-profiles.csv',
			),
			'reviews'  => array(
				'table'    => $this->db->get_table_reviews(),
				'filename' => 'mentor-reviews.csv',
			),
			'goals'    => array(
				'table'    => $this->db->get_table_goals(),
				'filename' => 'mentor-goals.csv',
			),
		);

		$export = $tables[ $type ] ?? $tables['sessions'];
		$rows   = $wpdb->get_results( "SELECT * FROM {$export['table']}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $export['filename'] );

		$fp = fopen( 'php://output', 'w' );
		if ( ! empty( $rows ) ) {
			fputcsv( $fp, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $fp, $row );
			}
		}
		fclose( $fp );
		exit;
	}

	/**
	 * Render analytics dashboard with charts and metrics.
	 */
	public function render_analytics(): void {
		global $wpdb;
		$sessions_table = $this->db->get_table_sessions();
		$reviews_table  = $this->db->get_table_reviews();
		$programs_table = $this->db->get_table_programs();
		$profiles_table = $this->db->get_table_profiles();

		$days      = absint( wp_unslash( $_GET['days'] ?? 30 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET display filter on manage_options page; value allow-listed against $days_options keys.
		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$sessions_by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$sessions_table} WHERE session_date >= %s GROUP BY status",
				$date_from
			),
			OBJECT_K
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$revenue_by_month = $wpdb->get_results(
			"SELECT DATE_FORMAT(session_date, '%Y-%m') AS month, SUM(amount) AS revenue, COUNT(*) AS count
			 FROM {$sessions_table}
			 WHERE payment_status = 'paid'
			 GROUP BY month
			 ORDER BY month DESC
			 LIMIT 12"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$top_mentors = $wpdb->get_results(
			"SELECT user_id, total_sessions, avg_rating, hourly_rate
			 FROM {$profiles_table}
			 WHERE is_active = 1
			 ORDER BY total_sessions DESC
			 LIMIT 10"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$total_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$sessions_table} WHERE payment_status = 'paid' AND session_date >= %s",
				$date_from
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		$days_options = array(
			7   => '7 days',
			30  => '30 days',
			90  => '90 days',
			365 => '1 year',
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mentor Analytics', 'zeko-mentor' ); ?></h1>

			<div style="margin-bottom:16px;">
				<form method="get" style="display:inline;">
					<input type="hidden" name="page" value="zeko-mentor-analytics" />
					<select name="days" onchange="this.form.submit()">
						<?php foreach ( $days_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $days, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:20px 0;">
				<div style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#10b981;"><?php echo esc_html( '$' . number_format( $total_revenue, 2 ) ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php echo esc_html( __( 'Revenue (', 'zeko-mentor' ) . $days . __( 'd)', 'zeko-mentor' ) ); ?></p>
				</div>
				<div style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#3b82f6;"><?php echo esc_html( array_sum( array_column( $sessions_by_status, 'count' ) ) ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Total Sessions', 'zeko-mentor' ); ?></p>
				</div>
				<div style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#10b981;"><?php echo esc_html( $sessions_by_status['completed']->count ?? 0 ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Completed', 'zeko-mentor' ); ?></p>
				</div>
				<div style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:8px;text-align:center;">
					<h3 style="margin:0;color:#ef4444;"><?php echo esc_html( $sessions_by_status['cancelled']->count ?? 0 ); ?></h3>
					<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Cancelled', 'zeko-mentor' ); ?></p>
				</div>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
				<div>
					<h2><?php esc_html_e( 'Revenue by Month', 'zeko-mentor' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th><?php esc_html_e( 'Month', 'zeko-mentor' ); ?></th><th><?php esc_html_e( 'Revenue', 'zeko-mentor' ); ?></th><th><?php esc_html_e( 'Sessions', 'zeko-mentor' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $revenue_by_month ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No data yet.', 'zeko-mentor' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $revenue_by_month as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row->month ); ?></td>
										<td style="color:#10b981;font-weight:600;"><?php echo esc_html( '$' . number_format( (float) $row->revenue, 2 ) ); ?></td>
										<td><?php echo esc_html( $row->count ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div>
					<h2><?php esc_html_e( 'Top Mentors', 'zeko-mentor' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th><?php esc_html_e( 'Mentor', 'zeko-mentor' ); ?></th><th><?php esc_html_e( 'Sessions', 'zeko-mentor' ); ?></th><th><?php esc_html_e( 'Rating', 'zeko-mentor' ); ?></th><th><?php esc_html_e( 'Rate', 'zeko-mentor' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $top_mentors ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No mentors yet.', 'zeko-mentor' ); ?></td></tr>
							<?php else : ?>
								<?php
								foreach ( $top_mentors as $t ) :
									$ud = get_userdata( (int) $t->user_id );
									?>
									<tr>
										<td><?php echo esc_html( $ud ? $ud->display_name : 'User #' . $t->user_id ); ?></td>
										<td><?php echo esc_html( $t->total_sessions ); ?></td>
										<td>★ <?php echo esc_html( number_format( (float) $t->avg_rating, 1 ) ); ?></td>
										<td><?php echo esc_html( $t->hourly_rate ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render mentor applications management page.
	 */
	public function render_applications(): void {
		global $wpdb;
		$profiles_table = $this->db->get_table_profiles();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$applications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$profiles_table} WHERE verification_status = %s ORDER BY created_at DESC",
				'pending'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		$all = $wpdb->get_results(
			"SELECT * FROM {$profiles_table} WHERE verification_status != 'none' ORDER BY created_at DESC LIMIT 50",
			ARRAY_A
		); // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mentor Applications', 'zeko-mentor' ); ?></h1>

			<?php if ( ! empty( $applications ) ) : ?>
				<h2><?php esc_html_e( 'Pending Applications', 'zeko-mentor' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Expertise', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Bio', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Rate', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Applied', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'zeko-mentor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $applications as $app ) :
							$ud = get_userdata( (int) $app['user_id'] );
							?>
							<tr>
								<td><?php echo esc_html( $ud ? $ud->display_name . ' (' . $ud->user_email . ')' : 'User #' . $app['user_id'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', json_decode( $app['expertise_areas'] ?? '[]', true ) ) ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $app['bio'] ?? '', 20 ) ); ?></td>
								<td><?php echo esc_html( $app['hourly_rate'] . ' ' . $app['currency'] ); ?></td>
								<td><?php echo esc_html( $app['created_at'] ); ?></td>
								<td>
									<button class="button button-primary zeko-mentor-verify-btn" data-user="<?php echo esc_attr( $app['user_id'] ); ?>" data-action="approve"><?php esc_html_e( 'Approve', 'zeko-mentor' ); ?></button>
									<button class="button zeko-mentor-verify-btn" data-user="<?php echo esc_attr( $app['user_id'] ); ?>" data-action="reject" style="color:#ef4444;"><?php esc_html_e( 'Reject', 'zeko-mentor' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No pending applications.', 'zeko-mentor' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $all ) ) : ?>
				<h2 style="margin-top:24px;"><?php esc_html_e( 'All Verified Applications', 'zeko-mentor' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Status', 'zeko-mentor' ); ?></th>
							<th><?php esc_html_e( 'Verified Date', 'zeko-mentor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $all as $app ) :
							$ud = get_userdata( (int) $app['user_id'] );
							?>
							<tr>
								<td><?php echo esc_html( $ud ? $ud->display_name : 'User #' . $app['user_id'] ); ?></td>
								<td>
									<?php
									$colors = array(
										'approved' => '#10b981',
										'rejected' => '#ef4444',
										'pending'  => '#f59e0b',
									);
									$color  = $colors[ $app['verification_status'] ] ?? '#666';
									?>
									<span style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;">
										<?php echo esc_html( ucfirst( $app['verification_status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $app['verification_date'] ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output demo data JS in admin footer (only on demo data page).
	 */
	public function render_demo_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'zeko-mentor-demo' ) ) {
			return;
		}
		$nonce = wp_create_nonce( 'zeko_mentor_demo_nonce' );
		?>
		<script>
		var $genBtn = null, $clrBtn = null;
		jQuery(function($) {
			$genBtn = $('#zeko-mentor-generate-demo');
			$clrBtn = $('#zeko-mentor-clear-demo');

			function setBusy(busy) {
				$genBtn.prop('disabled', busy);
				$clrBtn.prop('disabled', busy);
				if (busy) {
					$('#zeko-demo-status').empty();
				}
			}

			$genBtn.on('click', function() {
				setBusy(true);
				$genBtn.text('<?php echo esc_js( __( 'Generating...', 'zeko-mentor' ) ); ?>');
				$.post(ajaxurl, {
					action: 'zeko_mentor_generate_demo_data',
					_ajax_nonce: '<?php echo esc_js( $nonce ); ?>'
				}).done(function(res) {
					if (res.success) {
						var html = '<div class="notice notice-success"><p><?php echo esc_js( __( 'Demo data generated:', 'zeko-mentor' ) ); ?></p><ul>';
						$.each(res.data.counts, function(k, v) {
							html += '<li><strong>' + k + ':</strong> ' + v + '</li>';
						});
						html += '</ul></div>';
						$('#zeko-demo-status').html(html);
					} else {
						$('#zeko-demo-status').html('<div class="notice notice-error"><p>' + res.data.message + '</p></div>');
					}
				}).fail(function() {
					$('#zeko-demo-status').html('<div class="notice notice-error"><p><?php echo esc_js( __( 'Server error. Check the PHP error log for details.', 'zeko-mentor' ) ); ?></p></div>');
				}).always(function() {
					setBusy(false);
					$genBtn.text('<?php echo esc_js( __( 'Generate Demo Data', 'zeko-mentor' ) ); ?>');
				});
			});

			$clrBtn.on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'This will delete ALL demo data and users. Continue?', 'zeko-mentor' ) ); ?>')) return;
				setBusy(true);
				$clrBtn.text('<?php echo esc_js( __( 'Clearing...', 'zeko-mentor' ) ); ?>');
				$.post(ajaxurl, {
					action: 'zeko_mentor_clear_demo_data',
					_ajax_nonce: '<?php echo esc_js( $nonce ); ?>'
				}).done(function(res) {
					if (res.success) {
						$('#zeko-demo-status').html('<div class="notice notice-success"><p><?php echo esc_js( __( 'All demo data cleared.', 'zeko-mentor' ) ); ?></p></div>');
					} else {
						$('#zeko-demo-status').html('<div class="notice notice-error"><p>' + res.data.message + '</p></div>');
					}
				}).fail(function() {
					$('#zeko-demo-status').html('<div class="notice notice-error"><p><?php echo esc_js( __( 'Server error. Check the PHP error log for details.', 'zeko-mentor' ) ); ?></p></div>');
				}).always(function() {
					setBusy(false);
					$clrBtn.text('<?php echo esc_js( __( 'Clear Demo Data', 'zeko-mentor' ) ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Demo Data admin page.
	 */
	public function render_demo_data(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Demo Data', 'zeko-mentor' ); ?></h1>
			<p><?php esc_html_e( 'Generate sample data to test the Zeko Mentor plugin, or clear all demo data to start fresh.', 'zeko-mentor' ); ?></p>

			<div id="zeko-demo-status"></div>

			<div style="display:flex;gap:20px;margin-top:20px;">
				<div style="flex:1;max-width:400px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Generate Demo Data', 'zeko-mentor' ); ?></h2>
					<p><?php esc_html_e( 'Creates 5 mentors, 10 mentees, and sample sessions, goals, reviews, and more.', 'zeko-mentor' ); ?></p>
					<button type="button" class="button button-primary" id="zeko-mentor-generate-demo">
						<?php esc_html_e( 'Generate Demo Data', 'zeko-mentor' ); ?>
					</button>
				</div>

				<div style="flex:1;max-width:400px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Clear Demo Data', 'zeko-mentor' ); ?></h2>
					<p><?php esc_html_e( 'Removes all demo users, profiles, sessions, and related data from the database.', 'zeko-mentor' ); ?></p>
					<button type="button" class="button button-secondary" id="zeko-mentor-clear-demo" style="color:#b32d2e;border-color:#b32d2e;">
						<?php esc_html_e( 'Clear Demo Data', 'zeko-mentor' ); ?>
					</button>
				</div>
			</div>

			<div style="margin-top:30px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'What Gets Created', 'zeko-mentor' ); ?></h2>
				<ul style="list-style:disc;padding-left:20px;">
					<li><?php esc_html_e( '5 demo mentor accounts (demo_mentor_1 through demo_mentor_5)', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( '10 demo mentee accounts (demo_mentee_1 through demo_mentee_10)', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Mentor profiles with expertise, bio, rates, and availability', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Match suggestions between mentors and mentees', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Sample sessions (pending, confirmed, completed, cancelled)', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Goals with progress entries', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Reviews for completed sessions', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'A demo mentorship program with enrolled mentees', 'zeko-mentor' ); ?></li>
					<li><?php esc_html_e( 'Sample messages between mentors and mentees', 'zeko-mentor' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
