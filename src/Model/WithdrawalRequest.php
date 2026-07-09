<?php
/**
 * Withdrawal request data object.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable read model mapping one row of the requests table.
 *
 * Writes never happen through this object: it is a snapshot returned by the
 * repository. Mutations go back through RequestRepository by id.
 */
final class WithdrawalRequest {

	/**
	 * Constructor.
	 *
	 * @param int         $id                  Row id.
	 * @param string      $public_uuid         Public reference used in URLs.
	 * @param int|null    $order_id            Matched WooCommerce order id, if any.
	 * @param string      $order_reference     Order number as typed by the customer.
	 * @param string      $customer_name       Customer name.
	 * @param string      $customer_email      Receipt email.
	 * @param string|null $items               JSON-encoded selected items.
	 * @param string|null $reason              Optional reason.
	 * @param string      $status              Current status value.
	 * @param bool        $needs_manual_review Manual-review flag.
	 * @param bool        $is_guest            Whether it came from the guest flow.
	 * @param string|null $declaration_snapshot Frozen declaration content (JSON).
	 * @param string|null $submitted_at        UTC datetime the draft was submitted.
	 * @param string|null $confirmed_at        UTC datetime of the binding confirmation.
	 * @param string|null $receipt_sent_at     UTC datetime the receipt was sent.
	 * @param string      $created_at          UTC creation datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $public_uuid,
		public readonly ?int $order_id,
		public readonly string $order_reference,
		public readonly string $customer_name,
		public readonly string $customer_email,
		public readonly ?string $items,
		public readonly ?string $reason,
		public readonly string $status,
		public readonly bool $needs_manual_review,
		public readonly bool $is_guest,
		public readonly ?string $declaration_snapshot,
		public readonly ?string $submitted_at,
		public readonly ?string $confirmed_at,
		public readonly ?string $receipt_sent_at,
		public readonly string $created_at
	) {}

	/**
	 * Build a sample instance for rendering (e.g. the WooCommerce email
	 * preview, which passes a dummy order object instead of a real request).
	 */
	public static function sample(): self {
		$now = gmdate( 'Y-m-d H:i:s' );

		return new self(
			0,
			'00000000-0000-0000-0000-000000000000',
			123,
			'#12345',
			'Mario Rossi',
			'mario.rossi@example.com',
			(string) wp_json_encode(
				array(
					array(
						'name'     => __( 'Prodotto di esempio', 'ms-wc-recesso' ),
						'quantity' => 1,
					),
				)
			),
			__( 'Esempio di motivo del reso', 'ms-wc-recesso' ),
			RequestStatus::Confirmed->value,
			false,
			false,
			null,
			$now,
			$now,
			null,
			$now
		);
	}

	/**
	 * Build an instance from a raw DB row object.
	 *
	 * @param object $row Row from $wpdb.
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->public_uuid,
			null === $row->order_id ? null : (int) $row->order_id,
			(string) $row->order_reference,
			(string) $row->customer_name,
			(string) $row->customer_email,
			null === $row->items ? null : (string) $row->items,
			null === $row->reason ? null : (string) $row->reason,
			(string) $row->status,
			(bool) $row->needs_manual_review,
			(bool) $row->is_guest,
			null === $row->declaration_snapshot ? null : (string) $row->declaration_snapshot,
			null === $row->submitted_at ? null : (string) $row->submitted_at,
			null === $row->confirmed_at ? null : (string) $row->confirmed_at,
			null === $row->receipt_sent_at ? null : (string) $row->receipt_sent_at,
			(string) $row->created_at
		);
	}

	/**
	 * Decoded selected items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function items(): array {
		if ( empty( $this->items ) ) {
			return array();
		}

		$decoded = json_decode( $this->items, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Decoded declaration snapshot (available once confirmed).
	 *
	 * @return array<string,mixed>
	 */
	public function declaration(): array {
		if ( empty( $this->declaration_snapshot ) ) {
			return array();
		}

		$decoded = json_decode( $this->declaration_snapshot, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Status as an enum.
	 */
	public function status_enum(): RequestStatus {
		return RequestStatus::from( $this->status );
	}

	/**
	 * Whether the request has reached the immutable, legally-binding state.
	 */
	public function is_confirmed(): bool {
		return $this->status_enum()->is_locked();
	}

	/**
	 * The binding confirmation time as a Unix timestamp (UTC), or null.
	 */
	public function confirmed_timestamp(): ?int {
		if ( empty( $this->confirmed_at ) ) {
			return null;
		}

		$ts = strtotime( $this->confirmed_at . ' UTC' );

		return false === $ts ? null : $ts;
	}
}
