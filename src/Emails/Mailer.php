<?php
/**
 * Facade to trigger the plugin's WC_Email instances.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Emails;

use MS\WcRecesso\Model\WithdrawalRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Thin helper that resolves the registered WooCommerce email objects and
 * triggers them. Calling WC()->mailer() first guarantees the email classes are
 * instantiated before we look them up.
 */
final class Mailer {

	/**
	 * WC_Emails array keys (chosen when registering via woocommerce_email_classes).
	 */
	public const KEY_GUEST   = 'MS_WC_Recesso_Guest_Verification';
	public const KEY_RECEIPT = 'MS_WC_Recesso_Receipt';
	public const KEY_ADMIN   = 'MS_WC_Recesso_Admin';

	/**
	 * All registered WooCommerce email objects, keyed by class key.
	 *
	 * @return array<string,\WC_Email>
	 */
	private static function emails(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return array();
		}

		return WC()->mailer()->get_emails();
	}

	/**
	 * Send the guest verification email.
	 *
	 * @param WithdrawalRequest $request    Pending request.
	 * @param string            $verify_url One-time verification URL.
	 */
	public static function send_guest_verification( WithdrawalRequest $request, string $verify_url ): void {
		$emails = self::emails();

		if ( isset( $emails[ self::KEY_GUEST ] ) && $emails[ self::KEY_GUEST ] instanceof GuestVerificationEmail ) {
			$emails[ self::KEY_GUEST ]->trigger( $request, $verify_url );
		}
	}

	/**
	 * Send the withdrawal receipt to the consumer.
	 *
	 * @param WithdrawalRequest $request Confirmed request.
	 */
	public static function send_receipt( WithdrawalRequest $request ): void {
		$emails = self::emails();

		if ( isset( $emails[ self::KEY_RECEIPT ] ) && $emails[ self::KEY_RECEIPT ] instanceof WithdrawalReceiptEmail ) {
			$emails[ self::KEY_RECEIPT ]->trigger( $request );
		}
	}

	/**
	 * Send the admin notification.
	 *
	 * @param WithdrawalRequest $request Confirmed request.
	 */
	public static function send_admin_notification( WithdrawalRequest $request ): void {
		$emails = self::emails();

		if ( isset( $emails[ self::KEY_ADMIN ] ) && $emails[ self::KEY_ADMIN ] instanceof AdminNotificationEmail ) {
			$emails[ self::KEY_ADMIN ]->trigger( $request );
		}
	}
}
