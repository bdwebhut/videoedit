<?php
namespace GhoroaHat\Rewards;

final class Admin {
	public const SCREENS = array(
		'dashboard'       => 'Dashboard',
		'members'         => 'Members',
		'approvals'       => 'Membership Approvals',
		'trust'           => 'Trust Wallet',
		'referrals'       => 'Referral Management',
		'referral-wallet' => 'Referral Wallet',
		'seeds'           => 'Trust Seeds',
		'leaderboard'     => 'Monthly Leaderboard',
		'rewards'         => 'Winners & Magic Boxes',
		'notifications'   => 'Notifications',
		'reports'         => 'Reports',
		'settings'        => 'Settings',
		'audit'           => 'Activity & Audit Logs',
		'health'          => 'System Health',
	);
	public const REPORTS = array(
		'members'        => 'memberships',
		'registrations'  => 'memberships',
		'orders'         => 'order_effects',
		'referral-sales' => 'referral_conversions',
		'commissions'    => 'referral_conversions',
		'transactions'   => 'wallet_transactions',
		'redemptions'    => 'wallet_transactions',
		'trust-seeds'    => 'wallet_transactions',
		'leaderboard'    => 'monthly_results',
		'fulfillment'    => 'reward_assignments',
		'deliveries'     => 'notification_deliveries',
		'liabilities'    => 'wallet_accounts',
	);
	public static function cap( string $screen ): string {
		return match ( $screen ) {
			'settings', 'health' => 'ghr_manage_settings', 'members', 'approvals' => 'ghr_manage_members', 'trust', 'referral-wallet', 'seeds' => 'ghr_adjust_wallets', 'referrals' => 'ghr_approve_commissions', 'leaderboard' => 'ghr_manage_competitions', 'rewards' => 'ghr_manage_rewards', 'notifications' => 'ghr_manage_notifications', default => 'ghr_view_reports' }; }
	public static function hooks(): void {
		add_action(
			'admin_menu',
			static function () {
				add_menu_page( 'Ghoroa Hat Rewards', 'Ghoroa Hat Rewards', 'ghr_view_reports', 'ghr', array( self::class, 'screen' ), 'dashicons-awards', 56 );
				foreach ( self::SCREENS as $key => $title ) {
					add_submenu_page( 'ghr', $title, $title, self::cap( $key ), 'dashboard' === $key ? 'ghr' : 'ghr-' . $key, array( self::class, 'screen' ) );
				} }
		);
		add_action(
			'admin_enqueue_scripts',
			static function ( $hook ) {
				if ( str_contains( $hook, 'ghr' ) ) {
					Frontend::assets();
					wp_enqueue_style( 'ghr-admin', plugins_url( 'assets/admin.css', GHR_FILE ), array(), GHR_VERSION );
				} }
		);
		add_action( 'admin_post_ghr_export', array( self::class, 'export' ) );
		add_action( 'admin_post_ghr_settings', array( self::class, 'save_settings' ) );
		add_action(
			'add_meta_boxes',
			static function () {
				foreach ( array( 'shop_order', function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order' ) as $screen ) {
					add_meta_box( 'ghr-member-order', 'Ghoroa Hat Rewards', array( self::class, 'order_box' ), $screen, 'side' );
				} }
		);
		add_action( 'woocommerce_process_shop_order_meta', array( self::class, 'save_order' ), 30 );
		add_action(
			'admin_notices',
			static function () {
				if ( current_user_can( 'ghr_manage_settings' ) && get_option( 'ghr_setup_required' ) ) {
					echo '<div class="notice notice-info"><p>Ghoroa Hat Rewards: <a href="' . esc_url( admin_url( 'admin.php?page=ghr-settings' ) ) . '">Complete the setup checklist</a> before enabling rewards for customers.</p></div>';
				} }
		);
	}
	public static function health(): array {
		global $wpdb;
		$inconsistent = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . ' a WHERE posted<>(SELECT COALESCE(SUM(amount),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' t WHERE t.account_id=a.id)' );
		return array(
			'plugin'                       => GHR_VERSION,
			'php'                          => PHP_VERSION,
			'wordpress'                    => get_bloginfo( 'version' ),
			'woocommerce'                  => WC_VERSION,
			'schema'                       => get_option( 'ghr_db_version' ),
			'currency'                     => get_woocommerce_currency(),
			'timezone'                     => wp_timezone_string(),
			'ledger_projection_mismatches' => $inconsistent,
			'last_maintenance'             => get_option( 'ghr_last_maintenance', 'Not run' ),
			'monthly_review_due'           => get_option( 'ghr_month_review_due', '' ),
			'failed_deliveries'            => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " WHERE state='failed'" ),
			'review_orders'                => count(
				wc_get_orders(
					array(
						'limit'    => 100,
						'meta_key' => '_ghr_review_required',
						'return'   => 'ids',
					)
				)
			),
			'checkout_tax_adapter'         => 'Native WooCommerce product coupons; statutory tax recalculation',
			'stacking'                     => 'One wallet only',
			'sms_adapter'                  => is_callable( apply_filters( 'ghr_sms_adapter', null ) ) ? 'Registered' : 'Disabled/unconfigured',
		);
	}
	public static function screen(): void {
		$page = sanitize_key( $_GET['page'] ?? 'ghr' );
		$key  = 'ghr' === $page ? 'dashboard' : substr( $page, 4 );
		if ( ! isset( self::SCREENS[ $key ] ) || ! current_user_can( self::cap( $key ) ) ) {
			wp_die( esc_html__( 'Access denied.', 'ghoroa-hat-rewards' ) ); }
		global $wpdb;
		echo '<div class="wrap ghr-admin"><h1>Ghoroa Hat · ' . esc_html( self::SCREENS[ $key ] ) . '</h1><section class="ghr">';
		if ( 'settings' === $key ) {
			self::settings(); } elseif ( 'health' === $key ) {
			echo Frontend::table( array( self::health() ) );
			self::form( 'maintenance', 'Run safe maintenance', array() );
			self::form( 'removal-preview', 'Preview separately confirmed data removal', array() );
			self::form( 'removal-confirm', 'Arm record deletion on next WordPress uninstall', array( 'ticket', 'confirmation', 'backup_confirmation' ) );
			self::form( 'reconcile-preview', 'Preview duplicate identity reconciliation', array( 'source_id', 'target_id', 'reason' ) );
			self::form( 'reconcile-execute', 'Confirm reviewed identity reconciliation', array( 'case_id', 'confirmation' ) ); } elseif ( 'dashboard' === $key ) {
						$kpis                                        = array(
							'Registered members'     => 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_memberships' ),
							'Pending members'        => 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . " WHERE status='pending'",
							'Active members'         => 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . " WHERE status='active'",
							'Successful referrals'   => 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE status='approved'",
							'Referral sales (paisa)' => 'SELECT COALESCE(SUM(eligible),0) FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE status='approved'",
							'Pending rewards'        => 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . " WHERE state IN ('unopened','opened','claimed')",
							'Outstanding cash credit (paisa)' => 'SELECT COALESCE(SUM(GREATEST(0,posted)),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . " WHERE wallet<>'seeds'",
							'Outstanding Seeds'      => 'SELECT COALESCE(SUM(GREATEST(0,posted)),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . " WHERE wallet='seeds'",
						);
						[ $month_from, $month_to ]                   = Leaderboard::bounds( wp_date( 'Y-m' ) );
						$kpis['New members this month']              = $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' WHERE registered_at>=%s AND registered_at<%s', $month_from, $month_to );
						$kpis['Trust bonuses issued']                = 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " WHERE wallet='trust' AND kind='earn'" . self::report_filters( 'wallet_transactions' );
						$kpis['Referral commissions earned (paisa)'] = 'SELECT COALESCE(SUM(earned),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . " WHERE wallet='referral'";
						$kpis['Trust Seeds issued']                  = 'SELECT COALESCE(SUM(earned),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' ) . " WHERE wallet='seeds'";
						$kpis['Credits redeemed (paisa including Seeds)'] = 'SELECT COALESCE(SUM(CASE WHEN wallet=\'seeds\' THEN -amount*100 ELSE -amount END),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . " WHERE kind IN ('redeem','debt_redemption')" . self::report_filters( 'wallet_transactions' );
						$kpis['Reward wallet liability (paisa)']          = 'SELECT COALESCE(SUM(CASE WHEN wallet=\'seeds\' THEN GREATEST(posted,0)*100 ELSE GREATEST(posted,0) END),0) FROM ' . ( $wpdb->prefix . 'ghr_wallet_accounts' );
						$kpis['Notification delivery failures']           = 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_notification_deliveries' ) . " WHERE state='failed'";
						echo '<p>Active monthly competition: ' . esc_html( wp_date( 'F Y' ) ) . '</p><form method="get"><input type="hidden" name="page" value="ghr"><label>Activity from<input type="date" name="from"></label><label>Through<input type="date" name="to"></label><button class="button">Filter financial activity</button></form>';
						echo '<div class="ghr-grid">';
						foreach ( $kpis as $label => $sql ) {
							echo '<article class="ghr-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $wpdb->get_var( $sql ) ) . '</strong></article>'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Queries originate only from the local fixed KPI map and prepared filters.
						} echo '</div><p>Financial report amounts are integer paisa; Seeds are integer units.</p>';
			} elseif ( 'reports' === $key ) {
				foreach ( self::REPORTS as $report => $table ) {
						echo '<form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ghr_export"><input type="hidden" name="report" value="' . esc_attr( $report ) . '">';
					wp_nonce_field( 'ghr_export' );
					echo '<h3>' . esc_html( $report ) . '</h3><label>From<input type="date" name="from"></label><label>Through<input type="date" name="to"></label><label>Member User ID (optional)<input name="user_id" type="number" min="1"></label><button class="button">Export CSV</button></form>'; }
			} else {
						self::filters( $page );
				if ( in_array( $key, array( 'members', 'approvals' ), true ) && ! empty( $_GET['user_id'] ) ) {
					self::member_profile( absint( $_GET['user_id'] ) ); }
				$table = match ( $key ) {
					'members', 'approvals' => 'memberships', 'trust', 'referral-wallet', 'seeds' => 'wallet_transactions', 'referrals' => 'referral_conversions', 'leaderboard' => 'monthly_results', 'rewards' => 'reward_assignments', 'notifications' => 'notification_deliveries', default => 'audit_logs' };
				$where  = '1=1';
				$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
				if ( in_array( $key, array( 'members', 'approvals' ), true ) && $search ) {
					$user   = get_user_by( 'email', $search );
					$where .= $wpdb->prepare( ' AND (member_id LIKE %s OR phone LIKE %s OR user_id=%d)', '%' . $wpdb->esc_like( $search ) . '%', '%' . $wpdb->esc_like( $search ) . '%', $user ? $user->ID : absint( $search ) ); }
				if ( 'approvals' === $key ) {
					$where .= " AND status='pending'"; }
				if ( in_array( $key, array( 'trust', 'referral-wallet', 'seeds' ), true ) ) {
					$where .= $wpdb->prepare( ' AND wallet=%s', 'referral-wallet' === $key ? 'referral' : $key ); }
				$pg   = max( 1, absint( $_GET['ghr_page'] ?? 1 ) );
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . DB::table( $table ) . " WHERE $where ORDER BY id DESC LIMIT 20 OFFSET %d", ( $pg - 1 ) * 20 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table comes from fixed screen map; WHERE values and offset are prepared.
				foreach ( $rows as &$r ) {
					unset( $r['reward_snapshot'] );
				} unset( $r );
				echo Frontend::table( $rows );
				echo '<p><a href="' . esc_url( add_query_arg( 'ghr_page', max( 1, $pg - 1 ) ) ) . '">Previous</a> · <a href="' . esc_url( add_query_arg( 'ghr_page', $pg + 1 ) ) . '">Next</a></p>';
				if ( in_array( $key, array( 'members', 'approvals' ), true ) ) {
					self::form( 'member-status', 'Change membership state', array( 'user_id', 'status', 'reason' ) );
					self::form( 'member-note', 'Add internal note', array( 'user_id', 'reason' ) );
					self::form( 'verify-phone', 'Verify phone against recorded evidence', array( 'user_id', 'phone', 'reason' ) );
					self::form( 'enroll-existing', 'Enroll existing WooCommerce customers', array() ); }
				if ( in_array( $key, array( 'trust', 'referral-wallet', 'seeds' ), true ) ) {
					self::form( 'adjust', 'Post immutable wallet adjustment', array( 'user_id', 'wallet', 'amount', 'reference', 'reason' ) );
					self::form( 'reverse', 'Reverse transaction once', array( 'transaction_id', 'reference', 'reason' ) );
					self::form( 'hold', 'Hold/release units (negative releases)', array( 'user_id', 'wallet', 'amount', 'reason' ) );
					self::form( 'late-settlement', 'Reconcile a paid order after released credit', array( 'order_id', 'reason', 'debt_approval' ) ); }
				if ( 'referrals' === $key ) {
					echo '<p>Invalid referral visits: ' . esc_html( get_option( 'ghr_counter_invalid_referrals', 0 ) ) . '</p>';
					echo Frontend::table( $wpdb->get_results( 'SELECT referrer_id,COUNT(*) clicks,COUNT(DISTINCT visitor_hash) unique_visitors,SUM(flags=\'repeat_clicks\') suspicious_repeats FROM ' . ( $wpdb->prefix . 'ghr_referral_visits' ) . ' GROUP BY referrer_id ORDER BY clicks DESC LIMIT 20', ARRAY_A ) );
					self::form( 'approve', 'Approve referral commission', array( 'conversion_id', 'reason' ) );
					self::form( 'reconcile-order', 'Reconcile order', array( 'order_id' ) );
					self::form( 'refund-review', 'Review amount-only refund allocation', array( 'order_id', 'refund_id', 'allocations', 'reason' ) ); }
				if ( 'leaderboard' === $key ) {
					$month = isset( $_GET['month'] ) ? Leaderboard::month( sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) : wp_date( 'Y-m' );
					foreach ( array( 'regular', 'referrals', 'seeds' ) as $category ) {
						echo '<h3>' . esc_html( $category . ' · ' . $month ) . '</h3>' . Frontend::table( Leaderboard::live( $month, $category ) ); }
					self::form( 'month-rules', 'Set versioned monthly scoring rules', array( 'month', 'monthly_min', 'purchase_weight', 'regular_order_weight', 'referral_weight', 'referral_value_weight', 'seeds_weight', 'seeds_purchase_weight', 'tie_breaker', 'reason' ) );
					self::form( 'prepare', 'Refresh monthly rankings', array( 'month' ) );
					self::form( 'finalize', 'Finalize reviewed month / correction version', array( 'month', 'reason' ) );
					self::form( 'publish', 'Publish reviewed snapshot and assign configured awards', array( 'snapshot_id', 'reason' ) ); }
				if ( 'rewards' === $key ) {
					echo '<h3>Prize definitions</h3>' . Frontend::table( $wpdb->get_results( 'SELECT id,name,type,stock,active FROM ' . ( $wpdb->prefix . 'ghr_reward_definitions' ) . ' ORDER BY id DESC LIMIT 50', ARRAY_A ) );
					echo '<h3>Campaigns</h3>' . Frontend::table( $wpdb->get_results( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' ORDER BY id DESC LIMIT 50', ARRAY_A ) );
					self::form( 'definition', 'Create prize definition', array( 'name', 'type', 'stock', 'product_id', 'value', 'expiry_days' ) );
					echo '<p>Types: free_product, product_credit, percent, coupon, gift, manual. Monetary values are paisa; percent is 1–100.</p>';
					self::form( 'campaign', 'Create campaign', array( 'name', 'month', 'category', 'definition_id', 'capacity', 'expires_at' ) );
					self::form( 'assign', 'Assign predefined Magic Box', array( 'user_id', 'campaign_id', 'reference' ) );
					self::form( 'fulfill', 'Fulfill claimed reward', array( 'user_id', 'reward_id', 'reason' ) );
					self::form( 'revoke', 'Revoke reward', array( 'user_id', 'reward_id', 'reason' ) ); }
				if ( 'notifications' === $key ) {
					self::form( 'announcement', 'Send membership announcement', array( 'user_id', 'message', 'reference' ) );
					self::form( 'retry-delivery', 'Retry failed delivery', array( 'delivery_id', 'reason' ) ); }
			}
			echo '<p class="ghr-status" role="status" aria-live="polite"></p></section></div>';
	}

	private static function member_profile( int $user ): void {
		$member  = Membership::get( $user );
		$wp_user = get_userdata( $user );
		if ( ! $member || ! $wp_user ) {
			echo '<p>Member not found.</p>';
			return; }
		echo '<h2>' . esc_html( $member['member_id'] . ' · ' . $wp_user->display_name ) . '</h2><p>' . esc_html( $wp_user->user_email ) . '</p>';
		echo Frontend::table( array( $member ) );
		$balances = array();
		foreach ( Wallet::summary( $user ) as $wallet => $a ) {
			$balances[] = array(
				'wallet'    => $wallet,
				'available' => Frontend::amount( $a['available'], $wallet ),
				'pending'   => Frontend::amount( $a['pending'], $wallet ),
				'held'      => Frontend::amount( $a['held'], $wallet ),
				'earned'    => Frontend::amount( $a['earned'], $wallet ),
			); }
		echo Frontend::table( $balances );
		echo '<h3>Linked WooCommerce orders</h3><ul>';
		foreach ( wc_get_orders(
			array(
				'customer' => Membership::sources( $user ),
				'limit'    => 20,
			)
		) as $order ) {
			echo '<li><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( '#' . $order->get_order_number() . ' · ' . $order->get_status() ) . '</a></li>';
		} echo '</ul><h3>Referral activity</h3>';
		echo Frontend::table( Referral::stats( $user )['activity'] );
		echo '<h3>Rewards</h3>' . Frontend::table( Rewards::list( $user ) );
		global $wpdb;
		echo '<h3>Internal notes</h3>' . Frontend::table( $wpdb->get_results( $wpdb->prepare( 'SELECT actor,reason,created_at FROM ' . ( $wpdb->prefix . 'ghr_audit_logs' ) . " WHERE subject=%s AND operation='member.note' ORDER BY id DESC LIMIT 20", 'user:' . $user ), ARRAY_A ) );
	}
	private static function filters( string $page ): void {
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( $page ) . '"><label>Search Member ID, email, phone, or user ID <input name="s" value="' . esc_attr( sanitize_text_field( $_GET['s'] ?? '' ) ) . '"></label><label>Open profile by User ID<input type="number" min="1" name="user_id"></label><button class="button">Search / Open profile</button></form>'; }
	private static function form( string $command, string $title, array $fields ): void {
		if ( ! current_user_can( Rest::command_cap( $command ) ) ) {
			return; }
		echo '<details class="ghr-admin-form"><summary>' . esc_html( $title ) . '</summary><form data-ghr-form="admin/commands/' . esc_attr( $command ) . '">';
		foreach ( $fields as $field ) {
			echo '<label>' . esc_html( ucwords( str_replace( '_', ' ', $field ) ) ) . '<input name="' . esc_attr( $field ) . '"' . ( in_array( $field, array( 'expires_at', 'reason', 'debt_approval' ), true ) ? '' : ' required' ) . '></label>';
		} echo '<button class="button button-primary">' . esc_html( $title ) . '</button></form></details>'; }
	private static function settings(): void {
		echo '<h2>First-run setup checklist</h2><ol><li>Verify BDT currency, site timezone and Action Scheduler health.</li><li>Select the inaugural package Product IDs; confirm exclusions and reward rates.</li><li>Verify the mapped pages and account endpoints.</li><li>Configure prize definitions and campaigns for each category/award.</li><li>Test checkout, refunds and outgoing email on staging. SMS requires an adapter.</li><li>Optionally enroll existing customers; each verifies their email.</li></ol><p>Safe default: one wallet per order; tax, shipping, cash withdrawal and member transfers are not supported. Earlier qualifying statuses carry reversal risk.</p>';
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="ghr_settings">';
		wp_nonce_field( 'ghr_settings' );
		$s       = Settings::all();
		$last    = '';
		$ordered = array();
		$groups  = array_fill_keys( array( 'General', 'Trust Wallet', 'Referral System', 'Trust Seeds', 'Leaderboard', 'Notifications', 'Checkout' ), array() );
		foreach ( Settings::defaults() as $setting_key => $default_value ) {
			$group = match ( true ) {
				in_array( $setting_key, array( 'monthly_min', 'purchase_weight', 'referral_weight', 'seeds_weight', 'regular_order_weight', 'referral_value_weight', 'seeds_purchase_weight', 'tie_breaker', 'leaderboard_enabled' ), true ) => 'Leaderboard',
				in_array( $setting_key, array( 'checkout_wallets', 'redemption_order', 'stacking_policy', 'minimum_payable' ), true ) => 'Checkout',
				str_starts_with( $setting_key, 'trust_' ), str_starts_with( $setting_key, 'bonus_' ) => 'Trust Wallet',
				str_starts_with( $setting_key, 'referral_' ), in_array( $setting_key, array( 'guest_referrals', 'repeat_referrals', 'allow_self_referrals', 'allow_referral_reassignment' ), true ) => 'Referral System',
				str_starts_with( $setting_key, 'seeds_' ) => 'Trust Seeds',
				str_starts_with( $setting_key, 'sms_' ), in_array( $setting_key, array( 'email_enabled', 'retry_limit' ), true ) => 'Notifications',
				default => 'General',
			};
			$groups[ $group ][ $setting_key ] = $default_value;
		}
		foreach ( $groups as $group => $values ) {
			foreach ( $values as $setting_key => $default_value ) {
				$ordered[ $setting_key ] = array(
					'group'   => $group,
					'default' => $default_value,
				); }
		}
		foreach ( $ordered as $key => $field ) {
			$default = $field['default'];
			$section = $field['group'];
			if ( $section !== $last ) {
				echo '<h3>' . esc_html( $section ) . '</h3>';
				$last = $section;
			} echo '<label class="ghr-setting">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) );
			if ( is_bool( $default ) ) {
				echo '<input type="hidden" name="settings[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="settings[' . esc_attr( $key ) . ']" value="1"' . checked( $s[ $key ], true, false ) . '>'; } else {
				echo '<input name="settings[' . esc_attr( $key ) . ']" value="' . esc_attr( is_array( $s[ $key ] ) ? implode( ',', $s[ $key ] ) : $s[ $key ] ) . '" type="' . ( is_int( $default ) ? 'number" min="0' : 'text' ) . '">'; }
				echo '<small>' . esc_html( self::description( $key ) ) . '</small></label>';
		}
		echo '<h3>SMS credential</h3><label>Gateway bearer token (write only; leave blank to keep)<input type="password" name="sms_api_key" autocomplete="new-password" value=""></label><p>Optional HTTPS JSON gateway. Credentials are encrypted using WordPress salts and never returned by APIs.</p>';
		echo '<h3>Page mapping</h3>';
		foreach ( array( 'membership', 'refer', 'leaderboard', 'winners' ) as $key ) {
			echo '<label>' . esc_html( $key );
			wp_dropdown_pages(
				array(
					'name'              => 'pages[' . $key . ']',
					'selected'          => get_option( 'ghr_pages', array() )[ $key ] ?? 0,
					'show_option_none'  => 'Create/map page',
					'option_none_value' => 0,
				)
			);
			echo '</label>'; }
		echo '<h3>Notification templates</h3><p>Merge tags: {member_name}, {member_id}, {order_number}, {reward_amount}, {wallet_balance}, {referral_code}, {reward_name}, {site_name}.</p>';
		$templates = get_option( 'ghr_notification_templates', array() );
		foreach ( Notifications::EVENTS as $event => $title ) {
			echo '<label>' . esc_html( $event ) . '<textarea name="templates[' . esc_attr( $event ) . ']">' . esc_textarea( $templates[ $event ] ?? '{member_name}, ' . $title . ' {reward_amount} {reward_name}' ) . '</textarea></label>'; }
		echo '<button class="button button-primary">Save validated settings</button></form>';
	}
	private static function description( string $key ): string {
		return match ( true ) {
			str_contains( $key, 'bps' ) => 'Integer basis points: 100 = 1%, 200 = 2%.', str_contains( $key, 'products' ), str_contains( $key, 'categories' ) => 'Comma-separated WooCommerce product/variation or category IDs. Empty bonus selection disables the bonus.', str_contains( $key, 'expiry' ) => 'Days; 0 keeps credits without expiry.', str_starts_with( $key, 'endpoint_' ) => 'Unique account route slug; preserve WooCommerce reserved slugs.', 'statuses' === $key => 'Default completed. Earlier awards may be spent before fulfillment and require reversals.', str_contains( $key, 'amount' ), str_contains( $key, 'order' ), in_array( $key, array( 'monthly_min', 'referral_min', 'minimum_payable' ), true ) => 'Money uses integer paisa (100 paisa = ৳1). Lists use comma-separated keys.', default => 'See docs/ADMIN.md for policy, units, allowed values and defaults.' }; }
	public static function save_settings(): void {
		if ( ! current_user_can( 'ghr_manage_settings' ) ) {
			wp_die( 'Access denied', '', 403 );
		} check_admin_referer( 'ghr_settings' );
		try {
			Settings::save( (array) wp_unslash( $_POST['settings'] ?? array() ) );
			SMS::save_secret( trim( (string) wp_unslash( $_POST['sms_api_key'] ?? '' ) ) );
			$pages = array_map( 'absint', (array) ( $_POST['pages'] ?? array() ) );
			foreach ( $pages as $id ) {
				if ( $id && 'page' !== get_post_type( $id ) ) {
					throw new \DomainException( 'Page mapping must reference a WordPress page.' );
				}
			} if ( (array) get_option( 'ghr_pages', array() ) !== $pages ) {
				update_option( 'ghr_pages', $pages, false );
				update_option( 'ghr_flush_routes', 1, false ); }
			$templates = array_map( 'sanitize_textarea_field', (array) wp_unslash( $_POST['templates'] ?? array() ) );
			update_option( 'ghr_notification_templates', array_intersect_key( $templates, Notifications::EVENTS ), false );
			delete_option( 'ghr_setup_required' );
			wp_safe_redirect( admin_url( 'admin.php?page=ghr-settings&saved=1' ) );
			exit;
		} catch ( \Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) ); }
	}
	private static function report_filters( string $table ): string {
		global $wpdb;
		$where   = '';
		$columns = array(
			'memberships'             => 'registered_at',
			'wallet_accounts'         => 'updated_at',
			'reward_assignments'      => 'assigned_at',
			'notification_deliveries' => 'next_attempt',
		);
		$column  = $columns[ $table ] ?? 'created_at';
		foreach ( array(
			'from' => '>=',
			'to'   => '<',
		) as $key => $operator ) {
			$date = sanitize_text_field( wp_unslash( $_GET[ $key ] ?? '' ) );
			if ( $date ) {
				$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
				if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
					throw new \DomainException( 'Invalid report date.' );
				} if ( 'to' === $key ) {
					$parsed = $parsed->modify( '+1 day' );
				} $utc  = $parsed->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
				$where .= $wpdb->prepare( " AND $column $operator %s", $utc ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column and operator come from fixed report maps; date value is prepared.
		}
		$user = absint( $_GET['user_id'] ?? 0 );
		if ( $user && ! in_array( $table, array( 'monthly_results', 'notification_deliveries' ), true ) ) {
			$column = 'referral_conversions' === $table ? 'referrer_id' : 'user_id';
			$where .= $wpdb->prepare( " AND $column=%d", $user ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column comes from a fixed two-value selection; user ID is prepared.
		return $where;
	}
	public static function export(): void {
		if ( ! current_user_can( 'ghr_view_reports' ) ) {
			wp_die( 'Access denied', '', 403 );
		} check_admin_referer( 'ghr_export' );
		$report = sanitize_key( $_GET['report'] ?? '' );
		if ( ! isset( self::REPORTS[ $report ] ) ) {
			wp_die( 'Unknown report' ); }
		if ( in_array( $report, array( 'members', 'registrations' ), true ) && ! current_user_can( 'ghr_manage_members' ) ) {
			wp_die( 'Member export permission required', '', 403 ); }
		global $wpdb;
		$where = '1=1' . self::report_filters( self::REPORTS[ $report ] );
		if ( 'redemptions' === $report ) {
			$where .= " AND kind='redeem'";
		} if ( 'trust-seeds' === $report ) {
			$where .= " AND wallet='seeds'"; }
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ghr-' . $report . '.csv"' );
		$out    = fopen( 'php://output', 'w' );
		$cursor = 0;
		$header = false;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . DB::table( self::REPORTS[ $report ] ) . " WHERE $where AND id>%d ORDER BY id LIMIT 500", $cursor ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table comes from REPORTS allowlist; filters and cursor are prepared.
			foreach ( $rows as $r ) {
				$cursor = (int) $r['id'];
				unset( $r['reward_snapshot'] );
				if ( ! $header ) {
					fputcsv( $out, array_keys( $r ), ',', '"', '' );
					$header = true;
				} $r = array_map( static fn( $v ) => ! preg_match( '/^-?[0-9]+(?:\.[0-9]+)?$/D', (string) $v ) && preg_match( '/^[=+@\-\t\r]/', ltrim( (string) $v ) ) ? "'" . $v : $v, $r );
				fputcsv( $out, $r, ',', '"', '' );
			}
		} while ( count( $rows ) === 500 );
		fclose( $out );
		DB::audit( 'report.exported', $report );
		exit;
	}
	public static function order_box( $order_object ): void {
		$order = $order_object instanceof \WC_Order ? $order_object : wc_get_order( $order_object->ID );
		if ( ! $order || ! current_user_can( 'ghr_manage_members' ) ) {
			return;
		} wp_nonce_field( 'ghr_order', 'ghr_order_nonce' );
		echo '<p>Associate an existing member before finalization. WooCommerce still owns this order.</p><label>Member ID, email or verified phone<input name="ghr_member_lookup" value=""></label><label>Referral code<input name="ghr_referral_code" value=""></label>'; }
	public static function save_order( int $id ): void {
		if ( ! current_user_can( 'ghr_manage_members' ) || ! isset( $_POST['ghr_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghr_order_nonce'] ) ), 'ghr_order' ) ) {
			return; }
		$order = wc_get_order( $id );
		if ( ! $order || $order->has_status( Settings::get( 'statuses' ) ) ) {
			return; }
		global $wpdb;
		$lookup = sanitize_text_field( wp_unslash( $_POST['ghr_member_lookup'] ?? '' ) );
		if ( $lookup ) {
			$u      = get_user_by( 'email', $lookup );
			$member = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . ' WHERE member_id=%s OR verified_phone=%s OR user_id=%d LIMIT 1', $lookup, $normalized_phone, $u ? $u->ID : 0 ), ARRAY_A );
			if ( $member ) {
				$order->set_customer_id( (int) $member['user_id'] ); }
		}
		$code = sanitize_text_field( wp_unslash( $_POST['ghr_referral_code'] ?? '' ) );
		if ( $code && ( ! $order->get_meta( '_ghr_referrer' ) || ( Settings::get( 'allow_referral_reassignment' ) && ! $order->get_date_completed() ) ) ) {
			$ref = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . " WHERE referral_code=%s AND status='active'", $code ) );
			if ( $ref && $ref !== $order->get_customer_id() ) {
				$order->update_meta_data( '_ghr_referrer', $ref ); }
		}
		$order->save();
		DB::audit( 'order.attributed', 'order:' . $id );
	}
}
