<?php
namespace GhoroaHat\Rewards;

/** Separately confirmed removal is performed only by WordPress uninstall. */
final class Removal {
	public static function preview(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \DomainException( 'Administrator permission is required.' ); }
		global $wpdb;
		$counts = array();
		foreach ( array_keys( Installation::schema() ) as $name ) {
			$counts[ $name ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . DB::table( $name ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name comes only from Installation::schema and DB::table validates identifiers.
		$ticket = wp_generate_uuid4();
		update_option(
			'ghr_removal_preview',
			array(
				'ticket'  => $ticket,
				'actor'   => get_current_user_id(),
				'expires' => time() + 900,
			),
			false
		);
		return array(
			'ticket'       => $ticket,
			'counts'       => $counts,
			'confirmation' => 'DELETE ALL GHOROA HAT RECORDS',
			'instruction'  => 'Export and verify a database backup first. Confirmation arms data removal for the next WordPress uninstall within one hour. No WooCommerce users or orders will be deleted.',
		);
	}
	public static function confirm( string $ticket, string $confirmation, string $backup ): void {
		$preview = get_option( 'ghr_removal_preview' );
		if ( ! current_user_can( 'manage_options' ) || ! $preview || get_current_user_id() !== $preview['actor'] || ! hash_equals( $preview['ticket'], $ticket ) || $preview['expires'] < time() || 'DELETE ALL GHOROA HAT RECORDS' !== $confirmation || 'BACKUP VERIFIED' !== $backup ) {
			throw new \DomainException( 'Valid preview, exact typed confirmation and BACKUP VERIFIED are required.' ); }
		update_option(
			'ghr_confirmed_uninstall',
			array(
				'actor'     => get_current_user_id(),
				'expires'   => time() + HOUR_IN_SECONDS,
				'confirmed' => true,
			),
			false
		);
		delete_option( 'ghr_removal_preview' );
		DB::audit( 'uninstall.explicitly_armed', 'settings', array(), 'Administrator confirmed export/backup and financial record deletion.' );
	}
}
