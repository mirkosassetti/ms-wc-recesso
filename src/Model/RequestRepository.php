<?php
/**
 * Data-access layer for withdrawal requests.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Model;

use MS\WcRecesso\Support\Dates;
use MS\WcRecesso\Support\Schema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Custom-table gateway: interpolated identifiers are trusted names from $wpdb->prefix, WHERE clauses are built with bound params passed as an array, and per-request data is not cacheable.

/**
 * The single point of access to the requests table.
 *
 * All SQL lives here and uses prepared statements. No other class touches the
 * table directly.
 */
final class RequestRepository {

	/**
	 * Insert a new request row.
	 *
	 * @param array<string,mixed> $data Column values (partial; defaults applied).
	 * @return int New row id (0 on failure).
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$now = Dates::now_mysql();

		$row = array_merge(
			array(
				'public_uuid'          => wp_generate_uuid4(),
				'order_id'             => null,
				'order_reference'      => '',
				'customer_name'        => '',
				'customer_email'       => '',
				'items'                => null,
				'reason'               => null,
				'status'               => RequestStatus::Draft->value,
				'needs_manual_review'  => 0,
				'is_guest'             => 0,
				'verification_token'   => null,
				'token_expires'        => null,
				'declaration_snapshot' => null,
				'receipt_subject'      => null,
				'receipt_body'         => null,
				'ip_hash'              => null,
				'submitted_at'         => null,
				'confirmed_at'         => null,
				'receipt_sent_at'      => null,
				'created_at'           => $now,
				'updated_at'           => $now,
			),
			$data
		);

		$inserted = $wpdb->insert( Schema::requests_table(), $row );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing request row.
	 *
	 * @param int                 $id   Row id.
	 * @param array<string,mixed> $data Columns to change.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = Dates::now_mysql();

		$result = $wpdb->update( Schema::requests_table(), $data, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Fetch a request by id.
	 *
	 * @param int $id Row id.
	 */
	public function get( int $id ): ?WithdrawalRequest {
		global $wpdb;

		$table = Schema::requests_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ? WithdrawalRequest::from_row( $row ) : null;
	}

	/**
	 * Fetch a request by its public UUID.
	 *
	 * @param string $uuid Public UUID.
	 */
	public function get_by_uuid( string $uuid ): ?WithdrawalRequest {
		global $wpdb;

		$table = Schema::requests_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_uuid = %s", $uuid ) );

		return $row ? WithdrawalRequest::from_row( $row ) : null;
	}

	/**
	 * Find the latest open draft for a given order and email, to allow reuse
	 * instead of piling up abandoned drafts.
	 *
	 * @param int    $order_id Order id.
	 * @param string $email    Customer email.
	 */
	public function find_open_draft( int $order_id, string $email ): ?WithdrawalRequest {
		global $wpdb;

		$table = Schema::requests_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND customer_email = %s AND status = %s ORDER BY id DESC LIMIT 1",
				$order_id,
				$email,
				RequestStatus::Draft->value
			)
		);

		return $row ? WithdrawalRequest::from_row( $row ) : null;
	}

	/**
	 * Fetch a non-expired request by its hashed verification token.
	 *
	 * @param string $token_hash SHA-256 hash of the raw token.
	 */
	public function get_by_token( string $token_hash ): ?WithdrawalRequest {
		global $wpdb;

		$table = Schema::requests_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE verification_token = %s AND token_expires > %s ORDER BY id DESC LIMIT 1",
				$token_hash,
				Dates::now_mysql()
			)
		);

		return $row ? WithdrawalRequest::from_row( $row ) : null;
	}

	/**
	 * Query requests for the admin list, with optional status filter/search.
	 *
	 * @param array<string,mixed> $args status, search, orderby, order, per_page, offset.
	 * @return array<int,WithdrawalRequest>
	 */
	public function query( array $args ): array {
		global $wpdb;

		$args  = array_merge(
			array(
				'status'   => '',
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'offset'   => 0,
			),
			$args
		);
		$table = Schema::requests_table();

		list( $where, $params ) = $this->build_where( $args );

		$orderby  = $this->sanitize_orderby( (string) $args['orderby'] );
		$order    = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$params[] = (int) $args['per_page'];
		$params[] = (int) $args['offset'];

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $params )
		);

		return array_map( array( WithdrawalRequest::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Count requests matching a status filter/search.
	 *
	 * @param array<string,mixed> $args status, search.
	 */
	public function count( array $args ): int {
		global $wpdb;

		$table = Schema::requests_table();

		list( $where, $params ) = $this->build_where( $args );

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
	}

	/**
	 * Counts grouped by status (for the list-table views).
	 *
	 * @return array<string,int>
	 */
	public function status_counts(): array {
		global $wpdb;

		$table = Schema::requests_table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );
		$out   = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->status ] = (int) $row->total;
		}

		return $out;
	}

	/**
	 * Evidentiary log entries for a request, oldest first.
	 *
	 * @param int $request_id Request id.
	 * @return array<int,object>
	 */
	public function get_logs( int $request_id ): array {
		global $wpdb;

		$log  = Schema::log_table();
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$log} WHERE request_id = %d ORDER BY id ASC", $request_id )
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Permanently delete requests (and their log entries) by id.
	 *
	 * @param array<int,int> $ids Request ids.
	 * @return int Number of requests deleted.
	 */
	public function delete_many( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$table        = Schema::requests_table();
		$log          = Schema::log_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$log} WHERE request_id IN ({$placeholders})", $ids ) );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );

		return (int) $deleted;
	}

	/**
	 * Build the WHERE clause and bound params for list queries.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private function build_where( array $args ): array {
		global $wpdb;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = (string) $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND ( order_reference LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR public_uuid LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( $where, $params );
	}

	/**
	 * Whitelist the ORDER BY column.
	 *
	 * @param string $orderby Requested column.
	 */
	private function sanitize_orderby( string $orderby ): string {
		$allowed = array( 'id', 'created_at', 'confirmed_at', 'status', 'customer_name' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
	}

	/**
	 * All requests linked to an order, newest first.
	 *
	 * @param int $order_id Order id.
	 * @return array<int,WithdrawalRequest>
	 */
	public function get_by_order( int $order_id ): array {
		global $wpdb;

		$table = Schema::requests_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id DESC", $order_id )
		);

		return array_map( array( WithdrawalRequest::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}
}
