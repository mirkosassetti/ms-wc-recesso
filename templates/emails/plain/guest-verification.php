<?php
/**
 * Email (plain): guest verification link.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $verify_url
 * @var string                                $email_heading
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

echo esc_html__( 'Abbiamo ricevuto una richiesta di recesso relativa al seguente ordine:', 'ms-wc-recesso' ) . "\n";
echo esc_html( $request->order_reference ) . "\n\n";

echo esc_html__( 'Per proseguire e trasmettere la dichiarazione di recesso, apri il link seguente (valido 48 ore):', 'ms-wc-recesso' ) . "\n";
echo esc_url_raw( $verify_url ) . "\n\n";

echo esc_html__( 'Se non hai richiesto tu il recesso, ignora questa email.', 'ms-wc-recesso' ) . "\n";
