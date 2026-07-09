<?php
/**
 * Email (HTML): withdrawal receipt to the consumer.
 *
 * Overridable at your-theme/woocommerce/emails/withdrawal-receipt.php.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $email_heading
 * @var \WC_Email                             $email
 */

defined( 'ABSPATH' ) || exit;

$ms_ts    = $request->confirmed_timestamp();
$ms_items = $request->items();

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: customer name. */
		esc_html__( 'Gentile %s,', 'ms-wc-recesso' ),
		esc_html( $request->customer_name )
	);
	?>
</p>

<p>
	<?php
	printf(
		/* translators: %s: order reference. */
		esc_html__( 'confermiamo di aver ricevuto la tua dichiarazione di recesso relativa all’ordine %s.', 'ms-wc-recesso' ),
		'<strong>' . esc_html( $request->order_reference ) . '</strong>'
	);
	?>
</p>

<?php if ( null !== $ms_ts ) : ?>
	<p>
		<strong><?php esc_html_e( 'Data e ora della trasmissione:', 'ms-wc-recesso' ); ?></strong>
		<?php echo esc_html( wp_date( 'd/m/Y H:i', $ms_ts ) ); ?>
	</p>
<?php endif; ?>

<h2><?php esc_html_e( 'Contenuto della dichiarazione', 'ms-wc-recesso' ); ?></h2>

<ul>
	<li><?php esc_html_e( 'Nome e cognome:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->customer_name ); ?></li>
	<li><?php esc_html_e( 'Ordine:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->order_reference ); ?></li>
	<li><?php esc_html_e( 'Email:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->customer_email ); ?></li>
</ul>

<?php if ( ! empty( $ms_items ) ) : ?>
	<p><strong><?php esc_html_e( 'Articoli:', 'ms-wc-recesso' ); ?></strong></p>
	<ul>
		<?php foreach ( $ms_items as $ms_line ) : ?>
			<li>
				<?php echo esc_html( (string) ( $ms_line['name'] ?? '' ) ); ?>
				&times; <?php echo esc_html( (string) ( $ms_line['quantity'] ?? 1 ) ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( '' !== (string) $request->reason ) : ?>
	<p><strong><?php esc_html_e( 'Motivo del reso:', 'ms-wc-recesso' ); ?></strong> <?php echo esc_html( (string) $request->reason ); ?></p>
<?php endif; ?>

<p style="font-size:12px;color:#777;">
	<?php esc_html_e( 'Riferimento richiesta:', 'ms-wc-recesso' ); ?> <?php echo esc_html( $request->public_uuid ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
