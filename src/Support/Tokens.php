<?php
/**
 * Guest verification token helpers.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and hashes one-time guest verification tokens.
 *
 * The raw token travels only inside the emailed link; the database stores only
 * its SHA-256 hash, so a database read never exposes a usable token.
 */
final class Tokens {

	/**
	 * Generate a new token.
	 *
	 * @return array{raw:string,hash:string}
	 */
	public static function generate(): array {
		$raw = bin2hex( random_bytes( 32 ) );

		return array(
			'raw'  => $raw,
			'hash' => self::hash( $raw ),
		);
	}

	/**
	 * Hash a raw token for storage/lookup.
	 *
	 * @param string $raw Raw token.
	 */
	public static function hash( string $raw ): string {
		return hash( 'sha256', $raw );
	}
}
