<?php
/**
 * Withdrawal request status enum and allowed transitions.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Finite set of request statuses and the state machine that governs them.
 *
 * Lifecycle:
 *   pending_verification --(guest clicks email link)--> draft
 *   draft                --(customer confirms)-------> confirmed  [immutable]
 *   confirmed            --> in_review --> approved | rejected_out_of_scope | completed
 *
 * The transition to CONFIRMED is the legally relevant one: once reached the
 * declaration snapshot, confirmed_at and receipt fields are frozen.
 */
enum RequestStatus: string {

	case PendingVerification = 'pending_verification';
	case Draft               = 'draft';
	case Confirmed           = 'confirmed';
	case InReview            = 'in_review';
	case Approved            = 'approved';
	case RejectedOutOfScope  = 'rejected_out_of_scope';
	case Completed           = 'completed';

	/**
	 * Human-readable, translatable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::PendingVerification => __( 'In attesa di verifica', 'ms-wc-recesso' ),
			self::Draft               => __( 'Bozza', 'ms-wc-recesso' ),
			self::Confirmed           => __( 'Recesso trasmesso', 'ms-wc-recesso' ),
			self::InReview            => __( 'In lavorazione', 'ms-wc-recesso' ),
			self::Approved            => __( 'Approvato', 'ms-wc-recesso' ),
			self::RejectedOutOfScope  => __( 'Respinto (fuori ambito)', 'ms-wc-recesso' ),
			self::Completed           => __( 'Completato', 'ms-wc-recesso' ),
		};
	}

	/**
	 * Whether this status is the immutable, legally-binding one.
	 */
	public function is_locked(): bool {
		return match ( $this ) {
			self::PendingVerification, self::Draft => false,
			default                                => true,
		};
	}

	/**
	 * Statuses reachable from the current one.
	 *
	 * @return array<int,RequestStatus>
	 */
	public function allowed_transitions(): array {
		return match ( $this ) {
			self::PendingVerification => array( self::Draft ),
			self::Draft               => array( self::Confirmed ),
			self::Confirmed           => array( self::InReview, self::Approved, self::RejectedOutOfScope, self::Completed ),
			self::InReview            => array( self::Approved, self::RejectedOutOfScope, self::Completed ),
			self::Approved            => array( self::Completed ),
			self::RejectedOutOfScope  => array(),
			self::Completed           => array(),
		};
	}

	/**
	 * Whether a transition to $target is permitted.
	 *
	 * @param RequestStatus $target Desired target status.
	 */
	public function can_transition_to( RequestStatus $target ): bool {
		return in_array( $target, $this->allowed_transitions(), true );
	}
}
