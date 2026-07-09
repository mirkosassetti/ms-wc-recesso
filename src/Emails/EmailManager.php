<?php
/**
 * Registers the plugin's WC_Email classes and wires transactional sends.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Emails;

use MS\WcRecesso\Model\WithdrawalRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Adds our emails to WooCommerce and sends the receipt + admin notification
 * when a withdrawal is confirmed.
 */
final class EmailManager {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_classes' ) );
		add_action( 'ms_wc_recesso_request_confirmed', array( $this, 'on_request_confirmed' ) );
	}

	/**
	 * Add our WC_Email classes to WooCommerce.
	 *
	 * @param array<string,\WC_Email> $classes Existing email classes.
	 * @return array<string,\WC_Email>
	 */
	public function register_classes( array $classes ): array {
		$classes[ Mailer::KEY_GUEST ]   = new GuestVerificationEmail();
		$classes[ Mailer::KEY_RECEIPT ] = new WithdrawalReceiptEmail();
		$classes[ Mailer::KEY_ADMIN ]   = new AdminNotificationEmail();

		return $classes;
	}

	/**
	 * Send the receipt and admin notification without undue delay on confirm.
	 *
	 * @param WithdrawalRequest $request The confirmed request.
	 */
	public function on_request_confirmed( WithdrawalRequest $request ): void {
		Mailer::send_receipt( $request );
		Mailer::send_admin_notification( $request );
	}
}
