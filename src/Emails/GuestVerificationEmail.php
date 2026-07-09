<?php
/**
 * Guest verification email (WC_Email).
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Emails;

use MS\WcRecesso\Model\WithdrawalRequest;
use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the guest the one-time link needed to access the withdrawal
 * declaration. Editable and toggleable under WooCommerce > Settings > Emails,
 * with theme-overridable templates under woocommerce/emails/.
 */
class GuestVerificationEmail extends WC_Email {

	/**
	 * Verification URL for the current trigger.
	 *
	 * @var string
	 */
	protected string $verify_url = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'ms_wc_recesso_guest_verification';
		$this->customer_email = true;
		$this->title          = __( 'Recesso — verifica email (ospite)', 'ms-wc-recesso' );
		$this->description    = __( 'Inviata all’ospite per confermare l’email e accedere alla dichiarazione di recesso.', 'ms-wc-recesso' );
		$this->template_html  = 'emails/guest-verification.php';
		$this->template_plain = 'emails/plain/guest-verification.php';
		$this->template_base  = MS_WC_RECESSO_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_reference}' => '',
		);

		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( 'Conferma la tua richiesta di recesso', 'ms-wc-recesso' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'Richiesta di recesso', 'ms-wc-recesso' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param WithdrawalRequest $request    Pending request.
	 * @param string            $verify_url One-time verification URL.
	 */
	public function trigger( WithdrawalRequest $request, string $verify_url ): void {
		$this->setup_locale();

		$this->object                            = $request;
		$this->verify_url                        = $verify_url;
		$this->recipient                         = $request->customer_email;
		$this->placeholders['{order_reference}'] = $request->order_reference;

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
	 * The verification URL to render: the real one, or a placeholder for previews.
	 */
	private function template_verify_url(): string {
		return '' !== $this->verify_url ? $this->verify_url : home_url( '/' );
	}

	/**
	 * HTML body.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'request'       => $this->template_request(),
				'verify_url'    => $this->template_verify_url(),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
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
				'verify_url'    => $this->template_verify_url(),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
