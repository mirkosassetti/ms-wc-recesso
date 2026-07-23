<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader (plugin PSR-4 + Brain Monkey + Mockery).
 * WordPress/WooCommerce functions are stubbed per-test with Brain Monkey; the
 * WC_* classes are created on demand by Mockery, so no WP install is needed.
 *
 * @package MS\WcRecesso\Tests
 */

// Plugin files guard with `defined( 'ABSPATH' ) || exit;`; satisfy it so the
// classes can be autoloaded during unit tests.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
