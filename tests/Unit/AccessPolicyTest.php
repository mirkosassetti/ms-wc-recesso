<?php
/**
 * Tests for role-based access exclusion (B2B).
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MS\WcRecesso\Domain\AccessPolicy;
use MS\WcRecesso\Tests\TestCase;

final class AccessPolicyTest extends TestCase {

	/**
	 * Stub get_userdata with an id => roles map.
	 *
	 * @param array<int,array<int,string>> $map User id to roles.
	 */
	private function stub_users( array $map ): void {
		Functions\when( 'get_userdata' )->alias(
			static function ( $id ) use ( $map ) {
				return isset( $map[ $id ] ) ? (object) array( 'roles' => $map[ $id ] ) : false;
			}
		);
	}

	public function test_user_excluded_when_role_matches(): void {
		$this->set_settings( array( 'excluded_roles' => array( 'wholesale' ) ) );
		$this->passthrough_filters();
		$this->stub_users(
			array(
				5 => array( 'customer', 'wholesale' ),
				6 => array( 'customer' ),
			)
		);

		$this->assertTrue( AccessPolicy::user_excluded( 5 ) );
		$this->assertFalse( AccessPolicy::user_excluded( 6 ) );
	}

	public function test_not_excluded_when_no_roles_configured(): void {
		$this->set_settings( array( 'excluded_roles' => array() ) );
		$this->passthrough_filters();

		$this->assertFalse( AccessPolicy::user_excluded( 5 ) );
	}

	public function test_order_customer_excluded(): void {
		$this->set_settings( array( 'excluded_roles' => array( 'wholesale' ) ) );
		$this->passthrough_filters();
		$this->stub_users( array( 9 => array( 'wholesale' ) ) );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 9 );

		$this->assertTrue( AccessPolicy::order_customer_excluded( $order ) );
	}

	public function test_guest_order_not_excluded(): void {
		$this->set_settings( array( 'excluded_roles' => array( 'wholesale' ) ) );
		$this->passthrough_filters();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 0 );

		$this->assertFalse( AccessPolicy::order_customer_excluded( $order ) );
	}

	public function test_current_user_excluded_when_logged_in(): void {
		$this->set_settings( array( 'excluded_roles' => array( 'wholesale' ) ) );
		$this->passthrough_filters();
		$this->stub_users( array( 5 => array( 'wholesale' ) ) );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 5 );

		$this->assertTrue( AccessPolicy::current_user_excluded() );
	}

	public function test_current_user_not_excluded_when_logged_out(): void {
		$this->set_settings( array( 'excluded_roles' => array( 'wholesale' ) ) );
		$this->passthrough_filters();
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$this->assertFalse( AccessPolicy::current_user_excluded() );
	}
}
