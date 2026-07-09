<?php
/**
 * Persistent placements of the withdrawal link (footer, orders, view order).
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

use MS\WcRecesso\Activator;
use MS\WcRecesso\Domain\OrderLocator;
use MS\WcRecesso\Plugin;
use MS\WcRecesso\Support\Options;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the always-available "Recedere dal contratto qui" link in the
 * configured locations. The link is shown continuously; being outside the
 * withdrawal window is handled inside the flow, never by hiding the link.
 */
final class PlacementService {

	/**
	 * Order locator (for status-eligibility checks).
	 *
	 * @var OrderLocator
	 */
	private OrderLocator $locator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->locator = new OrderLocator();
	}

	/**
	 * Register hooks according to the active placements.
	 */
	public function register(): void {
		add_shortcode( 'ms_recesso_link', array( $this, 'link_shortcode' ) );

		if ( self::footer_enabled() ) {
			add_action( 'wp_footer', array( $this, 'render_footer_link' ) );
		}

		if ( Options::get_bool( 'placement_orders_list', true ) ) {
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_order_action' ), 10, 2 );
		}

		if ( Options::get_bool( 'placement_view_order', true ) ) {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_view_order_link' ) );
		}
	}

	/**
	 * Whether the site-wide footer link should be shown.
	 *
	 * Controlled by the `placement_footer` setting (default on) and overridable
	 * via the `ms_wc_recesso_footer_link_enabled` filter.
	 */
	public static function footer_enabled(): bool {
		/**
		 * Filter whether the persistent footer withdrawal link is shown.
		 *
		 * @param bool $enabled Current enabled state.
		 */
		return (bool) apply_filters( 'ms_wc_recesso_footer_link_enabled', Options::get_bool( 'placement_footer', true ) );
	}

	/**
	 * Footer link to the standalone withdrawal page.
	 */
	public function render_footer_link(): void {
		$url = $this->page_url();

		if ( '' === $url ) {
			return;
		}

		printf(
			'<div class="ms-recesso-footer-link"><a href="%1$s">%2$s</a></div>',
			esc_url( $url ),
			esc_html( $this->label() )
		);
	}

	/**
	 * `[ms_recesso_link]` shortcode: a bare link for manual placement.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes (unused).
	 */
	public function link_shortcode( $atts = array() ): string {
		$url = $this->page_url();

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="ms-recesso-link" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $this->label() )
		);
	}

	/**
	 * Add a withdrawal action to each row of the My Account orders table.
	 *
	 * @param array<string,array<string,string>> $actions Existing actions.
	 * @param WC_Order                           $order   The order.
	 * @return array<string,array<string,string>>
	 */
	public function add_order_action( array $actions, WC_Order $order ): array {
		if ( ! $this->locator->is_status_eligible( $order ) ) {
			return $actions;
		}

		$actions['ms_recesso'] = array(
			'url'  => $this->order_url( $order->get_id() ),
			'name' => $this->label(),
		);

		return $actions;
	}

	/**
	 * Render the withdrawal link under the order details on the view-order page.
	 *
	 * @param WC_Order $order The order.
	 */
	public function render_view_order_link( WC_Order $order ): void {
		if ( ! is_account_page() || ! $this->locator->is_status_eligible( $order ) ) {
			return;
		}

		printf(
			'<p class="ms-recesso-view-order-link"><a href="%1$s">%2$s</a></p>',
			esc_url( $this->order_url( $order->get_id() ) ),
			esc_html( $this->label() )
		);
	}

	/**
	 * Configured button label (law-compliant default).
	 */
	private function label(): string {
		$label = (string) Options::get( 'button_label', 'Recedere dal contratto qui' );

		return '' !== $label ? $label : __( 'Recedere dal contratto qui', 'ms-wc-recesso' );
	}

	/**
	 * URL of the standalone withdrawal page.
	 */
	private function page_url(): string {
		$page_id = (int) get_option( Activator::PAGE_OPTION, 0 );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : '';

		return $url ? $url : '';
	}

	/**
	 * URL of the My Account endpoint for a specific order.
	 *
	 * @param int $order_id Order id.
	 */
	private function order_url( int $order_id ): string {
		return add_query_arg( 'order', $order_id, wc_get_account_endpoint_url( Plugin::ENDPOINT ) );
	}
}
