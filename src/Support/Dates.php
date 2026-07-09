<?php
/**
 * Date helpers: UTC handling and withdrawal-window computation.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

use DateTimeImmutable;
use DateTimeZone;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Centralises all temporal logic.
 *
 * Every timestamp the plugin persists is stored in UTC. The legally relevant
 * moment (confirmation) is generated here, server-side, at confirmation time.
 */
final class Dates {

	/**
	 * MySQL datetime format.
	 */
	public const MYSQL = 'Y-m-d H:i:s';

	/**
	 * Current time in UTC.
	 */
	public static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Current UTC timestamp as a MySQL datetime string (for DB writes).
	 */
	public static function now_mysql(): string {
		return self::now()->format( self::MYSQL );
	}

	/**
	 * Format a DateTimeImmutable as a UTC MySQL datetime string.
	 *
	 * @param DateTimeImmutable $date Date to format.
	 */
	public static function to_mysql( DateTimeImmutable $date ): string {
		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::MYSQL );
	}

	/**
	 * Compute the end of the withdrawal window for an order (UTC).
	 *
	 * Algorithm (confirmed with product owner):
	 *  - Base date = order completion date when the order is completed,
	 *    otherwise the order creation date.
	 *  - A delivery-estimate delta is added to the base date. The delta is
	 *    configurable and differs per base: creation_delta_days (default 4)
	 *    when falling back to creation, completion_delta_days (default 2)
	 *    when the order is completed.
	 *  - The withdrawal window (window_days, default 14) is added to that
	 *    estimated delivery date.
	 *
	 * The result is advisory: being past the window never blocks a request
	 * (it only flags it for manual review). See requirements 1 and 7.
	 *
	 * @param WC_Order $order The order.
	 * @return DateTimeImmutable Window end, in UTC.
	 */
	public static function window_end( WC_Order $order ): DateTimeImmutable {
		$completed = $order->get_date_completed();

		if ( $completed instanceof \WC_DateTime ) {
			$base       = self::from_wc_datetime( $completed );
			$delta_days = Options::get_int( 'completion_delta_days', 2 );
		} else {
			$created    = $order->get_date_created();
			$base       = $created instanceof \WC_DateTime
				? self::from_wc_datetime( $created )
				: self::now();
			$delta_days = Options::get_int( 'creation_delta_days', 4 );
		}

		$window_days = Options::get_int( 'window_days', 14 );

		$end = $base->modify( sprintf( '+%d days', $delta_days + $window_days ) );

		/**
		 * Filter the computed withdrawal-window end date.
		 *
		 * Lets integrations override the deadline, e.g. injecting a real
		 * delivery date from a shipment-tracking plugin.
		 *
		 * @param DateTimeImmutable $end   Computed window end (UTC).
		 * @param WC_Order          $order The order.
		 */
		return apply_filters( 'ms_wc_recesso_window_end', $end, $order );
	}

	/**
	 * Whether an order is still within its withdrawal window right now.
	 *
	 * @param WC_Order $order The order.
	 */
	public static function is_within_window( WC_Order $order ): bool {
		return self::now() <= self::window_end( $order );
	}

	/**
	 * Convert a WC_DateTime to a UTC DateTimeImmutable.
	 *
	 * WC_DateTime->getTimestamp() already returns a correct UTC epoch.
	 *
	 * @param \WC_DateTime $date Source WooCommerce date.
	 */
	private static function from_wc_datetime( \WC_DateTime $date ): DateTimeImmutable {
		return ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->setTimestamp( $date->getTimestamp() );
	}
}
