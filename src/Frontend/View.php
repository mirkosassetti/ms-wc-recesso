<?php
/**
 * Template loader with theme-override support.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a plugin template, letting the active theme override it under
 * `your-theme/ms-wc-recesso/<template>` or
 * `your-theme/woocommerce/ms-wc-recesso/<template>`.
 *
 * Templates receive a single `$args` array (no extract()) to keep the data
 * flow explicit and WPCS-clean.
 */
final class View {

	/**
	 * Render a template to a string.
	 *
	 * @param string              $template Template file name, e.g. 'form-summary.php'.
	 * @param array<string,mixed> $args     Data made available to the template as $args.
	 */
	public static function render( string $template, array $args = array() ): string {
		$file = self::locate( $template );

		if ( '' === $file ) {
			return '';
		}

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Resolve the absolute path of a template, preferring theme overrides.
	 *
	 * @param string $template Template file name.
	 */
	private static function locate( string $template ): string {
		$template = ltrim( $template, '/' );

		$theme = locate_template(
			array(
				'ms-wc-recesso/' . $template,
				'woocommerce/ms-wc-recesso/' . $template,
			)
		);

		if ( '' !== $theme && is_readable( $theme ) ) {
			return $theme;
		}

		$plugin = MS_WC_RECESSO_DIR . 'templates/' . $template;

		return is_readable( $plugin ) ? $plugin : '';
	}
}
