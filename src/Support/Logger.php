<?php
/**
 * Evidentiary log writer.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Append-only audit trail on a custom table; caching is not applicable.

/**
 * Append-only writer for the request state-transition log.
 *
 * Every meaningful event in a request's lifecycle is recorded here with a UTC
 * timestamp, forming the evidentiary trail required by the withdrawal law.
 */
final class Logger {

	/**
	 * Record an event in the log table.
	 *
	 * @param int         $request_id  Related request id.
	 * @param string      $event       Event slug (e.g. created, confirmed, receipt_sent).
	 * @param string|null $from_status Previous status, when it is a transition.
	 * @param string|null $to_status   New status, when it is a transition.
	 * @param string      $actor_type  Who triggered it: customer|guest|admin|system.
	 * @param int|null    $actor_id    User id, when an authenticated user acted.
	 * @param string|null $note        Optional free-text note.
	 */
	public static function log(
		int $request_id,
		string $event,
		?string $from_status = null,
		?string $to_status = null,
		string $actor_type = 'system',
		?int $actor_id = null,
		?string $note = null
	): void {
		global $wpdb;

		$wpdb->insert(
			Schema::log_table(),
			array(
				'request_id'  => $request_id,
				'event'       => $event,
				'from_status' => $from_status,
				'to_status'   => $to_status,
				'actor_type'  => $actor_type,
				'actor_id'    => $actor_id,
				'note'        => $note,
				'created_at'  => Dates::now_mysql(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		/**
		 * Fires after an event has been written to the evidentiary log.
		 *
		 * @param int    $request_id Related request id.
		 * @param string $event      Event slug.
		 */
		do_action( 'ms_wc_recesso_logged', $request_id, $event );
	}
}
