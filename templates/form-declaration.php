<?php
/**
 * Template: withdrawal declaration form (step 1).
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type \WC_Order                          $order    The order.
 *     @type array<int,array<string,mixed>>     $items    Line items with eligibility.
 *     @type array{within:bool,deadline:mixed}  $window   Window status.
 *     @type array<string,mixed>                $prefill  Prefilled values.
 *     @type array<int,string>                  $errors   Validation errors.
 *     @type string                             $base_url Form action URL.
 *     @type string                             $nonce    Nonce value.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_order    = $args['order'];
$ms_items    = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$ms_window   = isset( $args['window'] ) ? $args['window'] : array( 'within' => true );
$ms_prefill  = isset( $args['prefill'] ) && is_array( $args['prefill'] ) ? $args['prefill'] : array();
$ms_errors   = isset( $args['errors'] ) && is_array( $args['errors'] ) ? $args['errors'] : array();
$ms_base_url = isset( $args['base_url'] ) ? (string) $args['base_url'] : '';
$ms_nonce    = isset( $args['nonce'] ) ? (string) $args['nonce'] : '';

$ms_selected = isset( $ms_prefill['selected_ids'] ) && is_array( $ms_prefill['selected_ids'] ) ? array_map( 'absint', $ms_prefill['selected_ids'] ) : array();
$ms_name     = isset( $ms_prefill['name'] ) ? (string) $ms_prefill['name'] : '';
$ms_email    = isset( $ms_prefill['email'] ) ? (string) $ms_prefill['email'] : '';
$ms_reason   = isset( $ms_prefill['reason'] ) ? (string) $ms_prefill['reason'] : '';
?>
<div class="ms-recesso ms-recesso--form">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Dichiarazione di recesso', 'ms-wc-recesso' ); ?></h2>

	<?php if ( ! empty( $ms_errors ) ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--error" role="alert">
			<ul>
				<?php foreach ( $ms_errors as $ms_error ) : ?>
					<li><?php echo esc_html( $ms_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( empty( $ms_window['within'] ) ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--warning" role="status">
			<?php esc_html_e( 'In base ai nostri dati il periodo di recesso potrebbe essere scaduto. Puoi comunque inviare la dichiarazione: sarà registrata con data e ora e verificata dal nostro personale.', 'ms-wc-recesso' ); ?>
		</div>
	<?php endif; ?>

	<form class="ms-recesso__form" method="post" action="<?php echo esc_url( $ms_base_url ); ?>">
		<input type="hidden" name="ms_wc_recesso_action" value="declaration" />
		<input type="hidden" name="_ms_wc_recesso_nonce" value="<?php echo esc_attr( $ms_nonce ); ?>" />
		<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $ms_order->get_id() ); ?>" />

		<p class="ms-recesso__field ms-recesso__field--static">
			<span class="ms-recesso__label"><?php esc_html_e( 'Ordine', 'ms-wc-recesso' ); ?></span>
			<span class="ms-recesso__value">
				<?php
				printf(
					/* translators: 1: order number, 2: order date. */
					esc_html__( 'n. %1$s del %2$s', 'ms-wc-recesso' ),
					esc_html( $ms_order->get_order_number() ),
					esc_html( wc_format_datetime( $ms_order->get_date_created() ) )
				);
				?>
			</span>
		</p>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-name"><?php esc_html_e( 'Nome e cognome', 'ms-wc-recesso' ); ?></label>
			<input type="text" id="ms-recesso-name" name="customer_name" value="<?php echo esc_attr( $ms_name ); ?>" required />
		</p>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-email"><?php esc_html_e( 'Email per la conferma', 'ms-wc-recesso' ); ?></label>
			<input type="email" id="ms-recesso-email" name="customer_email" value="<?php echo esc_attr( $ms_email ); ?>" required aria-describedby="ms-recesso-email-help" />
			<small id="ms-recesso-email-help" class="ms-recesso__help"><?php esc_html_e( 'Riceverai qui la conferma con data e ora della trasmissione.', 'ms-wc-recesso' ); ?></small>
		</p>

		<fieldset class="ms-recesso__items">
			<legend class="ms-recesso__label"><?php esc_html_e( 'Articoli dai quali recedere', 'ms-wc-recesso' ); ?></legend>

			<?php foreach ( $ms_items as $ms_line ) : ?>
				<?php
				$ms_id       = (int) $ms_line['order_item_id'];
				$ms_eligible = ! empty( $ms_line['eligible'] );
				$ms_checked  = $ms_eligible && in_array( $ms_id, $ms_selected, true );
				$ms_field_id = 'ms-recesso-item-' . $ms_id;
				?>
				<label class="ms-recesso__item <?php echo $ms_eligible ? '' : 'ms-recesso__item--excluded'; ?>" for="<?php echo esc_attr( $ms_field_id ); ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $ms_field_id ); ?>"
						name="items[]"
						value="<?php echo esc_attr( (string) $ms_id ); ?>"
						<?php checked( $ms_checked ); ?>
						<?php disabled( ! $ms_eligible ); ?>
					/>
					<span class="ms-recesso__item-name">
						<?php echo esc_html( $ms_line['name'] ); ?>
						<span class="ms-recesso__item-qty">&times; <?php echo esc_html( (string) $ms_line['quantity'] ); ?></span>
					</span>
					<?php if ( ! $ms_eligible && '' !== (string) $ms_line['reason'] ) : ?>
						<span class="ms-recesso__item-reason"><?php echo esc_html( $ms_line['reason'] ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-reason">
				<?php esc_html_e( 'Motivo del reso (facoltativo)', 'ms-wc-recesso' ); ?>
			</label>
			<textarea id="ms-recesso-reason" name="reason" rows="3"><?php echo esc_textarea( $ms_reason ); ?></textarea>
		</p>

		<p class="ms-recesso__actions">
			<button type="submit" class="button ms-recesso__submit">
				<?php esc_html_e( 'Prosegui al riepilogo', 'ms-wc-recesso' ); ?>
			</button>
		</p>
	</form>
</div>
