<?php
/**
 * Template: withdrawal summary (step 2) with the separate confirmation button.
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type \MS\WcRecesso\Model\WithdrawalRequest $request  The persisted draft.
 *     @type array{within:bool,deadline:mixed}     $window   Window status.
 *     @type string                                $base_url Form action URL.
 *     @type string                                $edit_url URL to edit the declaration.
 *     @type string                                $nonce    Nonce value.
 * }
 */

defined( 'ABSPATH' ) || exit;

use MS\WcRecesso\Support\Options;

$ms_request  = $args['request'];
$ms_window   = isset( $args['window'] ) ? $args['window'] : array( 'within' => true );
$ms_base_url = isset( $args['base_url'] ) ? (string) $args['base_url'] : '';
$ms_edit_url = isset( $args['edit_url'] ) ? (string) $args['edit_url'] : '';
$ms_nonce    = isset( $args['nonce'] ) ? (string) $args['nonce'] : '';
$ms_token    = isset( $args['token'] ) ? (string) $args['token'] : '';
$ms_notice   = isset( $args['notice'] ) ? (string) $args['notice'] : '';

$ms_confirm_label = (string) Options::get( 'confirm_label', 'Conferma recesso' );
$ms_items         = $ms_request->items();
?>
<div class="ms-recesso ms-recesso--summary">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Riepilogo della dichiarazione', 'ms-wc-recesso' ); ?></h2>

	<p class="ms-recesso__intro">
		<?php esc_html_e( 'Controlla i dati. Il recesso sarà trasmesso solo dopo aver premuto il pulsante di conferma.', 'ms-wc-recesso' ); ?>
	</p>

	<dl class="ms-recesso__recap">
		<dt><?php esc_html_e( 'Nome e cognome', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo esc_html( $ms_request->customer_name ); ?></dd>

		<dt><?php esc_html_e( 'Ordine', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo esc_html( $ms_request->order_reference ); ?></dd>

		<dt><?php esc_html_e( 'Email per la conferma', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo esc_html( $ms_request->customer_email ); ?></dd>

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

		<dt><?php esc_html_e( 'Motivo del reso', 'ms-wc-recesso' ); ?></dt>
		<dd><?php echo '' !== (string) $ms_request->reason ? esc_html( (string) $ms_request->reason ) : esc_html__( 'Non specificato', 'ms-wc-recesso' ); ?></dd>
	</dl>

	<?php if ( '' !== $ms_notice ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--warning" role="status">
			<?php echo esc_html( $ms_notice ); ?>
		</div>
	<?php elseif ( empty( $ms_window['within'] ) ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--warning" role="status">
			<?php esc_html_e( 'Nota: la richiesta risulta oltre il termine stimato e sarà verificata manualmente. La data e l’ora di trasmissione faranno comunque fede.', 'ms-wc-recesso' ); ?>
		</div>
	<?php endif; ?>

	<form class="ms-recesso__confirm-form" method="post" action="<?php echo esc_url( $ms_base_url ); ?>">
		<input type="hidden" name="ms_wc_recesso_action" value="confirm" />
		<input type="hidden" name="_ms_wc_recesso_nonce" value="<?php echo esc_attr( $ms_nonce ); ?>" />
		<input type="hidden" name="request_uuid" value="<?php echo esc_attr( $ms_request->public_uuid ); ?>" />
		<?php if ( '' !== $ms_token ) : ?>
			<input type="hidden" name="ms_recesso_token" value="<?php echo esc_attr( $ms_token ); ?>" />
		<?php endif; ?>

		<p class="ms-recesso__actions">
			<?php if ( '' !== $ms_edit_url ) : ?>
				<a class="ms-recesso__edit" href="<?php echo esc_url( $ms_edit_url ); ?>"><?php esc_html_e( 'Modifica', 'ms-wc-recesso' ); ?></a>
			<?php endif; ?>
			<button type="submit" class="button ms-recesso__submit ms-recesso__submit--confirm">
				<?php echo esc_html( '' !== $ms_confirm_label ? $ms_confirm_label : __( 'Conferma recesso', 'ms-wc-recesso' ) ); ?>
			</button>
		</p>
	</form>
</div>
