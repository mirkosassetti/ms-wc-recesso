<?php
/**
 * Admin notification email (WC_Email).
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Emails;

use MS\WcRecesso\Model\WithdrawalRequest;
use MS\WcRecesso\Support\Options;
use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * Notifies the shop admin that a withdrawal was transmitted, with a link to
 * the request (and to the linked order, when available).
 */
class AdminNotificationEmail extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'ms_wc_recesso_admin';
		$this->title          = __( 'Recesso — notifica amministratore', 'ms-wc-recesso' );
		$this->description    = __( 'Inviata all’amministratore quando un recesso viene trasmesso.', 'ms-wc-recesso' );
		$this->template_html  = 'emails/admin-notification.php';
		$this->template_plain = 'emails/plain/admin-notification.php';
		$this->template_base  = MS_WC_RECESSO_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_reference}' => '',
		);

		parent::__construct();

		$this->recipient = $this->default_recipient();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Nuova richiesta di recesso — ordine {order_reference}', 'ms-wc-recesso' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'Nuova richiesta di recesso', 'ms-wc-recesso' );
	}

	/**
	 * Recipient default: configured admin address, or the site admin email.
	 */
	private function default_recipient(): string {
		$configured = (string) Options::get( 'admin_notification_email', '' );

		return '' !== $configured ? $configured : get_option( 'admin_email' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param WithdrawalRequest $request Confirmed request.
	 */
	public function trigger( WithdrawalRequest $request ): void {
		$this->setup_locale();

		$this->object                            = $request;
		$this->placeholders['{order_reference}'] = $request->order_reference;

		if ( ! $this->get_recipient() ) {
			$this->recipient = $this->default_recipient();
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * The request to render: the real object, or a sample for previews.
	 */
	private function template_request(): WithdrawalRequest {
		return $this->object instanceof WithdrawalRequest ? $this->object : WithdrawalRequest::sample();
	}

	/**
	 * HTML body.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'request'       => $this->template_request(),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain-text body.
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'request'       => $this->template_request(),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
