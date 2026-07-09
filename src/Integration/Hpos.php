<?php
/**
 * WooCommerce High-Performance Order Storage (HPOS) compatibility.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Declares the plugin compatible with custom order tables (HPOS).
 *
 * The plugin never queries order data through postmeta; every order access
 * goes through WooCommerce CRUD APIs, so we can safely declare compatibility.
 */
final class Hpos {

	/**
	 * Register the compatibility declaration.
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
	}

	/**
	 * Declare compatibility with the custom order tables feature.
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', MS_WC_RECESSO_FILE, true );
	}
}
