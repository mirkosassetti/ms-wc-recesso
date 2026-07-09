<?php
/**
 * Plugin deactivation routine.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on deactivation. Intentionally conservative: it never deletes data,
 * only flushes rewrite rules so the temporary endpoint is removed cleanly.
 */
final class Deactivator {

	/**
	 * Deactivation entry point.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
