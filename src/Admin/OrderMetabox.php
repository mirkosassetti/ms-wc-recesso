<?php
/**
 * Order edit-screen metabox listing linked withdrawal requests.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Admin;

use MS\WcRecesso\Model\RequestRepository;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a metabox to the order edit screen (both legacy post table and HPOS)
 * showing the withdrawal requests attached to the order, with links to detail.
 */
final class OrderMetabox {

	/**
	 * Repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new RequestRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
	}

	/**
	 * Register the metabox on both order screen ids.
	 */
	public function add(): void {
		$screens = array( 'shop_order' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		add_meta_box(
			'ms_wc_recesso_order',
			__( 'Recesso 54-bis', 'ms-wc-recesso' ),
			array( $this, 'render' ),
			$screens,
			'side',
			'default'
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post|WC_Order $post_or_order Screen object (post on legacy, order on HPOS).
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$requests = $this->repository->get_by_order( $order->get_id() );

		if ( empty( $requests ) ) {
			echo '<p>' . esc_html__( 'Nessuna richiesta di recesso per questo ordine.', 'ms-wc-recesso' ) . '</p>';
			return;
		}

		echo '<ul style="margin:0;">';
		foreach ( $requests as $request ) {
			$url = add_query_arg(
				array(
					'page'    => RequestsListTable::PAGE,
					'request' => $request->public_uuid,
				),
				admin_url( 'admin.php' )
			);

			echo '<li style="margin-bottom:6px;">';
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $request->status_enum()->label() ) . '</a> — ';
			echo esc_html( $request->public_uuid );
			if ( $request->needs_manual_review ) {
				echo ' <strong style="color:#8a6d00;">' . esc_html__( '(da verificare)', 'ms-wc-recesso' ) . '</strong>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
