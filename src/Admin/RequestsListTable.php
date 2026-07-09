<?php
/**
 * Admin list table for withdrawal requests.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Admin;

use MS\WcRecesso\Model\RequestRepository;
use MS\WcRecesso\Model\RequestStatus;
use MS\WcRecesso\Model\WithdrawalRequest;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists withdrawal requests with status views, search and pagination.
 */
final class RequestsListTable extends \WP_List_Table {

	/**
	 * Admin page slug the table lives on.
	 */
	public const PAGE = 'ms-wc-recesso-requests';

	/**
	 * Repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param RequestRepository $repository Data-access layer.
	 */
	public function __construct( RequestRepository $repository ) {
		$this->repository = $repository;

		parent::__construct(
			array(
				'singular' => 'ms_wc_recesso_request',
				'plural'   => 'ms_wc_recesso_requests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'reference'  => __( 'Richiesta', 'ms-wc-recesso' ),
			'customer'   => __( 'Cliente', 'ms-wc-recesso' ),
			'status'     => __( 'Stato', 'ms-wc-recesso' ),
			'review'     => __( 'Verifica', 'ms-wc-recesso' ),
			'order'      => __( 'Ordine', 'ms-wc-recesso' ),
			'created_at' => __( 'Creata', 'ms-wc-recesso' ),
		);
	}

	/**
	 * Current status filter from the request.
	 */
	private function current_status(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter via GET link.
		return isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	}

	/**
	 * Current search term from the request.
	 */
	private function current_search(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search via GET.
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	/**
	 * Status filter views with counts.
	 *
	 * @return array<string,string>
	 */
	public function get_views(): array {
		$counts  = $this->repository->status_counts();
		$total   = array_sum( $counts );
		$current = $this->current_status();
		$base    = admin_url( 'admin.php?page=' . self::PAGE );

		$views = array();

		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( $base ),
			'' === $current ? ' class="current"' : '',
			esc_html__( 'Tutte', 'ms-wc-recesso' ),
			(int) $total
		);

		foreach ( RequestStatus::cases() as $status ) {
			$count = $counts[ $status->value ] ?? 0;
			if ( 0 === $count ) {
				continue;
			}

			$views[ $status->value ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', $status->value, $base ) ),
				$current === $status->value ? ' class="current"' : '',
				esc_html( $status->label() ),
				(int) $count
			);
		}

		return $views;
	}

	/**
	 * Load items.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$status   = $this->current_status();
		$search   = $this->current_search();

		$filter = array(
			'status' => $status,
			'search' => $search,
		);

		$this->items = $this->repository->query(
			array_merge(
				$filter,
				array(
					'per_page' => $per_page,
					'offset'   => ( $paged - 1 ) * $per_page,
				)
			)
		);

		$total = $this->repository->count( $filter );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * Reference column with a link to the detail screen.
	 *
	 * @param WithdrawalRequest $item Request.
	 */
	public function column_reference( WithdrawalRequest $item ): string {
		$url = add_query_arg(
			array(
				'page'    => self::PAGE,
				'request' => $item->public_uuid,
			),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $url ),
			esc_html( '' !== $item->order_reference ? $item->order_reference : $item->public_uuid )
		);
	}

	/**
	 * Default column rendering.
	 *
	 * @param WithdrawalRequest $item        Request.
	 * @param string            $column_name Column key.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'customer':
				return esc_html( $item->customer_name ) . '<br /><span class="description">' . esc_html( $item->customer_email ) . '</span>';

			case 'status':
				return esc_html( $item->status_enum()->label() );

			case 'review':
				return $item->needs_manual_review
					? '<span style="color:#8a6d00;">' . esc_html__( 'Da verificare', 'ms-wc-recesso' ) . '</span>'
					: '&mdash;';

			case 'order':
				if ( null === $item->order_id ) {
					return '&mdash;';
				}
				$order = wc_get_order( $item->order_id );
				if ( ! $order ) {
					return esc_html( (string) $item->order_id );
				}
				return sprintf(
					'<a href="%s">#%s</a>',
					esc_url( $order->get_edit_order_url() ),
					esc_html( $order->get_order_number() )
				);

			case 'created_at':
				return esc_html( $this->format_datetime( $item->created_at ) );

			default:
				return '&mdash;';
		}
	}

	/**
	 * Message when there are no items.
	 */
	public function no_items(): void {
		esc_html_e( 'Nessuna richiesta di recesso.', 'ms-wc-recesso' );
	}

	/**
	 * Format a stored UTC datetime for admin display in site time.
	 *
	 * @param string $utc UTC datetime string.
	 */
	private function format_datetime( string $utc ): string {
		$ts = strtotime( $utc . ' UTC' );

		return false === $ts ? $utc : wp_date( 'd/m/Y H:i', $ts );
	}
}
