<?php
/**
 * Tests for art. 59 exclusion rules.
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MS\WcRecesso\Domain\ExclusionRules;
use MS\WcRecesso\Tests\TestCase;

final class ExclusionRulesTest extends TestCase {

	/**
	 * A WC_Product mock.
	 *
	 * @param array<string,string> $meta   Meta key => value.
	 * @param array<int,int>       $cats   Category ids.
	 * @param string               $type   Product type.
	 * @param int                  $parent Parent id (for variations).
	 */
	private function product( array $meta, array $cats, string $type = 'simple', int $parent = 0 ) {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_type' )->with( 'variation' )->andReturn( 'variation' === $type );
		$product->shouldReceive( 'get_meta' )->andReturnUsing(
			static fn( $key ) => $meta[ $key ] ?? ''
		);
		$product->shouldReceive( 'get_category_ids' )->andReturn( $cats );
		$product->shouldReceive( 'get_parent_id' )->andReturn( $parent );

		return $product;
	}

	private function default_eligibility(): array {
		return array(
			'eligible' => true,
			'reason'   => '',
		);
	}

	public function test_product_flagged_excluded_uses_custom_reason(): void {
		$this->set_settings(
			array(
				'excluded_categories'      => array(),
				'default_exclusion_reason' => 'Motivo predefinito',
			)
		);

		$product = $this->product(
			array(
				'_ms_wc_recesso_excluded'         => 'yes',
				'_ms_wc_recesso_exclusion_reason' => 'Bene sigillato',
			),
			array()
		);

		$result = ( new ExclusionRules() )->evaluate( $this->default_eligibility(), null, $product, null );

		$this->assertFalse( $result['eligible'] );
		$this->assertSame( 'Bene sigillato', $result['reason'] );
	}

	public function test_product_in_excluded_category_uses_default_reason(): void {
		$this->set_settings(
			array(
				'excluded_categories'      => array( 5 ),
				'default_exclusion_reason' => 'Motivo predefinito',
			)
		);
		Functions\when( 'get_ancestors' )->alias(
			static fn( $id, $tax ) => 10 === $id ? array( 5 ) : array()
		);

		$product = $this->product( array( '_ms_wc_recesso_excluded' => 'no' ), array( 10 ) );

		$result = ( new ExclusionRules() )->evaluate( $this->default_eligibility(), null, $product, null );

		$this->assertFalse( $result['eligible'] );
		$this->assertSame( 'Motivo predefinito', $result['reason'] );
	}

	public function test_product_not_excluded_stays_eligible(): void {
		$this->set_settings(
			array(
				'excluded_categories'      => array( 5 ),
				'default_exclusion_reason' => 'Motivo predefinito',
			)
		);
		Functions\when( 'get_ancestors' )->justReturn( array() );

		$product = $this->product( array( '_ms_wc_recesso_excluded' => 'no' ), array( 99 ) );

		$result = ( new ExclusionRules() )->evaluate( $this->default_eligibility(), null, $product, null );

		$this->assertTrue( $result['eligible'] );
	}

	public function test_variation_inherits_parent_exclusion(): void {
		$this->set_settings(
			array(
				'excluded_categories'      => array(),
				'default_exclusion_reason' => 'Motivo predefinito',
			)
		);

		$parent = $this->product(
			array(
				'_ms_wc_recesso_excluded'         => 'yes',
				'_ms_wc_recesso_exclusion_reason' => 'Motivo del padre',
			),
			array()
		);
		$variation = $this->product( array( '_ms_wc_recesso_excluded' => 'no' ), array(), 'variation', 77 );
		Functions\when( 'wc_get_product' )->justReturn( $parent );

		$result = ( new ExclusionRules() )->evaluate( $this->default_eligibility(), null, $variation, null );

		$this->assertFalse( $result['eligible'] );
		$this->assertSame( 'Motivo del padre', $result['reason'] );
	}

	public function test_non_product_is_left_untouched(): void {
		$input  = $this->default_eligibility();
		$result = ( new ExclusionRules() )->evaluate( $input, null, false, null );

		$this->assertSame( $input, $result );
	}
}
