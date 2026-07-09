<?php
/**
 * Orchestrates the creation, confirmation and persistence of withdrawal
 * requests.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Domain;

use MS\WcRecesso\Model\RequestRepository;
use MS\WcRecesso\Model\RequestStatus;
use MS\WcRecesso\Model\WithdrawalRequest;
use MS\WcRecesso\Support\Dates;
use MS\WcRecesso\Support\Logger;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Application service for the withdrawal lifecycle.
 *
 * The two-step flow (declaration -> confirmation) maps to two methods:
 * create_or_update_draft() and confirm(). Only confirm() produces the legally
 * binding, immutable record with a server-side UTC timestamp.
 */
final class WithdrawalService {

	/**
	 * Order meta key holding the linked request UUID (latest).
	 */
	public const META_UUID = '_ms_wc_recesso_request_uuid';

	/**
	 * Order meta key holding the linked request status (latest).
	 */
	public const META_STATUS = '_ms_wc_recesso_request_status';

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
	}

	/**
	 * Create a new draft, or update the existing open draft for the same order
	 * and email. Never binding on its own.
	 *
	 * @param array<string,mixed> $data       Declaration data. See the flow controller.
	 * @param string              $actor_type customer|guest.
	 * @param int|null            $actor_id   User id when authenticated.
	 * @return WithdrawalRequest The persisted draft.
	 *
	 * @throws RuntimeException When persistence fails.
	 */
	public function create_or_update_draft( array $data, string $actor_type, ?int $actor_id ): WithdrawalRequest {
		$order_id = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
		$email    = isset( $data['customer_email'] ) ? (string) $data['customer_email'] : '';

		$fields = array(
			'order_id'            => $order_id > 0 ? $order_id : null,
			'order_reference'     => (string) ( $data['order_reference'] ?? '' ),
			'customer_name'       => (string) ( $data['customer_name'] ?? '' ),
			'customer_email'      => $email,
			'items'               => wp_json_encode( $data['items'] ?? array() ),
			'reason'              => '' !== (string) ( $data['reason'] ?? '' ) ? (string) $data['reason'] : null,
			'needs_manual_review' => ! empty( $data['needs_manual_review'] ) ? 1 : 0,
			'is_guest'            => ! empty( $data['is_guest'] ) ? 1 : 0,
			'ip_hash'             => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : null,
			'status'              => RequestStatus::Draft->value,
			'submitted_at'        => Dates::now_mysql(),
		);

		$existing = $order_id > 0 ? $this->repository->find_open_draft( $order_id, $email ) : null;

		if ( $existing instanceof WithdrawalRequest ) {
			$this->repository->update( $existing->id, $fields );
			$id = $existing->id;
			Logger::log( $id, 'draft_updated', null, null, $actor_type, $actor_id );
		} else {
			$id = $this->repository->insert( $fields );

			if ( $id <= 0 ) {
				throw new RuntimeException( 'Unable to persist withdrawal draft.' );
			}

			Logger::log( $id, 'created', null, RequestStatus::Draft->value, $actor_type, $actor_id );
		}

		$request = $this->repository->get( $id );

		if ( ! $request instanceof WithdrawalRequest ) {
			throw new RuntimeException( 'Withdrawal draft could not be reloaded.' );
		}

		return $request;
	}

	/**
	 * Create a pending guest request awaiting email verification.
	 *
	 * The request is not yet a declaration: it only records the lookup so the
	 * emailed token can later grant access to compile and confirm it.
	 *
	 * @param array<string,mixed> $data Lookup data incl. hashed token & expiry.
	 * @return WithdrawalRequest The pending request.
	 *
	 * @throws RuntimeException When persistence fails.
	 */
	public function create_guest_pending( array $data ): WithdrawalRequest {
		$fields = array(
			'order_id'            => ! empty( $data['order_id'] ) ? (int) $data['order_id'] : null,
			'order_reference'     => (string) ( $data['order_reference'] ?? '' ),
			'customer_name'       => (string) ( $data['customer_name'] ?? '' ),
			'customer_email'      => (string) ( $data['customer_email'] ?? '' ),
			'items'               => null,
			'needs_manual_review' => ! empty( $data['needs_manual_review'] ) ? 1 : 0,
			'is_guest'            => 1,
			'verification_token'  => (string) ( $data['verification_token'] ?? '' ),
			'token_expires'       => (string) ( $data['token_expires'] ?? '' ),
			'ip_hash'             => isset( $data['ip_hash'] ) ? (string) $data['ip_hash'] : null,
			'status'              => RequestStatus::PendingVerification->value,
		);

		$id = $this->repository->insert( $fields );

		if ( $id <= 0 ) {
			throw new RuntimeException( 'Unable to persist guest request.' );
		}

		Logger::log( $id, 'created', null, RequestStatus::PendingVerification->value, 'guest', null );

		$request = $this->repository->get( $id );

		if ( ! $request instanceof WithdrawalRequest ) {
			throw new RuntimeException( 'Guest request could not be reloaded.' );
		}

		return $request;
	}

	/**
	 * Promote a verified guest request to a compiled draft.
	 *
	 * @param WithdrawalRequest   $request The pending request being verified.
	 * @param array<string,mixed> $data    Declaration data (name, items, reason).
	 * @return WithdrawalRequest The draft.
	 *
	 * @throws RuntimeException When the draft cannot be reloaded.
	 */
	public function save_guest_draft( WithdrawalRequest $request, array $data ): WithdrawalRequest {
		$fields = array(
			'customer_name'       => (string) ( $data['customer_name'] ?? $request->customer_name ),
			'items'               => wp_json_encode( $data['items'] ?? array() ),
			'reason'              => '' !== (string) ( $data['reason'] ?? '' ) ? (string) $data['reason'] : null,
			'needs_manual_review' => ! empty( $data['needs_manual_review'] ) ? 1 : 0,
			'status'              => RequestStatus::Draft->value,
			'submitted_at'        => Dates::now_mysql(),
		);

		$this->repository->update( $request->id, $fields );

		Logger::log(
			$request->id,
			'verified',
			$request->status,
			RequestStatus::Draft->value,
			'guest',
			null
		);

		$draft = $this->repository->get( $request->id );

		if ( ! $draft instanceof WithdrawalRequest ) {
			throw new RuntimeException( 'Guest draft could not be reloaded.' );
		}

		return $draft;
	}

	/**
	 * Confirm a draft: this is the binding transmission of the withdrawal.
	 *
	 * Idempotent — confirming an already-confirmed request returns it unchanged.
	 * The confirmation timestamp is generated here, server-side, in UTC.
	 *
	 * @param WithdrawalRequest $request    The draft to confirm.
	 * @param string            $actor_type customer|guest.
	 * @param int|null          $actor_id   User id when authenticated.
	 * @return WithdrawalRequest The confirmed request.
	 *
	 * @throws RuntimeException When the request is not in a confirmable state.
	 */
	public function confirm( WithdrawalRequest $request, string $actor_type, ?int $actor_id ): WithdrawalRequest {
		// Already binding: return as-is so a page refresh cannot double-submit.
		if ( $request->is_confirmed() ) {
			return $request;
		}

		if ( RequestStatus::Draft !== $request->status_enum() ) {
			throw new RuntimeException( 'Only a draft can be confirmed.' );
		}

		$now      = Dates::now();
		$snapshot = $this->build_snapshot( $request, $now->getTimestamp() );

		$this->repository->update(
			$request->id,
			array(
				'status'               => RequestStatus::Confirmed->value,
				'confirmed_at'         => Dates::to_mysql( $now ),
				'declaration_snapshot' => wp_json_encode( $snapshot ),
			)
		);

		Logger::log(
			$request->id,
			'confirmed',
			RequestStatus::Draft->value,
			RequestStatus::Confirmed->value,
			$actor_type,
			$actor_id
		);

		$confirmed = $this->repository->get( $request->id );

		if ( ! $confirmed instanceof WithdrawalRequest ) {
			throw new RuntimeException( 'Confirmed request could not be reloaded.' );
		}

		$this->annotate_order( $confirmed );

		/**
		 * Fires when a withdrawal has been legally transmitted (confirmed).
		 *
		 * Phase 4 hooks here to send the durable-medium receipt to the consumer
		 * and the notification to the admin.
		 *
		 * @param WithdrawalRequest $confirmed The confirmed request.
		 */
		do_action( 'ms_wc_recesso_request_confirmed', $confirmed );

		return $confirmed;
	}

	/**
	 * Apply an administrative status transition (post-confirmation).
	 *
	 * @param WithdrawalRequest $request  The request.
	 * @param RequestStatus     $target   Desired status.
	 * @param int|null          $actor_id Admin user id.
	 * @return WithdrawalRequest The updated request.
	 *
	 * @throws RuntimeException When the transition is not allowed.
	 */
	public function transition_status( WithdrawalRequest $request, RequestStatus $target, ?int $actor_id ): WithdrawalRequest {
		$from = $request->status_enum();

		if ( $from === $target ) {
			return $request;
		}

		if ( ! $from->can_transition_to( $target ) ) {
			throw new RuntimeException( 'Invalid status transition.' );
		}

		$this->repository->update( $request->id, array( 'status' => $target->value ) );

		Logger::log( $request->id, 'status_changed', $from->value, $target->value, 'admin', $actor_id );

		if ( null !== $request->order_id ) {
			$order = wc_get_order( $request->order_id );
			if ( $order ) {
				$order->update_meta_data( self::META_STATUS, $target->value );
				$order->save();
			}
		}

		$updated = $this->repository->get( $request->id );

		if ( ! $updated instanceof WithdrawalRequest ) {
			throw new RuntimeException( 'Updated request could not be reloaded.' );
		}

		/**
		 * Fires after an administrative status change.
		 *
		 * @param WithdrawalRequest $updated The updated request.
		 * @param RequestStatus     $from    Previous status.
		 */
		do_action( 'ms_wc_recesso_status_changed', $updated, $from );

		return $updated;
	}

	/**
	 * Build the immutable declaration snapshot stored at confirmation.
	 *
	 * @param WithdrawalRequest $request   The request.
	 * @param int               $timestamp Confirmation Unix timestamp (UTC).
	 * @return array<string,mixed>
	 */
	private function build_snapshot( WithdrawalRequest $request, int $timestamp ): array {
		return array(
			'customer_name'   => $request->customer_name,
			'customer_email'  => $request->customer_email,
			'order_reference' => $request->order_reference,
			'order_id'        => $request->order_id,
			'items'           => $request->items(),
			'reason'          => $request->reason,
			'confirmed_at'    => gmdate( Dates::MYSQL, $timestamp ),
			'confirmed_ts'    => $timestamp,
		);
	}

	/**
	 * Mirror the confirmed request onto the WooCommerce order (meta + note) so
	 * it is visible in the standard order panel. HPOS-safe via CRUD API.
	 *
	 * @param WithdrawalRequest $request The confirmed request.
	 */
	private function annotate_order( WithdrawalRequest $request ): void {
		if ( null === $request->order_id ) {
			return;
		}

		$order = wc_get_order( $request->order_id );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( self::META_UUID, $request->public_uuid );
		$order->update_meta_data( self::META_STATUS, $request->status );

		$order->add_order_note(
			sprintf(
				/* translators: 1: request UUID, 2: confirmation date/time. */
				__( 'Recesso ex art. 54-bis trasmesso (rif. %1$s) in data %2$s.', 'ms-wc-recesso' ),
				$request->public_uuid,
				wp_date(
					'd/m/Y H:i',
					$request->confirmed_timestamp() ?? time()
				)
			)
		);

		$order->save();
	}
}
