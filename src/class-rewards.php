<?php
namespace GhoroaHat\Rewards;

final class Rewards {
	public static function definition( array $data ): int {
		$types  = array( 'free_product', 'product_credit', 'percent', 'coupon', 'gift', 'manual' );
		$type   = sanitize_key( $data['type'] ?? '' );
		$name   = sanitize_text_field( $data['name'] ?? '' );
		$config = (array) ( $data['config'] ?? array() );
		if ( ! $name || ! in_array( $type, $types, true ) || (int) ( $data['stock'] ?? -1 ) < 0 ) {
			throw new \DomainException( 'Valid name, type and stock are required.' ); }
		$safe = array(
			'product_id'  => absint( $config['product_id'] ?? 0 ),
			'value'       => absint( $config['value'] ?? 0 ),
			'expiry_days' => min( 3650, absint( $config['expiry_days'] ?? 0 ) ),
		);
		if ( 'free_product' === $type && ! wc_get_product( $safe['product_id'] ) ) {
			throw new \DomainException( 'Select an existing product.' ); }
		if ( in_array( $type, array( 'product_credit', 'coupon', 'percent' ), true ) && ( ! $safe['value'] || ( 'percent' === $type && $safe['value'] > 100 ) ) ) {
			throw new \DomainException( 'Invalid prize value.' ); }
		$id = DB::insert(
			'reward_definitions',
			array(
				'name'   => $name,
				'type'   => $type,
				'config' => wp_json_encode( $safe ),
				'stock'  => (int) $data['stock'],
			)
		);
		DB::audit( 'reward.definition', 'definition:' . $id );
		return $id;
	}
	public static function campaign( array $d ): int {
		$definition = DB::row( 'reward_definitions', absint( $d['definition_id'] ?? 0 ) );
		$category   = sanitize_text_field( $d['category'] ?? 'manual' );
		if ( ! $definition || ! trim( $d['name'] ?? '' ) || (int) ( $d['capacity'] ?? 0 ) <= 0 || ! in_array( $category, array( 'manual', 'regular:top_prize', 'regular:magic_box', 'referrals:top_prize', 'referrals:magic_box', 'seeds:top_prize', 'seeds:magic_box' ), true ) ) {
			throw new \DomainException( 'Valid campaign, definition, capacity and category required.' ); }
		return DB::insert(
			'reward_campaigns',
			array(
				'name'          => sanitize_text_field( $d['name'] ),
				'month'         => Leaderboard::month( $d['month'] ?? '' ),
				'category'      => $category,
				'definition_id' => $definition['id'],
				'capacity'      => (int) $d['capacity'],
				'expires_at'    => ! empty( $d['expires_at'] ) ? ( new \DateTimeImmutable( $d['expires_at'], wp_timezone() ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null,
			)
		);
	}
	public static function assign( int $user, int $campaign_id, string $slot ): int {
		return DB::transaction(
			static function () use ( $user, $campaign_id, $slot ) {
				global $wpdb;
				Wallet::lock_member( $user );
				$t   = ( $wpdb->prefix . 'ghr_reward_assignments' );
				$old = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE slot=%s", $slot ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed reward_assignments table; values use placeholders.
				if ( $old ) {
					return (int) $old; }
				$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' WHERE id=%d FOR UPDATE', $campaign_id ), ARRAY_A );
				if ( ! $c || (int) $c['assigned'] >= (int) $c['capacity'] || ( $c['expires_at'] && $c['expires_at'] <= DB::now() ) ) {
					throw new \DomainException( 'Campaign is unavailable or full.' ); }
				$d = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ( $wpdb->prefix . 'ghr_reward_definitions' ) . ' WHERE id=%d FOR UPDATE', $c['definition_id'] ), ARRAY_A );
				if ( ! $d || ! $d['active'] || (int) $d['stock'] < 1 ) {
					throw new \DomainException( 'Prize quota is exhausted.' ); }
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_reward_definitions' ) . ' SET stock=stock-1 WHERE id=%d', $d['id'] ) );
				DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' SET assigned=assigned+1 WHERE id=%d', $campaign_id ) );
				$id = DB::insert(
					'reward_assignments',
					array(
						'box_id'          => bin2hex( random_bytes( 16 ) ),
						'user_id'         => $user,
						'campaign_id'     => $campaign_id,
						'slot'            => $slot,
						'definition_id'   => $d['id'],
						'reward_snapshot' => wp_json_encode( $d ),
						'state'           => str_ends_with( $c['category'], ':top_prize' ) ? 'opened' : 'unopened',
						'opened_at'       => str_ends_with( $c['category'], ':top_prize' ) ? DB::now() : null,
						'assigned_at'     => DB::now(),
						'expires_at'      => $c['expires_at'],
					)
				);
				DB::audit( 'reward.assigned', 'reward:' . $id, array( 'user' => $user ) );
				Notifications::emit( $user, 'box:' . $id, 'magic_box_assigned' );
				return $id;
			}
		);
	}
	public static function list( int $user, int $page = 1 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*,c.month,c.category,c.name campaign_name FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' c ON c.id=a.campaign_id WHERE a.user_id=%d ORDER BY a.id DESC LIMIT 20 OFFSET %d', $user, ( max( 1, $page ) - 1 ) * 20 ), ARRAY_A );
		return array_map(
			static function ( $r ) {
				$d   = json_decode( $r['reward_snapshot'], true );
				$out = array_intersect_key( $r, array_flip( array( 'id', 'box_id', 'state', 'assigned_at', 'opened_at', 'claimed_at', 'expires_at', 'delivery_status', 'explanation', 'month', 'category', 'campaign_name' ) ) );
				if ( 'unopened' !== $r['state'] ) {
					$out['reward_name'] = $d['name'];
				} if ( $r['coupon_id'] && in_array( $r['state'], array( 'claimed', 'fulfilled' ), true ) ) {
					$out['coupon'] = ( new \WC_Coupon( (int) $r['coupon_id'] ) )->get_code();
				} return $out;
			},
			$rows
		);
	}
	public static function transition( int $user, int $id, string $action, string $reason = '' ): array {
		return DB::transaction(
			static function () use ( $user, $id, $action, $reason ) {
				global $wpdb;
				$member = Wallet::lock_member( $user );
				if ( 'claim' === $action && ( 'active' !== $member['status'] || ! $member['email_verified_at'] || Membership::canonical( $user ) !== $user ) ) {
					throw new \DomainException( 'An active, verified membership is required to claim rewards.' );
				}
				$t = ( $wpdb->prefix . 'ghr_reward_assignments' );
				$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d AND user_id=%d FOR UPDATE", $id, $user ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed reward_assignments table; values use placeholders.
				if ( ! $r ) {
					throw new \DomainException( 'Reward not found.' ); }
				if ( $r['expires_at'] && $r['expires_at'] <= DB::now() && in_array( $r['state'], array( 'unopened', 'opened' ), true ) ) {
					throw new \DomainException( 'Reward has expired.' ); }
				$next = array(
					'open'    => 'opened',
					'claim'   => 'claimed',
					'fulfill' => 'fulfilled',
					'revoke'  => 'revoked',
				)[ $action ] ?? '';
				if ( ! $next || in_array( $r['state'], array( 'expired', 'revoked' ), true ) ) {
					throw new \DomainException( 'Reward action is unavailable.' ); }
				if ( $next === $r['state'] || ( 'open' === $action && in_array( $r['state'], array( 'claimed', 'fulfilled' ), true ) ) ) {
					return array(
						'state'       => $r['state'],
						'reward_name' => json_decode( $r['reward_snapshot'], true )['name'],
					); }
				if ( ( 'open' === $action && 'unopened' !== $r['state'] ) || ( 'claim' === $action && 'opened' !== $r['state'] ) || ( 'fulfill' === $action && 'claimed' !== $r['state'] ) ) {
					throw new \DomainException( 'Invalid reward lifecycle transition.' ); }
				if ( in_array( $action, array( 'fulfill', 'revoke' ), true ) && ! trim( $reason ) ) {
					throw new \DomainException( 'A fulfillment/revocation reason is required.' ); }
				$d       = json_decode( $r['reward_snapshot'], true );
				$config  = json_decode( $d['config'], true );
				$updates = array( 'state' => $next );
				if ( 'revoke' === $action && in_array( $r['state'], array( 'unopened', 'opened' ), true ) ) {
					DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_reward_definitions' ) . ' SET stock=stock+1 WHERE id=%d', $r['definition_id'] ) );
					DB::query( $wpdb->prepare( 'UPDATE ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' SET assigned=GREATEST(0,assigned-1) WHERE id=%d', $r['campaign_id'] ) );
				}
				if ( 'revoke' === $action ) {
					$updates['explanation'] = $reason;
					if ( $r['coupon_id'] ) {
								$coupon = new \WC_Coupon( (int) $r['coupon_id'] );
								$coupon->set_date_expires( time() - 1 );
								$coupon->save(); }
				} else {
					$updates[ array(
						'open'    => 'opened_at',
						'claim'   => 'claimed_at',
						'fulfill' => 'fulfilled_at',
					)[ $action ] ] = DB::now(); }
				if ( 'claim' === $action && in_array( $d['type'], array( 'free_product', 'product_credit', 'percent', 'coupon' ), true ) ) {
					$code     = 'ghr-prize-' . $r['box_id'];
					$existing = wc_get_coupon_id_by_code( $code );
					$coupon   = new \WC_Coupon( $existing ? $existing : 0 );
					if ( ! $existing ) {
						$coupon->set_code( $code );
						$coupon->set_usage_limit( 1 );
						$coupon->set_usage_limit_per_user( 1 );
						$coupon->set_email_restrictions( array() );
						$coupon->set_individual_use( true );
						$coupon->update_meta_data( '_ghr_owner', $user );
						$coupon->update_meta_data( '_ghr_assignment', $id );
						$percent = in_array( $d['type'], array( 'percent', 'free_product' ), true );
						$coupon->set_discount_type( $percent ? 'percent' : 'fixed_cart' );
						$coupon->set_amount( 'free_product' === $d['type'] ? '100' : ( $percent ? (string) $config['value'] : Money::decimal( $config['value'] ) ) );
						if ( $config['product_id'] ) {
							$coupon->set_product_ids( array( $config['product_id'] ) );
						} if ( 'free_product' === $d['type'] ) {
										$coupon->set_limit_usage_to_x_items( 1 ); }
						$expires = $r['expires_at'] ? strtotime( $r['expires_at'] . ' UTC' ) : ( $config['expiry_days'] ? time() + $config['expiry_days'] * DAY_IN_SECONDS : null );
						if ( $expires ) {
									$coupon->set_date_expires( $expires );
						} $coupon->save(); }
					$updates['coupon_id'] = $coupon->get_id();
				}
				if ( 'fulfill' === $action ) {
					$updates['delivery_status'] = 'fulfilled'; }
				DB::update( $t, $updates, array( 'id' => $id ) );
				DB::audit( 'reward.' . $action, 'reward:' . $id, array(), $reason );
				Notifications::emit(
					$user,
					'reward:' . $id . ':' . $action,
					array(
						'open'    => 'magic_box_opened',
						'claim'   => 'reward_claimed',
						'fulfill' => 'reward_fulfilled',
						'revoke'  => 'announcement',
					)[ $action ],
					array( 'reward_name' => $d['name'] )
				);
				return array(
					'state'       => $next,
					'reward_name' => $d['name'],
					'coupon'      => isset( $coupon ) ? $coupon->get_code() : '',
				);
			}
		);
	}
}
