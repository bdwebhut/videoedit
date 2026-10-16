<?php
namespace GhoroaHat\Rewards;

final class Wallet {
	public static function guard(): void {
		if ( ( 'BDT' !== get_woocommerce_currency() || 2 !== wc_get_price_decimals() ) || (int) get_option( 'ghr_db_version' ) !== 1 ) {
			throw new \DomainException( 'Rewards are unavailable; verify currency and system configuration.' ); }
	}
	public static function lock_member( int $user ): array {
		global $wpdb;
		$m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' WHERE user_id=%d FOR UPDATE', $user ), ARRAY_A );
		if ( ! $m ) {
			throw new \DomainException( 'Membership not found.' );
		} return $m;
	}
	public static function account( int $user, string $wallet, bool $lock = false ): array {
		if ( ! in_array( $wallet, array( 'trust', 'referral', 'seeds' ), true ) ) {
			throw new \DomainException( 'Unknown wallet.' ); }
		global $wpdb;
		$t = ( $wpdb->prefix . 'ghr_wallet_accounts' );

		$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id=%d AND wallet=%s" . ( $lock ? ' FOR UPDATE' : '' ), $user, $wallet ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Trusted prefixed table and fixed lock clause; values use placeholders.
		if ( ! $a ) {
			DB::query( $wpdb->prepare( "INSERT INTO $t (user_id,wallet,updated_at) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)", $user, $wallet, DB::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed table; values use placeholders.
			$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id=%d AND wallet=%s" . ( $lock ? ' FOR UPDATE' : '' ), $user, $wallet ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Trusted prefixed table and fixed lock clause; values use placeholders.
		}

		foreach ( array( 'id', 'posted', 'reserved', 'held', 'earned', 'redeemed', 'reversed' ) as $k ) {
			$a[ $k ] = (int) $a[ $k ];
		} $a['available'] = max( 0, $a['posted'] - $a['reserved'] - $a['held'] );
		return $a;
	}
	public static function summary( int $user ): array {
		if ( Membership::get( $user ) ) {
			DB::transaction(
				static function () use ( $user ) {
					self::lock_member( $user );
					self::expire_user( $user );
				}
			); }
		global $wpdb;
		$out = array();
		foreach ( array( 'trust', 'referral', 'seeds' ) as $wallet ) {
			$a = self::account( $user, $wallet );

			$a['pending'] = 'referral' === $wallet ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(commission),0) FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE referrer_id=%d AND status IN ('pending','review','held')", $user ) ) : 0;
			$aliases      = Membership::sources( $user );
			if ( count( $aliases ) > 1 ) {
				$totals = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(earned) earned,SUM(redeemed) redeemed,SUM(reversed) reversed FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' WHERE wallet=%s AND user_id IN (' . implode( ',', array_fill( 0, count( $aliases ), '%d' ) ) . ')', ...array_merge( array( $wallet ), $aliases ) ), ARRAY_A );
				foreach ( $totals as $k => $v ) {
					$a[ $k ] = (int) $v; }
			}
			$out[ $wallet ] = $a;
		} return $out;
	}
	public static function history( int $user, string $wallet = '', int $page = 1, int $limit = 20, array $filters = array() ): array {
		global $wpdb;
		$ids   = Membership::sources( $user );
		$where = $wpdb->prepare( 't.user_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids );
		if ( $wallet ) {
			$where .= $wpdb->prepare( ' AND t.wallet=%s', $wallet );
		}
		foreach ( array( 'kind', 'status' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$where .= $wpdb->prepare( ' AND t.' . $key . '=%s', sanitize_key( $filters[ $key ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column comes only from the fixed kind/status list; value is prepared.
			}
		}
		if ( in_array( $filters['direction'] ?? '', array( 'credit', 'debit' ), true ) ) {
			$where .= 'credit' === $filters['direction'] ? ' AND t.amount>0' : ' AND t.amount<0';
		}
		foreach ( array( 'from', 'to' ) as $key ) {
			$value = (string) ( $filters[ $key ] ?? '' );
			$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
			if ( $date && $date->format( 'Y-m-d' ) === $value ) {
				if ( 'to' === $key ) {
					$date = $date->modify( '+1 day' );
				}
				$where .= $wpdb->prepare( ' AND t.created_at' . ( 'from' === $key ? '>=' : '<' ) . '%s', $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Operator comes only from fixed from/to keys; UTC value is prepared.
			}
		}
		$limit = min( 100, max( 1, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT t.id,t.wallet,t.kind,t.amount,t.status,t.source_type,t.source_ref,t.order_id,t.created_at,l.expires_at FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' t LEFT JOIN ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . " l ON l.transaction_id=t.id WHERE $where ORDER BY t.id DESC LIMIT %d OFFSET %d", $limit, ( max( 1, $page ) - 1 ) * $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE contains only fixed clauses and already-prepared filter values.
		foreach ( $rows as &$row ) {
			if ( 'referral' === $row['wallet'] ) {
				$row['order_reference'] = $row['order_id'] ? substr( hash_hmac( 'sha256', (string) $row['order_id'], wp_salt( 'auth' ) ), 0, 12 ) : '';
				if ( $row['order_id'] ) {
					$row['source_ref'] = $row['order_reference'];
				}
				unset( $row['order_id'] );
			}
		}
		unset( $row );
		return $rows;
	}
	public static function post( int $user, string $wallet, int $amount, string $key, string $kind, string $source, int $order = 0, string $reason = '', ?int $reversal = null, ?int $lot_debit = null, ?int $expiry_days = null, string|false|null $expiry_override = false ): int {
		self::guard();
		if ( Membership::canonical( $user ) !== $user ) {
			throw new \DomainException( 'Use the canonical member for financial adjustments.' ); }
		return DB::transaction(
			static function () use ( $user, $wallet, $amount, $key, $kind, $source, $order, $reason, $reversal, $lot_debit, $expiry_days, $expiry_override ) {
				global $wpdb;
				$member = self::lock_member( $user );
				if ( 'closed' === $member['status'] ) {
					wp_cache_delete( $user, 'user_meta' );
					if ( Membership::canonical( $user ) !== $user ) {
						throw new \DomainException( 'Use the reconciled canonical membership.' ); }
				}
				$a   = self::account( $user, $wallet, true );
				$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE idempotency_key=%s', $key ), ARRAY_A );
				if ( $old ) {
					if ( (int) $old['user_id'] !== $user || $old['wallet'] !== $wallet || (int) $old['amount'] !== $amount ) {
								throw new \DomainException( 'Idempotency key conflict.' );
					} return (int) $old['id']; }
				if ( ! $amount ) {
					throw new \DomainException( 'A nonzero amount is required.' ); }
				if ( $amount < 0 && ! in_array( $kind, array( 'reversal', 'expiry', 'reconciliation_debit', 'debt_redemption' ), true ) && $a['available'] < -$amount ) {
					throw new \DomainException( 'Insufficient available balance.' ); }
				$id = DB::insert(
					'wallet_transactions',
					array(
						'account_id'      => $a['id'],
						'user_id'         => $user,
						'wallet'          => $wallet,
						'kind'            => $kind,
						'amount'          => $amount,
						'source_type'     => $order ? 'order' : 'admin',
						'source_ref'      => $source,
						'order_id'        => $order,
						'idempotency_key' => $key,
						'actor'           => get_current_user_id(),
						'reversal_of'     => $reversal,
						'reason'          => $reason,
						'created_at'      => DB::now(),
					)
				);
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET posted=posted+%d,redeemed=redeemed+%d,earned=earned+%d,reversed=reversed+%d,updated_at=%s WHERE id=%d', $amount, $amount < 0 && in_array( $kind, array( 'redeem', 'debt_redemption' ), true ) ? -$amount : 0, $amount > 0 && ! in_array( $kind, array( 'restore', 'expiry_recovery', 'reconciliation_credit' ), true ) ? $amount : 0, 'expiry_recovery' === $kind ? -$amount : ( $amount < 0 && in_array( $kind, array( 'reversal', 'expiry' ), true ) ? -$amount : 0 ), DB::now(), $a['id'] ) );
				$expires_at = null;
				if ( $amount > 0 && ! in_array( $kind, array( 'expiry_recovery', 'reconciliation_credit' ), true ) ) {
					$days       = $expiry_days ?? (int) Settings::get( $wallet . '_expiry_days' );
					$expires_at = false === $expiry_override ? ( $days ? gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) : null ) : $expiry_override;
					$usable     = max( 0, $amount + min( 0, $a['posted'] ) );
					if ( $amount > 0 ) {
						DB::insert(
							'credit_lots',
							array(
								'transaction_id' => $id,
								'account_id'     => $a['id'],
								'original'       => $amount,
								'debt_repaid'    => $amount - $usable,
								'remaining'      => $usable,
								'expires_at'     => $expires_at,
							)
						); }
				} elseif ( $amount < 0 ) {
					// Reversals may invalidate reservations. Unbound intents are released; bound orders enter recovery.
					if ( 'reversal' === $kind ) {
						self::release_unbound( $user ); }
					$preferred = $reversal;
					if ( 'expiry' === $kind && str_starts_with( $source, 'lot:' ) ) {
						$source_lot = DB::row( 'credit_lots', (int) substr( $source, 4 ) );
						$preferred  = $source_lot ? (int) $source_lot['transaction_id'] : null; }
					self::consume_lots( $a['id'], $lot_debit ?? -$amount, in_array( $kind, array( 'redeem', 'debt_redemption' ), true ) ? $source : 'posting:' . $id, 'expiry' === $kind ? 'expired' : 'spent', in_array( $kind, array( 'reversal', 'reconciliation_debit', 'debt_redemption' ), true ), $preferred );
				}
				DB::audit(
					'wallet.' . $kind,
					'user:' . $user,
					array(
						'transaction' => $id,
						'wallet'      => $wallet,
						'amount'      => $amount,
						'order'       => $order,
					),
					$reason
				);
				$event = $amount < 0 ? ( in_array( $kind, array( 'redeem', 'debt_redemption' ), true ) ? 'wallet_credit_redeemed' : 'wallet_credit_reversed' ) : ( 'trust' === $wallet ? 'trust_bonus_earned' : ( 'seeds' === $wallet ? 'trust_seeds_earned' : 'referral_commission_approved' ) );
				if ( 'expiry_recovery' !== $kind ) {
					Notifications::emit(
						$user,
						'transaction:' . $id,
						$event,
						array(
							'reward_amount'  => 'seeds' === $wallet ? $amount : Money::decimal( $amount ),
							'wallet_balance' => 'seeds' === $wallet ? (string) max( 0, $a['available'] + $amount ) : Money::decimal( max( 0, $a['available'] + $amount ) ),
							'order_number'   => $order,
						)
					);
				} $threshold = 'seeds' === $wallet ? (int) Settings::get( 'seeds_min' ) : (int) Settings::get( 'referral_min' );
				if ( 'expiry_recovery' !== $kind && in_array( $wallet, array( 'seeds', 'referral' ), true ) && $a['available'] < $threshold && $a['available'] + $amount >= $threshold && ( ! $expires_at || $expires_at > DB::now() ) ) {
					Notifications::emit( $user, 'redeemable:' . $id, $wallet . '_redeemable' ); }
				return $id;
			}
		);
	}
	public static function settle_migrated_debt( int $account, int $units, string $reference ): void {
		if ( $units > 0 ) {
			self::consume_lots( $account, $units, $reference, 'debt_repaid', true ); } }
	private static function consume_lots( int $account, int $units, string $ref, string $state, bool $allow_debt = false, ?int $preferred_credit = null ): void {
		global $wpdb;
		$lots = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' WHERE account_id=%d AND remaining>0 ORDER BY (transaction_id=%d) DESC,expires_at IS NULL,expires_at,id FOR UPDATE', $account, $preferred_credit ?? 0 ), ARRAY_A );
		foreach ( $lots as $lot ) {
			$take = min( $units, (int) $lot['remaining'] );
			if ( ! $take ) {
				break; }
			DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET remaining=remaining-%d WHERE id=%d', $take, $lot['id'] ) );
			DB::insert(
				'credit_allocations',
				array(
					'lot_id'         => $lot['id'],
					'reservation_id' => $ref,
					'units'          => $take,
					'state'          => $state,
				)
			);
			if ( 'debt_repaid' === $state ) {
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET debt_repaid=debt_repaid+%d WHERE id=%d', $take, $lot['id'] ) ); }
			if ( 'expired' === $state ) {
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET expired=expired+%d WHERE id=%d', $take, $lot['id'] ) ); }
			$units -= $take;
		}
		if ( $units && ! $allow_debt ) {
			throw new \DomainException( 'Credit lots are unavailable; reconcile the wallet.' ); }
	}
	public static function reserve( int $user, string $wallet, int $units, string $intent, string $cart_hash ): array {
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( $wallet . '_enabled' ) ) {
			throw new \DomainException( 'This reward program or wallet is paused.' ); }
		self::guard();
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{16,64}$/D', $intent ) || $units <= 0 ) {
			throw new \DomainException( 'Invalid checkout intent or amount.' ); }
		return DB::transaction(
			static function () use ( $user, $wallet, $units, $intent, $cart_hash ) {
				global $wpdb;
				$m = self::lock_member( $user );
				if ( ! self::can_spend( $m ) ) {
					throw new \DomainException( 'Active verified membership is required.' ); }
				self::expire_user( $user );
				$a   = self::account( $user, $wallet, true );
				$id  = hash_hmac( 'sha256', $user . ':' . $intent, wp_salt() );
				$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . ' WHERE id=%s FOR UPDATE', $id ), ARRAY_A );
				if ( $old && 'reserved' === $old['state'] && ! $old['order_id'] && $old['cart_hash'] === $cart_hash && $old['wallet'] === $wallet && (int) $old['units'] === $units && $old['expires_at'] > DB::now() ) {
					return $old; }
				if ( $old && ( $old['order_id'] || 'committed' === $old['state'] ) ) {
					throw new \DomainException( 'Checkout intent is already bound to an order.' ); }
				if ( $old && 'reserved' === $old['state'] ) {
					self::release( $old['id'], $user );
					$a = self::account( $user, $wallet, true ); }
				if ( $a['available'] < $units ) {
					throw new \DomainException( 'Insufficient available balance.' ); }
				// New reservation generation preserves all prior lot-allocation history.
				if ( $old ) {
					$id = bin2hex( random_bytes( 32 ) ); }
				$r = array(
					'id'          => $id,
					'account_id'  => $a['id'],
					'user_id'     => $user,
					'wallet'      => $wallet,
					'intent_hash' => hash( 'sha256', $intent ),
					'cart_hash'   => $cart_hash,
					'units'       => $units,
					'state'       => 'reserved',
					'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 1200 ),
					'created_at'  => DB::now(),
				);
				self::consume_lots( $a['id'], $units, $id, 'reserved' );
				DB::insert( 'wallet_reservations', $r );
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET reserved=reserved+%d WHERE id=%d', $units, $a['id'] ) );
				DB::audit(
					'wallet.reserved',
					'user:' . $user,
					array(
						'reservation' => $id,
						'units'       => $units,
					)
				);
				return $r;
			}
		);
	}
	public static function can_spend( array $m ): bool {
		return 'active' === $m['status'] && ! empty( $m['email_verified_at'] ); }
	public static function reservation( string $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . ' WHERE id=%s', $id ), ARRAY_A ); }
	public static function bind( string $id, int $user, int $order ): void {
		DB::transaction(
			static function () use ( $id, $user, $order ) {
				global $wpdb;
				$m = self::lock_member( $user );
				$r = self::reservation( $id );
				if ( ! $r || (int) $r['user_id'] !== $user || ! self::can_spend( $m ) || ! in_array( $r['state'], array( 'reserved', 'committed' ), true ) || ( $r['order_id'] && (int) $r['order_id'] !== $order ) || ( ! $r['order_id'] && $r['expires_at'] <= DB::now() ) ) {
					throw new \DomainException( 'Wallet reservation is no longer valid.' ); }
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . ' SET order_id=%d WHERE id=%s', $order, $id ) );
			}
		);
	}
	public static function commit( string $id, int $user, int $order ): void {
		DB::transaction(
			static function () use ( $id, $user, $order ) {
				global $wpdb;
				self::bind( $id, $user, $order );
				$r = self::reservation( $id );
				if ( 'committed' === $r['state'] ) {
					return; }
				$a = self::account( $user, $r['wallet'], true );
				if ( $a['posted'] - $a['held'] < (int) $r['units'] ) {
					throw new \DomainException( 'Wallet credit was reversed; checkout must be recalculated.' ); }
				$tx = DB::insert(
					'wallet_transactions',
					array(
						'account_id'      => $a['id'],
						'user_id'         => $user,
						'wallet'          => $r['wallet'],
						'kind'            => 'redeem',
						'amount'          => - (int) $r['units'],
						'source_type'     => 'order',
						'source_ref'      => (string) $order,
						'order_id'        => $order,
						'idempotency_key' => 'redeem:' . $id,
						'actor'           => $user,
						'created_at'      => DB::now(),
					)
				);
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET posted=posted-%d,reserved=reserved-%d,redeemed=redeemed+%d WHERE id=%d', $r['units'], $r['units'], $r['units'], $a['id'] ) );
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " SET state='committed' WHERE id=%s", $id ) );
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . " SET state='spent' WHERE reservation_id=%s", $id ) );
				DB::audit(
					'wallet.redeemed',
					'user:' . $user,
					array(
						'transaction' => $tx,
						'order'       => $order,
					)
				);
				Notifications::emit(
					$user,
					'redeem:' . $id,
					'wallet_credit_redeemed',
					array(
						'wallet_balance' => 'seeds' === $r['wallet'] ? (string) $a['available'] : Money::decimal( $a['available'] ),
						'order_number'   => $order,
					)
				);
			}
		);
	}
	public static function release( string $id, int $user ): void {
		DB::transaction(
			static function () use ( $id, $user ) {
				global $wpdb;
				self::lock_member( $user );
				$r = self::reservation( $id );
				if ( ! $r || (int) $r['user_id'] !== $user ) {
					throw new \DomainException( 'Reservation not found.' ); }
				if ( 'reserved' !== $r['state'] || $r['order_id'] ) {
					return; }
				self::account( $user, $r['wallet'], true );
				$expired = self::restore_lots( $id, (int) $r['units'] );
				if ( $expired ) {
					DB::insert(
						'wallet_transactions',
						array(
							'account_id'      => $r['account_id'],
							'user_id'         => $user,
							'wallet'          => $r['wallet'],
							'kind'            => 'expiry',
							'amount'          => -$expired,
							'source_type'     => 'reservation',
							'source_ref'      => $id,
							'idempotency_key' => 'release-expiry:' . $id,
							'created_at'      => DB::now(),
						)
					);
					DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET posted=posted-%d,reversed=reversed+%d WHERE id=%d', $expired, $expired, $r['account_id'] ) );
				}
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET reserved=reserved-%d WHERE id=%d', $r['units'], $r['account_id'] ) );
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " SET state='released' WHERE id=%s", $id ) );
				DB::audit( 'wallet.released', 'user:' . $user, array( 'reservation' => $id ) );
			}
		);
	}
	private static function restore_lots( string $id, int $units ): int {
		global $wpdb;
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*,l.expires_at,l.original,l.remaining,l.expired,l.debt_repaid,l.transaction_id FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . " l ON l.id=a.lot_id WHERE a.reservation_id=%s AND a.state IN ('reserved','spent') ORDER BY a.id FOR UPDATE", $id ), ARRAY_A );
		$expired = 0;
		foreach ( $rows as $row ) {
			$take = min( $units, (int) $row['units'] );
			if ( ! $take ) {
				break; }
			$reversed    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(-amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE reversal_of=%d AND amount<0', $row['transaction_id'] ) );
			$other       = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(units),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . " WHERE lot_id=%d AND id<>%d AND state IN ('reserved','spent')", $row['lot_id'], $row['id'] ) );
			$outstanding = $other + max( 0, (int) $row['units'] - $take );
			$usable      = min( $take, max( 0, (int) $row['original'] - $reversed - (int) $row['remaining'] - (int) $row['expired'] - (int) $row['debt_repaid'] - $outstanding ) );
			if ( $row['expires_at'] && $row['expires_at'] <= DB::now() ) {
				$expired += $usable;
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET expired=expired+%d WHERE id=%d', $usable, $row['lot_id'] ) ); } else {
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET remaining=remaining+%d WHERE id=%d', $usable, $row['lot_id'] ) ); }
				if ( $take === (int) $row['units'] ) {
					DB::update( ( $wpdb->prefix . 'ghr_credit_allocations' ), array( 'state' => 'restored' ), array( 'id' => $row['id'] ) ); } else {
								DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . ' SET units=units-%d WHERE id=%d', $take, $row['id'] ) ); }
					$units -= $take;
		} return $expired;
	}
	public static function reverse_credit( int $transaction, int $amount, string $key, string $reason, int $order = 0 ): void {
		DB::transaction(
			static function () use ( $transaction, $amount, $key, $reason, $order ) {
				global $wpdb;
				$tx = DB::row( 'wallet_transactions', $transaction );
				if ( ! $tx || (int) $tx['amount'] <= 0 || $amount <= 0 ) {
					throw new \DomainException( 'Select a positive source credit.' ); }
				$user = Membership::canonical( (int) $tx['user_id'] );
				self::lock_member( $user );
				self::expire_user( $user );
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE idempotency_key=%s', $key ) ) ) {
					return; }
				$already = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(-amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE reversal_of=%d AND amount<0', $transaction ) );
				if ( $amount > (int) $tx['amount'] - $already ) {
					throw new \DomainException( 'Credit reversal exceeds its unreversed source amount.' ); }
				$expired   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(expired),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' WHERE transaction_id=%d', $transaction ) );
				$recovered = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " WHERE kind='expiry_recovery' AND reversal_of=%d", $transaction ) );
				$recovery  = min( $amount, max( 0, $expired - $recovered ) );
				self::post( $user, $tx['wallet'], -$amount, $key, 'reversal', $tx['source_ref'], $order, $reason, $transaction, $amount - $recovery );
				if ( $recovery ) {
					self::post( $user, $tx['wallet'], $recovery, $key . ':expiry', 'expiry_recovery', $tx['source_ref'], $order, 'Offset already-expired source credit', $transaction ); }
			}
		);
	}

	/** Restore credits that previously covered a reversed grant's debt, retaining their original expiry. */
	private static function restore_debt_payments( int $account, int $units ): int {
		global $wpdb;
		$expired = 0;
		$lots    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' WHERE account_id=%d ORDER BY expires_at IS NULL,expires_at,id FOR UPDATE', $account ), ARRAY_A );
		foreach ( $lots as $lot ) {
			if ( ! $units ) {
				break; }
			$reversed    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(-amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE reversal_of=%d AND amount<0', $lot['transaction_id'] ) );
			$outstanding = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(units),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . " WHERE lot_id=%d AND state IN ('reserved','spent')", $lot['id'] ) );
			$capacity    = max( 0, (int) $lot['original'] - $reversed - (int) $lot['remaining'] - (int) $lot['expired'] - $outstanding );
			$take        = min( $units, (int) $lot['debt_repaid'], $capacity );
			if ( $take ) {
				$is_expired = $lot['expires_at'] && $lot['expires_at'] <= DB::now();
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET debt_repaid=debt_repaid-%d,remaining=remaining+%d,expired=expired+%d WHERE id=%d', $take, $is_expired ? 0 : $take, $is_expired ? $take : 0, $lot['id'] ) );
				$expired += $is_expired ? $take : 0;
				$units   -= $take;
			}
			// A reversal may have recovered its debt immediately from other existing credits.
			$allocations = $wpdb->get_results( $wpdb->prepare( 'SELECT a.* FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " t ON a.reservation_id=CONCAT('posting:',t.id) WHERE a.lot_id=%d AND a.state='spent' AND t.kind IN ('reversal','reconciliation_debit') ORDER BY a.id", $lot['id'] ), ARRAY_A );
			foreach ( $allocations as $allocation ) {
				if ( ! $units ) {
					break; }
				$current  = DB::row( 'credit_lots', (int) $lot['id'] );
				$other    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(units),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . " WHERE lot_id=%d AND id<>%d AND state IN ('reserved','spent')", $lot['id'], $allocation['id'] ) );
				$capacity = max( 0, (int) $current['original'] - $reversed - (int) $current['remaining'] - (int) $current['expired'] - (int) $current['debt_repaid'] - $other );
				$take     = min( $units, (int) $allocation['units'], $capacity );
				if ( ! $take ) {
					continue; }
				$is_expired = $lot['expires_at'] && $lot['expires_at'] <= DB::now();
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' SET remaining=remaining+%d,expired=expired+%d WHERE id=%d', $is_expired ? 0 : $take, $is_expired ? $take : 0, $lot['id'] ) );
				if ( $take === (int) $allocation['units'] ) {
					DB::update( ( $wpdb->prefix . 'ghr_credit_allocations' ), array( 'state' => 'restored' ), array( 'id' => $allocation['id'] ) ); } else {
					DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_credit_allocations' ) . ' SET units=units-%d WHERE id=%d', $take, $allocation['id'] ) ); }
					$expired += $is_expired ? $take : 0;
					$units   -= $take;
			}
		}
		if ( $units ) {
			throw new \DomainException( 'Refund credit provenance needs debt reconciliation.' ); }
		return $expired;
	}
	public static function restore( string $id, int $cumulative, string $key ): void {
		$r = self::reservation( $id );
		if ( ! $r || ! in_array( $r['state'], array( 'committed', 'restored' ), true ) ) {
			return; }
		DB::transaction(
			static function () use ( $r, $id, $cumulative, $key ) {
				global $wpdb;
				self::lock_member( (int) $r['user_id'] );
				self::account( (int) $r['user_id'], $r['wallet'], true );
				$already = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " WHERE source_ref=%s AND kind='restore'", $id ) );
				$delta   = min( (int) $r['units'], $cumulative ) - $already;
				if ( $delta <= 0 ) {
					return; }
				$expired   = self::restore_lots( $id, $delta );
				$current   = self::account( (int) $r['user_id'], $r['wallet'], true );
				$lot_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(remaining),0) FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' WHERE account_id=%d', $r['account_id'] ) );
				$gap       = max( 0, $current['posted'] + $delta - $expired - $current['reserved'] ) - $lot_total;
				if ( $gap > 0 ) {
					$expired += self::restore_debt_payments( (int) $r['account_id'], $gap ); }
				DB::insert(
					'wallet_transactions',
					array(
						'account_id'      => $r['account_id'],
						'user_id'         => $r['user_id'],
						'wallet'          => $r['wallet'],
						'kind'            => 'restore',
						'amount'          => $delta,
						'source_type'     => 'refund',
						'source_ref'      => $id,
						'order_id'        => $r['order_id'],
						'idempotency_key' => $key,
						'actor'           => get_current_user_id(),
						'created_at'      => DB::now(),
					)
				);
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET posted=posted+%d,reversed=reversed+%d WHERE id=%d', $delta - $expired, $expired, $r['account_id'] ) );
				if ( $expired ) {
					DB::insert(
						'wallet_transactions',
						array(
							'account_id'      => $r['account_id'],
							'user_id'         => $r['user_id'],
							'wallet'          => $r['wallet'],
							'kind'            => 'expiry',
							'amount'          => -$expired,
							'source_type'     => 'refund',
							'source_ref'      => $id,
							'order_id'        => $r['order_id'],
							'idempotency_key' => $key . ':expired',
							'created_at'      => DB::now(),
						)
					); }
				if ( $cumulative >= (int) $r['units'] ) {
					DB::update( ( $wpdb->prefix . 'ghr_wallet_reservations' ), array( 'state' => 'restored' ), array( 'id' => $id ) ); }
				DB::audit(
					'wallet.restored',
					'user:' . $r['user_id'],
					array(
						'units'   => $delta,
						'expired' => $expired,
					)
				);
			}
		);
	}
	public static function release_unbound( int $user ): void {
		global $wpdb;
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " WHERE user_id=%d AND state='reserved' AND order_id=0", $user ) ) as $id ) {
			self::release( $id, $user ); } }
	public static function expire_user( int $user ): void {
		global $wpdb;
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT r.id FROM ' . ( $wpdb->prefix . 'ghr_wallet_reservations' ) . " r WHERE user_id=%d AND state='reserved' AND order_id=0 AND expires_at<=%s", $user, DB::now() ), ARRAY_A ) as $r ) {
			self::release( $r['id'], $user ); }
		$lots = $wpdb->get_results( $wpdb->prepare( 'SELECT l.*,a.wallet FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' l JOIN ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' a ON a.id=l.account_id WHERE a.user_id=%d AND l.remaining>0 AND l.expires_at<=%s', $user, DB::now() ), ARRAY_A );
		foreach ( $lots as $lot ) {
			self::post( $user, $lot['wallet'], - (int) $lot['remaining'], 'expire:' . $lot['id'], 'expiry', 'lot:' . $lot['id'] ); }
	}
	public static function hold( int $user, string $wallet, int $amount, string $reason ): void {
		DB::transaction(
			static function () use ( $user, $wallet, $amount, $reason ) {
				global $wpdb;
				self::lock_member( $user );
				$a = self::account( $user, $wallet, true );
				if ( ! trim( $reason ) || $a['held'] + $amount < 0 || ( $amount > 0 && $a['available'] < $amount ) ) {
					throw new \DomainException( 'Invalid hold/release or missing reason.' ); }
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET held=held+%d WHERE id=%d', $amount, $a['id'] ) );
				DB::audit(
					'wallet.hold',
					'user:' . $user,
					array(
						'wallet' => $wallet,
						'amount' => $amount,
					),
					$reason
				);
			}
		);
	}
}
