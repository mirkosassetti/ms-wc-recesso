<?php
/**
 * Base test case: Brain Monkey lifecycle + common WP function stubs.
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use MS\WcRecesso\Support\Options;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Always-passthrough helpers used across the domain code.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_n' )->alias(
			static fn( $single, $plural, $number ) => 1 === (int) $number ? $single : $plural
		);
		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = array() ) => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );

		Options::flush_cache();
	}

	protected function tearDown(): void {
		Options::flush_cache();
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Stub the plugin settings option with the given (partial) values.
	 *
	 * @param array<string,mixed> $settings Settings to return for the plugin option.
	 */
	protected function set_settings( array $settings ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $settings ) {
				return Options::OPTION === $name ? $settings : $default;
			}
		);
		Options::flush_cache();
	}

	/**
	 * Make apply_filters a passthrough (returns the filtered value unchanged).
	 */
	protected function passthrough_filters(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}
}
