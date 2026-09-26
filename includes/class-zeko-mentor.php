<?php
/**
 * Core singleton orchestrator for Zeko Mentor.
 *
 * Wires all subsystems: DB, public, admin, AJAX, ecosystem, emails, matching.
 *
 * @package Zeko_Mentor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Class Zeko_Mentor. */
final class Zeko_Mentor {

	/**
	 * Instance.
	 *
	 * @var ?self Instance.
	 */
	private static ?self $instance = null;

	/**
	 * Db.
	 *
	 * @var Zeko_Mentor_DB Db.
	 */
	private Zeko_Mentor_DB $db;

	/**
	 * Public.
	 *
	 * @var Zeko_Mentor_Public Public.
	 */
	private Zeko_Mentor_Public $public;

	/**
	 * Admin.
	 *
	 * @var Zeko_Mentor_Admin Admin.
	 */
	private Zeko_Mentor_Admin $admin;

	/**
	 * Ajax.
	 *
	 * @var Zeko_Mentor_AJAX Ajax.
	 */
	private Zeko_Mentor_AJAX $ajax;

	/**
	 * Ecosystem.
	 *
	 * @var Zeko_Mentor_Ecosystem Ecosystem.
	 */
	private Zeko_Mentor_Ecosystem $ecosystem;

	/**
	 * Emails.
	 *
	 * @var Zeko_Mentor_Emails Emails.
	 */
	private Zeko_Mentor_Emails $emails;

	/**
	 * Matching.
	 *
	 * @var Zeko_Mentor_Matching Matching.
	 */
	private Zeko_Mentor_Matching $matching;

	/**
	 * Notifications.
	 *
	 * @var Zeko_Mentor_Notifications Notifications.
	 */
	private Zeko_Mentor_Notifications $notifications;

	/**
	 * Timezone.
	 *
	 * @var Zeko_Mentor_Timezone Timezone.
	 */
	private Zeko_Mentor_Timezone $timezone;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->init_hooks();
	}

	/**
	 * Load all class files.
	 */
	private function load_dependencies(): void {
		$base = ZEKO_MENTOR_PLUGIN_PATH . 'includes/';

		// Core.
		require_once $base . 'db/class-zeko-mentor-db.php';

		// Subsystems.
		require_once $base . 'public/class-zeko-mentor-public.php';
		require_once $base . 'admin/class-zeko-mentor-admin.php';
		require_once $base . 'public/class-zeko-mentor-ajax.php';
		require_once $base . 'class-zeko-mentor-ecosystem.php';
		require_once $base . 'class-zeko-mentor-emails.php';
		require_once $base . 'class-zeko-mentor-matching.php';
		require_once $base . 'class-zeko-mentor-rest.php';
		require_once $base . 'class-zeko-mentor-notifications.php';
		require_once $base . 'class-zeko-mentor-timezone.php';

		// Instantiate with shared DB layer.
		$this->db        = new Zeko_Mentor_DB();
		$this->public    = new Zeko_Mentor_Public( $this->db );
		$this->admin     = new Zeko_Mentor_Admin( $this->db );
		$this->ajax      = new Zeko_Mentor_AJAX( $this->db );
		$this->ecosystem = new Zeko_Mentor_Ecosystem( $this->db );
		$this->emails    = new Zeko_Mentor_Emails( $this->db );
		$this->matching  = new Zeko_Mentor_Matching( $this->db );
		new Zeko_Mentor_REST( $this->db );
		$this->notifications = new Zeko_Mentor_Notifications( $this->db );
		$this->timezone      = new Zeko_Mentor_Timezone( $this->db );
	}

	/**
	 * Load text domain for translations.
	 */
	private function set_locale(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'zeko-mentor', false, dirname( ZEKO_MENTOR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register core hooks.
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ), 5 );
		add_action( 'init', array( $this, 'flush_rewrites_late' ), 999 );

		// Cron hooks.
		add_action( 'zeko_mentor_session_reminders', 'zeko_mentor_send_session_reminders' );
		add_action( 'zeko_mentor_match_refresh', 'zeko_mentor_refresh_matches' );
		add_action( 'zeko_mentor_analytics_aggregate', 'zeko_mentor_aggregate_analytics' );
	}

	/**
	 * Check DB version and upgrade if needed.
	 */
	public function maybe_upgrade_db(): void {
		$installed = get_option( 'zeko_mentor_db_version', '0' );
		if ( version_compare( $installed, ZEKO_MENTOR_DB_VERSION, '<' ) ) {
			$this->db->create_tables();
			update_option( 'zeko_mentor_db_version', ZEKO_MENTOR_DB_VERSION );
		}
	}

	/**
	 * Flush rewrite rules on init priority 999.
	 */
	public function flush_rewrites_late(): void {
		if ( get_option( 'zeko_mentor_flush_rewrites' ) ) {
			flush_rewrite_rules();
			delete_option( 'zeko_mentor_flush_rewrites' );
		}
		if ( ! get_option( 'zeko_mentor_profile_routes_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'zeko_mentor_profile_routes_flushed', 1 );
		}
		$installed_version = get_option( 'zeko_mentor_plugin_version', '0' );
		if ( version_compare( $installed_version, ZEKO_MENTOR_VERSION, '<' ) ) {
			flush_rewrite_rules();
			update_option( 'zeko_mentor_plugin_version', ZEKO_MENTOR_VERSION );
		}
	}

	/**
	 * Get the DB layer.
	 */
	public function get_db(): Zeko_Mentor_DB {
		return $this->db;
	}

	/**
	 * Get the public frontend handler.
	 */
	public function get_public(): Zeko_Mentor_Public {
		return $this->public;
	}

	/**
	 * Get the admin handler.
	 */
	public function get_admin(): Zeko_Mentor_Admin {
		return $this->admin;
	}

	/**
	 * Get the AJAX handler.
	 */
	public function get_ajax(): Zeko_Mentor_AJAX {
		return $this->ajax;
	}

	/**
	 * Get the ecosystem integration handler.
	 */
	public function get_ecosystem(): Zeko_Mentor_Ecosystem {
		return $this->ecosystem;
	}

	/**
	 * Get the email notification handler.
	 */
	public function get_emails(): Zeko_Mentor_Emails {
		return $this->emails;
	}

	/**
	 * Get the timezone helper.
	 */
	public function get_timezone(): Zeko_Mentor_Timezone {
		return $this->timezone;
	}

	/**
	 * Get the matching engine.
	 */
	public function get_matching(): Zeko_Mentor_Matching {
		return $this->matching;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \LogicException When an error occurs.
	 */
	public function __wakeup() {
		throw new \LogicException( 'Cannot unserialize singleton.' );
	}
}
