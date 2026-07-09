<?php
/**
 * Email (HTML): admin notification of a transmitted withdrawal.
 *
 * Overridable at your-theme/woocommerce/emails/admin-notification.php.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $email_heading
 * @var \WC_Email                             $email
 */

defined( 'ABSPATH' ) || exit;

$ms_ts        = $request->confirmed_timestamp();
$ms_order     = null !== $request->order_id ? wc_get_order( $request->order_id ) : null;
$ms_order_url = $ms_order ? $ms_order->get_edit_order_url() : '';
$ms_admin_url = admin_url( 'admin.php?page=ms-wc-recesso-requests&request=' . rawurlencode( $request->public_uuid ) );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'È stata trasmessa una nuova dichiarazione di recesso.', 'ms-wc-recesso' ); ?></p>

<ul>
	<li><?php esc_html_e( 'Ordine:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->order_reference ); ?></li>
	<li><?php esc_html_e( 'Cliente:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->customer_name ); ?> (<?php echo esc_html( $request->customer_email ); ?>)</li>
	<?php if ( null !== $ms_ts ) : ?>
		<li><?php esc_html_e( 'Data e ora:', 'ms-wc-recesso' ); ?> <?php echo esc_html( wp_date( 'd/m/Y H:i', $ms_ts ) ); ?></li>
	<?php endif; ?>
	<li><?php esc_html_e( 'Riferimento:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->public_uuid ); ?></li>
</ul>

<?php if ( $request->needs_manual_review ) : ?>
	<p style="color:#8a6d00;background:#fff8e1;padding:8px 12px;border-radius:4px;">
		<strong><?php esc_html_e( 'Attenzione:', 'ms-wc-recesso' ); ?></strong>
		<?php esc_html_e( 'la richiesta è da verificare manualmente (ordine non abbinato o fuori termine stimato).', 'ms-wc-recesso' ); ?>
	</p>
<?php endif; ?>

<p>
	<a href="<?php echo esc_url( $ms_admin_url ); ?>"><?php esc_html_e( 'Apri la richiesta di recesso', 'ms-wc-recesso' ); ?></a>
	<?php if ( '' !== $ms_order_url ) : ?>
		&nbsp;·&nbsp;
		<a href="<?php echo esc_url( $ms_order_url ); ?>"><?php esc_html_e( 'Apri l’ordine', 'ms-wc-recesso' ); ?></a>
	<?php endif; ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
