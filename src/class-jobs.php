<?php
namespace GhoroaHat\Rewards;

final class Jobs {
	public const HOOKS = array( 'ghr_order_reconcile', 'ghr_maintenance', 'ghr_monthly', 'ghr_notification_delivery', 'ghr_verification_delivery', 'ghr_enroll_batch', 'ghr_broadcast', 'ghr_recovery_batch', 'ghr_score_refresh' );
	public static function hooks(): void {
		add_action( 'ghr_order_reconcile', array( Orders::class, 'reconcile' ) );
		add_action( 'ghr_maintenance', array( self::class, 'maintenance' ) );
		add_action( 'ghr_score_refresh', array( Leaderboard::class, 'prepare' ) );
		add_action( 'ghr_monthly', array( self::class, 'monthly' ) );
		add_action( 'ghr_notification_delivery', array( Notifications::class, 'deliver' ) );
		add_action( 'ghr_verification_delivery', array( Notifications::class, 'deliver_verification' ) );
		add_action( 'ghr_enroll_batch', array( self::class, 'enroll_batch' ) );
		add_action( 'ghr_broadcast', array( self::class, 'broadcast' ), 10, 3 );
		add_action( 'ghr_recovery_batch', array( self::class, 'recovery_batch' ) );
		add_action(
			'action_scheduler_init',
			static function () {
				foreach ( array(
					'ghr_maintenance' => 60,
					'ghr_monthly'     => 3600,
				) as $hook => $interval ) {
					if ( ! as_has_scheduled_action( $hook, array(), 'ghr' ) ) {
						as_schedule_recurring_action( time() + 30, $interval, $hook, array(), 'ghr', true );
					}
				} }
		);
		add_action(
			'action_scheduler_failed_execution',
			static function ( $id ) {
				$action = \ActionScheduler::store()->fetch_action( $id );
				if ( 'ghr_verification_delivery' === $action->get_hook() ) {
					$args   = $action->get_args();
					$job_id = $args[0] ?? '';
					$job    = get_option( 'ghr_verify_job_' . $job_id );
					if ( $job && $job['expires'] > time() && ( $job['attempts'] ?? 0 ) < 3 ) {
						$attempt         = (int) ( $job['attempts'] ?? 0 );
						$job['attempts'] = $attempt + 1;
						update_option( 'ghr_verify_job_' . $job_id, $job, false );
						as_schedule_single_action( time() + 60 * 2 ** $attempt, 'ghr_verification_delivery', array( $job_id ), 'ghr' ); }
				}

				if ( 'ghr_order_reconcile' === $action->get_hook() ) {
					$args     = $action->get_args();
					$order_id = (int) ( $args[0] ?? 0 );
					$attempts = (int) get_option( 'ghr_order_retry_' . $order_id, 0 );
					if ( $order_id && $attempts < 5 ) {
						update_option( 'ghr_order_retry_' . $order_id, $attempts + 1, false );
						as_schedule_single_action( time() + 60 * 2 ** $attempts, 'ghr_order_reconcile', array( $order_id ), 'ghr' ); }
				}

				DB::audit( 'job.failed', 'action:' . $id );
			}
		);
	}
	public static function order( int $id ): void {
		if ( $id && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'ghr_order_reconcile', array( $id ), 'ghr' ); } }
	public static function maintenance(): void {
		global $wpdb;
		$users = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " WHERE state='reserved' AND order_id=0 AND expires_at<=%s LIMIT 100", DB::now() ) );
		foreach ( $users as $user ) {
			DB::transaction(
				static function () use ( $user ) {
					Wallet::lock_member( (int) $user );
					Wallet::expire_user( (int) $user );
				}
			); }
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT a.user_id FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' l JOIN ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' a ON a.id=l.account_id WHERE remaining>0 AND expires_at<=%s LIMIT 100', DB::now() ) ) as $user ) {
			DB::transaction(
				static function () use ( $user ) {
					Wallet::lock_member( (int) $user );
					Wallet::expire_user( (int) $user );
				}
			); }
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT order_id FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE approved=1 AND status IN ('pending','held') AND release_at<=%s LIMIT 100", DB::now() ) ) as $order ) {
			self::order( (int) $order ); }
		foreach ( $wpdb->get_col( 'SELECT DISTINCT order_id FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " WHERE order_id>0 AND state='reserved' LIMIT 100" ) as $order ) {
			self::order( (int) $order ); }
		DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " SET state='retry' WHERE state='sending' AND next_attempt<=%s AND attempts<%d", DB::now(), Settings::get( 'retry_limit' ) ) );
		DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " SET state='failed' WHERE state='sending' AND next_attempt<=%s AND attempts>=%d", DB::now(), Settings::get( 'retry_limit' ) ) );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " WHERE state IN ('pending','retry') AND next_attempt<=%s LIMIT 50", DB::now() ) ) as $id ) {
			if ( ! as_has_scheduled_action( 'ghr_notification_delivery', array( (int) $id ), 'ghr' ) ) {
				as_enqueue_async_action( 'ghr_notification_delivery', array( (int) $id ), 'ghr' ); }
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id,user_id,state,expires_at FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . " WHERE state IN ('unopened','opened') AND expires_at IS NOT NULL AND expires_at<=%s LIMIT 100", gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) ), ARRAY_A ) as $r ) {
			if ( $r['expires_at'] <= DB::now() ) {
				DB::update(
					( $wpdb->prefix . 'ghr_reward_assignments' ),
					array(
						'state'       => 'expired',
						'explanation' => 'Campaign expiry reached.',
					),
					array( 'id' => $r['id'] )
				);
				DB::audit( 'reward.expired', 'reward:' . $r['id'] ); } else {
				Notifications::emit( (int) $r['user_id'], 'expiring:' . $r['id'], 'reward_expiring' ); }
		}
		foreach ( array(
			'referral_visits'        => 90,
			'referral_attributions'  => 90,
			'identity_verifications' => 30,
		) as $table => $days ) {
			$column = 'identity_verifications' === $table ? 'expires_at' : 'created_at';
			DB::query( $wpdb->prepare( 'DELETE FROM ' . DB::table( $table ) . " WHERE $column<%s LIMIT 500", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table and column come only from the fixed cleanup map; expiry value is prepared.
		DB::query( $wpdb->prepare( 'DELETE FROM ' . ( $wpdb->prefix . 'ghr_notifications' ) . ' WHERE read_at IS NOT NULL AND created_at<%s LIMIT 500', gmdate( 'Y-m-d H:i:s', time() - 365 * DAY_IN_SECONDS ) ) );
		// Remove expired rate counters without touching unrelated options.
		$prefix  = $wpdb->esc_like( 'ghr_rate_' ) . '%';
		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 200", $prefix ) );
		foreach ( $options as $name ) {
			if ( (int) substr( $name, strrpos( $name, '_' ) + 1 ) < time() - 60 ) {
				delete_option( $name ); }
		}
		DB::query( $wpdb->prepare( 'DELETE FROM ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " WHERE state IN ('sent','failed','skipped') AND next_attempt<%s LIMIT 500", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
		$verification_jobs = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100", $wpdb->esc_like( 'ghr_verify_job_' ) . '%' ) );
		foreach ( $verification_jobs as $name ) {
			$job = get_option( $name );
			if ( isset( $job['expires'] ) && $job['expires'] < time() ) {
				delete_option( $name ); }
		}
		update_option( 'ghr_last_maintenance', DB::now(), false );
	}
	public static function monthly(): void {
		if ( ! Settings::get( 'leaderboard_enabled' ) ) {
			return; }
		Leaderboard::prepare( wp_date( 'Y-m' ) );
		$previous = ( new \DateTimeImmutable( 'first day of last month', wp_timezone() ) )->format( 'Y-m' );
		Leaderboard::prepare( $previous );
		update_option( 'ghr_month_review_due', $previous, false );
	}
	public static function enroll_batch( int $page = 1 ): void {
		$users = get_users(
			array(
				'role__in' => array( 'customer', 'subscriber' ),
				'number'   => 100,
				'paged'    => $page,
				'fields'   => 'ID',
			)
		);
		foreach ( $users as $id ) {
			Membership::enroll( (int) $id );
		} if ( count( $users ) === 100 ) {
			as_enqueue_async_action( 'ghr_enroll_batch', array( $page + 1 ), 'ghr' ); } }
	public static function broadcast( string $event, string $reference, int $cursor = 0 ): void {
		global $wpdb;
		$users = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . " WHERE status='active' AND user_id>%d ORDER BY user_id LIMIT 100", $cursor ) );
		foreach ( $users as $user ) {
			Notifications::emit( (int) $user, $reference, $event ); }
		if ( count( $users ) === 100 ) {
			as_enqueue_async_action( 'ghr_broadcast', array( $event, $reference, (int) end( $users ) ), 'ghr' ); }
	}

	public static function recovery_batch( int $cursor = 0 ): void {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,order_id FROM ' . ( $wpdb->prefix . 'ghr_order_effects' ) . ' WHERE id>%d ORDER BY id LIMIT 100', $cursor ), ARRAY_A );
		foreach ( array_unique( array_column( $rows, 'order_id' ) ) as $order ) {
			self::order( (int) $order ); }
		if ( count( $rows ) === 100 ) {
			as_enqueue_async_action( 'ghr_recovery_batch', array( (int) end( $rows )['id'] ), 'ghr' ); } else {
			delete_option( 'ghr_recovery_needed' );
			update_option( 'ghr_last_recovery', DB::now(), false ); }
	}
}
