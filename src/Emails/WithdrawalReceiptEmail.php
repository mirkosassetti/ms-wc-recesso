<?php
/**
 * Withdrawal receipt email (WC_Email).
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Emails;

use MS\WcRecesso\Model\RequestRepository;
use MS\WcRecesso\Model\WithdrawalRequest;
use MS\WcRecesso\Support\Dates;
use MS\WcRecesso\Support\Logger;
use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * The acknowledgement of receipt required by comma 6: sent to the consumer on
 * a durable medium, containing the declaration content and the date/time of
 * transmission. The sent subject and body are snapshotted onto the request as
 * evidence.
 */
class WithdrawalReceiptEmail extends WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'ms_wc_recesso_receipt';
		$this->customer_email = true;
		$this->title          = __( 'Recesso — ricevuta al consumatore', 'ms-wc-recesso' );
		$this->description    = __( 'Ricevuta di avvenuto recesso inviata al consumatore, con contenuto della dichiarazione e data/ora.', 'ms-wc-recesso' );
		$this->template_html  = 'emails/withdrawal-receipt.php';
		$this->template_plain = 'emails/plain/withdrawal-receipt.php';
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
		return __( 'Ricevuta di recesso — ordine {order_reference}', 'ms-wc-recesso' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'Recesso trasmesso', 'ms-wc-recesso' );
	}

	/**
	 * Trigger the email and snapshot it onto the request.
	 *
	 * @param WithdrawalRequest $request Confirmed request.
	 */
	public function trigger( WithdrawalRequest $request ): void {
		$this->setup_locale();

		$this->object                            = $request;
		$this->recipient                         = $request->customer_email;
		$this->placeholders['{order_reference}'] = $request->order_reference;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$subject = $this->get_subject();
			$content = $this->get_content();

			$this->send( $this->get_recipient(), $subject, $content, $this->get_headers(), $this->get_attachments() );
			$this->store_snapshot( $request, $subject, $content );
		}

		$this->restore_locale();
	}

	/**
	 * Persist the sent receipt (subject + body) and timestamp onto the request.
	 *
	 * @param WithdrawalRequest $request Confirmed request.
	 * @param string            $subject Sent subject.
	 * @param string            $content Sent body.
	 */
	private function store_snapshot( WithdrawalRequest $request, string $subject, string $content ): void {
		$repository = new RequestRepository();

		$repository->update(
			$request->id,
			array(
				'receipt_subject' => $subject,
				'receipt_body'    => $content,
				'receipt_sent_at' => Dates::now_mysql(),
			)
		);

		Logger::log( $request->id, 'receipt_sent', null, null, 'system', null );
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
