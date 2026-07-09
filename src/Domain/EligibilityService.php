<?php
/**
 * Computes per-item withdrawal eligibility and window status.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Domain;

use MS\WcRecesso\Support\Dates;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which order lines can be withdrawn and whether the order is still
 * within its withdrawal window.
 *
 * The art. 59 exclusion rules (personalised goods, sealed items, digital
 * content consumed, etc.) are injected in Phase 6 via the
 * `ms_wc_recesso_item_eligibility` filter. By default every line is eligible.
 */
final class EligibilityService {

	/**
	 * Build the list of order lines with their eligibility.
	 *
	 * @param WC_Order $order The order.
	 * @return array<int,array<string,mixed>> One entry per line item.
	 */
	public function get_line_items( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			$eligibility = array(
				'eligible' => true,
				'reason'   => '',
			);

			/**
			 * Filter the withdrawal eligibility of a single order line.
			 *
			 * Phase 6 (art. 59 exclusions) hooks here to mark lines as not
			 * eligible with a human-readable reason shown in the flow.
			 *
			 * @param array<string,mixed>   $eligibility Keys: eligible (bool), reason (string).
			 * @param WC_Order_Item_Product $item        The order line item.
			 * @param mixed                 $product     The product object, or false.
			 * @param WC_Order              $order       The order.
			 */
			$eligibility = apply_filters(
				'ms_wc_recesso_item_eligibility',
				$eligibility,
				$item,
				$product,
				$order
			);

			$items[] = array(
				'order_item_id' => (int) $item_id,
				'product_id'    => $product ? (int) $product->get_id() : 0,
				'name'          => $item->get_name(),
				'quantity'      => (int) $item->get_quantity(),
				'eligible'      => ! empty( $eligibility['eligible'] ),
				'reason'        => isset( $eligibility['reason'] ) ? (string) $eligibility['reason'] : '',
			);
		}

		return $items;
	}

	/**
	 * Reduce a set of chosen line-item ids to the eligible ones, returning the
	 * snapshot rows to persist with the request.
	 *
	 * @param WC_Order       $order    The order.
	 * @param array<int,int> $chosen   Chosen order_item_id values.
	 * @return array<int,array<string,mixed>> Snapshot of selected eligible items.
	 */
	public function filter_selected( WC_Order $order, array $chosen ): array {
		$chosen   = array_map( 'absint', $chosen );
		$selected = array();

		foreach ( $this->get_line_items( $order ) as $line ) {
			if ( $line['eligible'] && in_array( $line['order_item_id'], $chosen, true ) ) {
				$selected[] = $line;
			}
		}

		return $selected;
	}

	/**
	 * Withdrawal-window status for an order.
	 *
	 * @param WC_Order $order The order.
	 * @return array{within:bool,deadline:\DateTimeImmutable}
	 */
	public function window_status( WC_Order $order ): array {
		return array(
			'within'   => Dates::is_within_window( $order ),
			'deadline' => Dates::window_end( $order ),
		);
	}
}
