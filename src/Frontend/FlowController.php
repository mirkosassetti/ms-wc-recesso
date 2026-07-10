<?php
/**
 * Multi-step withdrawal flow controller.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

use MS\WcRecesso\Activator;
use MS\WcRecesso\Domain\AccessPolicy;
use MS\WcRecesso\Domain\EligibilityService;
use MS\WcRecesso\Domain\OrderLocator;
use MS\WcRecesso\Domain\WithdrawalService;
use MS\WcRecesso\Emails\Mailer;
use MS\WcRecesso\Model\RequestRepository;
use MS\WcRecesso\Model\RequestStatus;
use MS\WcRecesso\Model\WithdrawalRequest;
use MS\WcRecesso\Plugin;
use MS\WcRecesso\Support\Dates;
use MS\WcRecesso\Support\Logger;
use MS\WcRecesso\Support\Options;
use MS\WcRecesso\Support\RateLimiter;
use MS\WcRecesso\Support\Tokens;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Shared controller driving the withdrawal flow for both the shortcode page
 * and the My Account endpoint.
 *
 * Steps (all POST-driven with nonces, no personal data in the query string):
 *   select   -> choose one of your orders (logged-in)
 *   form     -> compile the declaration (name, order, receipt email, items)
 *   summary  -> read-only recap + separate "Conferma recesso" button
 *   confirmed-> the withdrawal has been transmitted (server-side UTC timestamp)
 *
 * Phase 2 covers the logged-in owner path; the guest path (Phase 3) will plug
 * into the not-logged-in branch.
 */
final class FlowController {

	private const NONCE_DECLARATION  = 'ms_wc_recesso_declaration';
	private const NONCE_CONFIRM      = 'ms_wc_recesso_confirm';
	private const NONCE_GUEST_LOOKUP = 'ms_wc_recesso_guest_lookup';

	/**
	 * Guest lookup rate limit: max attempts per IP per hour.
	 */
	private const GUEST_RATE_MAX = 5;

	/**
	 * Repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $repository;

	/**
	 * Order locator.
	 *
	 * @var OrderLocator
	 */
	private OrderLocator $locator;

	/**
	 * Eligibility service.
	 *
	 * @var EligibilityService
	 */
	private EligibilityService $eligibility;

	/**
	 * Withdrawal service.
	 *
	 * @var WithdrawalService
	 */
	private WithdrawalService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository  = new RequestRepository();
		$this->locator     = new OrderLocator();
		$this->eligibility = new EligibilityService();
		$this->service     = new WithdrawalService( $this->repository );
	}

	/**
	 * Render the flow for a given context.
	 *
	 * @param string $context 'page' (shortcode) or 'myaccount' (endpoint).
	 */
	public function render( string $context ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_guest( $context );
		}

		if ( AccessPolicy::current_user_excluded() ) {
			return $this->notice( 'info', __( 'Il recesso non è disponibile per il tuo tipo di account.', 'ms-wc-recesso' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing only; each handler verifies its own nonce before processing.
		$action = isset( $_POST['ms_wc_recesso_action'] ) ? sanitize_key( wp_unslash( $_POST['ms_wc_recesso_action'] ) ) : '';

		if ( 'confirm' === $action ) {
			return $this->process_confirm( $context );
		}

		if ( 'declaration' === $action ) {
			return $this->process_declaration( $context );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation via a non-sensitive order id; ownership is enforced server-side.
		$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;

		if ( $order_id > 0 ) {
			$order = $this->locator->get_owned_order( $order_id, get_current_user_id() );

			if ( ! $order instanceof WC_Order ) {
				return $this->notice( 'error', __( 'Ordine non trovato o non associato al tuo account.', 'ms-wc-recesso' ) )
					. $this->back_link( $context );
			}

			if ( ! $this->locator->is_status_eligible( $order ) ) {
				return $this->notice( 'error', __( 'Questo ordine non è idoneo al recesso.', 'ms-wc-recesso' ) )
					. $this->back_link( $context );
			}

			return $this->render_form( $order, $context, null, array() );
		}

		return $this->render_order_select( $context );
	}

	/**
	 * Handle the declaration submission: validate and move to the summary step.
	 *
	 * @param string $context Flow context.
	 */
	private function process_declaration( string $context ): string {
		$nonce = isset( $_POST['_ms_wc_recesso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ms_wc_recesso_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_DECLARATION ) ) {
			return $this->notice( 'error', __( 'Sessione scaduta, riprova.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		$user_id  = get_current_user_id();
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order    = $this->locator->get_owned_order( $order_id, $user_id );

		if ( ! $order instanceof WC_Order ) {
			return $this->notice( 'error', __( 'Ordine non trovato o non associato al tuo account.', 'ms-wc-recesso' ) )
				. $this->back_link( $context );
		}

		if ( ! $this->locator->is_status_eligible( $order ) ) {
			return $this->notice( 'error', __( 'Questo ordine non è idoneo al recesso.', 'ms-wc-recesso' ) )
				. $this->back_link( $context );
		}

		$name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$email  = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$chosen = isset( $_POST['items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['items'] ) ) : array();

		$selected = $this->eligibility->filter_selected( $order, $chosen );

		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Inserisci il tuo nome.', 'ms-wc-recesso' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Inserisci un indirizzo email valido per ricevere la conferma.', 'ms-wc-recesso' );
		}
		if ( empty( $selected ) ) {
			$errors[] = __( 'Seleziona almeno un articolo idoneo al recesso.', 'ms-wc-recesso' );
		}

		if ( ! empty( $errors ) ) {
			return $this->render_form(
				$order,
				$context,
				array(
					'name'         => $name,
					'email'        => $email,
					'reason'       => $reason,
					'selected_ids' => $chosen,
				),
				$errors
			);
		}

		$window = $this->eligibility->window_status( $order );

		$draft = $this->service->create_or_update_draft(
			array(
				'order_id'            => $order->get_id(),
				'order_reference'     => $order->get_order_number(),
				'customer_name'       => $name,
				'customer_email'      => $email,
				'items'               => $selected,
				'reason'              => $reason,
				'needs_manual_review' => ! $window['within'],
				'is_guest'            => false,
				'ip_hash'             => $this->ip_hash(),
			),
			'customer',
			$user_id
		);

		return $this->render_summary(
			$draft,
			$context,
			$window,
			add_query_arg( 'order', (int) $draft->order_id, $this->base_url( $context ) )
		);
	}

	/**
	 * Handle the confirmation submission: the binding transmission.
	 *
	 * @param string $context Flow context.
	 */
	private function process_confirm( string $context ): string {
		$nonce = isset( $_POST['_ms_wc_recesso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ms_wc_recesso_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_CONFIRM ) ) {
			return $this->notice( 'error', __( 'Sessione scaduta, riprova.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		$uuid    = isset( $_POST['request_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['request_uuid'] ) ) : '';
		$request = '' !== $uuid ? $this->repository->get_by_uuid( $uuid ) : null;

		if ( ! $request instanceof WithdrawalRequest || ! $this->user_can_access( $request ) ) {
			return $this->notice( 'error', __( 'Richiesta di recesso non trovata.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		if ( $request->is_confirmed() ) {
			return $this->render_confirmation( $request );
		}

		$confirmed = $this->service->confirm( $request, 'customer', get_current_user_id() );

		return $this->render_confirmation( $confirmed );
	}

	/**
	 * Render the order-selection step.
	 *
	 * @param string $context Flow context.
	 */
	private function render_order_select( string $context ): string {
		$orders = $this->locator->get_user_orders( get_current_user_id() );

		return View::render(
			'order-select.php',
			array(
				'orders'   => $orders,
				'base_url' => $this->base_url( $context ),
			)
		);
	}

	/**
	 * Render the declaration form step.
	 *
	 * @param WC_Order                 $order   The order.
	 * @param string                   $context Flow context.
	 * @param array<string,mixed>|null $prefill Submitted values on re-render, or null for defaults.
	 * @param array<int,string>        $errors  Validation errors to display.
	 */
	private function render_form( WC_Order $order, string $context, ?array $prefill, array $errors ): string {
		$items  = $this->eligibility->get_line_items( $order );
		$window = $this->eligibility->window_status( $order );
		$user   = wp_get_current_user();

		if ( null === $prefill ) {
			$eligible_ids = array();
			foreach ( $items as $line ) {
				if ( $line['eligible'] ) {
					$eligible_ids[] = $line['order_item_id'];
				}
			}

			$default_name = trim( $order->get_formatted_billing_full_name() );

			$prefill = array(
				'name'         => '' !== $default_name ? $default_name : $user->display_name,
				'email'        => $user->user_email ? $user->user_email : $order->get_billing_email(),
				'reason'       => '',
				'selected_ids' => $eligible_ids,
			);
		}

		return View::render(
			'form-declaration.php',
			array(
				'order'    => $order,
				'items'    => $items,
				'window'   => $window,
				'prefill'  => $prefill,
				'errors'   => $errors,
				'base_url' => $this->base_url( $context ),
				'nonce'    => wp_create_nonce( self::NONCE_DECLARATION ),
			)
		);
	}

	/**
	 * Render the summary (step 2) with the separate confirmation button.
	 *
	 * @param WithdrawalRequest   $draft    The persisted draft.
	 * @param string              $context  Flow context.
	 * @param array<string,mixed> $window   Window status.
	 * @param string              $edit_url URL to go back and edit the declaration.
	 * @param string              $token    Guest raw token to carry through, or ''.
	 * @param string              $notice   Extra warning message to show, or ''.
	 */
	private function render_summary( WithdrawalRequest $draft, string $context, array $window, string $edit_url, string $token = '', string $notice = '' ): string {
		return View::render(
			'form-summary.php',
			array(
				'request'  => $draft,
				'window'   => $window,
				'base_url' => $this->base_url( $context ),
				'edit_url' => $edit_url,
				'token'    => $token,
				'notice'   => $notice,
				'nonce'    => wp_create_nonce( self::NONCE_CONFIRM ),
			)
		);
	}

	/**
	 * Render the post-confirmation screen.
	 *
	 * @param WithdrawalRequest $request The confirmed request.
	 */
	private function render_confirmation( WithdrawalRequest $request ): string {
		return View::render(
			'confirmation.php',
			array(
				'request' => $request,
			)
		);
	}

	/**
	 * Guest (not-logged-in) branch: lookup -> email verification -> declaration.
	 *
	 * @param string $context Flow context.
	 */
	private function render_guest( string $context ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing only; each handler verifies its own nonce before processing.
		$action = isset( $_POST['ms_wc_recesso_action'] ) ? sanitize_key( wp_unslash( $_POST['ms_wc_recesso_action'] ) ) : '';

		if ( 'guest_lookup' === $action ) {
			return $this->process_guest_lookup( $context );
		}

		if ( 'declaration' === $action ) {
			return $this->process_guest_declaration( $context );
		}

		if ( 'confirm' === $action ) {
			return $this->process_guest_confirm( $context );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is a hashed secret verified against the DB, not trusted form input.
		$token = isset( $_GET['ms_recesso_token'] ) ? sanitize_text_field( wp_unslash( $_GET['ms_recesso_token'] ) ) : '';

		if ( '' !== $token ) {
			$request = $this->resolve_guest_request( $token );

			if ( ! $request instanceof WithdrawalRequest ) {
				return $this->notice( 'error', __( 'Il link di verifica non è valido o è scaduto.', 'ms-wc-recesso' ) )
					. $this->back_link( $context );
			}

			if ( $request->is_confirmed() ) {
				return $this->render_confirmation( $request );
			}

			if ( $this->guest_order_blocked( $request ) ) {
				return $this->blocked_order_notice( $context );
			}

			return $this->render_guest_form( $request, $token, $context, null, array() );
		}

		return $this->render_guest_lookup( $context, array(), array() );
	}

	/**
	 * Handle the public lookup submission: register a pending request and send
	 * the verification link. Non-blocking: unmatched data is still recorded and
	 * flagged for manual review.
	 *
	 * @param string $context Flow context.
	 */
	private function process_guest_lookup( string $context ): string {
		$nonce = isset( $_POST['_ms_wc_recesso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ms_wc_recesso_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_GUEST_LOOKUP ) ) {
			return $this->notice( 'error', __( 'Sessione scaduta, riprova.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		if ( ! RateLimiter::attempt( 'lookup_' . $this->ip_hash(), self::GUEST_RATE_MAX, HOUR_IN_SECONDS ) ) {
			return $this->notice( 'error', __( 'Troppi tentativi. Riprova tra qualche minuto.', 'ms-wc-recesso' ) );
		}

		$reference = isset( $_POST['order_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['order_reference'] ) ) : '';
		$email     = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';

		$errors = array();
		if ( '' === $reference ) {
			$errors[] = __( 'Inserisci il numero dell’ordine.', 'ms-wc-recesso' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Inserisci un indirizzo email valido.', 'ms-wc-recesso' );
		}

		if ( ! empty( $errors ) ) {
			return $this->render_guest_lookup(
				$context,
				array(
					'reference' => $reference,
					'email'     => $email,
				),
				$errors
			);
		}

		// Dedupe re-submission (browser refresh) so we never send a duplicate
		// verification email for the same lookup. Keyed by nonce + email.
		$dedupe_key = 'ms_wc_recesso_lookup_' . md5( $nonce . '|' . strtolower( $email ) );
		if ( false !== get_transient( $dedupe_key ) ) {
			return $this->render_guest_verify_sent( $email );
		}

		$order = $this->locator->locate_guest( $reference, $email );

		if ( $order instanceof WC_Order && AccessPolicy::order_customer_excluded( $order ) ) {
			return $this->blocked_order_notice( $context );
		}

		$token   = Tokens::generate();
		$hours   = max( 1, Options::get_int( 'guest_token_hours', 48 ) );
		$expires = Dates::to_mysql( Dates::now()->modify( sprintf( '+%d hours', $hours ) ) );
		$name    = $order instanceof WC_Order ? trim( $order->get_formatted_billing_full_name() ) : '';

		$request = $this->service->create_guest_pending(
			array(
				'order_id'            => $order instanceof WC_Order ? $order->get_id() : null,
				'order_reference'     => $reference,
				'customer_name'       => $name,
				'customer_email'      => $email,
				'needs_manual_review' => ! $order instanceof WC_Order,
				'verification_token'  => $token['hash'],
				'token_expires'       => $expires,
				'ip_hash'             => $this->ip_hash(),
			)
		);

		$verify_url = add_query_arg( 'ms_recesso_token', $token['raw'], $this->base_url( $context ) );
		Mailer::send_guest_verification( $request, $verify_url );
		Logger::log( $request->id, 'verification_sent', null, null, 'guest', null );

		set_transient( $dedupe_key, 1, 10 * MINUTE_IN_SECONDS );

		return $this->render_guest_verify_sent( $email );
	}

	/**
	 * Handle the guest declaration submission (token-authorised).
	 *
	 * @param string $context Flow context.
	 */
	private function process_guest_declaration( string $context ): string {
		$nonce = isset( $_POST['_ms_wc_recesso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ms_wc_recesso_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_DECLARATION ) ) {
			return $this->notice( 'error', __( 'Sessione scaduta, riprova.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		$token   = isset( $_POST['ms_recesso_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ms_recesso_token'] ) ) : '';
		$request = $this->resolve_guest_request( $token );

		if ( ! $request instanceof WithdrawalRequest ) {
			return $this->notice( 'error', __( 'Il link di verifica non è valido o è scaduto.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		if ( $request->is_confirmed() ) {
			return $this->render_confirmation( $request );
		}

		if ( $this->guest_order_blocked( $request ) ) {
			return $this->blocked_order_notice( $context );
		}

		$name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$chosen = isset( $_POST['items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['items'] ) ) : array();

		$order        = $this->load_guest_order( $request );
		$needs_review = ! $order instanceof WC_Order;
		$selected     = array();

		if ( $order instanceof WC_Order ) {
			$selected = $this->eligibility->filter_selected( $order, $chosen );
			$window   = $this->eligibility->window_status( $order );
			if ( ! $window['within'] ) {
				$needs_review = true;
			}
		} else {
			$window = array( 'within' => true );
		}

		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Inserisci il tuo nome.', 'ms-wc-recesso' );
		}
		if ( $order instanceof WC_Order && empty( $selected ) ) {
			$errors[] = __( 'Seleziona almeno un articolo idoneo al recesso.', 'ms-wc-recesso' );
		}

		if ( ! empty( $errors ) ) {
			return $this->render_guest_form(
				$request,
				$token,
				$context,
				array(
					'name'         => $name,
					'reason'       => $reason,
					'selected_ids' => $chosen,
				),
				$errors
			);
		}

		$draft = $this->service->save_guest_draft(
			$request,
			array(
				'customer_name'       => $name,
				'items'               => $selected,
				'reason'              => $reason,
				'needs_manual_review' => $needs_review,
			)
		);

		$notice = $order instanceof WC_Order
			? ''
			: __( 'Non siamo riusciti ad abbinare automaticamente l’ordine indicato: la richiesta sarà comunque registrata e verificata manualmente.', 'ms-wc-recesso' );

		return $this->render_summary(
			$draft,
			$context,
			$window,
			add_query_arg( 'ms_recesso_token', $token, $this->base_url( $context ) ),
			$token,
			$notice
		);
	}

	/**
	 * Handle the guest confirmation submission (token-authorised).
	 *
	 * @param string $context Flow context.
	 */
	private function process_guest_confirm( string $context ): string {
		$nonce = isset( $_POST['_ms_wc_recesso_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ms_wc_recesso_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_CONFIRM ) ) {
			return $this->notice( 'error', __( 'Sessione scaduta, riprova.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		$token   = isset( $_POST['ms_recesso_token'] ) ? sanitize_text_field( wp_unslash( $_POST['ms_recesso_token'] ) ) : '';
		$request = $this->resolve_guest_request( $token );

		if ( ! $request instanceof WithdrawalRequest ) {
			return $this->notice( 'error', __( 'Il link di verifica non è valido o è scaduto.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		if ( $request->is_confirmed() ) {
			return $this->render_confirmation( $request );
		}

		if ( $this->guest_order_blocked( $request ) ) {
			return $this->blocked_order_notice( $context );
		}

		if ( RequestStatus::Draft !== $request->status_enum() ) {
			return $this->notice( 'error', __( 'Questa richiesta non può essere confermata.', 'ms-wc-recesso' ) ) . $this->back_link( $context );
		}

		$confirmed = $this->service->confirm( $request, 'guest', null );

		return $this->render_confirmation( $confirmed );
	}

	/**
	 * Render the public lookup form.
	 *
	 * @param string              $context Flow context.
	 * @param array<string,mixed> $prefill Prefilled values.
	 * @param array<int,string>   $errors  Validation errors.
	 */
	private function render_guest_lookup( string $context, array $prefill, array $errors ): string {
		return View::render(
			'guest-lookup.php',
			array(
				'base_url' => $this->base_url( $context ),
				'nonce'    => wp_create_nonce( self::NONCE_GUEST_LOOKUP ),
				'prefill'  => $prefill,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * Render the "verification email sent" screen.
	 *
	 * @param string $email The address the link was sent to.
	 */
	private function render_guest_verify_sent( string $email ): string {
		return View::render(
			'guest-verify-sent.php',
			array(
				'email' => $email,
			)
		);
	}

	/**
	 * Render the guest declaration form after email verification.
	 *
	 * @param WithdrawalRequest        $request The verified request.
	 * @param string                   $token   Raw token to carry through.
	 * @param string                   $context Flow context.
	 * @param array<string,mixed>|null $prefill Submitted values, or null for defaults.
	 * @param array<int,string>        $errors  Validation errors.
	 */
	private function render_guest_form( WithdrawalRequest $request, string $token, string $context, ?array $prefill, array $errors ): string {
		$order  = $this->load_guest_order( $request );
		$items  = $order instanceof WC_Order ? $this->eligibility->get_line_items( $order ) : array();
		$window = $order instanceof WC_Order ? $this->eligibility->window_status( $order ) : array( 'within' => true );

		if ( null === $prefill ) {
			$eligible_ids = array();
			foreach ( $items as $line ) {
				if ( $line['eligible'] ) {
					$eligible_ids[] = $line['order_item_id'];
				}
			}

			$prefill = array(
				'name'         => $request->customer_name,
				'reason'       => '',
				'selected_ids' => $eligible_ids,
			);
		}

		return View::render(
			'guest-declaration.php',
			array(
				'request'  => $request,
				'order'    => $order,
				'items'    => $items,
				'window'   => $window,
				'prefill'  => $prefill,
				'errors'   => $errors,
				'token'    => $token,
				'base_url' => $this->base_url( $context ),
				'nonce'    => wp_create_nonce( self::NONCE_DECLARATION ),
			)
		);
	}

	/**
	 * Resolve a guest request from a raw token (validates hash + expiry).
	 *
	 * @param string $token Raw token.
	 */
	private function resolve_guest_request( string $token ): ?WithdrawalRequest {
		if ( '' === $token ) {
			return null;
		}

		return $this->repository->get_by_token( Tokens::hash( $token ) );
	}

	/**
	 * Load the order linked to a guest request, if still valid and eligible.
	 *
	 * @param WithdrawalRequest $request The request.
	 */
	private function load_guest_order( WithdrawalRequest $request ): ?WC_Order {
		if ( null === $request->order_id ) {
			return null;
		}

		$order = wc_get_order( $request->order_id );

		if ( ! $order instanceof WC_Order || ! $this->locator->is_status_eligible( $order ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Whether a guest request targets an order whose customer has an excluded
	 * role (e.g. B2B), in which case withdrawal is not available.
	 *
	 * @param WithdrawalRequest $request The request.
	 */
	private function guest_order_blocked( WithdrawalRequest $request ): bool {
		if ( null === $request->order_id ) {
			return false;
		}

		$order = wc_get_order( $request->order_id );

		return $order instanceof WC_Order && AccessPolicy::order_customer_excluded( $order );
	}

	/**
	 * Notice shown when withdrawal is not available for an order.
	 *
	 * @param string $context Flow context.
	 */
	private function blocked_order_notice( string $context ): string {
		return $this->notice( 'info', __( 'Il recesso non è disponibile per questo ordine.', 'ms-wc-recesso' ) )
			. $this->back_link( $context );
	}

	/**
	 * Whether the current user may act on a request.
	 *
	 * @param WithdrawalRequest $request The request.
	 */
	private function user_can_access( WithdrawalRequest $request ): bool {
		$user = wp_get_current_user();

		if ( null !== $request->order_id ) {
			$order = wc_get_order( $request->order_id );
			if ( $order && (int) $order->get_customer_id() === (int) $user->ID ) {
				return true;
			}
		}

		return '' !== $request->customer_email
			&& strtolower( $request->customer_email ) === strtolower( (string) $user->user_email );
	}

	/**
	 * Base URL of the flow for the given context.
	 *
	 * @param string $context Flow context.
	 */
	private function base_url( string $context ): string {
		if ( 'myaccount' === $context ) {
			return wc_get_account_endpoint_url( Plugin::ENDPOINT );
		}

		$page_id = (int) get_option( Activator::PAGE_OPTION, 0 );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : '';

		return $url ? $url : home_url( '/' );
	}

	/**
	 * A one-way IP hash for rate limiting / provenance (no raw IP stored).
	 */
	private function ip_hash(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Server-provided value, not form input.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' !== $ip ? hash( 'sha256', $ip . wp_salt() ) : '';
	}

	/**
	 * Build a simple notice block.
	 *
	 * @param string $type    info|error|success.
	 * @param string $message Message (may contain a safe anchor tag).
	 */
	private function notice( string $type, string $message ): string {
		return sprintf(
			'<div class="ms-recesso-notice ms-recesso-notice--%1$s">%2$s</div>',
			esc_attr( $type ),
			wp_kses( $message, array( 'a' => array( 'href' => array() ) ) )
		);
	}

	/**
	 * A link back to the flow start.
	 *
	 * @param string $context Flow context.
	 */
	private function back_link( string $context ): string {
		return sprintf(
			'<p><a class="ms-recesso-back" href="%1$s">%2$s</a></p>',
			esc_url( $this->base_url( $context ) ),
			esc_html__( 'Torna indietro', 'ms-wc-recesso' )
		);
	}
}
