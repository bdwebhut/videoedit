<?php
namespace GhoroaHat\Rewards;

/** Reconciles an order effect with immutable, source-linked ledger postings. */
final class OrderEffects {
	public static function reconcile( int $order, string $effect, int $user, string $wallet, int $desired, int $eligible, array $policy ): void {
		if ( ! $user || ! Membership::get( $user ) ) {
			return; }
		global $wpdb;
		$t   = ( $wpdb->prefix . 'ghr_order_effects' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE order_id=%d AND effect_key=%s FOR UPDATE", $order, $effect ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed order_effects table; values use placeholders.
		if ( ! $row ) {
			$id  = DB::insert(
				'order_effects',
				array(
					'order_id'   => $order,
					'effect_key' => $effect,
					'user_id'    => $user,
					'wallet'     => $wallet,
					'policy'     => wp_json_encode( $policy ),
					'created_at' => DB::now(),
					'updated_at' => DB::now(),
				)
			);
			$row = DB::row( 'order_effects', $id ); }
		if ( $eligible > 0 && 0 === (int) $row['revision'] && 0 === (int) $row['eligible'] ) {
			$wc_order  = wc_get_order( $order );
			$completed = $wc_order ? $wc_order->get_date_completed() : null;
			if ( $completed ) {
				DB::update( $t, array( 'created_at' => gmdate( 'Y-m-d H:i:s', $completed->getTimestamp() ) ), array( 'id' => $row['id'] ) ); }
		}
		$delta    = $desired - (int) $row['posted'];
		$revision = (int) $row['revision'];
		if ( $delta ) {
			++$revision;
			if ( $delta > 0 ) {
				$old_lot = $wpdb->get_row( $wpdb->prepare( 'SELECT l.expires_at FROM ' . ( $wpdb->prefix . 'ghr_credit_lots' ) . ' l JOIN ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " t ON t.id=l.transaction_id WHERE t.idempotency_key LIKE %s AND t.kind='earn' ORDER BY t.id LIMIT 1", $wpdb->esc_like( 'order:' . $order . ':' . $effect . ':' ) . '%' ), ARRAY_A );
				Wallet::post( $user, $wallet, $delta, 'order:' . $order . ':' . $effect . ':' . $revision, 'earn', (string) $order, $order, 'Order reward reconciliation', null, null, (int) $policy[ $wallet . '_expiry_days' ], $old_lot ? $old_lot['expires_at'] : false );
				Wallet::expire_user( $user );
			} else {
				$credits   = $wpdb->get_results( $wpdb->prepare( 'SELECT t.id,t.amount-COALESCE((SELECT SUM(-r.amount) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' r WHERE r.reversal_of=t.id AND r.amount<0),0) remaining FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " t WHERE t.idempotency_key LIKE %s AND t.kind='earn' AND t.amount>0 ORDER BY t.id", $wpdb->esc_like( 'order:' . $order . ':' . $effect . ':' ) . '%' ), ARRAY_A );
				$remaining = -$delta;
				foreach ( $credits as $credit ) {
					$take = min( $remaining, max( 0, (int) $credit['remaining'] ) );
					if ( $take ) {
						Wallet::reverse_credit( (int) $credit['id'], $take, 'order:' . $order . ':' . $effect . ':' . $revision . ':reverse:' . $credit['id'], 'Order reward reconciliation', $order );
						$remaining -= $take; }
				}
				if ( $remaining ) {
					throw new \DomainException( 'Source credits do not match the order effect; reconciliation required.' ); }
			}
		}
		DB::update(
			$t,
			array(
				'desired'    => $desired,
				'posted'     => $desired,
				'eligible'   => $eligible,
				'revision'   => $revision,
				'updated_at' => DB::now(),
			),
			array( 'id' => $row['id'] )
		);
	}
}
