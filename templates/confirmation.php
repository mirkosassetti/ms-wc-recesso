<?php
/**
 * Template: post-confirmation screen.
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type \MS\WcRecesso\Model\WithdrawalRequest $request The confirmed request.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_request = $args['request'];
$ms_ts      = $ms_request->confirmed_timestamp();
$ms_items   = $ms_request->items();
?>
<div class="ms-recesso ms-recesso--confirmed">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Recesso trasmesso', 'ms-wc-recesso' ); ?></h2>

	<div class="ms-recesso-notice ms-recesso-notice--success" role="status">
		<?php esc_html_e( 'La tua dichiarazione di recesso è stata trasmessa correttamente.', 'ms-wc-recesso' ); ?>
	</div>

	<?php if ( null !== $ms_ts ) : ?>
		<p class="ms-recesso__timestamp">
			<?php
			printf(
				/* translators: %s: confirmation date and time. */
				esc_html__( 'Data e ora della trasmissione: %s', 'ms-wc-recesso' ),
				esc_html( wp_date( 'd/m/Y H:i', $ms_ts ) )
			);
			?>
		</p>
	<?php endif; ?>

	<dl class="ms-recesso__recap">
		<dt><?php esc_html_e( 'Riferimento richiesta', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo esc_html( $ms_request->public_uuid ); ?></dd>

		<dt><?php esc_html_e( 'Ordine', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo esc_html( $ms_request->order_reference ); ?></dd>

		<dt><?php esc_html_e( 'Articoli', 'ms-wc-recesso' ); ?></dt>
		<dd>
			<ul class="ms-recesso__recap-items">
				<?php foreach ( $ms_items as $ms_line ) : ?>
					<li>
						<?php echo esc_html( (string) ( $ms_line['name'] ?? '' ) ); ?>
						<span class="ms-recesso__item-qty">&times; <?php echo esc_html( (string) ( $ms_line['quantity'] ?? 1 ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</dd>
	</dl>

	<p class="ms-recesso__note">
		<?php
		printf(
			/* translators: %s: customer email. */
			esc_html__( 'A breve riceverai un’email di conferma all’indirizzo %s con il contenuto della dichiarazione e la data/ora della trasmissione.', 'ms-wc-recesso' ),
			esc_html( $ms_request->customer_email )
		);
		?>
	</p>
</div>
