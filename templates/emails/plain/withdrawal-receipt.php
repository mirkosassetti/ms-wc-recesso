<?php
/**
 * Email (plain): withdrawal receipt to the consumer.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var string                                $email_heading
 */

defined( 'ABSPATH' ) || exit;

$ms_ts    = $request->confirmed_timestamp();
$ms_items = $request->items();

echo '= ' . esc_html( $email_heading ) . " =\n\n";

printf(
	/* translators: %s: customer name. */
	esc_html__( 'Gentile %s,', 'ms-wc-recesso' ),
	esc_html( $request->customer_name )
);
echo "\n\n";

printf(
	/* translators: %s: order reference. */
	esc_html__( 'confermiamo di aver ricevuto la tua dichiarazione di recesso relativa all’ordine %s.', 'ms-wc-recesso' ),
	esc_html( $request->order_reference )
);
echo "\n\n";

if ( null !== $ms_ts ) {
	echo esc_html__( 'Data e ora della trasmissione:', 'ms-wc-recesso' ) . ' ' . esc_html( wp_date( 'd/m/Y H:i', $ms_ts ) ) . "\n\n";
}

echo esc_html__( 'Contenuto della dichiarazione', 'ms-wc-recesso' ) . "\n";
echo '- ' . esc_html__( 'Nome e cognome:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->customer_name ) . "\n";
echo '- ' . esc_html__( 'Ordine:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->order_reference ) . "\n";
echo '- ' . esc_html__( 'Email:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->customer_email ) . "\n";

if ( ! empty( $ms_items ) ) {
	echo "\n" . esc_html__( 'Articoli:', 'ms-wc-recesso' ) . "\n";
	foreach ( $ms_items as $ms_line ) {
		echo '- ' . esc_html( (string) ( $ms_line['name'] ?? '' ) ) . ' x' . esc_html( (string) ( $ms_line['quantity'] ?? 1 ) ) . "\n";
	}
}

if ( '' !== (string) $request->reason ) {
	echo "\n" . esc_html__( 'Motivo del reso:', 'ms-wc-recesso' ) . ' ' . esc_html( (string) $request->reason ) . "\n";
}

echo "\n" . esc_html__( 'Riferimento richiesta:', 'ms-wc-recesso' ) . ' ' . esc_html( $request->public_uuid ) . "\n";
