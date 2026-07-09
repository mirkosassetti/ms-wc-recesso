<?php
/**
 * Art. 59 exclusion rules.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Domain;

use MS\WcRecesso\Support\Options;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Marks order lines as not eligible for withdrawal when the product (or one of
 * its categories) is flagged as excluded under art. 59, providing a visible
 * reason. Hooks the eligibility filter used by EligibilityService.
 */
final class ExclusionRules {

	/**
	 * Product meta: whether the product is excluded from withdrawal.
	 */
	public const META_EXCLUDED = '_ms_wc_recesso_excluded';

	/**
	 * Product meta: optional per-product exclusion reason.
	 */
	public const META_REASON = '_ms_wc_recesso_exclusion_reason';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'ms_wc_recesso_item_eligibility', array( $this, 'evaluate' ), 10, 4 );
	}

	/**
	 * Evaluate a line item's eligibility against the exclusion rules.
	 *
	 * @param array<string,mixed> $eligibility Current eligibility (eligible, reason).
	 * @param mixed               $item        Order line item.
	 * @param mixed               $product     Product object, or false.
	 * @param mixed               $order       The order.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $eligibility, $item, $product, $order ): array {
		if ( ! $product instanceof WC_Product ) {
			return $eligibility;
		}

		$reference = $this->reference_product( $product );
		$excluded  = false;
		$reason    = '';

		if ( 'yes' === $reference->get_meta( self::META_EXCLUDED ) ) {
			$excluded = true;
			$reason   = (string) $reference->get_meta( self::META_REASON );
		} elseif ( $this->in_excluded_category( $reference ) ) {
			$excluded = true;
		}

		if ( $excluded ) {
			$eligibility['eligible'] = false;
			$eligibility['reason']   = '' !== $reason ? $reason : $this->default_reason();
		}

		return $eligibility;
	}

	/**
	 * The product whose exclusion settings apply (parent for variations).
	 *
	 * @param WC_Product $product The line product.
	 */
	private function reference_product( WC_Product $product ): WC_Product {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof WC_Product ) {
				return $parent;
			}
		}

		return $product;
	}

	/**
	 * Whether the product belongs to an excluded category (including ancestors).
	 *
	 * @param WC_Product $product The product.
	 */
	private function in_excluded_category( WC_Product $product ): bool {
		$excluded_ids = array_map( 'absint', (array) Options::get( 'excluded_categories', array() ) );

		if ( empty( $excluded_ids ) ) {
			return false;
		}

		$term_ids = array();
		foreach ( $product->get_category_ids() as $category_id ) {
			$term_ids[] = (int) $category_id;
			foreach ( get_ancestors( (int) $category_id, 'product_cat' ) as $ancestor_id ) {
				$term_ids[] = (int) $ancestor_id;
			}
		}

		return ! empty( array_intersect( $term_ids, $excluded_ids ) );
	}

	/**
	 * Default exclusion reason from settings.
	 */
	private function default_reason(): string {
		$reason = (string) Options::get( 'default_exclusion_reason', '' );

		return '' !== $reason
			? $reason
			: __( 'Articolo escluso dal diritto di recesso ai sensi dell’art. 59 del Codice del Consumo.', 'ms-wc-recesso' );
	}
}
