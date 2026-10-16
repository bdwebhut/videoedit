<?php
namespace GhoroaHat\Rewards;

final class Frontend {
	public const SHORTCODES = array( 'membership_information', 'referral_landing', 'membership_card', 'membership_dashboard', 'member_id', 'trust_wallet', 'trust_wallet_balance', 'referral_dashboard', 'referral_link', 'referral_stats', 'referral_wallet', 'referral_wallet_balance', 'trust_seeds', 'trust_seeds_balance', 'trust_seeds_progress', 'leaderboard', 'monthly_winners', 'leaderboard_preview', 'my_rewards', 'magic_box', 'rewards_summary', 'notification_bell' );
	public static function endpoints(): array {
		$out = array();
		foreach ( Settings::get( 'menu_order' ) as $key ) {
			if ( Settings::get( 'endpoint_' . $key ) ) {
				$out[ $key ] = Settings::get( 'endpoint_' . $key );
			}
		} return $out; }
	public static function routes(): void {
		foreach ( self::endpoints() as $slug ) {
			add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES ); }
		$pages = (array) get_option( 'ghr_pages', array() );
		if ( ! empty( $pages['refer'] ) ) {
			$path = trim( (string) wp_parse_url( get_permalink( $pages['refer'] ), PHP_URL_PATH ), '/' );
			$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			if ( $home ) {
				$path = substr( $path, strlen( $home ) + 1 );
			} add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/([A-Za-z0-9]+)/?$', 'index.php?page_id=' . $pages['refer'] . '&ghr_ref_code=$matches[1]', 'top' ); }
		add_rewrite_tag( '%ghr_ref_code%', '([A-Za-z0-9]+)' );
	}
	public static function url( string $key ): string {
		return wc_get_account_endpoint_url( Settings::get( 'endpoint_' . $key ) ); }
	public static function hooks(): void {
		add_filter(
			'the_content',
			static function ( $content ) {
				if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
					return $content; }
				$map     = (array) get_option( 'ghr_pages', array() );
				$modules = array(
					'membership'  => 'membership_information',
					'refer'       => 'referral_landing',
					'leaderboard' => 'leaderboard',
					'winners'     => 'monthly_winners',
				);
				foreach ( $modules as $key => $shortcode ) {
					if ( (int) ( $map[ $key ] ?? 0 ) === get_the_ID() && ! has_shortcode( $content, 'ghr_' . $shortcode ) && ! str_contains( $content, 'class="ghr"' ) ) {
								return $content . "\n[ghr_" . $shortcode . ']';
					}
				} return $content;
			},
			9
		);
		foreach ( self::SHORTCODES as $name ) {
			add_shortcode( 'ghr_' . $name, static fn( $a = array() ) => self::shortcode( $name, (array) $a ) ); }
		foreach ( self::endpoints() as $key => $slug ) {
			add_action(
				'woocommerce_account_' . $slug . '_endpoint',
				static function () use ( $key ) {
					self::assets();
					try {
						echo self::account( $key );
					} catch ( \Throwable $e ) {
						echo self::wrap( '<p role="alert">' . esc_html__( 'Rewards are temporarily unavailable. Please try again shortly.', 'ghoroa-hat-rewards' ) . '</p>' );
					}
				}
			); }
		add_filter(
			'woocommerce_account_menu_items',
			static function ( $items ) {
				$logout = $items['customer-logout'] ?? null;
				unset( $items['customer-logout'] );
				foreach ( self::endpoints() as $key => $slug ) {
					if ( ! in_array( $key, Settings::get( 'hidden_endpoints' ), true ) ) {
						$items[ $slug ] = Settings::get( 'label_' . $key );
					}
				} if ( $logout ) {
					$items['customer-logout'] = $logout;
				} return $items;
			}
		);
		add_action(
			'wp_enqueue_scripts',
			static function () {
				global $post;
				$needed = is_account_page() || is_checkout() || in_array( get_queried_object_id(), array_map( 'intval', (array) get_option( 'ghr_pages', array() ) ), true );
				if ( $post ) {
					foreach ( self::SHORTCODES as $name ) {
						if ( has_shortcode( $post->post_content, 'ghr_' . $name ) ) {
									$needed = true;
									break; }
					}
				}
				if ( $needed ) {
					self::assets(); }
				if ( is_checkout() && has_block( 'woocommerce/checkout', $post ) ) {
					wp_enqueue_script( 'ghr-checkout-blocks', plugins_url( 'assets/blocks.js', GHR_FILE ), array( 'wp-element', 'wc-cart-checkout-base', 'ghr' ), GHR_VERSION, true );
					$scripts = wp_scripts();
					if ( isset( $scripts->registered['wc-checkout-block-frontend'] ) && ! in_array( 'ghr-checkout-blocks', $scripts->registered['wc-checkout-block-frontend']->deps, true ) ) {
						$scripts->registered['wc-checkout-block-frontend']->deps[] = 'ghr-checkout-blocks'; }
				}
			}
		);
		add_action(
			'woocommerce_order_details_after_order_table',
			static function ( $order ) {
				if ( (int) $order->get_customer_id() === get_current_user_id() && $order->get_meta( '_ghr_reservation' ) ) {
					echo '<p class="ghr">' . esc_html__( 'ঘরোয়া হাট সদস্য সুবিধা এই অর্ডারে ব্যবহার করা হয়েছে।', 'ghoroa-hat-rewards' ) . '</p>';
				} }
		);
	}
	public static function assets(): void {
		wp_enqueue_style( 'ghr', plugins_url( 'assets/frontend.css', GHR_FILE ), array(), GHR_VERSION );
		wp_enqueue_script( 'ghr', plugins_url( 'assets/frontend.js', GHR_FILE ), array(), GHR_VERSION, true );
		wp_localize_script(
			'ghr',
			'ghrConfig',
			array(
				'api'            => esc_url_raw( rest_url( 'ghr/v1/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'loggedIn'       => is_user_logged_in(),
				'bengaliNumbers' => Settings::get( 'bengali_numbers' ),
				'walletLabels'   => array(
					'trust'    => Settings::get( 'label_trust' ),
					'referral' => Settings::get( 'label_referrals' ),
					'seeds'    => Settings::get( 'label_seeds' ),
				),
				'error'          => __( 'কাজটি সম্পন্ন হয়নি। আবার চেষ্টা করুন।', 'ghoroa-hat-rewards' ),
			)
		);
	}
	public static function number( int $n ): string {
		$s = number_format( $n );
		return Settings::get( 'bengali_numbers' ) ? strtr( $s, array_combine( range( 0, 9 ), preg_split( '//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY ) ) ) : $s; }
	public static function amount( int $units, string $wallet ): string {
		return 'seeds' === $wallet ? self::number( $units ) . ' বীজ' : '৳' . ( Settings::get( 'bengali_numbers' ) ? strtr( Money::decimal( $units ), array_combine( range( 0, 9 ), preg_split( '//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY ) ) ) : Money::decimal( $units ) ); }
	private static function wrap( string $html ): string {
		return '<section class="ghr" lang="bn" style="--ghr-green:' . esc_attr( Settings::get( 'primary_color' ) ) . ';--ghr-dark:' . esc_attr( Settings::get( 'dark_color' ) ) . ';--ghr-red:' . esc_attr( Settings::get( 'accent_color' ) ) . ';--ghr-bg:' . esc_attr( Settings::get( 'background_color' ) ) . '">' . $html . '<p class="ghr-status" role="status" aria-live="polite"></p></section>'; }
	public static function table( array $rows ): string {
		if ( ! $rows ) {
			return '<p class="ghr-empty">' . esc_html__( 'এখনো কোনো তথ্য নেই।', 'ghoroa-hat-rewards' ) . '</p>'; }
		$html = '<div class="ghr-table"><table><thead><tr>';
		foreach ( array_keys( $rows[0] ) as $key ) {
			$html .= '<th scope="col">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</th>';
		} $html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $row as $value ) {
				$html .= '<td>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</td>';
			} $html .= '</tr>';
		} return $html . '</tbody></table></div>';
	}
	private static function pagination( int $page, int $count ): string {
		$out = '<nav aria-label="' . esc_attr( wp_unique_id( 'ঘরোয়া হাট পৃষ্ঠা ' ) ) . '">';
		if ( $page > 1 ) {
			$out .= '<a href="' . esc_url( add_query_arg( 'ghr_page', $page - 1 ) ) . '">← আগের পৃষ্ঠা</a> ';
		} if ( $count >= 20 ) {
			$out .= '<a href="' . esc_url( add_query_arg( 'ghr_page', $page + 1 ) ) . '">পরের পৃষ্ঠা →</a>';
		} return $out . '</nav>'; }
	public static function account( string $key ): string {
		if ( ! is_user_logged_in() ) {
			return self::wrap( '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">লগইন / নিবন্ধন</a>' ); }
		$user = get_current_user_id();
		$m    = Membership::get( $user );
		if ( ! $m ) {
			return self::wrap( '<h2>ঘরোয়া হাট সদস্যপদ</h2><p>আপনার WooCommerce অ্যাকাউন্ট দিয়েই যোগ দিন।</p><button type="button" data-ghr-action="membership/enroll">সদস্য হোন</button>' ); }
		$page = isset( $_GET['ghr_page'] ) ? max( 1, absint( $_GET['ghr_page'] ) ) : 1;
		if ( in_array( $key, array( 'trust', 'seeds' ), true ) ) {
			return self::wrap( self::wallet( $user, $key, $page ) ); }
		if ( 'referrals' === $key ) {
			$stats = Referral::stats( $user, $page );
			foreach ( array( 'eligible', 'commission' ) as $amount_key ) {
				$stats['summary'][ $amount_key ] = self::amount( (int) $stats['summary'][ $amount_key ], 'referral' );
				foreach ( $stats['activity'] as &$row ) {
					$row[ $amount_key ] = self::amount( (int) $row[ $amount_key ], 'referral' );
				} unset( $row ); }
			return self::wrap( '<h2>আমার রেফারেল</h2>' . self::link( $m ) . self::table( array( array_merge( $stats['visits'], $stats['summary'] ) ) ) . self::wallet( $user, 'referral', $page ) . '<h3>রেফারেল কার্যক্রম</h3>' . self::table( $stats['activity'] ) ); }
		if ( 'rewards' === $key ) {
			return self::wrap( self::rewards( $user, $page ) ); }
		if ( 'notifications' === $key ) {
			return self::wrap( self::notifications( $user, $page ) . self::preferences_form( $user ) ); }
		$html  = self::member_card( $user, $m );
		$html .= '<div class="ghr-grid">';
		foreach ( Wallet::summary( $user ) as $wallet => $a ) {
			$html .= '<a class="ghr-card" href="' . esc_url( self::url( 'referral' === $wallet ? 'referrals' : $wallet ) ) . '"><span>' . esc_html( $wallet ) . '</span><strong>' . esc_html( self::amount( $a['available'], $wallet ) ) . '</strong></a>';
		} $html .= '</div>';
		global $wpdb;
		$stats                               = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) successful_orders,COALESCE(SUM(eligible),0) lifetime_eligible_purchase FROM ' . ( $wpdb->prefix . 'ghr_order_effects' ) . " WHERE user_id=%d AND effect_key='personal' AND eligible>0", $user ), ARRAY_A );
		$stats['lifetime_eligible_purchase'] = self::amount( (int) $stats['lifetime_eligible_purchase'], 'trust' );
		$html                               .= self::table( array( $stats ) );
		[ $from, $to ]                       = Leaderboard::bounds( wp_date( 'Y-m' ) );
		$monthly                             = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(eligible),0) monthly_purchase,COALESCE(SUM(posted),0) monthly_seeds FROM ' . ( $wpdb->prefix . 'ghr_order_effects' ) . " WHERE user_id=%d AND effect_key='personal' AND created_at>=%s AND created_at<%s", $user, $from, $to ), ARRAY_A );
		$monthly['monthly_purchase']         = self::amount( (int) $monthly['monthly_purchase'], 'trust' );
		$monthly['monthly_seeds']            = self::number( (int) $monthly['monthly_seeds'] );
		$monthly['unopened_magic_boxes']     = self::number( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . " WHERE user_id=%d AND state='unopened' AND (expires_at IS NULL OR expires_at>%s)", $user, DB::now() ) ) );
		$html                               .= self::table( array( $monthly ) );
		$html                               .= self::link( $m );
		if ( ! $m['email_verified_at'] ) {
			$html .= '<button type="button" data-ghr-action="verification/request" data-purpose="email">ইমেইল যাচাই কোড পাঠান</button><form data-ghr-form="verification/confirm"><input type="hidden" name="purpose" value="email"><label>ইমেইল যাচাই কোড<input name="token" required autocomplete="one-time-code"></label><button>যাচাই করুন</button></form>'; }
		$html .= '<details><summary>মোবাইল যাচাই</summary><form data-ghr-form="verification/request"><input type="hidden" name="purpose" value="phone"><label>মোবাইল<input name="target" type="tel" required placeholder="01XXXXXXXXX"></label><button>কোড পাঠান</button></form><form data-ghr-form="verification/confirm"><input type="hidden" name="purpose" value="phone"><label>যাচাই কোড<input name="token" required inputmode="numeric" autocomplete="one-time-code"></label><button>যাচাই করুন</button></form></details>';
		$html .= '<h3>এই মাসের প্রতিযোগিতা</h3>' . self::leaderboard(
			array(
				'category' => 'regular',
				'number'   => 5,
			)
		) . self::rewards( $user, 1 ) . self::notifications( $user, 1 );
		return self::wrap( $html );
	}
	private static function member_card( int $user, array $m ): string {
		return '<article class="ghr-member"><span>ঘরোয়া হাট · GHOROA HAT</span><h2>' . esc_html( get_userdata( $user )->display_name ) . '</h2><strong>' . esc_html( $m['member_id'] ) . '</strong><p>' . esc_html( $m['status'] ) . '</p><p>' . esc_html( get_userdata( $user )->user_email ) . ' · ' . ( $m['email_verified_at'] ? 'যাচাইকৃত' : 'যাচাই হয়নি' ) . '</p><p>' . esc_html( $m['verified_phone'] ? $m['verified_phone'] : $m['phone'] ) . '</p><p>নিবন্ধন: ' . esc_html( $m['registered_at'] ) . ' · সক্রিয়: ' . esc_html( $m['activated_at'] ? $m['activated_at'] : 'অপেক্ষমাণ' ) . '</p></article>'; }
	private static function wallet( int $user, string $wallet, int $page ): string {
		$a    = Wallet::summary( $user )[ $wallet ];
		$html = '<h2>' . esc_html(
			array(
				'trust'    => 'ট্রাস্ট ওয়ালেট',
				'referral' => 'রেফারেল ওয়ালেট',
				'seeds'    => 'বিশ্বাস বীজ',
			)[ $wallet ]
		) . '</h2><div class="ghr-grid">';
		foreach ( array(
			'available' => 'ব্যবহারযোগ্য',
			'pending'   => 'অপেক্ষমাণ',
			'earned'    => 'মোট অর্জিত',
			'redeemed'  => 'মোট ব্যবহৃত',
			'reversed'  => 'সমন্বয় / মেয়াদোত্তীর্ণ',
		) as $key => $label ) {
			$html .= '<article class="ghr-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( self::amount( (int) $a[ $key ], $wallet ) ) . '</strong></article>';
		} $html .= '</div><p>' . esc_html( self::rules( $wallet ) ) . '</p>';
		if ( 'seeds' === $wallet ) {
			$html .= self::progress( $a['available'] ); }
		$filters = array();
		foreach ( array( 'kind', 'status', 'direction', 'from', 'to' ) as $filter ) {
			$filters[ $filter ] = isset( $_GET[ 'ghr_' . $filter ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'ghr_' . $filter ] ) ) : '';
		}
		$html .= '<form method="get" class="ghr-filters"><label>শুরু<input type="date" name="ghr_from" value="' . esc_attr( $filters['from'] ) . '"></label><label>শেষ<input type="date" name="ghr_to" value="' . esc_attr( $filters['to'] ) . '"></label><label>ধরন<select name="ghr_kind"><option value="">সব</option>';
		foreach ( array( 'earn', 'bonus', 'redeem', 'restore', 'reversal', 'expiry', 'adjustment' ) as $kind ) {
			$html .= '<option value="' . esc_attr( $kind ) . '" ' . selected( $filters['kind'], $kind, false ) . '>' . esc_html( $kind ) . '</option>';
		}
		$html .= '</select></label><label>দিক<select name="ghr_direction"><option value="">সব</option><option value="credit" ' . selected( $filters['direction'], 'credit', false ) . '>জমা</option><option value="debit" ' . selected( $filters['direction'], 'debit', false ) . '>ব্যয়</option></select></label><button>ফিল্টার</button></form>';
		$rows  = Wallet::history( $user, $wallet, $page, 20, $filters );
		foreach ( $rows as &$row ) {
			$row['amount'] = ( (int) $row['amount'] > 0 ? '+' : '' ) . self::amount( (int) $row['amount'], $wallet );
		} unset( $row );
		return $html . self::table( $rows ) . self::pagination( $page, count( $rows ) );
	}
	public static function rules( string $wallet ): string {
		if ( ! Settings::get( $wallet . '_enabled' ) ) {
			return 'এই সুবিধাটি সাময়িকভাবে বন্ধ আছে।'; }
		if ( 'trust' === $wallet && ! Settings::get( 'bonus_products' ) ) {
			return 'ট্রাস্ট বোনাসের যোগ্য প্যাকেজ এখনো নির্ধারিত হয়নি।'; }
		return match ( $wallet ) {
			'trust' => 'নির্ধারিত প্যাকেজে একবারের বোনাস। পরবর্তী কমপক্ষে ৳' . Money::decimal( Settings::get( 'trust_min_order' ) ) . ' পণ্য অর্ডারে ব্যবহারযোগ্য।', 'referral' => 'যোগ্য পণ্যে ' . Money::decimal( Settings::get( 'referral_bps' ) ) . '% কমিশন। ব্যবহার করতে কমপক্ষে ৳' . Money::decimal( Settings::get( 'referral_min' ) ) . ' ব্যালেন্স প্রয়োজন।', default => 'নিজের যোগ্য কেনাকাটায় ' . Money::decimal( Settings::get( 'seeds_bps' ) ) . '% বিশ্বাস বীজ। কমপক্ষে ' . Settings::get( 'seeds_min' ) . ' বীজ ও ৳' . Money::decimal( Settings::get( 'seeds_min_order' ) ) . ' অর্ডার; সর্বোচ্চ ' . Settings::get( 'seeds_max' ) . ' বীজ।' } . ' নগদ উত্তোলন বা হস্তান্তর করা যায় না।'; }
	private static function compact_wallet( int $user, string $wallet ): string {
		$balance = Wallet::summary( $user )[ $wallet ];
		$rows    = Wallet::history( $user, $wallet, 1, 5 );
		foreach ( $rows as &$row ) {
			$row = array(
				'date'   => $row['created_at'],
				'type'   => $row['kind'],
				'amount' => ( (int) $row['amount'] > 0 ? '+' : '' ) . self::amount( (int) $row['amount'], $wallet ),
			);
		} unset( $row );
		return '<p>' . esc_html( self::amount( $balance['available'], $wallet ) ) . '</p><p>অপেক্ষমাণ: ' . esc_html( self::amount( $balance['pending'], $wallet ) ) . '</p>' . self::table( $rows ) . '<p>' . esc_html( self::rules( $wallet ) ) . '</p>';
	}
	private static function progress( int $seeds ): string {
		$min = (int) Settings::get( 'seeds_min' );
		return '<label>' . esc_html( self::number( $seeds ) . ' / ' . self::number( $min ) ) . '<progress max="' . esc_attr( $min ) . '" value="' . esc_attr( min( $min, $seeds ) ) . '"></progress></label>'; }
	private static function link( array $m ): string {
		$url = Referral::url( $m );
		return '<div class="ghr-card"><label>আপনার রেফারেল লিংক<input readonly value="' . esc_attr( $url ) . '"></label><p>' . esc_html( $m['referral_code'] ) . '</p><button type="button" data-ghr-copy="' . esc_attr( $url ) . '">লিংক কপি করুন</button> <a rel="noopener noreferrer" target="_blank" href="' . esc_url( 'https://wa.me/?text=' . rawurlencode( $url ) ) . '">WhatsApp</a> <a rel="noopener noreferrer" target="_blank" href="' . esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ) . '">Facebook</a></div>'; }
	private static function rewards( int $user, int $page ): string {
		$rows = Rewards::list( $user, $page );
		$html = '<h3>আমার পুরস্কার ও ম্যাজিক বক্স</h3><div class="ghr-grid">';
		foreach ( $rows as $r ) {
			$html .= '<article class="ghr-card ghr-box"><h4>' . esc_html( $r['reward_name'] ?? '🎁 ম্যাজিক বক্স' ) . '</h4><p>' . esc_html( $r['state'] . ' · ' . $r['delivery_status'] ) . '</p>';
			if ( $r['expires_at'] ) {
				$html .= '<p>' . esc_html( $r['expires_at'] ) . '</p>';
			} if ( ! empty( $r['coupon'] ) ) {
				$html .= '<code>' . esc_html( $r['coupon'] ) . '</code>';
			} if ( in_array( $r['state'], array( 'unopened', 'opened' ), true ) ) {
				$action = 'unopened' === $r['state'] ? 'open' : 'claim';
				$html  .= '<button type="button" data-ghr-action="rewards/' . esc_attr( $r['id'] . '/' . $action ) . '">' . ( 'open' === $action ? 'বক্স খুলুন' : 'পুরস্কার দাবি করুন' ) . '</button>';
			} $html .= '</article>'; }
		return $html . ( $rows ? '' : '<p>এখনো কোনো পুরস্কার নেই। মাসিক নিয়ম অনুযায়ী যোগ্য হোন।</p>' ) . '</div>' . self::pagination( $page, count( $rows ) );
	}
	private static function notifications( int $user, int $page ): string {
		$rows = Notifications::list( $user, $page );
		$html = '<h3>বিজ্ঞপ্তি</h3><button type="button" data-ghr-action="notifications/read-all">সব পড়া হয়েছে</button>';
		foreach ( $rows as $r ) {
			$html .= '<article class="ghr-notice' . ( $r['read_at'] ? '' : ' ghr-unread' ) . '"><h4>' . esc_html( $r['title'] ) . '</h4><p>' . esc_html( $r['message'] ) . '</p><time>' . esc_html( wp_date( 'j M Y H:i', strtotime( $r['created_at'] . ' UTC' ) ) ) . '</time> ';
			if ( ! $r['read_at'] ) {
				$html .= '<button type="button" data-ghr-action="notifications/' . esc_attr( $r['id'] ) . '/read">পড়া হয়েছে</button>';
			} $html .= '</article>';
		} return $html . self::pagination( $page, count( $rows ) ); }
	private static function preferences_form( int $user ): string {
		$html = '<form data-ghr-form="me/preferences"><h3>গোপনীয়তা ও বিজ্ঞপ্তি পছন্দ</h3>';
		foreach ( Notifications::preferences( $user ) as $k => $v ) {
			$html .= '<label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( $v, true, false ) . '> ' . esc_html( $k ) . '</label>';
		} return $html . '<button>সংরক্ষণ করুন</button></form>'; }
	private static function leaderboard( array $a ): string {
		$month    = isset( $a['month'] ) && strlen( (string) $a['month'] ) === 7 ? $a['month'] : ( ! empty( $a['year'] ) ? sprintf( '%04d-%02d', absint( $a['year'] ), absint( $a['month'] ?? wp_date( 'm' ) ) ) : wp_date( 'Y-m' ) );
		$month    = isset( $_GET['ghr_month'] ) ? Leaderboard::month( sanitize_text_field( wp_unslash( $_GET['ghr_month'] ) ) ) : $month;
		$category = sanitize_key( $a['category'] ?? 'regular' );
		$rows     = Leaderboard::live( $month, $category, absint( $a['number'] ?? 20 ) );
		$html     = '<h2>' . esc_html( $month . ' · ' . $category ) . '</h2><nav>';
		foreach ( array( 'regular', 'referrals', 'seeds' ) as $c ) {
			$html .= '<a href="' . esc_url( add_query_arg( 'ghr_category', $c ) ) . '">' . esc_html( $c ) . '</a> ';
		} $rules = Leaderboard::displayed_rules( $month );
		$ranking = self::table( $rows );
		if ( 'cards' === ( $a['layout'] ?? 'table' ) ) {
			$ranking = '<div class="ghr-grid">';
			foreach ( $rows as $row ) {
				$ranking .= '<article class="ghr-card">' . self::table( array( $row ) ) . '</article>';
			}
			$ranking .= '</div>';
		}
		return $html . '</nav>' . $ranking . '<p>সর্বশেষ হালনাগাদ: ' . esc_html( get_option( 'ghr_scores_updated_' . $month, 'অপেক্ষমাণ' ) ) . '</p><details><summary>প্রকাশিত মাসিক নিয়ম</summary><p>যোগ্য ব্যক্তিগত কেনাকাটা কমপক্ষে ৳' . esc_html( Money::decimal( $rules['monthly_min'] ) ) . '। ক্রেতা স্কোর: পয়সায় ক্রয়মূল্য × ' . esc_html( $rules['purchase_weight'] ) . ' + সফল অর্ডার × ' . esc_html( $rules['regular_order_weight'] ) . '; রেফারেল স্কোর: সফল রেফারেল সংখ্যা × ' . esc_html( $rules['referral_weight'] ) . ' + রেফারেল মূল্য (পয়সা) × ' . esc_html( $rules['referral_value_weight'] ) . '; বীজ স্কোর: অর্জিত বীজ × ' . esc_html( $rules['seeds_weight'] ) . ' + নিজের ক্রয়মূল্য (পয়সা) × ' . esc_html( $rules['seeds_purchase_weight'] ) . '।</p><p>' . esc_html( $rules['selection'] . '. ' . $rules['tie_break'] ) . '</p></details>'; }
	public static function shortcode( string $name, array $a ): string {
		self::assets();
		try {
			if ( in_array( $name, array( 'leaderboard', 'leaderboard_preview' ), true ) ) {
				if ( isset( $_GET['ghr_category'] ) ) {
					$a['category'] = sanitize_key( wp_unslash( $_GET['ghr_category'] ) );
				} return self::wrap( self::leaderboard( $a ) ); }
			if ( 'monthly_winners' === $name ) {
				return self::wrap( '<h2>বিগত বিজয়ী</h2>' . self::table( Leaderboard::winners( sanitize_text_field( $a['month'] ?? '' ) ) ) ); }
			if ( 'membership_information' === $name ) {
				return self::wrap( '<h2>ঘরোয়া হাট — বিশ্বাসের সদস্যপদ</h2><p>স্থায়ী সদস্য পরিচয়, ট্রাস্ট বোনাস, রেফারেল কমিশন, বিশ্বাস বীজ ও মাসিক পুরস্কার।</p><p>' . esc_html( self::rules( 'trust' ) ) . '</p><p>' . esc_html( self::rules( 'referral' ) ) . '</p><p>' . esc_html( self::rules( 'seeds' ) ) . '</p><a class="ghr-button" href="' . esc_url( self::url( 'membership' ) ) . '">সদস্য হোন</a><details><summary>কখন পুরস্কার পাব?</summary><p>যোগ্য অর্ডার সম্পন্ন হওয়ার পরে। ইমেইল যাচাই প্রয়োজন।</p></details><details><summary>নগদ উত্তোলন করা যাবে?</summary><p>না। শুধু যোগ্য পণ্য কেনাকাটায় ব্যবহারযোগ্য।</p></details>' ); }
			if ( 'referral_landing' === $name ) {
				return self::wrap( '<h2>স্বাগতম ঘরোয়া হাটে</h2><p>বন্ধুর সুপারিশে কেনাকাটা করুন। সম্মতি ও যোগ্য অর্ডারের ভিত্তিতে রেফারেল হিসাব করা হয়।</p><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">পণ্য দেখুন</a>' ); }
			if ( ! is_user_logged_in() ) {
				return self::account( 'membership' ); }
			$user = get_current_user_id();
			$m    = Membership::get( $user );
			if ( ! $m ) {
				return self::account( 'membership' ); }
			if ( 'member_id' === $name ) {
				return esc_html( $m['member_id'] ); }
			if ( 'membership_card' === $name ) {
				return self::wrap( self::member_card( $user, $m ) ); }
			if ( 'membership_dashboard' === $name ) {
				return self::account( 'membership' ); }
			if ( 'referral_link' === $name ) {
				return self::wrap( self::link( $m ) ); }
			if ( 'referral_stats' === $name ) {
				$s = Referral::stats( $user );
				return self::wrap( self::table( array( array_merge( $s['visits'], $s['summary'] ) ) ) ); }
			if ( 'referral_dashboard' === $name ) {
				return self::account( 'referrals' ); }
			if ( in_array( $name, array( 'my_rewards', 'magic_box', 'rewards_summary' ), true ) ) {
				return self::account( 'rewards' ); }
			if ( 'notification_bell' === $name ) {
				global $wpdb;
				$count = Notifications::unread( $user );
				return self::wrap( '<details class="ghr-dropdown"><summary>🔔 <span data-ghr-unread>' . esc_html( self::number( $count ) ) . '</span></summary><div>' . self::notifications( $user, 1 ) . '<a href="' . esc_url( self::url( 'notifications' ) ) . '">সব বিজ্ঞপ্তি</a></div></details>' ); }
			$wallet = str_starts_with( $name, 'trust_wallet' ) ? 'trust' : ( str_starts_with( $name, 'referral_wallet' ) ? 'referral' : 'seeds' );
			$s      = Wallet::summary( $user )[ $wallet ];
			if ( str_ends_with( $name, '_balance' ) ) {
				$label     = sanitize_text_field( $a['label'] ?? '' );
				$formatted = self::amount( $s['available'], $wallet );
				$currency  = in_array( $a['currency'] ?? 'symbol', array( 'symbol', 'code', 'none' ), true ) ? ( $a['currency'] ?? 'symbol' ) : 'symbol';
				if ( 'seeds' !== $wallet && 'symbol' !== $currency ) {
					$formatted = str_replace( '৳', 'code' === $currency ? 'BDT ' : '', $formatted ); }
				$style = in_array( $a['style'] ?? 'text', array( 'text', 'badge' ), true ) ? ( $a['style'] ?? 'text' ) : 'text';
				return '<span class="ghr ghr-balance-' . esc_attr( $style ) . '" data-ghr-balance="' . esc_attr( $wallet ) . '">' . esc_html( $label . ' ' . $formatted ) . '</span>'; }
			if ( 'trust_seeds_progress' === $name ) {
				return self::wrap( self::progress( $s['available'] ) ); }
			return self::wrap( '<details class="ghr-dropdown" data-ghr-wallet="' . esc_attr( $wallet ) . '"><summary>' . esc_html( $wallet . ' · ' . self::amount( $s['available'], $wallet ) ) . '</summary><div>' . self::compact_wallet( $user, $wallet ) . '<a href="' . esc_url( self::url( 'referral' === $wallet ? 'referrals' : $wallet ) ) . '">সম্পূর্ণ ওয়ালেট</a></div></details>' );
		} catch ( \Throwable $e ) {
			return self::wrap( '<p role="alert">' . esc_html( $e->getMessage() ) . '</p>' ); }
	}
}
