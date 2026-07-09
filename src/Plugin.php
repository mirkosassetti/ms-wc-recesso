<?php
/**
 * Main plugin container and bootstrapper.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso;

use MS\WcRecesso\Admin\AdminPages;
use MS\WcRecesso\Admin\OrderMetabox;
use MS\WcRecesso\Admin\ProductFields;
use MS\WcRecesso\Domain\ExclusionRules;
use MS\WcRecesso\Emails\EmailManager;
use MS\WcRecesso\Frontend\Assets;
use MS\WcRecesso\Frontend\MyAccountEndpoint;
use MS\WcRecesso\Frontend\PlacementService;
use MS\WcRecesso\Frontend\Shortcode;
use MS\WcRecesso\Integration\Hpos;
use MS\WcRecesso\Support\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin.
 *
 * Central wiring point. Keeps a single instance and registers the hooks for
 * every subsystem. Subsystems introduced in later phases (frontend, admin,
 * emails, domain services) are wired here as they land.
 */
final class Plugin {

	/**
	 * My Account rewrite endpoint slug.
	 *
	 * WooCommerce registers the rewrite endpoint for this via the
	 * `woocommerce_get_query_vars` filter (see MyAccountEndpoint). It must not
	 * match the standalone page slug, or WC's EP_ROOT rule would shadow it.
	 */
	public const ENDPOINT = 'recesso';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Get the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks and initialise subsystems.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Declare HPOS compatibility as early as possible.
		( new Hpos() )->register();

		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Hard gate: WooCommerce must be active for the rest to load.
		add_action( 'admin_init', array( $this, 'check_environment' ) );
		if ( ! $this->woocommerce_active() ) {
			return;
		}

		// Run idempotent schema upgrades if the DB version drifted.
		add_action( 'plugins_loaded', array( Schema::class, 'maybe_upgrade' ), 20 );

		// Front-end: standalone shortcode page, My Account endpoint, persistent
		// placements and minimal assets.
		( new Shortcode() )->register();
		( new MyAccountEndpoint() )->register();
		( new PlacementService() )->register();
		( new Assets() )->register();

		// Transactional emails (guest verification, receipt, admin notification).
		( new EmailManager() )->register();

		// Art. 59 exclusion rules (frontend eligibility filter).
		( new ExclusionRules() )->register();

		// Admin: requests list/detail, settings, order metabox, product fields.
		if ( is_admin() ) {
			( new AdminPages() )->register();
			( new OrderMetabox() )->register();
			( new ProductFields() )->register();
		}

		/**
		 * Fires once the plugin has finished wiring its core services.
		 *
		 * Extension point for add-ons; frontend/admin/email subsystems are
		 * attached here in the following phases.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'ms_wc_recesso_booted', $this );
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ms-wc-recesso',
			false,
			dirname( MS_WC_RECESSO_BASENAME ) . '/languages'
		);
	}

	/**
	 * Show an admin notice if WooCommerce is missing.
	 */
	public function check_environment(): void {
		if ( $this->woocommerce_active() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'MS Recesso 54-bis richiede WooCommerce attivo per funzionare.', 'ms-wc-recesso' )
				);
			}
		);
	}

	/**
	 * Whether WooCommerce is loaded.
	 */
	public function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}
}
