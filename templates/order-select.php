<?php
/**
 * Template: order selection step (logged-in).
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type array<int,\WC_Order> $orders   Eligible orders.
 *     @type string              $base_url  Flow base URL.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_orders   = isset( $args['orders'] ) && is_array( $args['orders'] ) ? $args['orders'] : array();
$ms_base_url = isset( $args['base_url'] ) ? (string) $args['base_url'] : '';
?>
<div class="ms-recesso ms-recesso--select">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Recesso dal contratto', 'ms-wc-recesso' ); ?></h2>

	<p class="ms-recesso__intro">
		<?php esc_html_e( 'Seleziona l’ordine dal quale desideri recedere. Potrai poi scegliere gli articoli e confermare.', 'ms-wc-recesso' ); ?>
	</p>

	<?php if ( empty( $ms_orders ) ) : ?>
		<p class="ms-recesso-notice ms-recesso-notice--info">
			<?php esc_html_e( 'Non risultano ordini idonei associati al tuo account.', 'ms-wc-recesso' ); ?>
		</p>
	<?php else : ?>
		<ul class="ms-recesso__orders">
			<?php foreach ( $ms_orders as $ms_order ) : ?>
				<?php
				$ms_link = esc_url( add_query_arg( 'order', $ms_order->get_id(), $ms_base_url ) );
				?>
				<li class="ms-recesso__order">
					<div class="ms-recesso__order-info">
						<span class="ms-recesso__order-number">
							<?php
							/* translators: %s: order number. */
							printf( esc_html__( 'Ordine n. %s', 'ms-wc-recesso' ), esc_html( $ms_order->get_order_number() ) );
							?>
						</span>
						<span class="ms-recesso__order-date">
							<?php echo esc_html( wc_format_datetime( $ms_order->get_date_created() ) ); ?>
						</span>
						<span class="ms-recesso__order-total">
							<?php echo wp_kses_post( $ms_order->get_formatted_order_total() ); ?>
						</span>
					</div>
					<a class="ms-recesso__order-action" href="<?php echo $ms_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
						<?php esc_html_e( 'Recedere da questo ordine', 'ms-wc-recesso' ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
