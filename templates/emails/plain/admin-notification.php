<?php
/**
 * Email (plain): admin notification of a transmitted withdrawal.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $email_heading
 */

defined( 'ABSPATH' ) || exit;

$ms_ts        = $request->confirmed_timestamp();
$ms_order     = null !== $request->order_id ? wc_get_order( $request->order_id ) : null;
$ms_order_url = $ms_order ? $ms_order->get_edit_order_url() : '';
$ms_admin_url = admin_url( 'admin.php?page=ms-wc-recesso-requests&request=' . rawurlencode( $request->public_uuid ) );

echo '= ' . esc_html( $email_heading ) . " =\n\n";

echo esc_html__( 'È stata trasmessa una nuova dichiarazione di recesso.', 'ms-wc-recesso' ) . "\n\n";

echo '- ' . esc_html__( 'Ordine:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->order_reference ) . "\n";
echo '- ' . esc_html__( 'Cliente:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->customer_name ) . ' (' . esc_html( $request->customer_email ) . ")\n";

if ( null !== $ms_ts ) {
	echo '- ' . esc_html__( 'Data e ora:', 'ms-wc-recesso' ) . ' ' . esc_html( wp_date( 'd/m/Y H:i', $ms_ts ) ) . "\n";
}

echo '- ' . esc_html__( 'Riferimento:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->public_uuid ) . "\n";

if ( $request->needs_manual_review ) {
	echo "\n" . esc_html__( 'ATTENZIONE: richiesta da verificare manualmente (ordine non abbinato o fuori termine stimato).', 'ms-wc-recesso' ) . "\n";
}

echo "\n" . esc_html__( 'Apri la richiesta:', 'ms-wc-recesso' ) . ' ' . esc_url_raw( $ms_admin_url ) . "\n";

if ( '' !== $ms_order_url ) {
	echo esc_html__( 'Apri l’ordine:', 'ms-wc-recesso' ) . ' ' . esc_url_raw( $ms_order_url ) . "\n";
}
