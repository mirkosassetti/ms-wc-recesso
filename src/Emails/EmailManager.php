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
		add_action( 'admin_init', array( $this, 'maybe_migrate_recipient' ) );
	}

	/**
	 * One-time migration (0.1.x -> 0.2.0): move the admin notification address
	 * from the plugin settings to the WooCommerce email "recipient" field, then
	 * drop the obsolete plugin setting.
	 */
	public function maybe_migrate_recipient(): void {
		if ( get_option( 'ms_wc_recesso_recipient_migrated' ) ) {
			return;
		}

		$settings = get_option( 'ms_wc_recesso_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$old      = isset( $settings['admin_notification_email'] ) ? (string) $settings['admin_notification_email'] : '';

		if ( '' !== $old ) {
			$key = 'woocommerce_ms_wc_recesso_admin_settings';
			$wc  = get_option( $key, array() );
			$wc  = is_array( $wc ) ? $wc : array();

			if ( empty( $wc['recipient'] ) ) {
				$wc['recipient'] = $old;
				update_option( $key, $wc );
			}
		}

		if ( array_key_exists( 'admin_notification_email', $settings ) ) {
			unset( $settings['admin_notification_email'] );
			update_option( 'ms_wc_recesso_settings', $settings );
		}

		update_option( 'ms_wc_recesso_recipient_migrated', 1 );
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
