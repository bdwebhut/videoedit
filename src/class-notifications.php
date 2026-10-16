<?php
namespace GhoroaHat\Rewards;

final class Notifications {
	public const EVENTS = array(
		'membership_registered'        => 'সদস্যপদ নিবন্ধিত হয়েছে',
		'membership_activated'         => 'সদস্যপদ সক্রিয় হয়েছে',
		'phone_verification_requested' => 'মোবাইল যাচাই করুন',
		'trust_bonus_earned'           => 'ট্রাস্ট বোনাস পেয়েছেন',
		'referral_click_recorded'      => 'রেফারেল লিংক দেখা হয়েছে',
		'referral_order_confirmed'     => 'রেফারেল অর্ডার নিশ্চিত হয়েছে',
		'referral_commission_approved' => 'রেফারেল কমিশন অনুমোদিত',
		'referral_redeemable'          => 'রেফারেল ওয়ালেট ব্যবহারযোগ্য',
		'trust_seeds_earned'           => 'বিশ্বাস বীজ পেয়েছেন',
		'seeds_redeemable'             => 'বিশ্বাস বীজ ব্যবহারযোগ্য',
		'wallet_credit_redeemed'       => 'ওয়ালেট ক্রেডিট ব্যবহার হয়েছে',
		'wallet_credit_reversed'       => 'ওয়ালেট ক্রেডিট সমন্বয় হয়েছে',
		'monthly_results_published'    => 'মাসিক ফলাফল প্রকাশিত',
		'monthly_prize_won'            => 'মাসিক পুরস্কার পেয়েছেন',
		'magic_box_assigned'           => 'ম্যাজিক বক্স পেয়েছেন',
		'magic_box_opened'             => 'ম্যাজিক বক্স খোলা হয়েছে',
		'reward_claimed'               => 'পুরস্কার দাবি করা হয়েছে',
		'reward_fulfilled'             => 'পুরস্কার প্রদান সম্পন্ন',
		'reward_expiring'              => 'পুরস্কারের মেয়াদ শেষ হবে',
		'announcement'                 => 'গুরুত্বপূর্ণ সদস্য বিজ্ঞপ্তি',
	);
	private static function owner_where( int $user ): string {
		global $wpdb;
		$ids = Membership::sources( $user );
		return $wpdb->prepare( 'user_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ); }
	public static function emit( int $user, string $key, string $event, array $tags = array() ): void {
		if ( ! get_userdata( $user ) ) {
			return; }
		global $wpdb;
		$t = ( $wpdb->prefix . 'ghr_notifications' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE " . self::owner_where( $user ) . ' AND event_key=%s', $key ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Trusted table and owner_where prepares integer user IDs; event key is prepared.
			return; }
		$templates = (array) get_option( 'ghr_notification_templates', array() );
		$m         = Membership::get( $user );
		$base      = array(
			'member_name'    => get_userdata( $user )->display_name,
			'member_id'      => $m['member_id'] ?? '',
			'site_name'      => get_bloginfo( 'name' ),
			'referral_code'  => $m['referral_code'] ?? '',
			'order_number'   => '',
			'reward_amount'  => '',
			'wallet_balance' => '',
			'reward_name'    => '',
		);
		$base      = array_replace( $base, array_intersect_key( $tags, $base ) );
		$replace   = array();
		foreach ( $base as $k => $v ) {
			$replace[ '{' . $k . '}' ] = sanitize_text_field( (string) $v ); }
		$title   = self::EVENTS[ $event ] ?? self::EVENTS['announcement'];
		$message = strtr( $templates[ $event ] ?? '{member_name}, ' . $title . '। {reward_amount} {reward_name}', $replace );
		DB::query( $wpdb->prepare( "INSERT INTO $t (user_id,event_key,category,title,message,created_at) VALUES (%d,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)", $user, $key, $event, $title, $message, DB::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed notifications table; values use placeholders.
		$id    = (int) $wpdb->insert_id;
		$prefs = self::preferences( $user );
		foreach ( array( 'email', 'sms' ) as $channel ) {
			if ( ! Settings::get( $channel . '_enabled' ) || empty( $prefs[ $channel ] ) ) {
				continue; }
			DB::query( $wpdb->prepare( 'INSERT INTO ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . ' (notification_id,channel,next_attempt) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE notification_id=VALUES(notification_id)', $id, $channel, DB::now() ) );
		}
	}
	public static function preferences( int $user ): array {
		global $wpdb;
		$json = $wpdb->get_var( $wpdb->prepare( 'SELECT preferences FROM ' . ( $wpdb->prefix . 'ghr_notification_preferences' ) . ' WHERE user_id=%d', $user ) );
		return array_replace(
			array(
				'email'            => true,
				'sms'              => false,
				'public_consent'   => false,
				'tracking_consent' => false,
			),
			(array) json_decode( $json ?? '{}', true )
		);
	}
	public static function save_preferences( int $user, array $input ): void {
		global $wpdb;
		$p = self::preferences( $user );
		foreach ( $p as $k => $v ) {
			if ( isset( $input[ $k ] ) ) {
				$p[ $k ] = (bool) $input[ $k ]; }
		}
		DB::query( $wpdb->prepare( 'INSERT INTO ' . ( $wpdb->prefix . 'ghr_notification_preferences' ) . ' (user_id,preferences,updated_at) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE preferences=VALUES(preferences),updated_at=VALUES(updated_at)', $user, wp_json_encode( $p ), DB::now() ) );
		foreach ( Membership::sources( $user ) as $alias ) {
			DB::update( ( $wpdb->prefix . 'ghr_memberships' ), array( 'public_consent' => (int) $p['public_consent'] ), array( 'user_id' => $alias ) ); }
		DB::audit( 'preferences.updated', 'user:' . $user );
	}
	public static function unread( int $user ): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_notifications' ) . ' WHERE ' . self::owner_where( $user ) . ' AND read_at IS NULL' ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- owner_where returns only prepared integer user-ID predicates.
	public static function list( int $user, int $page = 1 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id,category,title,message,link,read_at,created_at FROM ' . ( $wpdb->prefix . 'ghr_notifications' ) . ' WHERE ' . self::owner_where( $user ) . ' ORDER BY id DESC LIMIT 20 OFFSET %d', ( max( 1, $page ) - 1 ) * 20 ), ARRAY_A ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- owner_where prepares integer user IDs; offset is prepared.
	public static function read( int $user, int $id = 0 ): void {
		global $wpdb;
		$where = self::owner_where( $user ) . ' AND read_at IS NULL';
		if ( $id ) {
			$where .= $wpdb->prepare( ' AND id=%d', $id ); }
		DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_notifications' ) . " SET read_at=%s WHERE $where", DB::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Owner and optional notification predicates are prepared above.
	}
	public static function deliver( int $id ): void {
		global $wpdb;
		$t       = ( $wpdb->prefix . 'ghr_notification_deliveries' );
		$claimed = DB::query( $wpdb->prepare( "UPDATE $t SET state='sending',attempts=attempts+1,next_attempt=%s WHERE id=%d AND state IN ('pending','retry') AND next_attempt<=%s", gmdate( 'Y-m-d H:i:s', time() + 900 ), $id, DB::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed notification_deliveries table; values use placeholders.
		if ( ! $claimed ) {
			return; }
		$d = DB::row( 'notification_deliveries', $id );
		$n = DB::row( 'notifications', (int) $d['notification_id'] );
		try {
			if ( ! $n ) {
				throw new \RuntimeException( 'Notification no longer exists.' ); }
			$recipient = Membership::canonical( (int) $n['user_id'] );
			$user      = get_userdata( $recipient );
			if ( ! $user ) {
				throw new \RuntimeException( 'Account unavailable.' ); }
			if ( empty( self::preferences( $recipient )[ $d['channel'] ] ) ) {
				DB::update( $t, array( 'state' => 'skipped' ), array( 'id' => $id ) );
				return; }
			if ( 'email' === $d['channel'] ) {
				$sent = wp_mail( $user->user_email, $n['title'], $n['message'] ); } else {
				$m       = Membership::get( $recipient );
				$adapter = apply_filters( 'ghr_sms_adapter', null );
				if ( ! is_callable( $adapter ) || empty( $m['verified_phone'] ) ) {
					throw new \RuntimeException( 'SMS adapter or verified phone unavailable.' );
				} $sent = $adapter( $m['verified_phone'], $n['message'], 'ghr-delivery-' . $id ); }
				if ( ! $sent || is_wp_error( $sent ) ) {
					throw new \RuntimeException( 'Provider did not accept delivery.' ); }
				DB::update(
					$t,
					array(
						'state'      => 'sent',
						'last_error' => '',
					),
					array( 'id' => $id )
				);
		} catch ( \Throwable $e ) {
			DB::update(
				$t,
				array(
					'state'        => (int) $d['attempts'] >= Settings::get( 'retry_limit' ) ? 'failed' : 'retry',
					'last_error'   => 'Delivery failed; inspect provider configuration.',
					'next_attempt' => gmdate( 'Y-m-d H:i:s', time() + min( DAY_IN_SECONDS, 60 * 2 ** (int) $d['attempts'] ) ),
				),
				array( 'id' => $id )
			);
		}
	}
	public static function verification( int $user, string $purpose, string $target, string $token ): void {
		$nonce  = random_bytes( 12 );
		$tag    = '';
		$json   = wp_json_encode( compact( 'user', 'purpose', 'target', 'token' ) );
		$cipher = openssl_encrypt( $json, 'aes-256-gcm', hash( 'sha256', wp_salt(), true ), OPENSSL_RAW_DATA, $nonce, $tag );
		$id     = bin2hex( random_bytes( 16 ) );
		add_option(
			'ghr_verify_job_' . $id,
			array(
				'payload' => base64_encode( $nonce . $tag . $cipher ),
				'expires' => time() + 600,
			),
			'',
			false
		);
		as_enqueue_async_action( 'ghr_verification_delivery', array( $id ), 'ghr' );
	}
	public static function deliver_verification( string $id ): void {
		$job = get_option( 'ghr_verify_job_' . $id );
		if ( ! $job || $job['expires'] < time() ) {
			delete_option( 'ghr_verify_job_' . $id );
			return; }
		$bytes = base64_decode( $job['payload'], true );
		$json  = openssl_decrypt( substr( $bytes, 28 ), 'aes-256-gcm', hash( 'sha256', wp_salt(), true ), OPENSSL_RAW_DATA, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ) );
		$p     = json_decode( $json ? $json : '{}', true );
		if ( ! $p ) {
			delete_option( 'ghr_verify_job_' . $id );
			return; }
		/* translators: %s: The account owner's one-time verification code. */
		$message = sprintf( __( 'Your Ghoroa Hat verification code: %s. Enter it in your membership dashboard.', 'ghoroa-hat-rewards' ), $p['token'] );
		if ( 'email' === $p['purpose'] ) {
			$sent = wp_mail( $p['target'], __( 'Verify your Ghoroa Hat email', 'ghoroa-hat-rewards' ), $message ); } else {
			$adapter = apply_filters( 'ghr_sms_adapter', null );
			$sent    = is_callable( $adapter ) && $adapter( $p['target'], $message, 'verification:' . $id ); }
			if ( ! $sent ) {
				throw new \RuntimeException( 'Verification delivery failed.' );
			} delete_option( 'ghr_verify_job_' . $id );
	}
}
