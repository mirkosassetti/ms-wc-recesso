<?php
/**
 * Settings page.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Admin;

use MS\WcRecesso\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and persists the plugin settings (a single option array), using the
 * WordPress Settings API for saving.
 *
 * Email subjects/headings are managed under WooCommerce > Settings > Emails;
 * the art. 59 exclusions (per product/category) are added in Phase 6.
 */
final class SettingsPage {

	/**
	 * Settings page slug.
	 */
	public const PAGE = 'ms-wc-recesso-settings';

	/**
	 * Settings group.
	 */
	private const GROUP = 'ms_wc_recesso_settings_group';

	/**
	 * Register the setting.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Register the option with a sanitize callback.
	 */
	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize the submitted settings into a clean array.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = Options::defaults();

		$out['button_label']  = isset( $input['button_label'] ) ? sanitize_text_field( $input['button_label'] ) : $out['button_label'];
		$out['confirm_label'] = isset( $input['confirm_label'] ) ? sanitize_text_field( $input['confirm_label'] ) : $out['confirm_label'];

		$out['placement_footer']      = ! empty( $input['placement_footer'] );
		$out['placement_orders_list'] = ! empty( $input['placement_orders_list'] );
		$out['placement_view_order']  = ! empty( $input['placement_view_order'] );

		$out['window_days']           = max( 1, absint( $input['window_days'] ?? $out['window_days'] ) );
		$out['creation_delta_days']   = absint( $input['creation_delta_days'] ?? $out['creation_delta_days'] );
		$out['completion_delta_days'] = absint( $input['completion_delta_days'] ?? $out['completion_delta_days'] );
		$out['guest_token_hours']     = max( 1, absint( $input['guest_token_hours'] ?? $out['guest_token_hours'] ) );

		$roles                 = isset( $input['excluded_roles'] ) ? (array) $input['excluded_roles'] : array();
		$valid_roles           = array_keys( wp_roles()->get_names() );
		$out['excluded_roles'] = array_values( array_intersect( array_map( 'sanitize_key', $roles ), $valid_roles ) );

		$categories                      = isset( $input['excluded_categories'] ) ? (array) $input['excluded_categories'] : array();
		$out['excluded_categories']      = array_values( array_filter( array_map( 'absint', $categories ) ) );
		$out['default_exclusion_reason'] = isset( $input['default_exclusion_reason'] )
			? sanitize_text_field( $input['default_exclusion_reason'] )
			: $out['default_exclusion_reason'];

		$out['retain_data_on_uninstall'] = ! empty( $input['retain_data_on_uninstall'] );

		Options::flush_cache();

		return $out;
	}

	/**
	 * Render the settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$options = Options::all();

		require MS_WC_RECESSO_DIR . 'templates/admin/settings.php';
	}
}
