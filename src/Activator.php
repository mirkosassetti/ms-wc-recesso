<?php
/**
 * Plugin activation routine.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso;

use MS\WcRecesso\Support\Options;
use MS\WcRecesso\Support\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation: installs the schema, seeds default settings,
 * creates the front-end declaration page and flushes rewrite rules.
 */
final class Activator {

	/**
	 * Option storing the ID of the auto-created shortcode page.
	 */
	public const PAGE_OPTION = 'ms_wc_recesso_page_id';

	/**
	 * Activation entry point.
	 */
	public static function activate(): void {
		Schema::install();
		self::seed_settings();
		self::create_page();
		self::flush_rewrites();
	}

	/**
	 * Persist default settings on first activation (without overwriting).
	 *
	 * Customer-facing labels are seeded in Italian when WordPress runs in
	 * Italian, and in English otherwise, so non-Italian shops get sensible
	 * defaults out of the box (the source strings are Italian).
	 */
	private static function seed_settings(): void {
		if ( false !== get_option( Options::OPTION, false ) ) {
			return;
		}

		$defaults = array_merge( Options::defaults(), self::localized_label_defaults() );

		add_option( Options::OPTION, $defaults );
	}

	/**
	 * Locale-appropriate defaults for the configurable, customer-facing labels.
	 *
	 * @return array<string,string>
	 */
	private static function localized_label_defaults(): array {
		if ( 0 === strpos( get_locale(), 'it' ) ) {
			return array(
				'button_label'             => 'Recedere dal contratto qui',
				'confirm_label'            => 'Conferma recesso',
				'default_exclusion_reason' => 'Articolo escluso dal diritto di recesso ai sensi dell’art. 59 del Codice del Consumo.',
			);
		}

		return array(
			'button_label'             => 'Withdraw from the contract here',
			'confirm_label'            => 'Confirm withdrawal',
			'default_exclusion_reason' => 'Item excluded from the right of withdrawal under art. 59 of the Consumer Code.',
		);
	}

	/**
	 * Page slug for the standalone withdrawal page.
	 *
	 * Deliberately different from the My Account endpoint slug
	 * (Plugin::ENDPOINT) so WooCommerce's EP_ROOT endpoint rule does not shadow
	 * this page.
	 */
	private const PAGE_SLUG = 'recesso-dal-contratto';

	/**
	 * Create the public page hosting the withdrawal shortcode, once.
	 *
	 * If a previously created page still uses a slug that collides with the
	 * endpoint, its slug is healed to PAGE_SLUG.
	 */
	private static function create_page(): void {
		$existing = (int) get_option( self::PAGE_OPTION, 0 );

		if ( $existing > 0 && 'page' === get_post_type( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			if ( get_post_field( 'post_name', $existing ) !== self::PAGE_SLUG ) {
				wp_update_post(
					array(
						'ID'        => $existing,
						'post_name' => self::PAGE_SLUG,
					)
				);
			}

			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Recesso dal contratto', 'ms-wc-recesso' ),
				'post_name'    => self::PAGE_SLUG,
				'post_content' => '[ms_recesso_54bis]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( self::PAGE_OPTION, (int) $page_id );
		}
	}

	/**
	 * Register the My Account endpoint (matching WooCommerce's mask) then flush
	 * rewrite rules so the endpoint works immediately after activation.
	 */
	private static function flush_rewrites(): void {
		add_rewrite_endpoint( Plugin::ENDPOINT, EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}
}
