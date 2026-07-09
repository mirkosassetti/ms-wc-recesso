<?php
/**
 * Template: guest withdrawal declaration form (after email verification).
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type \MS\WcRecesso\Model\WithdrawalRequest $request  The verified request.
 *     @type \WC_Order|null                        $order    Matched order, or null.
 *     @type array<int,array<string,mixed>>        $items    Line items with eligibility.
 *     @type array{within:bool}                    $window   Window status.
 *     @type array<string,mixed>                   $prefill  Prefilled values.
 *     @type array<int,string>                     $errors   Validation errors.
 *     @type string                                $token    Raw token.
 *     @type string                                $base_url Form action URL.
 *     @type string                                $nonce    Nonce value.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_request  = $args['request'];
$ms_order    = isset( $args['order'] ) ? $args['order'] : null;
$ms_items    = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
$ms_window   = isset( $args['window'] ) ? $args['window'] : array( 'within' => true );
$ms_prefill  = isset( $args['prefill'] ) && is_array( $args['prefill'] ) ? $args['prefill'] : array();
$ms_errors   = isset( $args['errors'] ) && is_array( $args['errors'] ) ? $args['errors'] : array();
$ms_token    = isset( $args['token'] ) ? (string) $args['token'] : '';
$ms_base_url = isset( $args['base_url'] ) ? (string) $args['base_url'] : '';
$ms_nonce    = isset( $args['nonce'] ) ? (string) $args['nonce'] : '';

$ms_matched  = $ms_order instanceof WC_Order;
$ms_selected = isset( $ms_prefill['selected_ids'] ) && is_array( $ms_prefill['selected_ids'] ) ? array_map( 'absint', $ms_prefill['selected_ids'] ) : array();
$ms_name     = isset( $ms_prefill['name'] ) ? (string) $ms_prefill['name'] : '';
$ms_reason   = isset( $ms_prefill['reason'] ) ? (string) $ms_prefill['reason'] : '';
?>
<div class="ms-recesso ms-recesso--form ms-recesso--guest">
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

	<?php if ( ! $ms_matched ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--warning" role="status">
			<?php esc_html_e( 'Non siamo riusciti ad abbinare automaticamente l’ordine indicato. Puoi comunque trasmettere la dichiarazione: sarà registrata con data e ora e verificata manualmente.', 'ms-wc-recesso' ); ?>
		</div>
	<?php elseif ( empty( $ms_window['within'] ) ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--warning" role="status">
			<?php esc_html_e( 'In base ai nostri dati il periodo di recesso potrebbe essere scaduto. Puoi comunque inviare la dichiarazione: sarà registrata con data e ora e verificata dal nostro personale.', 'ms-wc-recesso' ); ?>
		</div>
	<?php endif; ?>

	<form class="ms-recesso__form" method="post" action="<?php echo esc_url( $ms_base_url ); ?>">
		<input type="hidden" name="ms_wc_recesso_action" value="declaration" />
		<input type="hidden" name="_ms_wc_recesso_nonce" value="<?php echo esc_attr( $ms_nonce ); ?>" />
		<input type="hidden" name="ms_recesso_token" value="<?php echo esc_attr( $ms_token ); ?>" />

		<p class="ms-recesso__field ms-recesso__field--static">
			<span class="ms-recesso__label"><?php esc_html_e( 'Ordine', 'ms-wc-recesso' ); ?></span>
			<span class="ms-recesso__value"><?php echo esc_html( $ms_request->order_reference ); ?></span>
		</p>

		<p class="ms-recesso__field ms-recesso__field--static">
			<span class="ms-recesso__label"><?php esc_html_e( 'Email per la conferma', 'ms-wc-recesso' ); ?></span>
			<span class="ms-recesso__value"><?php echo esc_html( $ms_request->customer_email ); ?></span>
		</p>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-name"><?php esc_html_e( 'Nome e cognome', 'ms-wc-recesso' ); ?></label>
			<input type="text" id="ms-recesso-name" name="customer_name" value="<?php echo esc_attr( $ms_name ); ?>" required />
		</p>

		<?php if ( $ms_matched ) : ?>
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
		<?php endif; ?>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-reason"><?php esc_html_e( 'Motivo del reso (facoltativo)', 'ms-wc-recesso' ); ?></label>
			<textarea id="ms-recesso-reason" name="reason" rows="3"><?php echo esc_textarea( $ms_reason ); ?></textarea>
		</p>

		<p class="ms-recesso__actions">
			<button type="submit" class="button ms-recesso__submit"><?php esc_html_e( 'Prosegui al riepilogo', 'ms-wc-recesso' ); ?></button>
		</p>
	</form>
</div>
