<?php
namespace GhoroaHat\Rewards;

final class Orders {
	public static function reconcile( int $id ): void {
		Wallet::guard();
		$order = wc_get_order( $id );
		if ( ! $order || $order instanceof \WC_Order_Refund ) {
			return; }
		global $wpdb;
		$lock = 'ghr_order_' . $id;
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,10)', $lock ) ) ) {
			throw new \RuntimeException( 'Order reconciliation lock unavailable.' ); }
		try {
			$epoch     = get_option( 'ghr_ledger_epoch' );
			$old_epoch = $order->get_meta( '_ghr_epoch' );
			if ( $old_epoch && $old_epoch !== $epoch ) {
				return; }
			if ( ! $old_epoch ) {
				$order->update_meta_data( '_ghr_epoch', $epoch ); }
			$policy = $order->get_meta( '_ghr_policy' );
			if ( ! is_array( $policy ) ) {
				$policy = Settings::all();
				$order->update_meta_data( '_ghr_policy', $policy );
				$order->save(); }
			$policy = array_replace( Settings::defaults(), $policy );
			$calc   = Calculator::order( $order, $policy );
			if ( $calc['item_policy'] !== $order->get_meta( '_ghr_item_policy' ) ) {
				$order->update_meta_data( '_ghr_item_policy', $calc['item_policy'] );
				$order->save(); }
			$user      = Membership::canonical( (int) $order->get_customer_id() );
			$qualified = $policy['enabled'] && in_array( $order->get_status(), $policy['statuses'], true ) && $calc['eligible'] >= $policy['min_eligible'];
			foreach ( $order->get_refunds() as $refund ) {
				if ( ! $order->has_status( 'refunded' ) && ! $refund->get_items( 'line_item' ) && Money::minor( (string) $refund->get_amount() ) > 0 && ! $order->get_meta( '_ghr_refund_reviewed_' . $refund->get_id() ) ) {
					$order->update_meta_data( '_ghr_review_required', 'Unallocated refund ' . $refund->get_id() );
					$order->save();
					throw new \DomainException( 'Refund needs an audited item allocation review.' ); }
			}
			Checkout::reconcile_order( $order, $calc );
			DB::transaction(
				static function () use ( $order, $id, $policy, $calc, $user, $qualified ) {
					global $wpdb;
					$ref   = Membership::canonical( (int) $order->get_meta( '_ghr_referrer' ) );
					$users = array_unique( array_filter( array( $user, $ref ) ) );
					sort( $users, SORT_NUMERIC );
					foreach ( $users as $u ) {
						if ( Membership::get( $u ) ) {
										Wallet::lock_member( $u ); }
					}
					$m = $user ? Membership::get( $user ) : null;
					if ( $m && $qualified ) {
						Membership::activate( $user, $id ); }
					$prior_personal      = $user ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT eligible FROM ' . ( $wpdb->prefix . 'ghr_order_effects' ) . " WHERE order_id=%d AND effect_key='personal'", $id ) ) : 0;
					$completed_date      = $order->get_date_completed();
					$historical_eligible = ! $m || ! $completed_date || $policy['historical_rewards'] || gmdate( 'Y-m-d H:i:s', $completed_date->getTimestamp() ) >= $m['registered_at'];
					$earning             = $qualified && $m && ( Settings::get( 'enabled' ) || $prior_personal > 0 ) && ( $historical_eligible || $prior_personal > 0 ) && ( Membership::active( $user ) || $prior_personal > 0 );
					OrderEffects::reconcile( $id, 'personal', $user, 'seeds', TrustSeeds::desired( $earning, $calc['eligible'], $policy ), $earning ? $calc['eligible'] : 0, $policy );
					OrderEffects::reconcile( $id, 'bonus', $user, 'trust', TrustBonus::desired( $user, $id, $earning, $calc, $policy ), 0, $policy );
					ReferralCommission::reconcile( $order, $qualified, $calc, $policy );
				}
			);
			$order->delete_meta_data( '_ghr_review_required' );
			delete_option( 'ghr_order_retry_' . $id );
			$order->save();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
	public static function review_refund( int $order_id, int $refund_id, array $allocations, string $reason ): void {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order || ! $refund instanceof \WC_Order_Refund || $refund->get_parent_id() !== $order_id || ! trim( $reason ) ) {
			throw new \DomainException( 'Valid parent order, refund and evidence are required.' ); }
		$safe = array();
		foreach ( $allocations as $item_id => $minor ) {
			$item = $order->get_item( absint( $item_id ) );
			if ( ! $item || ! $item instanceof \WC_Order_Item_Product || ! preg_match( '/^\d{1,12}$/D', (string) $minor ) || (int) $minor > Money::minor( (string) $item->get_total() ) ) {
				throw new \DomainException( 'Invalid item allocation; use integer paisa within the original line total.' );
			} $safe[ $item->get_id() ] = (int) $minor; }
		if ( array_sum( $safe ) > Money::minor( (string) $refund->get_amount() ) ) {
			throw new \DomainException( 'Product allocations exceed the recorded refund.' ); }
		$existing = (array) $order->get_meta( '_ghr_manual_refunds' );
		if ( isset( $existing[ $refund_id ] ) && $existing[ $refund_id ] !== $safe ) {
			throw new \DomainException( 'Reviewed allocation is immutable; use a separate correction case.' ); }
		$existing[ $refund_id ] = $safe;
		$order->update_meta_data( '_ghr_manual_refunds', $existing );
		$order->update_meta_data( '_ghr_refund_reviewed_' . $refund_id, true );
		$order->save();
		DB::audit(
			'refund.reviewed',
			'order:' . $order_id,
			array(
				'refund'      => $refund_id,
				'allocations' => $safe,
			),
			$reason
		);
		Jobs::order( $order_id );
	}
}
