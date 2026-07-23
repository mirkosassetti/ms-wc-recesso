<?php
/**
 * Admin template: settings page.
 *
 * Included from SettingsPage::render() with $options.
 *
 * @package MS\WcRecesso
 *
 * @var array<string,mixed> $options
 */

defined( 'ABSPATH' ) || exit;

$ms_emails_url = admin_url( 'admin.php?page=wc-settings&tab=email' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Impostazioni recesso 54-bis', 'ms-wc-recesso' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'ms_wc_recesso_settings_group' ); ?>

		<h2><?php esc_html_e( 'Pulsante e posizionamenti', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ms-button-label"><?php esc_html_e( 'Testo del pulsante', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="text" id="ms-button-label" class="regular-text" name="ms_wc_recesso_settings[button_label]" value="<?php echo esc_attr( (string) $options['button_label'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ms-confirm-label"><?php esc_html_e( 'Testo del pulsante di conferma', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="text" id="ms-confirm-label" class="regular-text" name="ms_wc_recesso_settings[confirm_label]" value="<?php echo esc_attr( (string) $options['confirm_label'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Posizionamenti', 'ms-wc-recesso' ); ?></th>
				<td>
					<label><input type="checkbox" name="ms_wc_recesso_settings[placement_footer]" value="1" <?php checked( ! empty( $options['placement_footer'] ) ); ?> /> <?php esc_html_e( 'Pulsante nel footer del sito', 'ms-wc-recesso' ); ?></label><br />
					<label><input type="checkbox" name="ms_wc_recesso_settings[placement_orders_list]" value="1" <?php checked( ! empty( $options['placement_orders_list'] ) ); ?> /> <?php esc_html_e( 'Link nella lista ordini (Il mio account)', 'ms-wc-recesso' ); ?></label><br />
					<label><input type="checkbox" name="ms_wc_recesso_settings[placement_view_order]" value="1" <?php checked( ! empty( $options['placement_view_order'] ) ); ?> /> <?php esc_html_e( 'Link nel dettaglio ordine', 'ms-wc-recesso' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Finestra di recesso', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ms-window-days"><?php esc_html_e( 'Giorni di recesso', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="number" min="1" id="ms-window-days" name="ms_wc_recesso_settings[window_days]" value="<?php echo esc_attr( (string) $options['window_days'] ); ?>" /> <span class="description"><?php esc_html_e( 'default 14', 'ms-wc-recesso' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="ms-completion-delta"><?php esc_html_e( 'Giorni stimati di consegna dopo il completamento', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="number" min="0" id="ms-completion-delta" name="ms_wc_recesso_settings[completion_delta_days]" value="<?php echo esc_attr( (string) $options['completion_delta_days'] ); ?>" /> <span class="description"><?php esc_html_e( 'aggiunto alla data di completamento (default 2)', 'ms-wc-recesso' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="ms-creation-delta"><?php esc_html_e( 'Giorni stimati di consegna dalla creazione', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="number" min="0" id="ms-creation-delta" name="ms_wc_recesso_settings[creation_delta_days]" value="<?php echo esc_attr( (string) $options['creation_delta_days'] ); ?>" /> <span class="description"><?php esc_html_e( 'usato se l’ordine non è completato (default 4)', 'ms-wc-recesso' ); ?></span></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Ospiti', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ms-token-hours"><?php esc_html_e( 'Validità link di verifica (ore)', 'ms-wc-recesso' ); ?></label></th>
				<td><input type="number" min="1" id="ms-token-hours" name="ms_wc_recesso_settings[guest_token_hours]" value="<?php echo esc_attr( (string) $options['guest_token_hours'] ); ?>" /> <span class="description"><?php esc_html_e( 'default 48', 'ms-wc-recesso' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Anti-bot', 'ms-wc-recesso' ); ?></th>
				<td>
					<label><input type="checkbox" name="ms_wc_recesso_settings[honeypot_enabled]" value="1" <?php checked( ! empty( $options['honeypot_enabled'] ) ); ?> /> <?php esc_html_e( 'Attiva la protezione honeypot sul modulo di richiesta guest', 'ms-wc-recesso' ); ?></label>
					<p class="description"><?php esc_html_e( 'Aggiunge un campo trappola nascosto: i bot che lo compilano vengono scartati senza creare richieste.', 'ms-wc-recesso' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Disponibilità per ruolo', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ruoli senza recesso', 'ms-wc-recesso' ); ?></th>
				<td>
					<?php
					$ms_roles          = wp_roles()->get_names();
					$ms_excluded_roles = array_map( 'strval', (array) $options['excluded_roles'] );
					?>
					<fieldset id="ms-excluded-roles" style="max-height:240px;overflow:auto;border:1px solid #dcdcde;border-radius:4px;padding:10px 14px;background:#fff;column-width:220px;column-gap:28px;max-width:720px;">
						<?php foreach ( $ms_roles as $ms_role_slug => $ms_role_name ) : ?>
							<label style="display:block;margin:0 0 8px;break-inside:avoid;">
								<input type="checkbox" name="ms_wc_recesso_settings[excluded_roles][]" value="<?php echo esc_attr( (string) $ms_role_slug ); ?>" <?php checked( in_array( (string) $ms_role_slug, $ms_excluded_roles, true ) ); ?> />
								<?php echo esc_html( translate_user_role( $ms_role_name ) ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Per gli utenti con uno dei ruoli selezionati il recesso è disattivato (es. clienti B2B). Vale per il flusso da account, i link e il flusso guest sugli ordini di quei clienti.', 'ms-wc-recesso' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Esclusioni art. 59', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ms-exclusion-reason"><?php esc_html_e( 'Motivo predefinito di esclusione', 'ms-wc-recesso' ); ?></label></th>
				<td>
					<input type="text" id="ms-exclusion-reason" class="large-text" name="ms_wc_recesso_settings[default_exclusion_reason]" value="<?php echo esc_attr( (string) $options['default_exclusion_reason'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Mostrato accanto agli articoli esclusi quando il prodotto non ha un motivo specifico.', 'ms-wc-recesso' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ms-excluded-categories"><?php esc_html_e( 'Categorie escluse', 'ms-wc-recesso' ); ?></label></th>
				<td>
					<?php
					$ms_terms    = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'hide_empty' => false,
						)
					);
					$ms_selected = array_map( 'absint', (array) $options['excluded_categories'] );
					?>
					<?php if ( is_array( $ms_terms ) && ! empty( $ms_terms ) ) : ?>
						<fieldset id="ms-excluded-categories" style="max-height:240px;overflow:auto;border:1px solid #dcdcde;border-radius:4px;padding:10px 14px;background:#fff;column-width:220px;column-gap:28px;max-width:720px;">
							<?php foreach ( $ms_terms as $ms_term ) : ?>
								<label style="display:block;margin:0 0 8px;break-inside:avoid;">
									<input type="checkbox" name="ms_wc_recesso_settings[excluded_categories][]" value="<?php echo esc_attr( (string) $ms_term->term_id ); ?>" <?php checked( in_array( (int) $ms_term->term_id, $ms_selected, true ) ); ?> />
									<?php echo esc_html( $ms_term->name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'I prodotti nelle categorie selezionate (e nelle relative sottocategorie) non saranno selezionabili nel recesso.', 'ms-wc-recesso' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Nessuna categoria prodotto disponibile.', 'ms-wc-recesso' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Dati', 'ms-wc-recesso' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Disinstallazione', 'ms-wc-recesso' ); ?></th>
				<td>
					<label><input type="checkbox" name="ms_wc_recesso_settings[retain_data_on_uninstall]" value="1" <?php checked( ! empty( $options['retain_data_on_uninstall'] ) ); ?> /> <?php esc_html_e( 'Conserva le richieste alla disinstallazione (consigliato: sono documenti probatori)', 'ms-wc-recesso' ); ?></label>
				</td>
			</tr>
		</table>

		<p class="description">
			<?php
			printf(
				/* translators: %s: URL to WooCommerce email settings. */
				wp_kses_post( __( 'I testi e i destinatari delle email si gestiscono in <a href="%s">WooCommerce &rarr; Impostazioni &rarr; Email</a>.', 'ms-wc-recesso' ) ),
				esc_url( $ms_emails_url )
			);
			?>
		</p>

		<?php submit_button(); ?>
	</form>
</div>
