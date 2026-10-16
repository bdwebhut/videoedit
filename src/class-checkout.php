<?php
namespace GhoroaHat\Rewards;

final class Checkout {
	public static function hooks(): void {
		add_filter(
			'woocommerce_valid_order_statuses_for_payment',
			static function ( $statuses, $order ) {
				$id = $order->get_meta( '_ghr_reservation' );
				if ( ! $id ) {
					return $statuses; }
				$r = Wallet::reservation( $id );
				if ( ! $r || ! in_array( $r['state'], array( 'reserved', 'committed' ), true ) ) {
					return array(); }
				return array_values( array_diff( $statuses, array( 'failed', 'cancelled', 'refunded' ) ) );
			},
			20,
			2
		);
		add_filter(
			'woocommerce_coupon_get_items_to_apply',
			static function ( $items, $coupon ) {
				if ( ! str_starts_with( $coupon->get_code(), 'ghr-wallet-' ) ) {
					return $items;
				} return array_values( array_filter( $items, static fn( $item ) => Calculator::eligible_product( $item->product, Settings::all() ) ) );
			},
			20,
			2
		);
		add_action(
			'init',
			static function () {
				register_block_type(
					'ghr/member-benefits',
					array(
						'api_version'     => 2,
						'title'           => 'Ghoroa Hat Member Benefits',
						'category'        => 'woocommerce',
						'parent'          => array( 'woocommerce/checkout-fields-block' ),
						'render_callback' => static fn() => '<div class="wp-block-ghr-member-benefits" data-block-name="ghr/member-benefits"></div>',
					)
				);
			}
		);
		add_filter(
			'render_block_data',
			static function ( $block ) {
				if ( 'woocommerce/checkout-fields-block' !== $block['blockName'] ) {
					return $block; }
				foreach ( $block['innerBlocks'] as $child ) {
					if ( 'ghr/member-benefits' === $child['blockName'] ) {
								return $block; }
				}
				$block['innerBlocks'][] = array(
					'blockName'    => 'ghr/member-benefits',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
				array_splice( $block['innerContent'], max( 0, count( $block['innerContent'] ) - 1 ), 0, array( null ) );
				return $block;
			}
		);
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'remember_line' ), 20, 4 );
		add_filter( 'woocommerce_get_shop_coupon_data', array( self::class, 'coupon' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( self::class, 'valid_coupon' ), 10, 3 );
		add_action( 'woocommerce_review_order_before_payment', array( self::class, 'classic' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'attach' ), 20 );
		add_action( 'woocommerce_checkout_order_created', array( self::class, 'placed' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( self::class, 'attach' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( self::class, 'placed' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( self::class, 'validate_cart' ) );
		add_action(
			'woocommerce_blocks_loaded',
			static function () {
				if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
					woocommerce_store_api_register_update_callback(
						array(
							'namespace' => 'ghr',
							'callback'  => static function ( $data ) {
								try {
									self::select( get_current_user_id(), $data );
								} catch ( \Throwable $e ) {
									throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'ghr_redemption', $e->getMessage(), 400 );
								} },
						)
					);
				}
			}
		);
	}
	private static function eligible_ids(): array {
		$ids = array();
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( Calculator::eligible_product( $item['data'], Settings::all() ) ) {
					$ids[] = $item['data']->get_id();
				}
			}
		} return $ids;
	}
	public static function remember_line( $item, $key, $values, $order ): void {
		$item->update_meta_data( '_ghr_cart_key', $key ); }
	public static function ensure_cart(): void {
		if ( ! WC()->cart ) {
			wc_load_cart(); } }
	public static function current(): ?array {
		return WC()->session && WC()->session->get( 'ghr_reservation' ) ? Wallet::reservation( WC()->session->get( 'ghr_reservation' ) ) : null; }
	private static function code( string $id ): string {
		return 'ghr-wallet-' . $id; }
	public static function coupon( $data, string $code ): mixed {
		if ( ! str_starts_with( $code, 'ghr-wallet-' ) ) {
			return $data; }
		$id = substr( $code, 11 );
		$r  = Wallet::reservation( $id );
		if ( ! $r || get_current_user_id() !== (int) $r['user_id'] || 'reserved' !== $r['state'] || $r['expires_at'] <= DB::now() || ! WC()->session || WC()->session->get( 'ghr_reservation' ) !== $id ) {
			return false; }
		$minor    = (int) $r['units'] * ( 'seeds' === $r['wallet'] ? 100 : 1 );
		$override = WC()->session->get( 'ghr_coupon_amount' );
		if ( is_array( $override ) && $override['id'] === $id ) {
			$minor = (int) $override['minor']; }
		return array(
			'id'             => 0,
			'discount_type'  => 'fixed_cart',
			'amount'         => Money::decimal( $minor ),
			'individual_use' => false,
			'usage_limit'    => 0,
			'free_shipping'  => false,
			'product_ids'    => self::eligible_ids(),
			'description'    => 'Ghoroa Hat member credit',
		);
	}
	public static function valid_coupon( bool $valid, \WC_Coupon $coupon, $discounts ): bool {
		if ( ! str_starts_with( $coupon->get_code(), 'ghr-wallet-' ) ) {
			return $valid; }
		$r = self::current();
		if ( ! $r || self::code( $r['id'] ) !== $coupon->get_code() || get_current_user_id() !== (int) $r['user_id'] || ! Membership::active( get_current_user_id() ) ) {
			throw new \Exception( __( 'Wallet reservation is invalid. Please apply it again.', 'ghoroa-hat-rewards' ) ); }
		return $valid;
	}
	public static function state( int $user ): array {
		self::ensure_cart();
		$calc     = self::base_cart();
		$balances = Wallet::summary( $user );
		$choices  = array();
		foreach ( Settings::get( 'redemption_order' ) as $wallet ) {
			$balance            = $balances[ $wallet ];
			$choices[ $wallet ] = array(
				'available' => $balance['available'],
				'maximum'   => Membership::active( $user ) ? Calculator::redeemable( $wallet, $balance['available'], $calc['eligible'] ) : 0,
				'rules'     => Frontend::rules( $wallet ),
			); }
		return array(
			'wallets'     => $choices,
			'eligible'    => $calc['eligible'],
			'reservation' => self::current(),
			'message'     => __( 'একটি অর্ডারে একটি ওয়ালেট ব্যবহার করুন। কর ও ডেলিভারি চার্জ অন্তর্ভুক্ত নয়।', 'ghoroa-hat-rewards' ),
		);
	}
	private static function base_cart(): array {
		self::ensure_cart();
		$r       = self::current();
		$removed = false;
		if ( $r && WC()->cart->has_discount( self::code( $r['id'] ) ) ) {
			WC()->cart->remove_coupon( self::code( $r['id'] ) );
			$removed = true; }
		WC()->cart->calculate_totals();
		$calc = Calculator::cart();
		if ( $removed ) {
			WC()->cart->apply_coupon( self::code( $r['id'] ) );
			WC()->cart->calculate_totals(); }
		return $calc;
	}
	public static function select( int $user, array $data ): array {
		self::ensure_cart();
		if ( ! $user || ! Membership::active( $user ) ) {
			throw new \DomainException( 'Active verified membership is required.' );
		} DB::limit( 'checkout:' . $user, 30, 60 );
		$old = self::current();
		if ( $old ) {
			WC()->cart->remove_coupon( self::code( $old['id'] ) );
			if ( ! $old['order_id'] ) {
				Wallet::release( $old['id'], $user );
			} WC()->session->__unset( 'ghr_reservation' ); }
		WC()->cart->calculate_totals();
		if ( ! empty( $data['remove'] ) ) {
			return self::state( $user ); }
		$wallet = sanitize_key( $data['wallet'] ?? '' );
		$calc   = Calculator::cart();
		// Native coupons discount products before tax; WooCommerce recalculates tax, never shipping.
		$a   = Wallet::account( $user, $wallet );
		$max = Calculator::redeemable( $wallet, $a['available'], $calc['eligible'] );
		if ( isset( $data['units'] ) && ! preg_match( '/^\\d{1,12}$/D', (string) $data['units'] ) ) {
			throw new \DomainException( 'Enter a valid redemption amount.' ); }
		$units = isset( $data['units'] ) && preg_match( '/^\d{1,12}$/D', (string) $data['units'] ) ? (int) $data['units'] : $max;
		if ( $units <= 0 || $units > $max ) {
			throw new \DomainException( 'Wallet threshold, order minimum or requested amount is invalid.' ); }

		$hash = hash( 'sha256', wp_json_encode( array( WC()->cart->get_cart_hash(), $calc['lines'] ) ) );
		$r    = Wallet::reserve( $user, $wallet, $units, sanitize_text_field( $data['intent'] ?? '' ), $hash );
		WC()->session->set( 'ghr_reservation', $r['id'] );
		if ( ! WC()->cart->apply_coupon( self::code( $r['id'] ) ) ) {
			Wallet::release( $r['id'], $user );
			WC()->session->__unset( 'ghr_reservation' );
			throw new \DomainException( 'WooCommerce could not apply the credit.' ); }
		try {
			self::match_net_credit( $r, $calc );
			self::validate_applied( $r );
		} catch ( \Throwable $e ) {
			WC()->cart->remove_coupon( self::code( $r['id'] ) );
			Wallet::release( $r['id'], $user );
			WC()->session->__unset( 'ghr_reservation' );
			WC()->session->__unset( 'ghr_coupon_amount' );
			WC()->cart->calculate_totals();
			throw $e;
		}
		return self::state( $user );
	}

	private static function match_net_credit( array $r, array $calc ): void {
		$target = (int) $r['units'] * ( 'seeds' === $r['wallet'] ? 100 : 1 );
		$low    = 0;
		$high   = 0;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $calc['lines'][ $key ] ) ) {
				$high += Money::minor( (string) $item['line_subtotal'] ) + Money::minor( (string) $item['line_subtotal_tax'] ); }
		}
		$trial = $target;
		for ( $i = 0; $i < 48 && $low <= $high; $i++ ) {
			WC()->session->set(
				'ghr_coupon_amount',
				array(
					'id'    => $r['id'],
					'minor' => $trial,
				)
			);
			// Recreate the virtual coupon: WooCommerce otherwise retains its previous object.
			WC()->cart->remove_coupon( self::code( $r['id'] ) );
			WC()->cart->apply_coupon( self::code( $r['id'] ) );
			WC()->cart->calculate_totals();
			$actual = Money::minor( (string) WC()->cart->get_coupon_discount_amount( self::code( $r['id'] ), true ) );
			if ( $actual === $target ) {
				return; }
			if ( $actual < $target ) {
				$low = $trial + 1;
			} else {
				$high = $trial - 1; }
			$trial = intdiv( $low + $high, 2 );
		}
		throw new \DomainException( 'WooCommerce could not allocate the exact product credit under this tax/coupon configuration.' );
	}
	private static function validate_applied( array $r ): void {
		$amount   = Money::minor( (string) WC()->cart->get_coupon_discount_amount( self::code( $r['id'] ), true ) );
		$expected = (int) $r['units'] * ( 'seeds' === $r['wallet'] ? 100 : 1 );
		if ( $expected !== $amount || Money::minor( (string) WC()->cart->get_total( 'edit' ) ) < 0 || $r['expires_at'] <= DB::now() ) {
			throw new \DomainException( 'Cart changed. Remove the wallet credit and apply it again.' ); }
	}
	public static function validate_cart(): void {
		$r = self::current();
		if ( ! $r ) {
			return;
		} try {
			self::validate_applied( $r );
		} catch ( \Throwable $e ) {
			wc_add_notice( $e->getMessage(), 'error' ); } }
	public static function attach( \WC_Order $order ): void {
		$order->update_meta_data( '_ghr_epoch', get_option( 'ghr_ledger_epoch' ) );
		Referral::attach( $order );
		$r = self::current();
		if ( ! $r ) {
			return; }
		self::validate_applied( $r );
		$calc = self::base_cart();
		$a    = Wallet::account( (int) $r['user_id'], $r['wallet'] );
		$max  = Calculator::redeemable( $r['wallet'], $a['available'] + (int) $r['units'], $calc['eligible'] );
		if ( (int) $r['units'] > $max || (int) $r['user_id'] !== (int) $order->get_customer_id() ) {
			throw new \DomainException( 'Order is no longer eligible for wallet redemption.' ); }
		$order->update_meta_data( '_ghr_reservation', $r['id'] );
		$order->update_meta_data( '_ghr_credit_units', (int) $r['units'] );
		$order->update_meta_data( '_ghr_credit_wallet', $r['wallet'] );

		$by_key = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$by_key[ $key ] = isset( $calc['lines'][ $key ] ) ? max( 0, $calc['lines'][ $key ] - Money::minor( (string) $item['line_total'] ) ) : 0; }
		$expected = (int) $r['units'] * ( 'seeds' === $r['wallet'] ? 100 : 1 );
		if ( array_sum( $by_key ) !== $expected ) {
			throw new \DomainException( 'Item credit allocation does not match the reservation.' ); }
		foreach ( $order->get_items() as $item ) {
			$key = $item->get_meta( '_ghr_cart_key' );
			if ( ! array_key_exists( $key, $by_key ) ) {
				throw new \DomainException( 'Missing checkout item binding.' );
			} $item->update_meta_data( '_ghr_credit_minor', $by_key[ $key ] ); }
	}
	public static function placed( \WC_Order $order ): void {
		$id = $order->get_meta( '_ghr_reservation' );
		if ( ! $id ) {
			return; }
		Wallet::bind( $id, (int) $order->get_customer_id(), $order->get_id() );
		Wallet::commit( $id, (int) $order->get_customer_id(), $order->get_id() );
		$allocation = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$allocation[ $item_id ] = (int) $item->get_meta( '_ghr_credit_minor' ); }
		$expected = (int) $order->get_meta( '_ghr_credit_units' ) * ( 'seeds' === $order->get_meta( '_ghr_credit_wallet' ) ? 100 : 1 );
		if ( array_sum( $allocation ) !== $expected ) {
			throw new \DomainException( 'Order item credits do not match the committed credit.' ); }
		$order->update_meta_data( '_ghr_credit_allocation', $allocation );

		$order->save();
		if ( WC()->session && WC()->session->get( 'ghr_reservation' ) === $id ) {
			WC()->session->__unset( 'ghr_reservation' );
			WC()->session->__unset( 'ghr_coupon_amount' ); }
	}
	public static function reconcile_order( \WC_Order $order, array $calc ): void {
		$id = $order->get_meta( '_ghr_reservation' );
		if ( ! $id ) {
			return;
		} $r = Wallet::reservation( $id );
		if ( ! $r ) {
			throw new \DomainException( 'Order credit is missing its reservation.' ); }
		$cancelled = $order->has_status( array( 'failed', 'cancelled', 'refunded' ) );
		if ( 'reserved' === $r['state'] ) {
			if ( $cancelled ) {
				global $wpdb;
				DB::update( ( $wpdb->prefix . 'ghr_wallet_reservations' ), array( 'order_id' => 0 ), array( 'id' => $id ) );
				Wallet::release( $id, (int) $r['user_id'] );
				return; }
			self::placed( $order );
		}
		if ( in_array( $r['state'], array( 'released', 'restored' ), true ) && ! $cancelled ) {
			$order->update_meta_data( '_ghr_review_required', 'Late payment or retry after wallet credit release; create a fresh checkout or reconcile the order.' );
			$order->save();
			if ( Membership::active( (int) $r['user_id'] ) ) {
				Membership::status( (int) $r['user_id'], 'suspended', 'Late payment after wallet release requires reviewed reconciliation.' ); }
			throw new \DomainException( 'This order credit was released. Recreate checkout before retrying payment.' ); }
		if ( $cancelled ) {
			Wallet::restore( $id, (int) $r['units'], 'restore:' . $id . ':full' );
			return; }
		$allocation    = (array) $order->get_meta( '_ghr_credit_allocation' );
		$restore_minor = 0;
		foreach ( $allocation as $item_id => $credit ) {
			$item = $order->get_item( $item_id );
			if ( ! $item ) {
				continue; }
			$original = Money::minor( (string) $item->get_total() );
			$refunded = abs( Money::minor( (string) $order->get_total_refunded_for_item( $item_id ) ) );
			foreach ( (array) $order->get_meta( '_ghr_manual_refunds' ) as $reviewed ) {
				$refunded += (int) ( $reviewed[ $item_id ] ?? 0 ); }
			if ( $original > 0 ) {
				$restore_minor += intdiv( (int) $credit * min( $original, $refunded ), $original ); } else {
				$qty            = max( 1, (int) $item->get_quantity() );
				$restore_minor += intdiv( (int) $credit * min( $qty, abs( (int) $order->get_qty_refunded_for_item( $item_id ) ) ), $qty ); }
		}

		$units = 'seeds' === $r['wallet'] ? intdiv( $restore_minor, 100 ) : $restore_minor;
		if ( $units ) {
			Wallet::restore( $id, $units, 'restore:' . $id . ':' . $units ); }
	}

	public static function resolve_released_order( int $order_id, string $reason, bool $allow_debt = false ): string {
		global $wpdb;
		if ( ! trim( $reason ) ) {
			throw new \DomainException( 'An audited settlement reason is required.' ); }
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) {
			throw new \DomainException( 'Select a paid/accepted order that requires credit reconciliation.' ); }
		$lock = 'ghr_order_' . $order_id;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,10)', $lock ) ) ) {
			throw new \DomainException( 'Order reconciliation is already running.' ); }
		try {
			$old_id = (string) $order->get_meta( '_ghr_reservation' );
			$old    = Wallet::reservation( $old_id );
			if ( ! $old || Membership::canonical( (int) $order->get_customer_id() ) !== (int) $old['user_id'] ) {
				throw new \DomainException( 'The original credit and canonical order owner must match.' ); }
			if ( 'committed' === $old['state'] ) {
				return $old['id']; }
			if ( ! in_array( $old['state'], array( 'released', 'restored' ), true ) ) {
				throw new \DomainException( 'This reservation does not need late-payment settlement.' ); }
			$id = DB::transaction(
				static function () use ( $old, $old_id, $order_id, $reason, $allow_debt ) {
					global $wpdb;
					$user = (int) $old['user_id'];
					Wallet::lock_member( $user );
					Wallet::expire_user( $user );
					$a        = Wallet::account( $user, $old['wallet'], true );
					$key      = 'late-settlement:' . $old_id;
					$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT source_ref FROM ' . ( $wpdb->prefix . 'ghr_wallet_transactions' ) . ' WHERE idempotency_key=%s', $key ) );
					if ( $existing ) {
						return $existing; }
					if ( $a['available'] < (int) $old['units'] && ! $allow_debt ) {
						throw new \DomainException( 'Insufficient credit. Refund/recreate the order or explicitly approve reward debt.' ); }
					$new_id = bin2hex( random_bytes( 32 ) );
					Wallet::post( $user, $old['wallet'], - (int) $old['units'], $key, $allow_debt ? 'debt_redemption' : 'redeem', $new_id, $order_id, $reason );
					DB::insert(
						'wallet_reservations',
						array(
							'id'          => $new_id,
							'account_id'  => $a['id'],
							'user_id'     => $user,
							'wallet'      => $old['wallet'],
							'intent_hash' => hash( 'sha256', $key ),
							'cart_hash'   => $old['cart_hash'],
							'order_id'    => $order_id,
							'units'       => $old['units'],
							'state'       => 'committed',
							'expires_at'  => DB::now(),
							'created_at'  => DB::now(),
						)
					);
					DB::audit(
						'order.late_credit_settled',
						'order:' . $order_id,
						array(
							'original'                 => $old_id,
							'replacement'              => $new_id,
							'debt_explicitly_approved' => $allow_debt,
						),
						$reason
					);
					return $new_id;
				}
			);
			$order->update_meta_data( '_ghr_reservation', $id );
			$order->delete_meta_data( '_ghr_review_required' );
			$order->add_order_note( 'Ghoroa Hat credit settlement: ' . $reason );
			$order->save();
			Jobs::order( $order_id );
			return $id;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
	public static function classic(): void {
		if ( ! is_user_logged_in() || ! Membership::active( get_current_user_id() ) ) {
			return; }
		echo '<div class="ghr"><h3>' . esc_html__( 'ঘরোয়া হাট সদস্য সুবিধা', 'ghoroa-hat-rewards' ) . '</h3><div data-ghr-checkout></div></div>';
	}
}
