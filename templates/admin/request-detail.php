<?php
/**
 * Admin template: withdrawal request detail with timeline and actions.
 *
 * Included from AdminPages::render_detail() with $request, $logs, $order.
 *
 * @package MS\WcRecesso
 *
 * @var \MS\WcRecesso\Model\WithdrawalRequest $request
 * @var array<int,object>                     $logs
 * @var \WC_Order|null                        $order
 */

defined( 'ABSPATH' ) || exit;

$ms_fmt = static function ( $utc ) {
	if ( empty( $utc ) ) {
		return '—';
	}
	$ts = strtotime( $utc . ' UTC' );
	return false === $ts ? (string) $utc : wp_date( 'd/m/Y H:i', $ts );
};

$ms_items       = $request->items();
$ms_transitions = $request->status_enum()->allowed_transitions();
$ms_list_url    = admin_url( 'admin.php?page=' . \MS\WcRecesso\Admin\RequestsListTable::PAGE );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag after a redirect.
$ms_updated = isset( $_GET['updated'] );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Dettaglio richiesta di recesso', 'ms-wc-recesso' ); ?></h1>
	<a href="<?php echo esc_url( $ms_list_url ); ?>" class="page-title-action"><?php esc_html_e( 'Torna all’elenco', 'ms-wc-recesso' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( $ms_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Stato aggiornato.', 'ms-wc-recesso' ); ?></p></div>
	<?php endif; ?>

	<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
		<div style="flex:1 1 420px;">
			<h2><?php esc_html_e( 'Dati', 'ms-wc-recesso' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th><?php esc_html_e( 'Riferimento', 'ms-wc-recesso' ); ?></th><td><?php echo esc_html( $request->public_uuid ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Stato', 'ms-wc-recesso' ); ?></th><td><?php echo esc_html( $request->status_enum()->label() ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Cliente', 'ms-wc-recesso' ); ?></th><td><?php echo esc_html( $request->customer_name ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'ms-wc-recesso' ); ?></th><td><?php echo esc_html( $request->customer_email ); ?></td></tr>
					<tr>
						<th><?php esc_html_e( 'Ordine', 'ms-wc-recesso' ); ?></th>
						<td>
							<?php echo esc_html( $request->order_reference ); ?>
							<?php if ( $order instanceof WC_Order ) : ?>
								— <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'apri ordine', 'ms-wc-recesso' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr><th><?php esc_html_e( 'Verifica manuale', 'ms-wc-recesso' ); ?></th><td><?php echo $request->needs_manual_review ? esc_html__( 'Sì', 'ms-wc-recesso' ) : esc_html__( 'No', 'ms-wc-recesso' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Trasmesso il', 'ms-wc-recesso' ); ?></th><td><?php echo esc_html( $ms_fmt( $request->confirmed_at ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Motivo', 'ms-wc-recesso' ); ?></th><td><?php echo '' !== (string) $request->reason ? esc_html( (string) $request->reason ) : '—'; ?></td></tr>
				</tbody>
			</table>

			<?php if ( ! empty( $ms_items ) ) : ?>
				<h2><?php esc_html_e( 'Articoli', 'ms-wc-recesso' ); ?></h2>
				<ul>
					<?php foreach ( $ms_items as $ms_line ) : ?>
						<li><?php echo esc_html( (string) ( $ms_line['name'] ?? '' ) ); ?> &times; <?php echo esc_html( (string) ( $ms_line['quantity'] ?? 1 ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $request->receipt_subject ) ) : ?>
				<h2><?php esc_html_e( 'Ricevuta inviata', 'ms-wc-recesso' ); ?></h2>
				<p><strong><?php echo esc_html( (string) $request->receipt_subject ); ?></strong> — <?php echo esc_html( $ms_fmt( $request->receipt_sent_at ) ); ?></p>
			<?php endif; ?>
		</div>

		<div style="flex:0 0 300px;">
			<h2><?php esc_html_e( 'Gestione', 'ms-wc-recesso' ); ?></h2>
			<?php if ( ! empty( $ms_transitions ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ms_wc_recesso_change_status" />
					<input type="hidden" name="request" value="<?php echo esc_attr( $request->public_uuid ); ?>" />
					<?php wp_nonce_field( 'ms_wc_recesso_change_status' ); ?>
					<p>
						<label for="ms-recesso-new-status"><?php esc_html_e( 'Nuovo stato:', 'ms-wc-recesso' ); ?></label><br />
						<select id="ms-recesso-new-status" name="new_status">
							<?php foreach ( $ms_transitions as $ms_status ) : ?>
								<option value="<?php echo esc_attr( $ms_status->value ); ?>"><?php echo esc_html( $ms_status->label() ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Aggiorna stato', 'ms-wc-recesso' ); ?></button></p>
				</form>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Nessuna transizione disponibile da questo stato.', 'ms-wc-recesso' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Cronologia', 'ms-wc-recesso' ); ?></h2>
			<ul class="ms-recesso-timeline">
				<?php foreach ( $logs as $ms_log ) : ?>
					<li style="margin-bottom:8px;">
						<code><?php echo esc_html( (string) $ms_log->event ); ?></code>
						<?php if ( ! empty( $ms_log->to_status ) ) : ?>
							<?php echo esc_html( (string) $ms_log->from_status ); ?> &rarr; <?php echo esc_html( (string) $ms_log->to_status ); ?>
						<?php endif; ?>
						<br />
						<span class="description">
							<?php echo esc_html( $ms_fmt( $ms_log->created_at ) ); ?> · <?php echo esc_html( (string) $ms_log->actor_type ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
