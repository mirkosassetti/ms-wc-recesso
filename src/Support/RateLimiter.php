<?php
/**
 * Simple transient-based rate limiter.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Throttles abuse-prone public actions (the guest lookup form) by counting
 * attempts per key (a hashed IP) within a time window.
 */
final class RateLimiter {

	/**
	 * Transient key prefix.
	 */
	private const PREFIX = 'ms_wc_recesso_rl_';

	/**
	 * Register an attempt for a key and report whether it is still allowed.
	 *
	 * @param string $key            Bucket key (e.g. a hashed IP).
	 * @param int    $max            Maximum attempts within the window.
	 * @param int    $window_seconds Window length in seconds.
	 * @return bool True if the attempt is allowed, false if over the limit.
	 */
	public static function attempt( string $key, int $max, int $window_seconds ): bool {
		$transient = self::PREFIX . md5( $key );
		$count     = (int) get_transient( $transient );

		if ( $count >= $max ) {
			return false;
		}

		set_transient( $transient, $count + 1, $window_seconds );

		return true;
	}
}
