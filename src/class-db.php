<?php
namespace GhoroaHat\Rewards;

final class DB {
	private static int $depth = 0;
	public static function table( string $name ): string {
		global $wpdb;
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/D', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid rewards table identifier.' ); }
		return $wpdb->prefix . 'ghr_' . $name; }
	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' ); }
	public static function query( string $sql ): int {
		global $wpdb;
		$r = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal execution wrapper; callers supply static SQL or prepared values.
		if ( false === $r ) {
			throw new \RuntimeException( 'Database operation failed. See protected diagnostics.' );
		} return (int) $r; }
	public static function insert( string $table, array $data ): int {
		global $wpdb;
		if ( false === $wpdb->insert( self::table( $table ), $data ) ) {
			throw new \RuntimeException( 'Cannot persist record.' ); }
		return (int) $wpdb->insert_id;
	}
	public static function update( string $table, array $data, array $where ): int {
		global $wpdb;
		$result = $wpdb->update( $table, $data, $where );
		if ( false === $result ) {
			throw new \DomainException( 'Update failed; check duplicate identities and system diagnostics.' );
		} return (int) $result;
	}
	public static function transaction( callable $callback ): mixed {
		if ( self::$depth ) {
			return $callback(); }
		self::query( 'START TRANSACTION' );
		++self::$depth;
		try {
			$result = $callback();
			self::query( 'COMMIT' );
			return $result; } catch ( \Throwable $e ) {
			self::query( 'ROLLBACK' );
			throw $e; } finally {
				--self::$depth; }
	}
	public static function audit( string $operation, string $subject, array $data = array(), string $reason = '' ): void {
		self::insert(
			'audit_logs',
			array(
				'actor'      => get_current_user_id(),
				'operation'  => $operation,
				'subject'    => $subject,
				'details'    => wp_json_encode( $data ),
				'reason'     => $reason,
				'created_at' => self::now(),
			)
		);
	}
	public static function row( string $table, int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( $table ) . ' WHERE id=%d', $id ), ARRAY_A ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DB::table validates the internal identifier; record ID is prepared.
	public static function count_event( string $name ): void {
		global $wpdb;
		$key = 'ghr_counter_' . sanitize_key( $name );
		add_option( $key, 0, '', false );
		self::query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=CAST(option_value AS UNSIGNED)+1 WHERE option_name=%s", $key ) );
		wp_cache_delete( $key, 'options' ); }
	public static function limit( string $scope, int $max = 20, int $seconds = 60 ): void {
		$key = 'ghr_rate_' . hash_hmac( 'sha256', $scope, wp_salt( 'auth' ) );
		// Atomic option increment avoids the transient read-modify-write race.
		global $wpdb;
		$now    = time();
		$bucket = $key . '_' . ( ( intdiv( $now, $seconds ) + 1 ) * $seconds );
		add_option( $bucket, 0, '', false );
		$count = self::query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=CAST(option_value AS UNSIGNED)+1 WHERE option_name=%s AND CAST(option_value AS UNSIGNED)<%d", $bucket, $max ) );
		if ( ! $count ) {
			throw new \DomainException( 'Too many requests. Please try again later.' ); }
	}
}
