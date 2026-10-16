<?php
namespace GhoroaHat\Rewards;

final class Rest {
	public static function hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) ); }
	public static function permission( $request ): bool {
		return is_user_logged_in() && (bool) wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ); }
	private static function route( string $path, string $methods, callable $callback, ?callable $permission = null ): void {
		register_rest_route(
			'ghr/v1',
			$path,
			array(
				'methods'             => $methods,
				'callback'            => static function ( $r ) use ( $callback ) {
					try {
						$response = rest_ensure_response( $callback( $r ) );
						$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
						return $response; } catch ( \DomainException | \InvalidArgumentException $e ) {
						return new \WP_Error( 'ghr_validation', $e->getMessage(), array( 'status' => 400 ) ); } catch ( \Throwable $e ) {
							wc_get_logger()->error( 'Ghoroa Hat request failed: ' . get_class( $e ), array( 'source' => 'ghr' ) );
							return new \WP_Error( 'ghr_failure', __( 'The operation could not be completed. Check system health.', 'ghoroa-hat-rewards' ), array( 'status' => 500 ) ); }
				},
				'permission_callback' => $permission ?? array(
					self::class,
					'permission',
				),
			)
		);
	}
	public static function register(): void {
		self::route(
			'/me',
			'GET',
			static fn() => array(
				'membership'  => Membership::get( get_current_user_id() ),
				'preferences' => Notifications::preferences( get_current_user_id() ),
			)
		);
		self::route( '/me/wallets', 'GET', static fn() => Wallet::summary( get_current_user_id() ) );
		self::route( '/me/transactions', 'GET', static fn( $r ) => Wallet::history( get_current_user_id(), sanitize_key( $r['wallet'] ?? '' ), max( 1, absint( $r['page'] ?? 1 ) ), 20, array_intersect_key( $r->get_params(), array_flip( array( 'kind', 'status', 'direction', 'from', 'to' ) ) ) ) );
		self::route( '/me/referrals', 'GET', static fn( $r ) => Referral::stats( get_current_user_id(), max( 1, absint( $r['page'] ?? 1 ) ) ) );
		self::route( '/me/rewards', 'GET', static fn( $r ) => Rewards::list( get_current_user_id(), max( 1, absint( $r['page'] ?? 1 ) ) ) );
		self::route(
			'/me/notifications',
			'GET',
			static function ( $r ) {
				global $wpdb;
				return array(
					'items'  => Notifications::list( get_current_user_id(), max( 1, absint( $r['page'] ?? 1 ) ) ),
					'unread' => Notifications::unread( get_current_user_id() ),
				);
			}
		);
		self::route( '/membership/enroll', 'POST', static fn() => Membership::enroll( get_current_user_id() ) );
		self::route(
			'/verification/request',
			'POST',
			static function ( $r ) {
				Membership::request_verification( get_current_user_id(), sanitize_key( $r['purpose'] ?? '' ), sanitize_text_field( $r['target'] ?? '' ) );
				return array( 'sent' => true );
			}
		);
		self::route(
			'/verification/confirm',
			'POST',
			static function ( $r ) {
				Membership::confirm( get_current_user_id(), sanitize_key( $r['purpose'] ?? '' ), sanitize_text_field( $r['token'] ?? '' ) );
				return array( 'verified' => true );
			}
		);
		self::route( '/checkout', 'GET', static fn() => Checkout::state( get_current_user_id() ) );
		self::route( '/checkout/reservations', 'POST', static fn( $r ) => Checkout::select( get_current_user_id(), (array) $r->get_json_params() ) );
		self::route(
			'/checkout/reservations/(?P<id>[a-f0-9]{64})',
			'DELETE',
			static function ( $r ) {
				Wallet::release( $r['id'], get_current_user_id() );
				return array( 'released' => true );
			}
		);
		foreach ( array( 'open', 'claim' ) as $action ) {
			self::route( '/rewards/(?P<id>\d+)/' . $action, 'POST', static fn( $r ) => Rewards::transition( get_current_user_id(), absint( $r['id'] ), $action ) ); }
		self::route(
			'/notifications/(?P<id>\d+)/read',
			'POST',
			static function ( $r ) {
				Notifications::read( get_current_user_id(), absint( $r['id'] ) );
				return array( 'read' => true );
			}
		);
		self::route(
			'/notifications/read-all',
			'POST',
			static function () {
				Notifications::read( get_current_user_id() );
				return array( 'read' => true );
			}
		);
		self::route(
			'/me/preferences',
			'POST',
			static function ( $r ) {
				Notifications::save_preferences( get_current_user_id(), (array) $r->get_json_params() );
				return Notifications::preferences( get_current_user_id() );
			}
		);
		self::route( '/leaderboard', 'GET', static fn( $r ) => Leaderboard::live( sanitize_text_field( $r['month'] ?? '' ), sanitize_key( $r['category'] ?? 'regular' ), absint( $r['limit'] ?? 20 ) ), '__return_true' );
		self::route( '/winners', 'GET', static fn( $r ) => Leaderboard::winners( sanitize_text_field( $r['month'] ?? '' ) ), '__return_true' );
		self::route(
			'/admin/(?P<resource>members|transactions|commissions|results|campaigns)',
			'GET',
			static function ( $r ) {
				global $wpdb;
				$tables = array(
					'members'      => 'memberships',
					'transactions' => 'wallet_transactions',
					'commissions'  => 'referral_conversions',
					'results'      => 'monthly_results',
					'campaigns'    => 'reward_campaigns',
				);
				$page   = max( 1, absint( $r['page'] ?? 1 ) );
				return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . DB::table( $tables[ $r['resource'] ] ) . ' ORDER BY id DESC LIMIT 20 OFFSET %d', ( $page - 1 ) * 20 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Resource table comes from the fixed route map; offset is prepared.
			},
			static fn( $r ) => self::permission( $r ) && current_user_can(
				array(
					'members'      => 'ghr_manage_members',
					'transactions' => 'ghr_adjust_wallets',
					'commissions'  => 'ghr_approve_commissions',
					'results'      => 'ghr_manage_competitions',
					'campaigns'    => 'ghr_manage_rewards',
				)[ $r['resource'] ]
			)
		);
		self::route( '/admin/health', 'GET', static fn() => Admin::health(), static fn( $r ) => self::permission( $r ) && current_user_can( 'ghr_manage_settings' ) );
		self::route( '/admin/commands/(?P<command>[a-z-]+)', 'POST', static fn( $r ) => self::command( $r['command'], (array) $r->get_json_params() ), static fn( $r ) => self::permission( $r ) && current_user_can( self::command_cap( $r['command'] ) ) );
	}
	public static function command_cap( string $command ): string {
		return match ( $command ) {
			'member-status', 'member-note', 'enroll-existing' => 'ghr_manage_members', 'verify-phone' => 'ghr_verify_identity', 'adjust', 'reverse', 'hold', 'late-settlement' => 'ghr_adjust_wallets', 'approve', 'reconcile-order', 'refund-review' => 'ghr_approve_commissions', 'prepare', 'finalize', 'publish', 'month-rules' => 'ghr_manage_competitions', 'definition', 'campaign', 'assign', 'fulfill', 'revoke' => 'ghr_manage_rewards', 'announcement', 'retry-delivery' => 'ghr_manage_notifications', 'reconcile-preview', 'reconcile-execute' => 'ghr_reconcile_identities', default => 'ghr_manage_settings' }; }
	public static function command( string $command, array $d ): array {
		if ( ! current_user_can( self::command_cap( $command ) ) ) {
			throw new \DomainException( 'Access denied.' ); }
		$user   = absint( $d['user_id'] ?? 0 );
		$reason = sanitize_textarea_field( $d['reason'] ?? '' );
		$ref    = sanitize_text_field( $d['reference'] ?? '' );
		$result = array( 'success' => true );
		switch ( $command ) {
			case 'member-status':
				Membership::status( $user, sanitize_key( $d['status'] ?? '' ), $reason );
				break;
			case 'member-note':
				if ( ! $reason || ! Membership::get( $user ) ) {
					throw new \DomainException( 'Member and note required.' );
				} DB::audit( 'member.note', 'user:' . $user, array(), $reason );
				break;
			case 'verify-phone':
				if ( ! $reason ) {
					throw new \DomainException( 'Document verification evidence first.' ); }
				DB::transaction(
					static function () use ( $user, $d, $reason ) {
						global $wpdb;
						Wallet::lock_member( $user );
						$phone = Membership::phone( $d['phone'] ?? '' );
						if ( false === DB::update(
							( $wpdb->prefix . 'ghr_memberships' ),
							array(
								'phone'          => $phone,
								'verified_phone' => $phone,
							),
							array( 'user_id' => $user )
						) ) {
							throw new \DomainException( 'Phone already verified for another member.' );
						} DB::audit( 'identity.phone_admin_verified', 'user:' . $user, array(), $reason );
					}
				);
				break;
			case 'enroll-existing':
				update_option( 'ghr_auto_enroll', true, false );
				as_enqueue_async_action( 'ghr_enroll_batch', array( 1 ), 'ghr' );
				break;
			case 'adjust':
				if ( ! $ref || ! $reason || ! preg_match( '/^-?\d{1,12}$/D', (string) ( $d['amount'] ?? '' ) ) ) {
					throw new \DomainException( 'Signed integer units, unique reference and reason required.' ); }
				$result['transaction_id'] = Wallet::post( $user, sanitize_key( $d['wallet'] ?? '' ), (int) $d['amount'], 'adjust:' . $ref, 'adjustment', $ref, 0, $reason );
				break;
			case 'reverse':
				$original = DB::row( 'wallet_transactions', absint( $d['transaction_id'] ?? 0 ) );
				if ( ! $original || ! $reason || ! $ref || in_array( $original['kind'], array( 'redeem', 'restore', 'expiry', 'reversal' ), true ) ) {
					throw new \DomainException( 'Select an earning/adjustment transaction and provide reference and reason.' ); }
				if ( (int) $original['order_id'] > 0 ) {
					throw new \DomainException( 'Correct order earnings through order/refund reconciliation. Use a separate adjustment for discretionary credits.' ); }
				if ( (int) $original['amount'] > 0 ) {
					Wallet::reverse_credit( (int) $original['id'], (int) $original['amount'], 'manual-reverse:' . $original['id'], $reason ); } else {
					Wallet::post( (int) $original['user_id'], $original['wallet'], - (int) $original['amount'], 'manual-reverse:' . $original['id'], 'adjustment', $ref, 0, $reason, (int) $original['id'] ); }
				break;
			case 'late-settlement':
				$result['reservation_id'] = Checkout::resolve_released_order( absint( $d['order_id'] ?? 0 ), $reason, in_array( $d['debt_approval'] ?? '', array( 'ALLOW REWARD DEBT' ), true ) );
				break;
			case 'hold':
				if ( ! preg_match( '/^-?\d{1,12}$/D', (string) ( $d['amount'] ?? '' ) ) ) {
					throw new \DomainException( 'Integer units required.' );
				} Wallet::hold( $user, sanitize_key( $d['wallet'] ?? '' ), (int) $d['amount'], $reason );
				break;
			case 'approve':
				Referral::approve( absint( $d['conversion_id'] ?? 0 ), $reason );
				break;
			case 'refund-review':
				$allocations = is_array( $d['allocations'] ?? null ) ? $d['allocations'] : json_decode( $d['allocations'] ?? '{}', true );
				if ( ! is_array( $allocations ) ) {
					throw new \DomainException( 'Enter a JSON object of order item IDs and product refund paisa.' ); }
				Orders::review_refund( absint( $d['order_id'] ?? 0 ), absint( $d['refund_id'] ?? 0 ), $allocations, $reason );
				break;
			case 'reconcile-order':
				Orders::reconcile( absint( $d['order_id'] ?? 0 ) );
				break;
			case 'month-rules':
				Leaderboard::set_rules( sanitize_text_field( $d['month'] ?? '' ), $d, $reason );
				break;
			case 'prepare':
				$result['scores'] = Leaderboard::prepare( sanitize_text_field( $d['month'] ?? '' ) );
				break;
			case 'finalize':
				$result['snapshot_id'] = Leaderboard::finalize( sanitize_text_field( $d['month'] ?? '' ), $reason );
				break;
			case 'publish':
				Leaderboard::publish( absint( $d['snapshot_id'] ?? 0 ), $reason );
				break;
			case 'definition':
				$d['config']             = array_intersect_key( $d, array_flip( array( 'product_id', 'value', 'expiry_days' ) ) );
				$result['definition_id'] = Rewards::definition( $d );
				break;
			case 'campaign':
				$result['campaign_id'] = Rewards::campaign( $d );
				break;
			case 'assign':
				if ( ! $ref ) {
					throw new \DomainException( 'Unique assignment reference required.' );
				} $result['reward_id'] = Rewards::assign( $user, absint( $d['campaign_id'] ?? 0 ), 'manual:' . $ref );
				break;
			case 'fulfill':
			case 'revoke':
				$result = Rewards::transition( $user, absint( $d['reward_id'] ?? 0 ), $command, $reason );
				break;
			case 'announcement':
				if ( ! $ref || empty( $d['message'] ) ) {
					throw new \DomainException( 'Message and unique reference required.' );
				} Notifications::emit( $user, 'announcement:' . $ref, 'announcement', array( 'reward_name' => sanitize_textarea_field( $d['message'] ) ) );
				break;
			case 'retry-delivery':
				global $wpdb;
				if ( ! $reason ) {
					throw new \DomainException( 'Retry reason required.' );
				} DB::update(
					( $wpdb->prefix . 'ghr_notification_deliveries' ),
					array(
						'state'        => 'retry',
						'attempts'     => 0,
						'next_attempt' => DB::now(),
					),
					array(
						'id'    => absint( $d['delivery_id'] ?? 0 ),
						'state' => 'failed',
					)
				);
				DB::audit( 'delivery.retry', 'delivery:' . absint( $d['delivery_id'] ), array(), $reason );
				break;
			case 'reconcile-preview':
				$result = Reconciliation::preview( absint( $d['source_id'] ?? 0 ), absint( $d['target_id'] ?? 0 ), $reason );
				break;
			case 'reconcile-execute':
				Reconciliation::execute( absint( $d['case_id'] ?? 0 ), sanitize_text_field( $d['confirmation'] ?? '' ) );
				break;
			case 'removal-preview':
				$result = Removal::preview();
				break;
			case 'removal-confirm':
				Removal::confirm( sanitize_text_field( $d['ticket'] ?? '' ), sanitize_text_field( $d['confirmation'] ?? '' ), sanitize_text_field( $d['backup_confirmation'] ?? '' ) );
				break;
			case 'maintenance':
				Jobs::maintenance();
				break;
			default:
				throw new \DomainException( 'Unknown command.' );
		} return $result;
	}
}
