<?php
/**
 * Typed accessor for the plugin settings.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the single settings option array with typed getters and defaults.
 *
 * All settings live under one autoloaded option to keep the option table lean.
 * The admin Settings page (Phase 5) writes this same option.
 */
final class Options {

	/**
	 * Option key holding the settings array.
	 */
	public const OPTION = 'ms_wc_recesso_settings';

	/**
	 * Cached settings for the request lifecycle.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Frontend labels (Italian, law-compliant defaults).
			'button_label'             => 'Recedere dal contratto qui',
			'confirm_label'            => 'Conferma recesso',

			// Where the persistent link is shown.
			'placement_footer'         => true,
			'placement_orders_list'    => true,
			'placement_view_order'     => true,

			// Withdrawal window (days). See Support\Dates for the algorithm.
			'window_days'              => 14,
			// Delivery-estimate deltas added to the base date before the window.
			'creation_delta_days'      => 4,
			'completion_delta_days'    => 2,

			// Guest verification token lifetime (hours).
			'guest_token_hours'        => 48,

			// Admin notification recipient (empty => site admin email).
			'admin_notification_email' => '',

			// Art. 59 exclusions.
			'excluded_categories'      => array(),
			'default_exclusion_reason' => 'Articolo escluso dal diritto di recesso ai sensi dell’art. 59 del Codice del Consumo.',

			// Data retention: keep evidentiary records on uninstall by default.
			'retain_data_on_uninstall' => true,
		);
	}

	/**
	 * Get the full settings array merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = wp_parse_args( $stored, self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key           Setting key.
	 * @param mixed  $default_value Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	/**
	 * Get an integer setting (clamped to >= 0).
	 *
	 * @param string $key           Setting key.
	 * @param int    $default_value Fallback when the key is unknown.
	 */
	public static function get_int( string $key, int $default_value = 0 ): int {
		$value = self::get( $key, $default_value );

		return max( 0, (int) $value );
	}

	/**
	 * Get a boolean setting.
	 *
	 * @param string $key           Setting key.
	 * @param bool   $default_value Fallback when the key is unknown.
	 */
	public static function get_bool( string $key, bool $default_value = false ): bool {
		return (bool) self::get( $key, $default_value );
	}

	/**
	 * Reset the in-memory cache (useful after saving settings/tests).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
