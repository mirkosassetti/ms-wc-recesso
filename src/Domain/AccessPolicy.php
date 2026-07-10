<?php
/**
 * Access policy: which users/roles may exercise withdrawal.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Domain;

use MS\WcRecesso\Support\Options;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * The right of withdrawal is a B2C protection. Shops can mark certain user
 * roles (e.g. a B2B/wholesale role) as excluded, disabling the function for
 * those customers everywhere — logged-in flow, placements and guest flow.
 */
final class AccessPolicy {

	/**
	 * Whether a given user has any excluded role.
	 *
	 * @param int $user_id User id.
	 */
	public static function user_excluded( int $user_id ): bool {
		$excluded    = array_map( 'strval', (array) Options::get( 'excluded_roles', array() ) );
		$is_excluded = false;

		if ( $user_id > 0 && ! empty( $excluded ) ) {
			$user        = get_userdata( $user_id );
			$roles       = $user ? (array) $user->roles : array();
			$is_excluded = ! empty( array_intersect( $roles, $excluded ) );
		}

		/**
		 * Filter whether a user is excluded from the withdrawal function.
		 *
		 * @param bool $is_excluded Current decision.
		 * @param int  $user_id     User id.
		 */
		return (bool) apply_filters( 'ms_wc_recesso_user_excluded', $is_excluded, $user_id );
	}

	/**
	 * Whether the currently logged-in user is excluded.
	 */
	public static function current_user_excluded(): bool {
		return is_user_logged_in() && self::user_excluded( get_current_user_id() );
	}

	/**
	 * Whether an order's registered customer has an excluded role.
	 *
	 * @param WC_Order $order The order.
	 */
	public static function order_customer_excluded( WC_Order $order ): bool {
		$customer_id = (int) $order->get_customer_id();

		return $customer_id > 0 && self::user_excluded( $customer_id );
	}
}
