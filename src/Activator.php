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
	 */
	private static function seed_settings(): void {
		if ( false === get_option( Options::OPTION, false ) ) {
			add_option( Options::OPTION, Options::defaults() );
		}
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
