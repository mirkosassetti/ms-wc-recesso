<?php
/**
 * Locates and validates WooCommerce orders (HPOS-safe).
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Domain;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * All order lookups go through here, using WooCommerce CRUD APIs only so the
 * plugin stays compatible with HPOS (no direct postmeta queries).
 */
final class OrderLocator {

	/**
	 * Return an order only if it belongs to the given user.
	 *
	 * @param int $order_id Order id.
	 * @param int $user_id  Current user id.
	 */
	public function get_owned_order( int $order_id, int $user_id ): ?WC_Order {
		if ( $order_id <= 0 || $user_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		if ( (int) $order->get_customer_id() !== $user_id ) {
			return null;
		}

		return $order;
	}

	/**
	 * Locate an eligible order from a guest-supplied order number and billing
	 * email. HPOS-safe: queries via wc_get_orders, not postmeta.
	 *
	 * @param string $reference Order number as typed by the guest.
	 * @param string $email     Billing email.
	 */
	public function locate_guest( string $reference, string $email ): ?WC_Order {
		$reference = trim( $reference );
		$email     = sanitize_email( $email );

		if ( '' === $reference || ! is_email( $email ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => $this->eligible_statuses(),
				'type'          => 'shop_order',
				'limit'         => 20,
			)
		);

		if ( ! is_array( $orders ) ) {
			return null;
		}

		foreach ( $orders as $order ) {
			if ( (string) $order->get_order_number() === $reference || (string) $order->get_id() === $reference ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Orders belonging to a user that are eligible for the withdrawal flow.
	 *
	 * @param int $user_id User id.
	 * @return array<int,WC_Order>
	 */
	public function get_user_orders( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => $this->eligible_statuses(),
				'type'        => 'shop_order',
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Whether an order's status makes it eligible for withdrawal.
	 *
	 * @param WC_Order $order The order.
	 */
	public function is_status_eligible( WC_Order $order ): bool {
		return in_array( $order->get_status(), $this->eligible_statuses(), true );
	}

	/**
	 * Order statuses eligible for withdrawal.
	 *
	 * Per product decision: every status except cancelled, failed and refunded.
	 * The right of withdrawal exists from conclusion of the contract, even
	 * before delivery, so we do not restrict to completed orders.
	 *
	 * @return array<int,string>
	 */
	public function eligible_statuses(): array {
		$all      = array_keys( wc_get_order_statuses() );
		$excluded = array( 'wc-cancelled', 'wc-failed', 'wc-refunded' );

		$statuses = array_map(
			static fn( string $status ): string => 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status,
			array_diff( $all, $excluded )
		);

		/**
		 * Filter the list of order statuses eligible for withdrawal.
		 *
		 * @param array<int,string> $statuses Status slugs (without the wc- prefix).
		 */
		return apply_filters( 'ms_wc_recesso_eligible_statuses', array_values( $statuses ) );
	}
}
