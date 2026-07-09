<?php
/**
 * Shortcode entry point for the standalone withdrawal page.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `[ms_recesso_54bis]` shortcode, which renders the full flow on
 * the dedicated page created at activation.
 */
final class Shortcode {

	/**
	 * Shortcode tag.
	 */
	public const TAG = 'ms_recesso_54bis';

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes (unused).
	 */
	public function render( $atts = array() ): string {
		return ( new FlowController() )->render( 'page' );
	}
}
