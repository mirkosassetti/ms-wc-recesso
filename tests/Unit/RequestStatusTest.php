<?php
/**
 * Tests for the RequestStatus state machine.
 *
 * @package MS\WcRecesso\Tests
 */

namespace MS\WcRecesso\Tests\Unit;

use MS\WcRecesso\Model\RequestStatus;
use MS\WcRecesso\Tests\TestCase;

final class RequestStatusTest extends TestCase {

	public function test_backed_values(): void {
		$this->assertSame( 'pending_verification', RequestStatus::PendingVerification->value );
		$this->assertSame( 'draft', RequestStatus::Draft->value );
		$this->assertSame( 'confirmed', RequestStatus::Confirmed->value );
	}

	public function test_pending_verification_goes_to_draft_only(): void {
		$this->assertSame( array( RequestStatus::Draft ), RequestStatus::PendingVerification->allowed_transitions() );
	}

	public function test_draft_can_only_transition_to_confirmed(): void {
		$this->assertSame( array( RequestStatus::Confirmed ), RequestStatus::Draft->allowed_transitions() );
		$this->assertTrue( RequestStatus::Draft->can_transition_to( RequestStatus::Confirmed ) );
		$this->assertFalse( RequestStatus::Draft->can_transition_to( RequestStatus::Approved ) );
	}

	public function test_confirmed_allows_admin_transitions_but_not_backwards(): void {
		$this->assertTrue( RequestStatus::Confirmed->can_transition_to( RequestStatus::InReview ) );
		$this->assertTrue( RequestStatus::Confirmed->can_transition_to( RequestStatus::Approved ) );
		$this->assertTrue( RequestStatus::Confirmed->can_transition_to( RequestStatus::Completed ) );
		$this->assertFalse( RequestStatus::Confirmed->can_transition_to( RequestStatus::Draft ) );
		$this->assertFalse( RequestStatus::Confirmed->can_transition_to( RequestStatus::PendingVerification ) );
	}

	public function test_terminal_states_have_no_transitions(): void {
		$this->assertSame( array(), RequestStatus::Completed->allowed_transitions() );
		$this->assertSame( array(), RequestStatus::RejectedOutOfScope->allowed_transitions() );
	}

	public function test_locked_states(): void {
		$this->assertFalse( RequestStatus::PendingVerification->is_locked() );
		$this->assertFalse( RequestStatus::Draft->is_locked() );
		$this->assertTrue( RequestStatus::Confirmed->is_locked() );
		$this->assertTrue( RequestStatus::InReview->is_locked() );
		$this->assertTrue( RequestStatus::Completed->is_locked() );
	}

	public function test_label_returns_translatable_string(): void {
		$this->assertSame( 'Bozza', RequestStatus::Draft->label() );
		$this->assertSame( 'Recesso trasmesso', RequestStatus::Confirmed->label() );
	}
}
