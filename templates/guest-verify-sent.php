<?php
/**
 * Template: "verification email sent" screen.
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $args {
 *     @type string $email The address the link was sent to.
 * }
 */

defined( 'ABSPATH' ) || exit;

$ms_email = isset( $args['email'] ) ? (string) $args['email'] : '';
?>
<div class="ms-recesso ms-recesso--guest-sent">
	<h2 class="ms-recesso__title"><?php esc_html_e( 'Controlla la tua email', 'ms-wc-recesso' ); ?></h2>

	<div class="ms-recesso-notice ms-recesso-notice--success" role="status">
		<?php
		printf(
			/* translators: %s: email address. */
			wp_kses_post( __( 'Ti abbiamo inviato un’email a %s con un link per proseguire con la dichiarazione di recesso. Il link è valido 48 ore.', 'ms-wc-recesso' ) ),
			'<strong>' . esc_html( $ms_email ) . '</strong>'
		);
		?>
	</div>

	<p class="ms-recesso__note">
		<?php esc_html_e( 'Se non ricevi l’email entro pochi minuti, controlla la cartella spam.', 'ms-wc-recesso' ); ?>
	</p>
</div>
