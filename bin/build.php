<?php
/**
 * Build a clean, installable ZIP of the plugin (production files only).
 *
 * Cross-platform builder using ZipArchive so entry names always use forward
 * slashes (Windows PowerShell's Compress-Archive stores backslashes, which
 * break extraction on Linux). Uses an allowlist: no vendor/, composer, phpcs,
 * .git, bin/ or tests are packaged. The archive contains a single top-level
 * folder "ms-wc-recesso/".
 *
 * Usage:  php bin/build.php
 *
 * @package MS\WcRecesso
 */

$slug = 'ms-wc-recesso';
$root = dirname( __DIR__ );

// --- Read version from the main plugin file header ---
$version = 'dev';
$header  = (string) file_get_contents( $root . '/' . $slug . '.php' );
if ( preg_match( '/^\s*\*\s*Version:\s*(.+)\s*$/mi', $header, $m ) ) {
	$version = trim( $m[1] );
}

echo "Building {$slug} {$version} ...\n";

// --- Production allowlist ---
$include = array(
	$slug . '.php',
	'uninstall.php',
	'readme.txt',
	'README.md',
	'src',
	'templates',
	'assets',
	'languages',
);

// Files that must never ship, matched by basename.
$prune = array( '.gitkeep', '.DS_Store', 'Thumbs.db' );

/**
 * Collect files (relative paths) from an allowlist entry.
 *
 * @param string $root  Plugin root.
 * @param string $item  Allowlisted file or directory.
 * @param array  $prune Basenames to skip.
 * @return string[] Relative paths using forward slashes.
 */
function ms_collect( string $root, string $item, array $prune ): array {
	$path = $root . '/' . $item;
	$out  = array();

	if ( is_file( $path ) ) {
		return array( $item );
	}

	if ( ! is_dir( $path ) ) {
		fwrite( STDERR, "Skipping missing item: {$item}\n" );
		return array();
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		if ( in_array( $file->getFilename(), $prune, true ) || 'map' === strtolower( $file->getExtension() ) ) {
			continue;
		}
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		$out[]    = $relative;
	}

	return $out;
}

$files = array();
foreach ( $include as $item ) {
	$files = array_merge( $files, ms_collect( $root, $item, $prune ) );
}
sort( $files );

// --- Prepare dist/ and the zip ---
$dist = $root . '/dist';
if ( ! is_dir( $dist ) ) {
	mkdir( $dist, 0755, true );
}
$zip_path = $dist . '/' . $slug . '-' . $version . '.zip';
if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Unable to create ZIP at {$zip_path}\n" );
	exit( 1 );
}

foreach ( $files as $relative ) {
	// Always forward slashes, inside a single top-level folder.
	$zip->addFile( $root . '/' . $relative, $slug . '/' . $relative );
}

$count = $zip->numFiles;
$zip->close();

$size = round( filesize( $zip_path ) / 1024, 1 );
echo "Done: {$zip_path} ({$size} KB, {$count} files)\n";
