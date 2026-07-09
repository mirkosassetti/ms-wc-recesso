<?php
/**
 * Email (HTML): guest verification link.
 *
 * Overridable at your-theme/woocommerce/emails/guest-verification.php.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $verify_url
 * @var string                                $email_heading
 * @var \WC_Email                             $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Abbiamo ricevuto una richiesta di recesso relativa al seguente ordine:', 'ms-wc-recesso' ); ?></p>

<p><strong><?php echo esc_html( $request->order_reference ); ?></strong></p>

<p><?php esc_html_e( 'Per proseguire e trasmettere la dichiarazione di recesso, apri il link seguente (valido 48 ore):', 'ms-wc-recesso' ); ?></p>

<p>
	<a href="<?php echo esc_url( $verify_url ); ?>" style="display:inline-block;padding:10px 18px;background:#2c3338;color:#ffffff;text-decoration:none;border-radius:6px;">
		<?php esc_html_e( 'Prosegui con il recesso', 'ms-wc-recesso' ); ?>
	</a>
</p>

<p style="font-size:12px;color:#777;"><?php esc_html_e( 'Se non hai richiesto tu il recesso, ignora questa email.', 'ms-wc-recesso' ); ?></p>

<?php
do_action( 'woocommerce_email_footer', $email );
