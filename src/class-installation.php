<?php
namespace GhoroaHat\Rewards;

final class Installation {
	public const CAPS = array( 'ghr_manage_settings', 'ghr_manage_members', 'ghr_verify_identity', 'ghr_adjust_wallets', 'ghr_approve_commissions', 'ghr_manage_competitions', 'ghr_manage_rewards', 'ghr_manage_notifications', 'ghr_view_reports', 'ghr_reconcile_identities' );
	public static function schema(): array {
		return array(
			'memberships'              => "id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, member_id varchar(40) DEFAULT NULL, referral_code varchar(32) NOT NULL, status varchar(16) NOT NULL DEFAULT 'pending', phone varchar(20) NOT NULL DEFAULT '', verified_phone varchar(20) DEFAULT NULL, email_verified_at datetime DEFAULT NULL, registered_at datetime NOT NULL, activated_at datetime DEFAULT NULL, public_consent tinyint NOT NULL DEFAULT 0, notes text, PRIMARY KEY  (id), UNIQUE KEY user_id (user_id), UNIQUE KEY member_id (member_id), UNIQUE KEY referral_code (referral_code), UNIQUE KEY verified_phone (verified_phone), KEY status (status), KEY registered_at (registered_at)",
			'identity_verifications'   => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, purpose varchar(16) NOT NULL, target varchar(191) NOT NULL, token_hash char(64) NOT NULL, attempts int NOT NULL DEFAULT 0, expires_at datetime NOT NULL, consumed_at datetime DEFAULT NULL, PRIMARY KEY  (id), UNIQUE KEY token_hash (token_hash), KEY owner_expiry (user_id,purpose,expires_at)',
			'membership_events'        => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, event varchar(40) NOT NULL, reference varchar(191) NOT NULL, order_id bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY reference (reference), KEY user_date (user_id,created_at)',
			'wallet_accounts'          => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, wallet varchar(16) NOT NULL, posted bigint NOT NULL DEFAULT 0, reserved bigint NOT NULL DEFAULT 0, held bigint NOT NULL DEFAULT 0, earned bigint NOT NULL DEFAULT 0, redeemed bigint NOT NULL DEFAULT 0, reversed bigint NOT NULL DEFAULT 0, updated_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY owner_wallet (user_id,wallet)',
			'wallet_transactions'      => "id bigint unsigned NOT NULL AUTO_INCREMENT, account_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, wallet varchar(16) NOT NULL, kind varchar(32) NOT NULL, amount bigint NOT NULL, status varchar(20) NOT NULL DEFAULT 'committed', source_type varchar(32) NOT NULL, source_ref varchar(191) NOT NULL, order_id bigint unsigned NOT NULL DEFAULT 0, order_item_id bigint unsigned NOT NULL DEFAULT 0, idempotency_key varchar(191) NOT NULL, actor bigint unsigned NOT NULL DEFAULT 0, reversal_of bigint unsigned DEFAULT NULL, reason text, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY idempotency_key (idempotency_key), KEY account_date (account_id,created_at), KEY owner_wallet (user_id,wallet,id), KEY owner_history (user_id,id), KEY order_id (order_id), KEY source_ref (source_type,source_ref), KEY reversal_of (reversal_of)",
			'credit_lots'              => 'id bigint unsigned NOT NULL AUTO_INCREMENT, transaction_id bigint unsigned NOT NULL, account_id bigint unsigned NOT NULL, original bigint NOT NULL, remaining bigint NOT NULL, expired bigint NOT NULL DEFAULT 0, debt_repaid bigint NOT NULL DEFAULT 0, expires_at datetime DEFAULT NULL, PRIMARY KEY  (id), UNIQUE KEY transaction_id (transaction_id), KEY account_expiry (account_id,expires_at)',
			'credit_allocations'       => 'id bigint unsigned NOT NULL AUTO_INCREMENT, lot_id bigint unsigned NOT NULL, reservation_id varchar(64) NOT NULL, units bigint NOT NULL, state varchar(16) NOT NULL, PRIMARY KEY  (id), UNIQUE KEY allocation (lot_id,reservation_id), KEY reservation_id (reservation_id)',
			'wallet_reservations'      => 'id varchar(64) NOT NULL, account_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, wallet varchar(16) NOT NULL, intent_hash char(64) NOT NULL, cart_hash char(64) NOT NULL, order_id bigint unsigned NOT NULL DEFAULT 0, units bigint NOT NULL, state varchar(20) NOT NULL, expires_at datetime NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY account_state (account_id,state), KEY expiry (state,expires_at), KEY order_id (order_id)',
			'reward_entitlements'      => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, benefit varchar(32) NOT NULL, scope varchar(32) NOT NULL, revoked tinyint NOT NULL DEFAULT 0, order_id bigint unsigned NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY entitlement (user_id,benefit,scope), KEY order_id (order_id)',
			'order_effects'            => 'id bigint unsigned NOT NULL AUTO_INCREMENT, order_id bigint unsigned NOT NULL, effect_key varchar(64) NOT NULL, user_id bigint unsigned NOT NULL, wallet varchar(16) NOT NULL, desired bigint NOT NULL DEFAULT 0, posted bigint NOT NULL DEFAULT 0, eligible bigint NOT NULL DEFAULT 0, revision int NOT NULL DEFAULT 0, policy longtext NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY order_effect (order_id,effect_key), KEY user_date (user_id,created_at), KEY period (effect_key,created_at,user_id)',
			'referral_visits'          => "id bigint unsigned NOT NULL AUTO_INCREMENT, referrer_id bigint unsigned NOT NULL, visitor_hash char(64) NOT NULL, flags varchar(100) NOT NULL DEFAULT '', created_at datetime NOT NULL, PRIMARY KEY  (id), KEY referrer_date (referrer_id,created_at), KEY visitor_date (visitor_hash,created_at)",
			'referral_attributions'    => 'id varchar(64) NOT NULL, referrer_id bigint unsigned NOT NULL, customer_id bigint unsigned NOT NULL DEFAULT 0, visit_id bigint unsigned NOT NULL, expires_at datetime NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY customer_expiry (customer_id,expires_at), KEY referrer_id (referrer_id)',
			'referral_conversions'     => "id bigint unsigned NOT NULL AUTO_INCREMENT, order_id bigint unsigned NOT NULL, buyer_id bigint unsigned NOT NULL DEFAULT 0, referrer_id bigint unsigned NOT NULL, eligible bigint NOT NULL DEFAULT 0, commission bigint NOT NULL DEFAULT 0, status varchar(24) NOT NULL, approved tinyint NOT NULL DEFAULT 0, release_at datetime NOT NULL, qualified_at datetime DEFAULT NULL, flags varchar(191) NOT NULL DEFAULT '', created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY order_id (order_id), KEY referrer_state (referrer_id,status,created_at), KEY release_at (release_at), KEY period (status,created_at,referrer_id)",
			'monthly_scores'           => 'id bigint unsigned NOT NULL AUTO_INCREMENT, month char(7) NOT NULL, user_id bigint unsigned NOT NULL, rules_version char(64) NOT NULL, personal bigint NOT NULL DEFAULT 0, orders_count int NOT NULL DEFAULT 0, conversions int NOT NULL DEFAULT 0, referral_value bigint NOT NULL DEFAULT 0, seeds bigint NOT NULL DEFAULT 0, first_at datetime DEFAULT NULL, first_seeds_at datetime DEFAULT NULL, first_referral_at datetime DEFAULT NULL, updated_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY month_user (month,user_id,rules_version), KEY month_personal (month,personal)',
			'monthly_results'          => 'id bigint unsigned NOT NULL AUTO_INCREMENT, month char(7) NOT NULL, version int NOT NULL, rules longtext NOT NULL, entries longtext NOT NULL, digest char(64) NOT NULL, supersedes_id bigint unsigned DEFAULT NULL, actor bigint unsigned NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY month_version (month,version)',
			'result_publications'      => 'id bigint unsigned NOT NULL AUTO_INCREMENT, snapshot_id bigint unsigned NOT NULL, action varchar(24) NOT NULL, reason text, actor bigint unsigned NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY snapshot_action (snapshot_id,action)',
			'reward_definitions'       => 'id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(191) NOT NULL, type varchar(32) NOT NULL, config longtext NOT NULL, stock int NOT NULL DEFAULT 0, active tinyint NOT NULL DEFAULT 1, PRIMARY KEY  (id), KEY active (active)',
			'reward_campaigns'         => 'id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(191) NOT NULL, month char(7) NOT NULL, category varchar(32) NOT NULL, definition_id bigint unsigned NOT NULL, capacity int NOT NULL, assigned int NOT NULL DEFAULT 0, expires_at datetime DEFAULT NULL, PRIMARY KEY  (id), KEY month_category (month,category)',
			'reward_assignments'       => "id bigint unsigned NOT NULL AUTO_INCREMENT, box_id varchar(64) NOT NULL, user_id bigint unsigned NOT NULL, campaign_id bigint unsigned NOT NULL, slot varchar(191) NOT NULL, definition_id bigint unsigned NOT NULL, reward_snapshot longtext NOT NULL, state varchar(20) NOT NULL DEFAULT 'unopened', assigned_at datetime NOT NULL, opened_at datetime DEFAULT NULL, claimed_at datetime DEFAULT NULL, fulfilled_at datetime DEFAULT NULL, expires_at datetime DEFAULT NULL, delivery_status varchar(40) NOT NULL DEFAULT 'pending', coupon_id bigint unsigned NOT NULL DEFAULT 0, explanation text, PRIMARY KEY  (id), UNIQUE KEY box_id (box_id), UNIQUE KEY slot (slot), KEY owner_state (user_id,state), KEY expires_at (expires_at)",
			'notifications'            => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, event_key varchar(191) NOT NULL, category varchar(64) NOT NULL, title varchar(191) NOT NULL, message text NOT NULL, link text, read_at datetime DEFAULT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY owner_event (user_id,event_key), KEY owner_read (user_id,read_at,created_at)',
			'notification_deliveries'  => "id bigint unsigned NOT NULL AUTO_INCREMENT, notification_id bigint unsigned NOT NULL, channel varchar(16) NOT NULL, state varchar(20) NOT NULL DEFAULT 'pending', attempts int NOT NULL DEFAULT 0, next_attempt datetime NOT NULL, last_error varchar(191) NOT NULL DEFAULT '', PRIMARY KEY  (id), UNIQUE KEY notification_channel (notification_id,channel), KEY due (state,next_attempt)",
			'notification_preferences' => 'id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, preferences longtext NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY user_id (user_id)',
			'audit_logs'               => 'id bigint unsigned NOT NULL AUTO_INCREMENT, actor bigint unsigned NOT NULL DEFAULT 0, operation varchar(64) NOT NULL, subject varchar(191) NOT NULL, details longtext NOT NULL, reason text, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY subject_date (subject,created_at), KEY actor_date (actor,created_at)',
			'reconciliation_cases'     => 'id bigint unsigned NOT NULL AUTO_INCREMENT, source_id bigint unsigned NOT NULL, target_id bigint unsigned NOT NULL, evidence text NOT NULL, plan longtext NOT NULL, reviewer bigint unsigned NOT NULL, state varchar(20) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY state_date (state,created_at)',
		);
	}
	public static function migrate(): void {
		global $wpdb;
		$lock = 'ghr_migration_lock';
		if ( ! add_option( $lock, time(), '', false ) ) {
			throw new \RuntimeException( 'A migration is running; inspect the migration lock before retrying.' ); }
		try {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( self::schema() as $name => $columns ) {
				$lines = str_replace( ', ', ",\n", $columns );
				// Only split column/constraint boundaries, not composite indexes.
				$lines = preg_replace( '/, (?=[a-z_]+ (?:bigint|varchar|char|datetime|int|tinyint|text|longtext)|PRIMARY|UNIQUE|KEY)/', ",\n", $columns );
				dbDelta( 'CREATE TABLE ' . DB::table( $name ) . " (\n" . $lines . "\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
				$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', DB::table( $name ) ) );
				if ( 'InnoDB' !== $engine ) {
					throw new \RuntimeException( 'All rewards tables require InnoDB.' ); }
			}
			$accounts     = ( $wpdb->prefix . 'ghr_wallet_accounts' );
			$transactions = ( $wpdb->prefix . 'ghr_wallet_transactions' );
			DB::query( "UPDATE $accounts a SET earned=(SELECT COALESCE(SUM(CASE WHEN amount>0 AND kind NOT IN ('restore','expiry_recovery','reconciliation_credit') THEN amount ELSE 0 END),0) FROM $transactions t WHERE t.account_id=a.id),redeemed=(SELECT COALESCE(SUM(CASE WHEN kind IN ('redeem','debt_redemption') THEN -amount ELSE 0 END),0) FROM $transactions t WHERE t.account_id=a.id),reversed=(SELECT COALESCE(SUM(CASE WHEN kind IN ('reversal','expiry','expiry_recovery') THEN -amount ELSE 0 END),0) FROM $transactions t WHERE t.account_id=a.id)" );
			$sequence = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(CAST(SUBSTRING(member_id,4) AS UNSIGNED)),0) FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) );
			add_option( 'ghr_member_sequence', $sequence, '', false );
			DB::query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=GREATEST(CAST(option_value AS UNSIGNED),%d) WHERE option_name='ghr_member_sequence'", $sequence ) );
			add_option( 'ghr_ledger_epoch', wp_generate_uuid4(), '', false );
			update_option( 'ghr_db_version', 1, false );
		} finally {
			delete_option( $lock ); }
	}
	public static function activate( bool $network_wide = false ): void {
		if ( PHP_INT_SIZE < 8 ) {
			wp_die( esc_html__( 'Ghoroa Hat Rewards requires 64-bit PHP for safe financial arithmetic.', 'ghoroa-hat-rewards' ) ); }
		if ( $network_wide ) {
			wp_die( esc_html__( 'Activate Ghoroa Hat Rewards separately on each site; network activation is not supported.', 'ghoroa-hat-rewards' ) ); }
		if ( version_compare( PHP_VERSION, '8.2', '<' ) || version_compare( get_bloginfo( 'version' ), '6.7', '<' ) || ! class_exists( 'WooCommerce' ) || version_compare( WC_VERSION, '9.6', '<' ) ) {
			wp_die( esc_html__( 'Requires PHP 8.2, WordPress 6.7 and WooCommerce 9.6.', 'ghoroa-hat-rewards' ) ); }
		if ( ! extension_loaded( 'openssl' ) ) {
			wp_die( esc_html__( 'Ghoroa Hat Rewards requires the PHP OpenSSL extension for secure verification and SMS credentials.', 'ghoroa-hat-rewards' ) ); }
		self::migrate();
		add_option( 'ghr_settings', Settings::defaults(), '', false );
		$role = get_role( 'administrator' );
		foreach ( self::CAPS as $cap ) {
			$role->add_cap( $cap ); }
		self::pages();
		Frontend::routes();
		flush_rewrite_rules();
		update_option( 'ghr_setup_required', 1, false );
		update_option( 'ghr_recovery_needed', 1, false );
	}
	public static function pages(): void {
		$map = (array) get_option( 'ghr_pages', array() );
		foreach ( array(
			'membership'  => 'ঘরোয়া হাট সদস্যপদ',
			'refer'       => 'ঘরোয়া হাট রেফারেল',
			'leaderboard' => 'মাসিক লিডারবোর্ড',
			'winners'     => 'বিগত বিজয়ী',
		) as $key => $title ) {
			if ( ! empty( $map[ $key ] ) && get_post_status( $map[ $key ] ) ) {
				continue; }
			$path     = 'winners' === $key ? 'leaderboard/winners' : $key;
			$existing = get_page_by_path( $path );
			if ( $existing ) {
				if ( get_post_meta( $existing->ID, '_ghr_page', true ) === $key ) {
					$map[ $key ] = $existing->ID;
				} continue; }
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $key,
					'post_parent'  => 'winners' === $key ? ( $map['leaderboard'] ?? 0 ) : 0,
					'post_content' => '[ghr_' . array(
						'membership'  => 'membership_information',
						'refer'       => 'referral_landing',
						'leaderboard' => 'leaderboard',
						'winners'     => 'monthly_winners',
					)[ $key ] . ']',
				),
				true
			);
			if ( ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_ghr_page', $key );
				$map[ $key ] = $id; }
		}
		update_option( 'ghr_pages', $map, false );
	}
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( Jobs::HOOKS as $hook ) {
				as_unschedule_all_actions( $hook, null, 'ghr' ); }
		}
		global $wp_rewrite;
		foreach ( (array) $wp_rewrite->endpoints as $i => $endpoint ) {
			if ( in_array( $endpoint[1], Frontend::endpoints(), true ) ) {
				unset( $wp_rewrite->endpoints[ $i ] ); }
		}
		flush_rewrite_rules();
	}
}
