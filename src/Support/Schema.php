<?php
/**
 * Database schema installation and versioned migrations.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's custom tables via dbDelta.
 *
 * Two tables are used:
 *  - requests: one row per withdrawal request (mutable status, but with an
 *    immutable declaration snapshot frozen at confirmation time).
 *  - log: append-only evidentiary trail of every state transition.
 */
final class Schema {

	/**
	 * Option key holding the installed schema version.
	 */
	private const VERSION_OPTION = 'ms_wc_recesso_db_version';

	/**
	 * Return the requests table name (with prefix).
	 */
	public static function requests_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'ms_wc_recesso_requests';
	}

	/**
	 * Return the log table name (with prefix).
	 */
	public static function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'ms_wc_recesso_log';
	}

	/**
	 * Install or upgrade the schema. Idempotent thanks to dbDelta.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$requests        = self::requests_table();
		$log             = self::log_table();

		// Note: dbDelta is whitespace-sensitive (two spaces after PRIMARY KEY,
		// one field/definition per line, lowercase types). Do not reformat.
		$requests_sql = "CREATE TABLE {$requests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_uuid char(36) NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			order_reference varchar(100) NOT NULL DEFAULT '',
			customer_name varchar(255) NOT NULL DEFAULT '',
			customer_email varchar(191) NOT NULL DEFAULT '',
			items longtext DEFAULT NULL,
			reason text DEFAULT NULL,
			status varchar(30) NOT NULL DEFAULT 'draft',
			needs_manual_review tinyint(1) NOT NULL DEFAULT 0,
			is_guest tinyint(1) NOT NULL DEFAULT 0,
			verification_token char(64) DEFAULT NULL,
			token_expires datetime DEFAULT NULL,
			declaration_snapshot longtext DEFAULT NULL,
			receipt_subject varchar(255) DEFAULT NULL,
			receipt_body longtext DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			submitted_at datetime DEFAULT NULL,
			confirmed_at datetime DEFAULT NULL,
			receipt_sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_uuid (public_uuid),
			KEY order_id (order_id),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY verification_token (verification_token)
		) {$charset_collate};";

		$log_sql = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id bigint(20) unsigned NOT NULL,
			event varchar(50) NOT NULL,
			from_status varchar(30) DEFAULT NULL,
			to_status varchar(30) DEFAULT NULL,
			actor_type varchar(20) NOT NULL DEFAULT 'system',
			actor_id bigint(20) unsigned DEFAULT NULL,
			note text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id),
			KEY event (event)
		) {$charset_collate};";

		dbDelta( $requests_sql );
		dbDelta( $log_sql );

		update_option( self::VERSION_OPTION, MS_WC_RECESSO_DB_VERSION );
	}

	/**
	 * Run install() only when the stored schema version differs.
	 *
	 * Called on plugins_loaded so schema drift (e.g. after a plugin file
	 * update without re-activation) is healed automatically.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::VERSION_OPTION );

		if ( MS_WC_RECESSO_DB_VERSION === $installed ) {
			return;
		}

		self::install();
	}
}
