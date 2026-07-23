<?php
/**
 * Plugin Name:       MS Recesso 54-bis per WooCommerce
 * Plugin URI:        https://github.com/mirkosassetti/ms-wc-recesso
 * Description:       Implementa la funzione di recesso obbligatoria ex art. 54-bis del Codice del Consumo (D.Lgs. 209/2025, Direttiva UE 2023/2673) per e-commerce B2C.
 * Version:           0.2.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Mirko Sassetti
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ms-wc-recesso
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.5
 *
 * @package MS\WcRecesso
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MS_WC_RECESSO_VERSION', '0.2.1' );
define( 'MS_WC_RECESSO_DB_VERSION', '1.0.0' );
define( 'MS_WC_RECESSO_FILE', __FILE__ );
define( 'MS_WC_RECESSO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MS_WC_RECESSO_URL', plugin_dir_url( __FILE__ ) );
define( 'MS_WC_RECESSO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader.
 *
 * Prefer Composer's autoloader when the plugin is installed for development,
 * otherwise fall back to a lightweight PSR-4 SPL autoloader so the plugin also
 * works on client sites where `composer install` is never run.
 */
if ( is_readable( MS_WC_RECESSO_DIR . 'vendor/autoload.php' ) ) {
	require MS_WC_RECESSO_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix   = 'MS\\WcRecesso\\';
			$base_dir = MS_WC_RECESSO_DIR . 'src/';

			$len = strlen( $prefix );
			if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
				return;
			}

			$relative = substr( $class_name, $len );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	);
}

// Activation / deactivation hooks (kept at file scope as required by WordPress).
register_activation_hook( __FILE__, array( \MS\WcRecesso\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MS\WcRecesso\Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded (so WooCommerce is available).
 */
add_action(
	'plugins_loaded',
	static function () {
		\MS\WcRecesso\Plugin::instance()->boot();
	}
);
