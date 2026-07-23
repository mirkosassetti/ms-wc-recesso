<?php
/**
 * Tests for per-line eligibility and selection filtering.
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MS\WcRecesso\Domain\EligibilityService;
use MS\WcRecesso\Tests\TestCase;

final class EligibilityServiceTest extends TestCase {

	/**
	 * A WC_Order_Item_Product mock wrapping a product.
	 *
	 * @param int    $product_id Product id.
	 * @param string $name       Line name.
	 * @param int    $quantity   Quantity.
	 */
	private function item( int $product_id, string $name, int $quantity ) {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_id' )->andReturn( $product_id );

		$item = Mockery::mock( 'WC_Order_Item_Product' );
		$item->shouldReceive( 'get_product' )->andReturn( $product );
		$item->shouldReceive( 'get_name' )->andReturn( $name );
		$item->shouldReceive( 'get_quantity' )->andReturn( $quantity );

		return $item;
	}

	/**
	 * A WC_Order mock returning the given line items.
	 *
	 * @param array<int,mixed> $items order_item_id => item mock.
	 */
	private function order( array $items ) {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_items' )->andReturn( $items );

		return $order;
	}

	public function test_get_line_items_maps_order_lines(): void {
		$this->passthrough_filters();

		$order = $this->order( array( 10 => $this->item( 55, 'Prodotto X', 2 ) ) );
		$lines = ( new EligibilityService() )->get_line_items( $order );

		$this->assertCount( 1, $lines );
		$this->assertSame( 10, $lines[0]['order_item_id'] );
		$this->assertSame( 55, $lines[0]['product_id'] );
		$this->assertSame( 'Prodotto X', $lines[0]['name'] );
		$this->assertSame( 2, $lines[0]['quantity'] );
		$this->assertTrue( $lines[0]['eligible'] );
	}

	public function test_filter_selected_keeps_only_chosen(): void {
		$this->passthrough_filters();

		$order = $this->order(
			array(
				10 => $this->item( 55, 'A', 1 ),
				20 => $this->item( 66, 'B', 1 ),
			)
		);

		$selected = ( new EligibilityService() )->filter_selected( $order, array( 20 ) );

		$this->assertCount( 1, $selected );
		$this->assertSame( 20, $selected[0]['order_item_id'] );
	}

	public function test_filter_selected_drops_items_marked_ineligible(): void {
		// The eligibility filter (art. 59 rules) marks the line as not eligible.
		Functions\when( 'apply_filters' )->alias(
			static function ( $tag, $value ) {
				if ( is_array( $value ) ) {
					$value['eligible'] = false;
				}
				return $value;
			}
		);

		$order    = $this->order( array( 10 => $this->item( 55, 'A', 1 ) ) );
		$selected = ( new EligibilityService() )->filter_selected( $order, array( 10 ) );

		$this->assertSame( array(), $selected );
	}
}
