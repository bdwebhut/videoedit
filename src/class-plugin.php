<?php
namespace GhoroaHat\Rewards;

final class Plugin {
	private static ?self $instance = null;
	public static function instance(): self {
		return self::$instance ??= new self(); }
	public function boot(): void {
		add_action( 'init', array( Frontend::class, 'routes' ) );
		add_action(
			'init',
			static function () {
				if ( (int) get_option( 'ghr_db_version' ) !== 1 ) {
					Installation::migrate();
				} if ( get_option( 'ghr_flush_routes' ) ) {
					flush_rewrite_rules();
					delete_option( 'ghr_flush_routes' );
				} },
			30
		);
		add_action(
			'post_updated',
			static function ( $id, $after, $before ) {
				if ( in_array( $id, array_map( 'intval', (array) get_option( 'ghr_pages', array() ) ), true ) && ( $after->post_name !== $before->post_name || $after->post_parent !== $before->post_parent ) ) {
					update_option( 'ghr_flush_routes', 1, false );
				} },
			10,
			3
		);
		add_action(
			'send_headers',
			static function () {
				if ( is_user_logged_in() ) {
					nocache_headers();
				} }
		);
		add_filter(
			'user_has_cap',
			static function ( $allcaps, $caps, $args, $user ) {
				if ( 'view_order' === ( $args[0] ?? '' ) && ! empty( $args[2] ) ) {
					$order = wc_get_order( $args[2] );
					if ( $order && $order->get_customer_id() && Membership::canonical( (int) $order->get_customer_id() ) === (int) $user->ID ) {
								$allcaps['view_order'] = true;
					}
				} return $allcaps;
			},
			30,
			4
		);
		add_filter(
			'woocommerce_my_account_my_orders_query',
			static function ( $args ) {
				$ids = Membership::sources( get_current_user_id() );
				if ( count( $ids ) > 1 ) {
					$args['customer'] = $ids;
				} return $args;
			}
		);
		SMS::hooks();
		Frontend::hooks();
		Checkout::hooks();
		Admin::hooks();
		Rest::hooks();
		Privacy::hooks();
		add_action( 'template_redirect', array( Referral::class, 'capture' ), 5 );
		add_action( 'woocommerce_new_order', static fn( $id ) => Jobs::order( (int) $id ), 20 );
		add_action( 'woocommerce_order_status_changed', static fn( $id ) => Jobs::order( (int) $id ), 20 );
		add_action( 'woocommerce_order_refunded', static fn( $id ) => Jobs::order( (int) $id ), 20 );
		add_action( 'woocommerce_saved_order_items', static fn( $id ) => Jobs::order( (int) $id ), 20 );
		add_action(
			'woocommerce_created_customer',
			static function ( $id ) {
				if ( get_option( 'ghr_auto_enroll', false ) ) {
					Membership::enroll( (int) $id );
				} }
		);
		add_action(
			'profile_update',
			static function ( $id, $old ) {
				$user = get_userdata( $id );
				if ( $user && $user->user_email !== $old->user_email ) {
					global $wpdb;
					DB::update( ( $wpdb->prefix . 'ghr_memberships' ), array( 'email_verified_at' => null ), array( 'user_id' => $id ) );
					DB::audit( 'identity.email_changed', 'user:' . $id );
				} },
			10,
			2
		);
		add_filter(
			'woocommerce_coupon_is_valid',
			static function ( $valid, $coupon, $discounts = null ) {
				$owner    = (int) $coupon->get_meta( '_ghr_owner' );
				$customer = $discounts && method_exists( $discounts, 'get_object' ) && $discounts->get_object() instanceof \WC_Order && current_user_can( 'ghr_manage_rewards' ) ? (int) $discounts->get_object()->get_customer_id() : get_current_user_id();
				if ( $owner && Membership::canonical( $owner ) !== Membership::canonical( $customer ) ) {
					throw new \Exception( __( 'This reward belongs to another member.', 'ghoroa-hat-rewards' ) );
				}
				if ( $owner ) {
					$member = Membership::get( $customer );
					if ( ! $member || 'active' !== $member['status'] || ! $member['email_verified_at'] || Membership::canonical( $customer ) !== $customer ) {
						throw new \Exception( __( 'An active, verified membership is required to redeem this reward.', 'ghoroa-hat-rewards' ) );
					}
				} return $valid;
			},
			20,
			3
		);
		add_action(
			'woocommerce_order_status_completed',
			static function ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return; }
				foreach ( $order->get_coupon_codes() as $code ) {
					$coupon     = new \WC_Coupon( $code );
					$assignment = (int) $coupon->get_meta( '_ghr_assignment' );
					$reward     = $assignment ? DB::row( 'reward_assignments', $assignment ) : null;
					if ( $reward && 'claimed' === $reward['state'] && Membership::canonical( (int) $order->get_customer_id() ) === (int) $reward['user_id'] ) {
								Rewards::transition( (int) $reward['user_id'], $assignment, 'fulfill', 'Native WooCommerce order completed: ' . $order_id ); }
				}
			},
			30
		);
		Jobs::hooks();
	}
}
