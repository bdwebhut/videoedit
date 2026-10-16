<?php
namespace GhoroaHat\Rewards;

/** WordPress core privacy-tool adapters; statutory financial history remains intact. */
final class Privacy {
	public static function hooks(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( $exporters ) {
				$exporters['ghr'] = array(
					'exporter_friendly_name' => 'Ghoroa Hat Rewards',
					'callback'               => array( self::class, 'export' ),
				);
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( $erasers ) {
				$erasers['ghr'] = array(
					'eraser_friendly_name' => 'Ghoroa Hat Rewards',
					'callback'             => array( self::class, 'erase' ),
				);
				return $erasers;
			}
		);
		add_action(
			'admin_init',
			static function () {
				if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
					wp_add_privacy_policy_content( 'Ghoroa Hat Rewards', '<p>Membership rewards use your WordPress account ID, verified contact details, order-backed rewards and an immutable financial ledger. Optional referral tracking uses first-party attribution and pseudonymous visitor cookies after configured consent. Public winner announcements require consent. Financial and identity-reconciliation records are retained for accounting; contact the store about access, correction and retention. Optional SMS uses the store-configured gateway.</p>' );
				} }
		);
	}
	private static function entry( string $group, string $id, array $record ): array {
		$data = array();
		foreach ( $record as $key => $value ) {
			$data[] = array(
				'name'  => $key,
				'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			); }
		return array(
			'group_id'    => 'ghr-' . $group,
			'group_label' => 'Ghoroa Hat ' . $group,
			'item_id'     => 'ghr-' . $id,
			'data'        => $data,
		);
	}
	public static function export( string $email, int $page ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			); }
		$data = array();
		if ( 1 === $page ) {
			foreach ( Membership::sources( $user->ID ) as $alias ) {
				$member = Membership::get( $alias );
				if ( $member ) {
					$data[] = self::entry( 'Membership', 'user-' . $alias, array_intersect_key( $member, array_flip( array( 'member_id', 'status', 'phone', 'verified_phone', 'registered_at', 'activated_at' ) ) ) ); }
			}
			$data[] = self::entry( 'Preferences', 'preferences-' . $user->ID, Notifications::preferences( $user->ID ) );
		}
		$transactions = Wallet::history( $user->ID, '', $page, 100 );
		foreach ( $transactions as $row ) {
			$data[] = self::entry( 'Ledger', 'transaction-' . $row['id'], $row ); }
		$rewards = Rewards::list( $user->ID, $page );
		foreach ( $rewards as $row ) {
			$data[] = self::entry( 'Rewards', 'reward-' . $row['id'], $row ); }
		$notifications = Notifications::list( $user->ID, $page );
		foreach ( $notifications as $row ) {
			$data[] = self::entry( 'Notifications', 'notification-' . $row['id'], $row ); }
		$referrals = Referral::stats( $user->ID, $page );
		foreach ( $referrals['activity'] as $row ) {
			$data[] = self::entry( 'Referrals', 'conversion-' . $row['id'], $row ); }
		return array(
			'data' => $data,
			'done' => count( $transactions ) < 100 && count( $rewards ) < 20 && count( $notifications ) < 20 && count( $referrals['activity'] ) < 20,
		);
	}
	public static function erase( string $email, int $page ): array {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			); }
		foreach ( Membership::sources( $user->ID ) as $alias ) {
			DB::update(
				( $wpdb->prefix . 'ghr_memberships' ),
				array(
					'phone'          => '',
					'verified_phone' => null,
					'public_consent' => 0,
					'notes'          => '',
				),
				array( 'user_id' => $alias )
			);
			Notifications::save_preferences(
				$alias,
				array(
					'email'            => false,
					'sms'              => false,
					'public_consent'   => false,
					'tracking_consent' => false,
				)
			);
			$wpdb->delete( ( $wpdb->prefix . 'ghr_identity_verifications' ), array( 'user_id' => $alias ) );
			$wpdb->delete( ( $wpdb->prefix . 'ghr_notifications' ), array( 'user_id' => $alias ) );
		}
		DB::audit( 'privacy.anonymized', 'user:' . $user->ID );
		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'Membership, ledger and identity-reconciliation records retained for accounting obligations.', 'ghoroa-hat-rewards' ) ),
			'done'           => true,
		);
	}
}
