<?php
/**
 * My Account endpoint for the withdrawal flow.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

use MS\WcRecesso\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `recesso` endpoint inside WooCommerce My Account: menu item,
 * page title and content, driven by the shared FlowController.
 *
 * Registering the query var through `woocommerce_get_query_vars` is what makes
 * WooCommerce render our content in the account area (and register the rewrite
 * endpoint itself).
 */
final class MyAccountEndpoint {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . Plugin::ENDPOINT . '_title', array( $this, 'endpoint_title' ) );
		add_action( 'woocommerce_account_' . Plugin::ENDPOINT . '_endpoint', array( $this, 'render_content' ) );
	}

	/**
	 * Register the endpoint query var with WooCommerce.
	 *
	 * @param array<string,string> $vars WooCommerce query vars.
	 * @return array<string,string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[ Plugin::ENDPOINT ] = Plugin::ENDPOINT;

		return $vars;
	}

	/**
	 * Add the "Recesso" item to the My Account menu, after "orders".
	 *
	 * @param array<string,string> $items Existing menu items.
	 * @return array<string,string>
	 */
	public function add_menu_item( array $items ): array {
		$new = array();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ Plugin::ENDPOINT ] = __( 'Recesso', 'ms-wc-recesso' );
			}
		}

		// Fallback if there is no "orders" item to anchor to.
		if ( ! isset( $new[ Plugin::ENDPOINT ] ) ) {
			$new[ Plugin::ENDPOINT ] = __( 'Recesso', 'ms-wc-recesso' );
		}

		return $new;
	}

	/**
	 * Title of the endpoint page.
	 *
	 * @param string $title Current title.
	 */
	public function endpoint_title( string $title ): string {
		return __( 'Recesso dal contratto', 'ms-wc-recesso' );
	}

	/**
	 * Render the endpoint content.
	 */
	public function render_content(): void {
		echo ( new FlowController() )->render( 'myaccount' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Controller returns fully escaped template markup.
	}
}
