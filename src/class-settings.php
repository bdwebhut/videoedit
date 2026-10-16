<?php
namespace GhoroaHat\Rewards;

final class Settings {
	public static function defaults(): array {
		return array(
			'primary_color'               => '#087A3D',
			'dark_color'                  => '#075B2A',
			'accent_color'                => '#A80F16',
			'background_color'            => '#FFFAF0',
			'historical_rewards'          => false,
			'enabled'                     => true,
			'trust_enabled'               => true,
			'referral_enabled'            => true,
			'seeds_enabled'               => true,
			'leaderboard_enabled'         => true,
			'bonus_products'              => array(),
			'bonus_amount'                => 10000,
			'trust_min_order'             => 100000,
			'referral_bps'                => 200,
			'seeds_bps'                   => 100,
			'min_eligible'                => 1,
			'referral_days'               => 30,
			'referral_model'              => 'last',
			'referral_query'              => 'ghref',
			'guest_referrals'             => false,
			'repeat_referrals'            => true,
			'referral_approval'           => false,
			'hold_days'                   => 0,
			'referral_min'                => 50000,
			'seeds_min'                   => 500,
			'seeds_max'                   => 500,
			'seeds_min_order'             => 70000,
			'statuses'                    => array( 'completed' ),
			'excluded_products'           => array(),
			'excluded_categories'         => array(),
			'trust_expiry_days'           => 0,
			'referral_expiry_days'        => 0,
			'seeds_expiry_days'           => 0,
			'regular_order_weight'        => 0,
			'referral_value_weight'       => 0,
			'seeds_purchase_weight'       => 0,
			'tie_breaker'                 => 'personal_time_id',
			'monthly_min'                 => 50000,
			'purchase_weight'             => 1,
			'referral_weight'             => 1,
			'seeds_weight'                => 1,
			'checkout_wallets'            => array( 'trust', 'referral', 'seeds' ),
			'redemption_order'            => array( 'trust', 'referral', 'seeds' ),
			'stacking_policy'             => 'single',
			'allow_referral_reassignment' => false,
			'allow_self_referrals'        => false,
			'minimum_payable'             => 0,
			'email_enabled'               => true,
			'sms_endpoint'                => '',
			'sms_phone_field'             => 'to',
			'sms_message_field'           => 'message',
			'sms_enabled'                 => false,
			'retry_limit'                 => 5,
			'require_tracking_consent'    => true,
			'bengali_numbers'             => true,
			'endpoint_membership'         => 'gh-membership',
			'endpoint_trust'              => 'gh-trust-wallet',
			'endpoint_referrals'          => 'gh-referrals',
			'endpoint_seeds'              => 'gh-trust-seeds',
			'endpoint_rewards'            => 'gh-rewards',
			'endpoint_notifications'      => 'gh-notifications',
			'label_membership'            => 'সদস্যপদ',
			'label_trust'                 => 'ট্রাস্ট ওয়ালেট',
			'label_referrals'             => 'রেফারেল',
			'label_seeds'                 => 'বিশ্বাস বীজ',
			'label_rewards'               => 'পুরস্কার',
			'label_notifications'         => 'বিজ্ঞপ্তি',
			'menu_order'                  => array( 'membership', 'trust', 'referrals', 'seeds', 'rewards', 'notifications' ),
			'hidden_endpoints'            => array(),
		);
	}
	public static function all(): array {
		return array_replace( self::defaults(), (array) get_option( 'ghr_settings', array() ) ); }
	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null; }
	public static function save( array $input ): void {
		$old = self::all();
		$new = $old;
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue; }
			$value = $input[ $key ];
			if ( is_bool( $default ) ) {
				$new[ $key ] = (bool) $value; } elseif ( is_int( $default ) ) {
				if ( ! preg_match( '/^\d{1,10}$/', (string) $value ) ) {
					throw new \InvalidArgumentException( 'Invalid number: ' . $key );
				} $new[ $key ] = (int) $value; } elseif ( is_array( $default ) ) {
								$values      = is_array( $value ) ? $value : preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
								$new[ $key ] = array_values( array_unique( array_map( in_array( $key, array( 'statuses', 'menu_order', 'hidden_endpoints', 'checkout_wallets', 'redemption_order' ), true ) ? 'sanitize_key' : 'absint', $values ) ) );
				} else {
					$new[ $key ] = str_starts_with( $key, 'endpoint_' ) || 'referral_query' === $key ? sanitize_title( $value ) : sanitize_text_field( $value ); }
		}
		foreach ( array( 'referral_bps', 'seeds_bps' ) as $key ) {
			if ( $new[ $key ] > 10000 ) {
				throw new \DomainException( 'Percentages must be between 0 and 10000 basis points.' ); }
		}
		if ( $new['seeds_max'] < 1 || $new['seeds_min'] < 1 || $new['referral_days'] < 1 || $new['referral_days'] > 365 || $new['retry_limit'] < 1 || $new['retry_limit'] > 10 || ! in_array( $new['referral_model'], array( 'first', 'last' ), true ) ) {
			throw new \DomainException( 'Invalid reward or tracking policy.' ); }
		$allowed = array_keys( wc_get_order_statuses() );
		$allowed = array_map( static fn( $v ) => substr( $v, 3 ), $allowed );
		if ( ! $new['statuses'] || array_diff( $new['statuses'], $allowed ) || array_intersect( $new['statuses'], array( 'cancelled', 'failed', 'refunded' ) ) ) {
			throw new \DomainException( 'Select valid qualifying order statuses.' ); }
		$slugs = array();
		foreach ( $new as $key => $value ) {
			if ( str_starts_with( $key, 'endpoint_' ) ) {
				if ( ! $value || in_array( $value, $slugs, true ) || in_array( $value, array_merge( array( 'orders', 'downloads', 'edit-account', 'edit-address', 'payment-methods', 'customer-logout', 'view-order', 'order-pay', 'order-received', 'lost-password', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method' ), array_values( WC()->query->get_query_vars() ) ), true ) ) {
					throw new \DomainException( 'Account endpoints must be unique and avoid WooCommerce endpoints.' );
				} $slugs[] = $value; }
		}
		if ( ! preg_match( '/^[a-z][a-z0-9_-]{1,39}$/D', $new['referral_query'] ) || in_array( $new['referral_query'], array( 'p', 'page', 'page_id', 'post_type', 'author', 's', 'redirect_to' ), true ) ) {
			throw new \DomainException( 'Use a non-reserved referral query parameter.' ); }
		foreach ( array( 'primary_color', 'dark_color', 'accent_color', 'background_color' ) as $color ) {
			if ( ! sanitize_hex_color( $new[ $color ] ) ) {
				throw new \DomainException( 'Brand colors require valid hex colors.' ); }
		}
		$menu = array( 'membership', 'trust', 'referrals', 'seeds', 'rewards', 'notifications' );
		if ( count( $new['menu_order'] ) !== count( $menu ) || array_diff( $menu, $new['menu_order'] ) || array_diff( $new['hidden_endpoints'], $menu ) ) {
			throw new \DomainException( 'Menu order must include all six module keys once.' ); }
		if ( $new['sms_endpoint'] && ( 'https' !== wp_parse_url( $new['sms_endpoint'], PHP_URL_SCHEME ) || ! wp_http_validate_url( $new['sms_endpoint'] ) ) ) {
			throw new \DomainException( 'SMS gateway requires a valid public HTTPS URL.' ); }
		foreach ( array( 'sms_phone_field', 'sms_message_field' ) as $key ) {
			if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $new[ $key ] ) ) {
				throw new \DomainException( 'Invalid SMS JSON field name.' ); }
		}
		$wallets = array( 'trust', 'referral', 'seeds' );
		if ( array_diff( $new['checkout_wallets'], $wallets ) || count( $new['redemption_order'] ) !== 3 || array_diff( $wallets, $new['redemption_order'] ) || 'single' !== $new['stacking_policy'] ) {
			throw new \DomainException( 'Choose supported checkout wallets and a unique order. Only single-wallet stacking is certified.' ); }
		if ( $new['allow_self_referrals'] && ! $new['referral_approval'] ) {
			throw new \DomainException( 'Self-referral exceptions require manual commission approval and published promotion rules.' ); }
		foreach ( array( 'purchase_weight', 'regular_order_weight', 'referral_weight', 'referral_value_weight', 'seeds_weight', 'seeds_purchase_weight' ) as $weight_key ) {
			if ( $new[ $weight_key ] > 1000000 ) {
				throw new \DomainException( 'Competition weights cannot exceed 1000000.' ); }
		}
		if ( ! in_array( $new['tie_breaker'], array( 'personal_time_id', 'time_id', 'member_id' ), true ) ) {
			throw new \DomainException( 'Select personal_time_id, time_id or member_id tie-breaking.' ); }
		update_option( 'ghr_settings', $new, false );
		if ( array_filter( array_keys( $new ), static fn( $k ) => str_starts_with( $k, 'endpoint_' ) && $new[ $k ] !== $old[ $k ] ) ) {
			update_option( 'ghr_flush_routes', 1, false ); }
		DB::audit( 'settings.updated', 'settings', array( 'changed' => array_keys( array_diff_assoc( array_map( 'serialize', $new ), array_map( 'serialize', $old ) ) ) ) );
	}
}
