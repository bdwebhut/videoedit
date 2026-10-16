<?php
namespace GhoroaHat\Rewards;

final class Membership {
	public static function canonical( int $user ): int {
		$seen = array(); for ( $i = 0; $i < 20; $i++ ) {
			if ( isset( $seen[ $user ] ) ) {
				throw new \DomainException( 'Identity reconciliation cycle detected.' );
			} $seen[ $user ] = true;
			$next            = (int) get_user_meta( $user, '_ghr_identity_target', true );
			if ( ! $next ) {
				return $user;
			} $user = $next; }
		throw new \DomainException( 'Identity reconciliation chain requires review.' );
	}
	public static function sources( int $user ): array {
		$stored = get_user_meta( $user, '_ghr_identity_sources', true );
		$ids    = is_array( $stored ) ? array_filter( array_map( 'absint', $stored ) ) : array();
		return array_values( array_unique( array_merge( array( $user ), $ids ) ) ); }

	public static function get( int $user ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' WHERE user_id=%d', $user ), ARRAY_A ); }
	public static function enroll( int $user ): array {
		if ( ! get_userdata( $user ) ) {
			throw new \DomainException( 'A registered WooCommerce account is required.' ); }
		return DB::transaction(
			static function () use ( $user ) {
				global $wpdb;
				$existing = self::get( $user );
				if ( $existing ) {
					return $existing; }
				$code = 'GHR' . strtoupper( bin2hex( random_bytes( 6 ) ) );
				// Unique user constraint handles concurrent enrollments without creating a second account.
				DB::query( $wpdb->prepare( 'INSERT INTO ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' (user_id,referral_code,registered_at) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)', $user, $code, DB::now() ) );
				$m = Wallet::lock_member( $user );
				if ( empty( $m['member_id'] ) ) {
					DB::query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=LAST_INSERT_ID(CAST(option_value AS UNSIGNED)+1) WHERE option_name=%s", 'ghr_member_sequence' ) );
					$sequence = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
					DB::update( ( $wpdb->prefix . 'ghr_memberships' ), array( 'member_id' => 'GH-' . str_pad( (string) $sequence, 6, '0', STR_PAD_LEFT ) ), array( 'id' => $m['id'] ) ); }
				foreach ( array( 'trust', 'referral', 'seeds' ) as $wallet ) {
					Wallet::account( $user, $wallet ); }
				Notifications::emit( $user, 'membership-registered', 'membership_registered', array() );
				DB::audit( 'membership.enrolled', 'user:' . $user );
				return Wallet::lock_member( $user );
			}
		);
	}
	public static function active( int $user ): bool {
		$m = self::get( $user );
		return $m && 'active' === $m['status'] && $m['email_verified_at']; }
	public static function activate( int $user, int $order ): void {
		global $wpdb;
		$m = self::get( $user );
		if ( ! $m || ! $m['email_verified_at'] ) {
			return; }
		$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_memberships' ) . " SET status='active',activated_at=COALESCE(activated_at,%s) WHERE user_id=%d AND status='pending'", DB::now(), $user ) );
		if ( $n ) {
			Notifications::emit( $user, 'membership-activated', 'membership_activated', array( 'order_number' => $order ) );
			DB::audit( 'membership.activated', 'user:' . $user, array( 'order' => $order ) ); }
	}
	public static function status( int $user, string $status, string $reason ): void {
		if ( self::canonical( $user ) !== $user ) {
			throw new \DomainException( 'This identity was reconciled; manage its canonical membership.' ); }
		if ( ! in_array( $status, array( 'pending', 'active', 'suspended', 'closed' ), true ) || ! trim( $reason ) ) {
			throw new \DomainException( 'A valid state and reason are required.' ); }
		DB::transaction(
			static function () use ( $user, $status, $reason ) {
				global $wpdb;
				Wallet::lock_member( $user );
				$m = self::get( $user );
				if ( 'active' === $status && ( ! $m['email_verified_at'] || ! $m['activated_at'] ) ) {
					throw new \DomainException( 'Verify email and complete a qualifying purchase before activation.' ); }
				if ( in_array( $status, array( 'closed', 'suspended' ), true ) ) {
					Wallet::release_unbound( $user ); }
				DB::update( ( $wpdb->prefix . 'ghr_memberships' ), array( 'status' => $status ), array( 'user_id' => $user ) );
				DB::audit(
					'membership.status',
					'user:' . $user,
					array(
						'before' => $m['status'],
						'after'  => $status,
					),
					$reason
				);
			}
		);
	}
	public static function phone( string $input ): string {
		$input = strtr( $input, array_combine( preg_split( '//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY ), range( 0, 9 ) ) );
		$phone = preg_replace( '/[\s()\-]+/', '', $input );
		if ( str_starts_with( $phone, '00880' ) ) {
			$phone = '+' . substr( $phone, 2 ); }
		if ( str_starts_with( $phone, '01' ) ) {
			$phone = '+88' . $phone; }
		if ( str_starts_with( $phone, '880' ) ) {
			$phone = '+' . $phone; }
		if ( ! preg_match( '/^\+8801[3-9][0-9]{8}$/D', $phone ) ) {
			throw new \DomainException( 'Enter a valid Bangladesh mobile number.' ); }
		return $phone;
	}
	public static function request_verification( int $user, string $purpose, string $target = '' ): void {
		if ( self::canonical( $user ) !== $user ) {
			throw new \DomainException( 'Use the canonical account to verify contact details.' ); }
		global $wpdb;
		DB::limit( 'verification:' . $user, 3, 600 );
		self::enroll( $user );
		if ( ! in_array( $purpose, array( 'email', 'phone' ), true ) ) {
			throw new \DomainException( 'Invalid verification purpose.' ); }
		$target = 'email' === $purpose ? get_userdata( $user )->user_email : self::phone( $target );
		if ( 'phone' === $purpose && ( ! Settings::get( 'sms_enabled' ) || ! is_callable( apply_filters( 'ghr_sms_adapter', null ) ) ) ) {
			throw new \DomainException( 'Phone verification requires a configured SMS provider.' ); }
		$token = 'phone' === $purpose ? (string) random_int( 100000, 999999 ) : bin2hex( random_bytes( 24 ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_identity_verifications' ) . ' SET consumed_at=%s WHERE user_id=%d AND purpose=%s AND consumed_at IS NULL', DB::now(), $user, $purpose ) );
		DB::insert(
			'identity_verifications',
			array(
				'user_id'    => $user,
				'purpose'    => $purpose,
				'target'     => $target,
				'token_hash' => hash_hmac( 'sha256', $user . ':' . $token, wp_salt() ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( 'phone' === $purpose ? 600 : DAY_IN_SECONDS ) ),
			)
		);
		// Secret verification token is kept only in a short-lived encrypted queue payload.
		Notifications::verification( $user, $purpose, $target, $token );
		if ( 'phone' === $purpose ) {
			Notifications::emit( $user, 'phone-request:' . time(), 'phone_verification_requested' ); }
		DB::audit( 'identity.requested', 'user:' . $user, array( 'purpose' => $purpose ) );
	}
	public static function confirm( int $user, string $purpose, string $token ): void {
		if ( self::canonical( $user ) !== $user ) {
			throw new \DomainException( 'Use the canonical account to verify contact details.' ); }
		DB::limit( 'verify-confirm:' . $user, 10, 600 );
		// Attempt count must persist even when token validation fails.
		global $wpdb;
		$t = ( $wpdb->prefix . 'ghr_identity_verifications' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id=%d AND purpose=%s AND consumed_at IS NULL ORDER BY id DESC LIMIT 1", $user, $purpose ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed identity_verifications table; values use placeholders.
		if ( ! $r || $r['expires_at'] <= DB::now() || (int) $r['attempts'] >= 5 ) {
			throw new \DomainException( 'Verification expired or invalid.' ); }
		DB::query( $wpdb->prepare( "UPDATE $t SET attempts=attempts+1 WHERE id=%d AND attempts<5", $r['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed identity_verifications table; values use placeholders.
		if ( ! hash_equals( $r['token_hash'], hash_hmac( 'sha256', $user . ':' . $token, wp_salt() ) ) ) {
			throw new \DomainException( 'Verification expired or invalid.' ); }
		DB::transaction(
			static function () use ( $user, $purpose, $r, $t ) {
				global $wpdb;
				Wallet::lock_member( $user );
				$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d FOR UPDATE", $r['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed identity_verifications table; values use placeholders.
				if ( $current['consumed_at'] || $current['expires_at'] <= DB::now() ) {
					throw new \DomainException( 'Verification already used or expired.' ); }
				if ( 'email' === $purpose && get_userdata( $user )->user_email !== $r['target'] ) {
					throw new \DomainException( 'The account email has changed. Request a new verification.' ); }
				$data = 'email' === $purpose ? array( 'email_verified_at' => DB::now() ) : array(
					'phone'          => $r['target'],
					'verified_phone' => $r['target'],
				);
				if ( false === DB::update( ( $wpdb->prefix . 'ghr_memberships' ), $data, array( 'user_id' => $user ) ) ) {
					throw new \DomainException( 'This verified phone is already associated with a member.' ); }
				DB::update( $t, array( 'consumed_at' => DB::now() ), array( 'id' => $r['id'] ) );
				DB::audit( 'identity.verified', 'user:' . $user, array( 'purpose' => $purpose ) );
			}
		);
		if ( 'email' === $purpose ) {
			foreach ( wc_get_orders(
				array(
					'customer_id' => $user,
					'status'      => Settings::get( 'statuses' ),
					'limit'       => 20,
					'return'      => 'ids',
				)
			) as $order ) {
				Jobs::order( $order ); }
		}
	}
}
