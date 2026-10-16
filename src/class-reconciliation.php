<?php
namespace GhoroaHat\Rewards;

/** Audited identity reconciliation; WordPress users and WooCommerce orders remain intact. */
final class Reconciliation {
	public static function bonus_revoked( int $order ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_reward_entitlements' ) . ' WHERE order_id=%d AND revoked=1 LIMIT 1', $order ) );
	}
	public static function preview( int $source, int $target, string $evidence ): array {
		if ( ! $source || $source === $target || ! Membership::get( $source ) || ! Membership::get( $target ) || Membership::canonical( $source ) !== $source || Membership::canonical( $target ) !== $target || ! trim( $evidence ) ) {
			throw new \DomainException( 'Two distinct canonical members and documented identity evidence are required.' ); }
		$plan = array(
			'source'         => $source,
			'target'         => $target,
			'source_wallets' => Wallet::summary( $source ),
			'target_wallets' => Wallet::summary( $target ),
			'policy'         => 'Preserve target identity; close source membership; preserve all original ledger rows, WordPress users and WooCommerce orders. Transfer financial obligations and credit-lot expiry provenance. If both identities received the lifetime bonus, reverse the source bonus with linked ledger postings. Cancel unbound checkout intents; resolve bound reservations before execution.',
		);
		$id   = DB::insert(
			'reconciliation_cases',
			array(
				'source_id'  => $source,
				'target_id'  => $target,
				'evidence'   => $evidence,
				'plan'       => wp_json_encode( $plan ),
				'reviewer'   => get_current_user_id(),
				'state'      => 'preview',
				'created_at' => DB::now(),
			)
		);
		DB::audit( 'identity.reconciliation_preview', 'case:' . $id );
		return array(
			'case_id'      => $id,
			'confirmation' => 'RECONCILE-' . $id,
			'plan'         => $plan,
		);
	}
	public static function execute( int $id, string $confirmation ): void {
		if ( 'RECONCILE-' . $id !== $confirmation ) {
			throw new \DomainException( 'Enter the exact preview confirmation.' ); }
		$changed_users = array();
		try {
			DB::transaction(
				static function () use ( $id, &$changed_users ) {
					global $wpdb;
					$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reconciliation_cases' ) . ' WHERE id=%d FOR UPDATE', $id ), ARRAY_A );
					if ( ! $c || 'preview' !== $c['state'] || strtotime( $c['created_at'] . ' UTC' ) < time() - DAY_IN_SECONDS ) {
						throw new \DomainException( 'Reconciliation preview is missing, expired or already executed.' ); }
					$source = (int) $c['source_id'];
					$target = (int) $c['target_id'];
					$roots  = array( $source, $target );
					sort( $roots, SORT_NUMERIC );
					foreach ( $roots as $u ) {
						Wallet::lock_member( $u );
						wp_cache_delete( $u, 'user_meta' ); }
					if ( Membership::canonical( $source ) !== $source || Membership::canonical( $target ) !== $target || ! Membership::active( $target ) ) {
						throw new \DomainException( 'The target must be an active, verified canonical member.' ); }
					$sources       = Membership::sources( $source );
					$targets       = Membership::sources( $target );
					$changed_users = array_values( array_unique( array_merge( $sources, $targets ) ) );
					foreach ( $roots as $u ) {
						Wallet::release_unbound( $u );
						foreach ( Wallet::summary( $u ) as $a ) {
							if ( $a['reserved'] ) {
								throw new \DomainException( 'Resolve bound checkout reservations before reconciliation.' ); }
						}
					}
					if ( wc_get_orders(
						array(
							'customer_id' => $source,
							'status'      => array( 'pending', 'on-hold', 'checkout-draft' ),
							'limit'       => 1,
							'return'      => 'ids',
						)
					) ) {
						throw new \DomainException( 'Resolve unpaid source orders before reconciliation.' ); }
					$placeholders = implode( ',', array_fill( 0, count( $changed_users ), '%d' ) );
					$duplicates   = $wpdb->get_var( $wpdb->prepare( 'SELECT c.month FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . " c ON c.id=a.campaign_id WHERE a.user_id IN ($placeholders) AND a.slot LIKE %s AND a.state NOT IN ('revoked','expired') GROUP BY c.month HAVING COUNT(*)>1 LIMIT 1", ...array_merge( $changed_users, array( $wpdb->esc_like( 'monthly:' ) . '%' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN list contains generated %d placeholders only; all user IDs and LIKE value are prepared.
					if ( $duplicates ) {
						throw new \DomainException( 'Resolve duplicate monthly awards for ' . $duplicates . ' before consolidating these identities.' ); }
					$source_bonus = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_entitlements' ) . " WHERE user_id=%d AND benefit='trust' AND scope='lifetime'", $source ), ARRAY_A );
					$target_bonus = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_entitlements' ) . " WHERE user_id=%d AND benefit='trust' AND scope='lifetime'", $target ), ARRAY_A );
					if ( $source_bonus && $target_bonus && ! $source_bonus['revoked'] ) {
						DB::update( ( $wpdb->prefix . 'ghr_reward_entitlements' ), array( 'revoked' => 1 ), array( 'id' => $source_bonus['id'] ) );
						Orders::reconcile( (int) $source_bonus['order_id'] );
					} elseif ( $source_bonus && ! $target_bonus ) {
						DB::insert(
							'reward_entitlements',
							array(
								'user_id'    => $target,
								'benefit'    => 'trust',
								'scope'      => 'lifetime',
								'revoked'    => $source_bonus['revoked'],
								'order_id'   => $source_bonus['order_id'],
								'created_at' => DB::now(),
							)
						);
					}
					$transfers = array();
					foreach ( array( 'trust', 'referral', 'seeds' ) as $wallet ) {
						$from   = Wallet::account( $source, $wallet, true );
						$to     = Wallet::account( $target, $wallet, true );
						$amount = $from['posted'];
						$debt   = max( 0, -$to['posted'] );
						if ( $amount > 0 ) {
							Wallet::post( $source, $wallet, -$amount, 'reconcile:' . $id . ':' . $wallet . ':source', 'reconciliation_debit', 'case:' . $id, 0, $c['evidence'], null, 0 );
							Wallet::post( $target, $wallet, $amount, 'reconcile:' . $id . ':' . $wallet . ':target', 'reconciliation_credit', 'case:' . $id, 0, $c['evidence'] );
						} elseif ( $amount < 0 ) {
							Wallet::post( $source, $wallet, -$amount, 'reconcile:' . $id . ':' . $wallet . ':source', 'reconciliation_credit', 'case:' . $id, 0, $c['evidence'] );
							Wallet::post( $target, $wallet, $amount, 'reconcile:' . $id . ':' . $wallet . ':target', 'reconciliation_debit', 'case:' . $id, 0, $c['evidence'] );
						}
						DB::update( ( $wpdb->prefix . 'ghr_credit_lots' ), array( 'account_id' => $to['id'] ), array( 'account_id' => $from['id'] ) );
						DB::update(
							( $wpdb->prefix . 'ghr_wallet_reservations' ),
							array(
								'account_id' => $to['id'],
								'user_id'    => $target,
							),
							array( 'account_id' => $from['id'] )
						);
						if ( $amount > 0 && $debt ) {
							Wallet::settle_migrated_debt( $to['id'], min( $amount, $debt ), 'case-debt:' . $id . ':' . $wallet ); }
						if ( $from['held'] ) {
							DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' SET held=held+%d WHERE id=%d', $from['held'], $to['id'] ) );
							DB::update( ( $wpdb->prefix . 'ghr_wallet_accounts' ), array( 'held' => 0 ), array( 'id' => $from['id'] ) ); }
						$transfers[ $wallet ] = array(
							'units' => $amount,
							'held'  => $from['held'],
						);
					}
					foreach ( array(
						'order_effects'         => 'user_id',
						'reward_assignments'    => 'user_id',
						'referral_visits'       => 'referrer_id',
						'referral_attributions' => 'referrer_id',
						'referral_conversions'  => 'referrer_id',
					) as $table => $column ) {
						DB::update( DB::table( $table ), array( $column => $target ), array( $column => $source ) ); }
					$sm = Membership::get( $source );
					$tm = Membership::get( $target );
					DB::update(
						( $wpdb->prefix . 'ghr_memberships' ),
						array(
							'status'         => 'closed',
							'verified_phone' => null,
						),
						array( 'user_id' => $source )
					);
					if ( ! $tm['verified_phone'] && $sm['verified_phone'] ) {
						DB::update(
							( $wpdb->prefix . 'ghr_memberships' ),
							array(
								'phone'          => $sm['verified_phone'],
								'verified_phone' => $sm['verified_phone'],
							),
							array( 'user_id' => $target )
						); }
					update_user_meta( $source, '_ghr_identity_target', $target );
					update_user_meta( $target, '_ghr_identity_sources', array_values( array_diff( $changed_users, array( $target ) ) ) );
					DB::update( ( $wpdb->prefix . 'ghr_reconciliation_cases' ), array( 'state' => 'executed' ), array( 'id' => $id ) );
					DB::audit(
						'identity.reconciled',
						'case:' . $id,
						array(
							'source'       => $source,
							'target'       => $target,
							'transfers'    => $transfers,
							'bonus_policy' => 'keep target lifetime entitlement',
						),
						$c['evidence']
					);
					foreach ( array_unique( $wpdb->get_col( $wpdb->prepare( 'SELECT order_id FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE buyer_id IN ($placeholders) AND referrer_id IN ($placeholders)", ...array_merge( $changed_users, $changed_users ) ) ) ) as $order ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- IN list contains generated %d placeholders only; all user IDs and LIKE value are prepared.
						Jobs::order( (int) $order ); }
				}
			);
		} finally {
			foreach ( $changed_users as $u ) {
				wp_cache_delete( $u, 'user_meta' ); }
		}
	}
}
