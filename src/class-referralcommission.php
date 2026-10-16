<?php
namespace GhoroaHat\Rewards;

/** Order-backed commission approval, holds and reversal orchestration. */
final class ReferralCommission {
	public static function reconcile( \WC_Order $order, bool $qualified, array $calc, array $policy ): void {
		global $wpdb;
		$id = $order->get_id();
		if ( $order->has_status( 'checkout-draft' ) ) {
			return; }
					$conversion        = $policy['referral_enabled'] ? Referral::conversion( $order, $calc['eligible'], $policy ) : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . ' WHERE order_id=%d', $id ), ARRAY_A );
					$referral_eligible = (bool) $conversion;
		if ( ! $conversion ) {
			$conversion = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . ' WHERE order_id=%d', $id ), ARRAY_A );
			if ( $conversion && 'approved' === $conversion['status'] && ! Membership::active( (int) $conversion['referrer_id'] ) && $qualified ) {
				$referral_eligible = true; }
		}
		if ( $conversion ) {
			$approved = ( Settings::get( 'enabled' ) || 'approved' === $conversion['status'] ) && $qualified && $referral_eligible && $conversion['approved'] && $conversion['release_at'] <= DB::now() && ( Membership::active( (int) $conversion['referrer_id'] ) || 'approved' === $conversion['status'] );
			$pending  = $referral_eligible && ! $qualified && ! $order->has_status( array( 'failed', 'cancelled', 'refunded' ) );
			$amount   = ( ( $qualified && $referral_eligible ) || $pending ) ? Money::percent( $calc['eligible'], $policy['referral_bps'] ) : 0;
			if ( ! Membership::active( (int) $conversion['referrer_id'] ) ) {
				$amount = min( $amount, (int) $conversion['commission'] ); }
			OrderEffects::reconcile( $id, 'referral', (int) $conversion['referrer_id'], 'referral', $approved ? $amount : 0, $qualified && $referral_eligible ? $calc['eligible'] : 0, $policy );
			DB::update(
				( $wpdb->prefix . 'ghr_referral_conversions' ),
				array(
					'eligible'   => $referral_eligible && ( $qualified || $pending ) ? $calc['eligible'] : 0,
					'commission' => $amount,
					'status'     => ! $referral_eligible ? 'rejected' : ( ! $qualified ? ( $pending ? 'pending' : 'reversed' ) : ( $approved ? 'approved' : ( $conversion['approved'] ? 'held' : 'review' ) ) ),
				),
				array( 'id' => $conversion['id'] )
			);
		}
	}
}
