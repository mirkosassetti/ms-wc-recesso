<?php
/**
 * Uninstall routine.
 *
 * Withdrawal requests are evidentiary documents. By default the plugin keeps
 * all data on uninstall; only when the site owner explicitly opts out of data
 * retention are the custom tables and options removed.
 *
 * @package MS\WcRecesso
 */

// Bail if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ms_wc_recesso_settings = get_option( 'ms_wc_recesso_settings', array() );
$ms_wc_recesso_retain   = ! is_array( $ms_wc_recesso_settings )
	|| ! array_key_exists( 'retain_data_on_uninstall', $ms_wc_recesso_settings )
	|| (bool) $ms_wc_recesso_settings['retain_data_on_uninstall'];

// Default is to retain: never destroy legal records unless explicitly told to.
if ( $ms_wc_recesso_retain ) {
	return;
}

global $wpdb;

// Drop custom tables.
$ms_wc_recesso_tables = array(
	$wpdb->prefix . 'ms_wc_recesso_requests',
	$wpdb->prefix . 'ms_wc_recesso_log',
);

foreach ( $ms_wc_recesso_tables as $ms_wc_recesso_table ) {
	// Table name cannot be parameterised; it is built from a trusted prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$ms_wc_recesso_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted prefix; cannot be parameterised.
}

// Remove options.
delete_option( 'ms_wc_recesso_settings' );
delete_option( 'ms_wc_recesso_db_version' );
delete_option( 'ms_wc_recesso_page_id' );
delete_option( 'ms_wc_recesso_recipient_migrated' );
