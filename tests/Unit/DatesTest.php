<?php
/**
 * Tests for the withdrawal-window computation.
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests\Unit;

use DateTimeImmutable;
use Mockery;
use MS\WcRecesso\Support\Dates;
use MS\WcRecesso\Tests\TestCase;

final class DatesTest extends TestCase {

	private const DELTAS = array(
		'window_days'           => 14,
		'completion_delta_days' => 2,
		'creation_delta_days'   => 4,
	);

	/**
	 * A WC_DateTime mock returning the timestamp of the given UTC datetime.
	 *
	 * @param string $utc UTC datetime string.
	 */
	private function wc_datetime( string $utc ) {
		$date = Mockery::mock( 'WC_DateTime' );
		$date->shouldReceive( 'getTimestamp' )->andReturn( strtotime( $utc . ' UTC' ) );

		return $date;
	}

	public function test_window_end_uses_completion_date_plus_deltas(): void {
		$this->set_settings( self::DELTAS );
		$this->passthrough_filters();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_date_completed' )->andReturn( $this->wc_datetime( '2026-06-01 10:00:00' ) );
		$order->shouldReceive( 'get_date_created' )->andReturn( null );

		$end = Dates::window_end( $order );

		$this->assertInstanceOf( DateTimeImmutable::class, $end );
		// 2026-06-01 + (2 + 14) days.
		$this->assertSame( '2026-06-17', $end->format( 'Y-m-d' ) );
	}

	public function test_window_end_falls_back_to_creation_date(): void {
		$this->set_settings( self::DELTAS );
		$this->passthrough_filters();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_date_completed' )->andReturn( null );
		$order->shouldReceive( 'get_date_created' )->andReturn( $this->wc_datetime( '2026-06-01 10:00:00' ) );

		$end = Dates::window_end( $order );

		// 2026-06-01 + (4 + 14) days.
		$this->assertSame( '2026-06-19', $end->format( 'Y-m-d' ) );
	}

	public function test_is_within_window_false_for_old_order(): void {
		$this->set_settings( self::DELTAS );
		$this->passthrough_filters();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_date_completed' )->andReturn(
			$this->wc_datetime( gmdate( 'Y-m-d H:i:s', time() - 120 * 86400 ) )
		);
		$order->shouldReceive( 'get_date_created' )->andReturn( null );

		$this->assertFalse( Dates::is_within_window( $order ) );
	}

	public function test_is_within_window_true_for_recent_order(): void {
		$this->set_settings( self::DELTAS );
		$this->passthrough_filters();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_date_completed' )->andReturn(
			$this->wc_datetime( gmdate( 'Y-m-d H:i:s', time() ) )
		);
		$order->shouldReceive( 'get_date_created' )->andReturn( null );

		$this->assertTrue( Dates::is_within_window( $order ) );
	}
}
