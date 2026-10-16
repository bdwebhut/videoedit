<?php
/** Preserve all records unless an administrator separately armed this uninstall. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }
$ghr_removal = get_option( 'ghr_confirmed_uninstall' );
if ( ! $ghr_removal || empty( $ghr_removal['confirmed'] ) || $ghr_removal['expires'] < time() || get_current_user_id() !== (int) $ghr_removal['actor'] || ! current_user_can( 'manage_options' ) ) {
	return; }
require_once __DIR__ . '/src/class-db.php';
require_once __DIR__ . '/src/class-installation.php';
global $wpdb;
foreach ( array_keys( \GhoroaHat\Rewards\Installation::schema() ) as $ghr_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'ghr_' . $ghr_table ) );
}
$ghr_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ghr_' ) . '%' ) );
foreach ( $ghr_options as $ghr_option ) {
	if ( 'ghr_member_sequence' !== $ghr_option ) {
		delete_option( $ghr_option ); }
}
$ghr_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_ghr_' ) . '%' ) );
foreach ( $ghr_meta_keys as $ghr_key ) {
	delete_metadata( 'user', 0, $ghr_key, '', true ); }
$ghr_admin = get_role( 'administrator' );
if ( $ghr_admin ) {
	foreach ( \GhoroaHat\Rewards\Installation::CAPS as $ghr_capability ) {
		$ghr_admin->remove_cap( $ghr_capability ); }
}
update_option(
	'ghr_removal_receipt',
	array(
		'actor'      => get_current_user_id(),
		'removed_at' => gmdate( 'c' ),
	),
	false
);
