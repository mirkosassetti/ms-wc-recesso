<?php
/**
 * Front-end asset registration.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

use MS\WcRecesso\Activator;
use MS\WcRecesso\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and conditionally enqueues the minimal CSS/JS used by the flow.
 *
 * Assets load only on the withdrawal page and the My Account endpoint, and are
 * overridable/removable by themes (standard handles, low footprint).
 */
final class Assets {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets where they are needed.
	 *
	 * The stylesheet loads on the flow screens and, since the footer link is
	 * site-wide, wherever that link is shown. The script only loads on the flow
	 * screens (the footer link needs no JS).
	 */
	public function enqueue(): void {
		$is_flow = $this->is_flow_screen();

		if ( ! $is_flow && ! PlacementService::footer_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'ms-wc-recesso',
			MS_WC_RECESSO_URL . 'assets/css/frontend.css',
			array(),
			MS_WC_RECESSO_VERSION
		);

		if ( true === $is_flow ) {
			wp_enqueue_script(
				'ms-wc-recesso',
				MS_WC_RECESSO_URL . 'assets/js/frontend.js',
				array(),
				MS_WC_RECESSO_VERSION,
				true
			);
		}
	}

	/**
	 * Whether the current screen hosts the flow.
	 */
	private function is_flow_screen(): bool {
		$page_id = (int) get_option( Activator::PAGE_OPTION, 0 );

		if ( $page_id > 0 && is_page( $page_id ) ) {
			return true;
		}

		return function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( Plugin::ENDPOINT );
	}
}
