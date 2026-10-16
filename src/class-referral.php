<?php
namespace GhoroaHat\Rewards;

final class Referral {
	public static function url( array $member ): string {
		global $wp_rewrite;
		if ( ! $wp_rewrite->using_permalinks() ) {
			return add_query_arg( Settings::get( 'referral_query' ), $member['referral_code'], home_url( '/' ) );
		} $pages = get_option( 'ghr_pages', array() );
		$base    = ! empty( $pages['refer'] ) ? get_permalink( $pages['refer'] ) : home_url( '/refer/' );
		return trailingslashit( $base ) . rawurlencode( $member['referral_code'] ) . '/'; }
	public static function current(): ?array {
		global $wpdb;
		$cookie = isset( $_COOKIE['ghr_ref'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ghr_ref'] ) ) : '';
		$parts  = explode( '.', $cookie );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt() ), $parts[1] ) ) {
			return null; }
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_referral_attributions' ) . ' WHERE id=%s AND expires_at>%s', $parts[0], DB::now() ), ARRAY_A );
	}
	public static function capture(): void {
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'referral_enabled' ) || is_admin() ) {
			return; }
		$query = Settings::get( 'referral_query' );
		$code  = get_query_var( 'ghr_ref_code' );
		if ( ! $code ) {
			$code = isset( $_GET[ $query ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query ] ) ) : ''; }
		if ( ! $code ) {
			return; }
		try {
			self::track( $code ); } catch ( \Throwable $e ) {
			/* Invalid/rate-limited attribution must not interrupt public navigation. */ }
	}
	public static function track( string $code ): void {
		global $wpdb;
		$m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' WHERE referral_code=%s', $code ), ARRAY_A );
		if ( $m && Membership::canonical( (int) $m['user_id'] ) !== (int) $m['user_id'] ) {
			$m = Membership::get( Membership::canonical( (int) $m['user_id'] ) ); }
		if ( ! $m ) {
			DB::count_event( 'invalid_referrals' );
			return; }
		if ( ! $m || 'active' !== $m['status'] || ! $m['email_verified_at'] || ( ! Settings::get( 'allow_self_referrals' ) && Membership::canonical( get_current_user_id() ) === (int) $m['user_id'] ) ) {
			return; }
		$consent = is_user_logged_in() ? Notifications::preferences( get_current_user_id() )['tracking_consent'] : ( function_exists( 'wp_has_consent' ) && wp_has_consent( 'marketing' ) );
		if ( Settings::get( 'require_tracking_consent' ) && ! apply_filters( 'ghr_tracking_consent', $consent ) ) {
			return; }
		$visitor_token = isset( $_COOKIE['ghr_visitor'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ghr_visitor'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/D', $visitor_token ) ) {
			$visitor_token = bin2hex( random_bytes( 16 ) ); }
		$visitor     = hash_hmac( 'sha256', $visitor_token, wp_salt( 'auth' ) );
		$rate_source = hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '', wp_salt( 'auth' ) );
		DB::limit( 'ref-click:' . $rate_source, 60, 3600 );
		setcookie(
			'ghr_visitor',
			$visitor_token,
			array(
				'expires'  => time() + Settings::get( 'referral_days' ) * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE['ghr_visitor'] = $visitor_token;
		if ( 'first' === Settings::get( 'referral_model' ) && self::current() ) {
			return; }
		$repeat  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_referral_visits' ) . ' WHERE visitor_hash=%s AND created_at>=%s', $visitor, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
		$visit   = DB::insert(
			'referral_visits',
			array(
				'referrer_id'  => $m['user_id'],
				'visitor_hash' => $visitor,
				'flags'        => $repeat >= 5 ? 'repeat_clicks' : '',
				'created_at'   => DB::now(),
			)
		);
		$id      = bin2hex( random_bytes( 24 ) );
		$expires = time() + Settings::get( 'referral_days' ) * DAY_IN_SECONDS;
		DB::insert(
			'referral_attributions',
			array(
				'id'          => $id,
				'referrer_id' => $m['user_id'],
				'customer_id' => get_current_user_id(),
				'visit_id'    => $visit,
				'expires_at'  => gmdate( 'Y-m-d H:i:s', $expires ),
				'created_at'  => DB::now(),
			)
		);
		$value = $id . '.' . hash_hmac( 'sha256', $id, wp_salt() );
		setcookie(
			'ghr_ref',
			$value,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE['ghr_ref'] = $value;
	}
	public static function attach( \WC_Order $order ): void {
		if ( $order->get_meta( '_ghr_referrer' ) && ! $order->has_status( 'checkout-draft' ) ) {
			return; }
		$a = self::current();
		if ( ! $a && $order->has_status( 'checkout-draft' ) ) {
			$order->delete_meta_data( '_ghr_referrer' );
			$order->delete_meta_data( '_ghr_attribution' ); }
		if ( ! $a || ( ! Settings::get( 'allow_self_referrals' ) && Membership::canonical( (int) $a['referrer_id'] ) === Membership::canonical( (int) $order->get_customer_id() ) ) ) {
			return; }
		$order->update_meta_data( '_ghr_referrer', (int) $a['referrer_id'] );
		$order->update_meta_data( '_ghr_attribution', $a['id'] );
	}
	public static function conversion( \WC_Order $order, int $eligible, array $policy ): ?array {
		global $wpdb;
		$ref   = Membership::canonical( (int) $order->get_meta( '_ghr_referrer' ) );
		$buyer = Membership::canonical( (int) $order->get_customer_id() );
		if ( ! $ref || ! Membership::active( $ref ) || ( $ref === $buyer && ! $policy['allow_self_referrals'] ) || ( ! $buyer && ! $policy['guest_referrals'] ) ) {
			return null; }
		$rm      = get_userdata( $ref );
		$bm      = Membership::get( $buyer );
		$rmember = Membership::get( $ref );
		$risk    = ( ! $buyer && ! is_email( $order->get_billing_email() ) ) || $ref === $buyer || ( $rm && strtolower( $order->get_billing_email() ) === strtolower( $rm->user_email ) );
		try {
			$risk = $risk || ( $rmember['verified_phone'] && self::normalized_phone( $order->get_billing_phone() ) === $rmember['verified_phone'] ); } catch ( \Throwable $e ) {
			}
			if ( ! $policy['repeat_referrals'] ) {
				$identity_filter = $buyer ? array( 'customer_id' => $buyer ) : array( 'billing_email' => $order->get_billing_email() );
				if ( ! $buyer && ! is_email( $order->get_billing_email() ) ) {
					return null; }
				$first = wc_get_orders(
					array_merge(
						$identity_filter,
						array(
							'status'  => $policy['statuses'],
							'orderby' => 'ID',
							'order'   => 'ASC',
							'limit'   => 1,
							'return'  => 'ids',
						)
					)
				);
				if ( $first && (int) $first[0] !== $order->get_id() ) {
					return null; }
			}
			$t   = ( $wpdb->prefix . 'ghr_referral_conversions' );
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_id=%d", $order->get_id() ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed referral_conversions table; order ID is prepared.
			if ( ! $old ) {
				$id  = DB::insert(
					'referral_conversions',
					array(
						'order_id'    => $order->get_id(),
						'buyer_id'    => $buyer,
						'referrer_id' => $ref,
						'eligible'    => $eligible,
						'commission'  => Money::percent( $eligible, $policy['referral_bps'] ),
						'status'      => $risk ? 'review' : 'pending',
						'approved'    => ! $risk && ! $policy['referral_approval'],
						'release_at'  => gmdate( 'Y-m-d H:i:s', ( $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : time() ) + $policy['hold_days'] * DAY_IN_SECONDS ),
						'flags'       => $risk ? 'identity_match' : '',
						'created_at'  => $order->get_date_completed() ? gmdate( 'Y-m-d H:i:s', $order->get_date_completed()->getTimestamp() ) : DB::now(),
					)
				);
				$old = DB::row( 'referral_conversions', $id );

			}

			if ( empty( $old['qualified_at'] ) && in_array( $order->get_status(), $policy['statuses'], true ) ) {
				$stamp = $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : time();
				DB::update(
					$t,
					array(
						'qualified_at' => gmdate( 'Y-m-d H:i:s', $stamp ),
						'created_at'   => gmdate( 'Y-m-d H:i:s', $stamp ),
						'release_at'   => gmdate( 'Y-m-d H:i:s', $stamp + $policy['hold_days'] * DAY_IN_SECONDS ),
					),
					array( 'id' => $old['id'] )
				);
				$old = DB::row( 'referral_conversions', (int) $old['id'] );
				Notifications::emit( $ref, 'referral-order:' . $order->get_id(), 'referral_order_confirmed' );
			}
			return $old;
	}
	private static function normalized_phone( string $phone ): string {
		return Membership::phone( $phone ); }
	public static function approve( int $id, string $reason ): void {
		global $wpdb;
		$r = DB::row( 'referral_conversions', $id );
		if ( ! $r || ! trim( $reason ) ) {
			throw new \DomainException( 'Conversion and reason required.' ); }
		DB::update( ( $wpdb->prefix . 'ghr_referral_conversions' ), array( 'approved' => 1 ), array( 'id' => $id ) );
		DB::audit( 'referral.approved', 'conversion:' . $id, array(), $reason );
		Jobs::order( (int) $r['order_id'] );
	}
	public static function stats( int $user, int $page = 1 ): array {
		global $wpdb;
		$clicks = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) clicks,COUNT(DISTINCT visitor_hash) visitors FROM ' . ( $wpdb->prefix . 'ghr_referral_visits' ) . ' WHERE referrer_id=%d', $user ), ARRAY_A );
		$orders = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) tracked,SUM(status=\'approved\') completed,COALESCE(SUM(CASE WHEN status=\'approved\' THEN eligible ELSE 0 END),0) eligible,COALESCE(SUM(CASE WHEN status=\'approved\' THEN commission ELSE 0 END),0) commission,SUM(status IN (\'pending\',\'held\',\'review\')) pending FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . ' WHERE referrer_id=%d', $user ), ARRAY_A );
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT id,order_id,status,eligible,commission,created_at FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . ' WHERE referrer_id=%d ORDER BY id DESC LIMIT 20 OFFSET %d', $user, ( max( 1, $page ) - 1 ) * 20 ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['order_reference'] = 'R-' . strtoupper( substr( hash_hmac( 'sha256', (string) $row['order_id'], wp_salt() ), 0, 10 ) );
			if ( 'approved' !== $row['status'] ) {
				$row['redemption_status'] = $row['status']; } else {
				$lots                     = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(l.remaining),0) available,COALESCE(SUM(l.expired),0) expired,COALESCE(SUM(l.debt_repaid),0) debt_repaid FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' l JOIN ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " t ON t.id=l.transaction_id WHERE t.order_id=%d AND t.wallet='referral' AND t.kind='earn'", $row['order_id'] ), ARRAY_A );
				$used                     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(a.units),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . ' r ON r.id=a.reservation_id JOIN ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' l ON l.id=a.lot_id JOIN ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " t ON t.id=l.transaction_id WHERE t.order_id=%d AND t.wallet='referral' AND a.state='spent' AND r.state IN ('committed','restored')", $row['order_id'] ) );
				$row['redemption_status'] = (int) $lots['available'] > 0 ? ( $used > 0 ? 'partially_redeemed' : 'available' ) : ( $used > 0 ? 'redeemed' : ( (int) $lots['expired'] > 0 ? 'expired' : ( (int) $lots['debt_repaid'] > 0 ? 'debt_recovery' : 'reserved_or_held' ) ) );
				}
				unset( $row['order_id'] );
		} unset( $row );
		// No buyer identifiers or private order links are exposed.
		return array(
			'visits'   => $clicks,
			'summary'  => $orders,
			'activity' => $rows,
		);
	}
}
