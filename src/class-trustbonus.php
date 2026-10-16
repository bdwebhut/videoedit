<?php
namespace GhoroaHat\Rewards;

/** Selected-product introductory bonus with a permanent lifetime entitlement. */
final class TrustBonus {
	public static function desired( int $user, int $order, bool $earning, array $calc, array $policy ): int {
		if ( ! $user || ! Membership::get( $user ) ) {
			return 0; }
		global $wpdb;
		$entitlement = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_entitlements' ) . " WHERE user_id=%d AND benefit='trust' AND scope='lifetime'", $user ), ARRAY_A );
		if ( $earning && $calc['bonus'] && $policy['trust_enabled'] && ! $entitlement ) {
			DB::insert(
				'reward_entitlements',
				array(
					'user_id'    => $user,
					'benefit'    => 'trust',
					'scope'      => 'lifetime',
					'order_id'   => $order,
					'created_at' => DB::now(),
				)
			);
			$entitlement = array( 'order_id' => $order ); }
		return $earning && $policy['trust_enabled'] && $calc['bonus'] && $entitlement && ! Reconciliation::bonus_revoked( $order ) && (int) $entitlement['order_id'] === $order ? (int) $policy['bonus_amount'] : 0;
	}
}
