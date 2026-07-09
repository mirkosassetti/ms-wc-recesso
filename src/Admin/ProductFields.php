<?php
/**
 * Product data fields for art. 59 exclusion.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Admin;

use MS\WcRecesso\Domain\ExclusionRules;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "excluded from withdrawal" checkbox and optional reason to the
 * product Data panel, and saves them as product meta.
 */
final class ProductFields {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
	}

	/**
	 * Render the fields in the General product data tab.
	 */
	public function render_fields(): void {
		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => ExclusionRules::META_EXCLUDED,
				'label'       => __( 'Escluso dal recesso (art. 59)', 'ms-wc-recesso' ),
				'description' => __( 'Se attivo, questo prodotto non sarà selezionabile nel flusso di recesso.', 'ms-wc-recesso' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => ExclusionRules::META_REASON,
				'label'       => __( 'Motivo esclusione', 'ms-wc-recesso' ),
				'desc_tip'    => true,
				'description' => __( 'Mostrato al cliente accanto all’articolo escluso (facoltativo).', 'ms-wc-recesso' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save the fields.
	 *
	 * @param int $post_id Product post id.
	 */
	public function save_fields( $post_id ): void {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product editor nonce before this hook fires.
		$excluded = isset( $_POST[ ExclusionRules::META_EXCLUDED ] ) ? 'yes' : 'no';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
		$reason = isset( $_POST[ ExclusionRules::META_REASON ] ) ? sanitize_text_field( wp_unslash( $_POST[ ExclusionRules::META_REASON ] ) ) : '';

		$product->update_meta_data( ExclusionRules::META_EXCLUDED, $excluded );
		$product->update_meta_data( ExclusionRules::META_REASON, $reason );
		$product->save();
	}
}
