<?php
/**
 * Template: public guest lookup form.
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type string              $base_url Form action URL.
 *     @type string              $nonce    Nonce value.
 *     @type array<string,mixed> $prefill  Prefilled values.
 *     @type array<int,string>   $errors   Validation errors.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_base_url = isset( $args['base_url'] ) ? (string) $args['base_url'] : '';
$ms_nonce    = isset( $args['nonce'] ) ? (string) $args['nonce'] : '';
$ms_prefill  = isset( $args['prefill'] ) && is_array( $args['prefill'] ) ? $args['prefill'] : array();
$ms_errors   = isset( $args['errors'] ) && is_array( $args['errors'] ) ? $args['errors'] : array();

$ms_reference = isset( $ms_prefill['reference'] ) ? (string) $ms_prefill['reference'] : '';
$ms_email     = isset( $ms_prefill['email'] ) ? (string) $ms_prefill['email'] : '';
$ms_honeypot  = ! isset( $args['honeypot'] ) || (bool) $args['honeypot'];
?>
<div class="ms-recesso ms-recesso--guest-lookup">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Recesso dal contratto', 'ms-wc-recesso' ); ?></h2>

	<p class="ms-recesso__intro">
		<?php esc_html_e( 'Hai acquistato senza account? Inserisci il numero dell’ordine e l’email usata in fase di acquisto: ti invieremo un link per proseguire con la dichiarazione di recesso.', 'ms-wc-recesso' ); ?>
	</p>

	<?php if ( ! empty( $ms_errors ) ) : ?>
		<div class="ms-recesso-notice ms-recesso-notice--error" role="alert">
			<ul>
				<?php foreach ( $ms_errors as $ms_error ) : ?>
					<li><?php echo esc_html( $ms_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form class="ms-recesso__form" method="post" action="<?php echo esc_url( $ms_base_url ); ?>">
		<input type="hidden" name="ms_wc_recesso_action" value="guest_lookup" />
		<input type="hidden" name="_ms_wc_recesso_nonce" value="<?php echo esc_attr( $ms_nonce ); ?>" />

		<?php if ( $ms_honeypot ) : ?>
			<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
				<label for="ms-recesso-website">Website</label>
				<input type="text" id="ms-recesso-website" name="website" value="" tabindex="-1" autocomplete="off" />
			</div>
		<?php endif; ?>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-reference"><?php esc_html_e( 'Numero dell’ordine', 'ms-wc-recesso' ); ?></label>
			<input type="text" id="ms-recesso-reference" name="order_reference" value="<?php echo esc_attr( $ms_reference ); ?>" required />
		</p>

		<p class="ms-recesso__field">
			<label class="ms-recesso__label" for="ms-recesso-email"><?php esc_html_e( 'Email usata per l’ordine', 'ms-wc-recesso' ); ?></label>
			<input type="email" id="ms-recesso-email" name="customer_email" value="<?php echo esc_attr( $ms_email ); ?>" required />
		</p>

		<p class="ms-recesso__actions">
			<button type="submit" class="button ms-recesso__submit"><?php esc_html_e( 'Prosegui', 'ms-wc-recesso' ); ?></button>
		</p>
	</form>
</div>
