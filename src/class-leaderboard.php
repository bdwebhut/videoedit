<?php
namespace GhoroaHat\Rewards;

final class Leaderboard {
	public static function month( string $month = '' ): string {
		$month = $month ? $month : wp_date( 'Y-m' );
		if ( ! preg_match( '/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $month ) ) {
			throw new \DomainException( 'Invalid calendar month.' );
		} return $month;
	}
	public static function bounds( string $month ): array {
		$start = new \DateTimeImmutable( self::month( $month ) . '-01 00:00:00', wp_timezone() );
		return array( $start->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ), $start->modify( '+1 month' )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) ); }
	public static function rules( string $month ): array {
		$key   = 'ghr_rules_' . self::month( $month );
		$rules = get_option( $key );
		if ( ! $rules ) {
			$rules              = array_intersect_key( Settings::all(), array_flip( array( 'monthly_min', 'purchase_weight', 'regular_order_weight', 'referral_weight', 'referral_value_weight', 'seeds_weight', 'seeds_purchase_weight', 'tie_breaker' ) ) );
			$rules['selection'] = 'Ranked next eligible, unique member, regular then referrals then seeds';
			$rules['tie_break'] = 'Referral value for referrals, personal spend, earliest activity, Member ID';
			add_option( $key, $rules, '', false ); }
		return array_replace(
			array(
				'regular_order_weight'  => 0,
				'referral_value_weight' => 0,
				'seeds_purchase_weight' => 0,
				'tie_breaker'           => 'personal_time_id',
			),
			$rules
		);
	}

	public static function displayed_rules( string $month ): array {
		global $wpdb;
		$month = self::month( $month );
		if ( $month < wp_date( 'Y-m' ) ) {
			$json = $wpdb->get_var( $wpdb->prepare( 'SELECT r.rules FROM ' . ( $wpdb->prefix . 'ghr_monthly_results' ) . ' r JOIN ' . ( $wpdb->prefix . 'ghr_result_publications' ) . " p ON p.snapshot_id=r.id AND p.action='published' WHERE r.month=%s ORDER BY r.version DESC LIMIT 1", $month ) );
			if ( $json ) {
				return array_replace(
					array(
						'regular_order_weight'  => 0,
						'referral_value_weight' => 0,
						'seeds_purchase_weight' => 0,
					),
					json_decode( $json, true )
				); }
		}
		return self::rules( $month );
	}
	private const RECIPES = array(
		'regular'   => array(
			'personal'     => 'purchase_weight',
			'orders_count' => 'regular_order_weight',
		),
		'referrals' => array(
			'conversions'    => 'referral_weight',
			'referral_value' => 'referral_value_weight',
		),
		'seeds'     => array(
			'seeds'    => 'seeds_weight',
			'personal' => 'seeds_purchase_weight',
		),
	);
	private static function score_expression( string $category, array $rules ): string {
		$terms = array();
		foreach ( self::RECIPES[ $category ] as $metric => $key ) {
			$terms[] = 's.' . $metric . '*' . (int) ( $rules[ $key ] ?? 0 );
		} return '(' . implode( '+', $terms ) . ')'; }
	private static function tie_expression( string $category, array $rules ): string {
		$field = array(
			'regular'   => 'first_at',
			'referrals' => 'first_referral_at',
			'seeds'     => 'first_seeds_at',
		)[ $category ];
		return match ( $rules['tie_breaker'] ?? 'personal_time_id' ) {
			'member_id' => 'm.member_id ASC', 'time_id' => 's.' . $field . ' ASC,m.member_id ASC', default => ( 'referrals' === $category ? 's.referral_value DESC,' : '' ) . 's.personal DESC,s.' . $field . ' ASC,m.member_id ASC' };
	}
	public static function set_rules( string $month, array $input, string $reason ): void {
		$month = self::month( $month );
		if ( ! trim( $reason ) ) {
			throw new \DomainException( 'A published rule change requires a reason.' ); }
		$rules = self::rules( $month );
		$old   = $rules;
		foreach ( array( 'monthly_min', 'purchase_weight', 'regular_order_weight', 'referral_weight', 'referral_value_weight', 'seeds_weight', 'seeds_purchase_weight' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				if ( ! preg_match( '/^\d{1,12}$/D', (string) $input[ $key ] ) || ( 'monthly_min' !== $key && (int) $input[ $key ] > 1000000 ) ) {
					throw new \DomainException( 'Use nonnegative integer rule values within supported limits.' );
				} $rules[ $key ] = (int) $input[ $key ]; }
		}
		if ( isset( $input['tie_breaker'] ) ) {
			if ( ! in_array( $input['tie_breaker'], array( 'personal_time_id', 'time_id', 'member_id' ), true ) ) {
				throw new \DomainException( 'Unsupported tie-break policy.' );
			} $rules['tie_breaker'] = $input['tie_breaker']; }
		$rules['tie_break'] = 'personal_time_id' === $rules['tie_breaker'] ? 'Referral value for referrals, personal spend, earliest category activity, Member ID' : ( 'time_id' === $rules['tie_breaker'] ? 'Earliest category activity, Member ID' : 'Member ID ascending' );
		update_option( 'ghr_rules_' . $month, $rules, false );
		DB::audit(
			'leaderboard.rules_changed',
			'month:' . $month,
			array(
				'before' => $old,
				'after'  => $rules,
			),
			$reason
		);
		as_enqueue_async_action( 'ghr_score_refresh', array( $month ), 'ghr' );
	}
	/** Refresh aggregate scores atomically with constant PHP memory and indexed set-based SQL. */
	public static function prepare( string $month ): array {
		global $wpdb;
		$month         = self::month( $month );
		[ $from, $to ] = self::bounds( $month );
		$rules         = self::rules( $month );
		$version       = hash( 'sha256', wp_json_encode( $rules ) );
		$rows          = DB::transaction(
			static function () use ( $wpdb, $month, $from, $to, $rules, $version ) {
				$personal  = $wpdb->prepare( 'SELECT user_id,SUM(eligible) personal,SUM(posted) seeds,SUM(eligible>0) orders_count,MIN(CASE WHEN eligible>0 THEN created_at END) first_at,MIN(CASE WHEN posted>0 THEN created_at END) first_seeds_at FROM ' . ( $wpdb->prefix . 'ghr_order_effects' ) . " WHERE effect_key='personal' AND created_at>=%s AND created_at<%s GROUP BY user_id", $from, $to );
				$referrals = $wpdb->prepare( 'SELECT referrer_id,COUNT(*) conversions,SUM(eligible) referral_value,MIN(created_at) first_referral_at FROM ' . ( $wpdb->prefix . 'ghr_referral_conversions' ) . " WHERE status='approved' AND created_at>=%s AND created_at<%s GROUP BY referrer_id", $from, $to );
				DB::query( $wpdb->prepare( 'DELETE FROM ' . ( $wpdb->prefix . 'ghr_monthly_scores' ) . ' WHERE month=%s AND rules_version=%s', $month, $version ) );
				DB::query( $wpdb->prepare( 'INSERT INTO ' . ( $wpdb->prefix . 'ghr_monthly_scores' ) . ' (month,user_id,rules_version,personal,orders_count,conversions,referral_value,seeds,first_at,first_seeds_at,first_referral_at,updated_at) SELECT %s,m.user_id,%s,COALESCE(p.personal,0),COALESCE(p.orders_count,0),COALESCE(r.conversions,0),COALESCE(r.referral_value,0),COALESCE(p.seeds,0),p.first_at,p.first_seeds_at,r.first_referral_at,%s FROM ' . ( $wpdb->prefix . 'ghr_memberships' ) . " m LEFT JOIN ($personal) p ON p.user_id=m.user_id LEFT JOIN ($referrals) r ON r.referrer_id=m.user_id WHERE m.status='active' AND COALESCE(p.personal,0)>=%d", $month, $version, DB::now(), (int) $rules['monthly_min'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Subqueries are prepared immediately above; other values use placeholders.
				$out = array();
				foreach ( array( 'regular', 'referrals', 'seeds' ) as $category ) {
					$metric     = array(
						'regular'   => 'personal',
						'referrals' => 'conversions',
						'seeds'     => 'seeds',
					)[ $category ];
					$weight     = array(
						'regular'   => 'purchase_weight',
						'referrals' => 'referral_weight',
						'seeds'     => 'seeds_weight',
					)[ $category ];
					$score      = self::score_expression( $category, $rules );
					$tie        = self::tie_expression( $category, $rules );
					$candidates = $wpdb->get_results( $wpdb->prepare( 'SELECT s.*,m.member_id,m.public_consent FROM ' . ( $wpdb->prefix . 'ghr_monthly_scores' ) . ' s JOIN ' . ( $wpdb->prefix . 'ghr_memberships' ) . " m ON m.user_id=s.user_id WHERE s.month=%s AND s.rules_version=%s AND s.$metric>0 AND $score>0 ORDER BY $score DESC,$tie LIMIT 100", $month, $version ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Columns, score recipes and tie clauses are fixed allowlists with integer weights.
					foreach ( $candidates as $candidate ) {
						$out[ $candidate['user_id'] ] = $candidate; }
				}
				return array_values( $out );
			}
		);
		update_option( 'ghr_scores_updated_' . $month, DB::now(), false );
		return $rows;
	}
	public static function rank( array $rows, string $category, array $rules ): array {
		if ( ! in_array( $category, array( 'regular', 'referrals', 'seeds' ), true ) ) {
			throw new \DomainException( 'Unknown competition category.' ); }
		$rows = array_values( array_filter( $rows, static fn( $r ) => (int) $r['personal'] >= (int) $rules['monthly_min'] ) );
		foreach ( $rows as &$r ) {
			$r['score'] = 0;
			foreach ( self::RECIPES[ $category ] as $metric => $key ) {
				$r['score'] += (int) ( $r[ $metric ] ?? 0 ) * (int) ( $rules[ $key ] ?? 0 );
			}
		} unset( $r );
		$rows = array_values( array_filter( $rows, static fn( $r ) => $r['score'] > 0 ) );

		usort(
			$rows,
			static function ( $a, $b ) use ( $category, $rules ) {
				$score = $b['score'] <=> $a['score'];
				if ( $score ) {
					return $score; }
				$mode = $rules['tie_breaker'] ?? 'personal_time_id';
				if ( 'personal_time_id' === $mode ) {
					$value = 'referrals' === $category ? $b['referral_value'] <=> $a['referral_value'] : 0;
					if ( 0 === $value ) {
						$value = $b['personal'] <=> $a['personal']; }
					if ( $value ) {
								return $value; }
				}
				if ( 'member_id' !== $mode ) {
					$time_key = array(
						'regular'   => 'first_at',
						'referrals' => 'first_referral_at',
						'seeds'     => 'first_seeds_at',
					)[ $category ];
					$date     = strcmp( $a[ $time_key ] ?? $a['first_at'] ?? '', $b[ $time_key ] ?? $b['first_at'] ?? '' );
					if ( $date ) {
									return $date; }
				}
				return strcmp( $a['member_id'], $b['member_id'] );
			}
		);
		return $rows;
	}
	public static function live( string $month, string $category, int $limit = 20 ): array {
		global $wpdb;
		$month   = self::month( $month );
		$rules   = self::rules( $month );
		$version = hash( 'sha256', wp_json_encode( $rules ) );
		$metrics = array(
			'regular'   => array( 'personal', 'purchase_weight' ),
			'referrals' => array( 'conversions', 'referral_weight' ),
			'seeds'     => array( 'seeds', 'seeds_weight' ),
		);
		if ( ! isset( $metrics[ $category ] ) ) {
			throw new \DomainException( 'Unknown competition category.' ); }
		if ( $month < wp_date( 'Y-m' ) ) {
			$snapshot = $wpdb->get_var( $wpdb->prepare( 'SELECT r.rules FROM ' . ( $wpdb->prefix . 'ghr_monthly_results' ) . ' r JOIN ' . ( $wpdb->prefix . 'ghr_result_publications' ) . " p ON p.snapshot_id=r.id AND p.action='published' WHERE r.month=%s ORDER BY r.version DESC LIMIT 1", $month ) );
			$saved    = json_decode( $snapshot ?? '{}', true );
			if ( isset( $saved['rankings'][ $category ] ) ) {
				return array_slice( $saved['rankings'][ $category ], 0, min( 100, max( 1, $limit ) ) ); }
		}

		[ $metric, $weight ] = $metrics[ $category ];
		$score               = self::score_expression( $category, $rules );
		$tie                 = self::tie_expression( $category, $rules );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT m.member_id,' . $score . ' score,1 qualified FROM ' . ( $wpdb->prefix . 'ghr_monthly_scores' ) . ' s JOIN ' . ( $wpdb->prefix . 'ghr_memberships' ) . " m ON m.user_id=s.user_id WHERE s.month=%s AND s.rules_version=%s AND m.status='active' AND s.personal>=%d AND s." . $metric . ">0 AND $score>0 ORDER BY score DESC,$tie LIMIT %d", $month, $version, (int) $rules['monthly_min'], min( 100, max( 1, $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Columns, score recipes and tie clauses are fixed allowlists with integer weights.
	}
	public static function finalize( string $month, string $reason = '' ): int {
		$month = self::month( $month );
		if ( $month >= wp_date( 'Y-m' ) ) {
			throw new \DomainException( 'Finalize only a completed calendar month.' ); }
		global $wpdb;
		$lock = 'ghr_month_' . $month;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,10)', $lock ) ) ) {
			throw new \RuntimeException( 'Month is being finalized.' ); }
		try {
			$rows                       = self::prepare( $month );
			$rules                      = self::rules( $month );
			$entries                    = array();
			$snapshot_rules             = $rules;
			$snapshot_rules['rankings'] = array();
			$used                       = array();
			foreach ( array( 'regular', 'referrals', 'seeds' ) as $category ) {
				$ranked                                  = self::rank( $rows, $category, $rules );
				$snapshot_rules['rankings'][ $category ] = array_map(
					static fn( $r ) => array(
						'member_id' => $r['member_id'],
						'score'     => $r['score'],
						'qualified' => true,
					),
					$ranked
				);
				$slot                                    = 0;
				foreach ( $ranked as $r ) {
					if ( in_array( $r['user_id'], $used, true ) ) {
						continue;
					} $used[]  = $r['user_id'];
					$entries[] = array(
						'category'  => $category,
						'award'     => $slot ? 'magic_box' : 'top_prize',
						'user_id'   => (int) $r['user_id'],
						'member_id' => $r['member_id'],
						'score'     => $r['score'],
					);
					if ( 2 === ++$slot ) {
						break; }
				}
			}
			$t        = ( $wpdb->prefix . 'ghr_monthly_results' );
			$previous = $wpdb->get_row( $wpdb->prepare( "SELECT id,version FROM $t WHERE month=%s ORDER BY version DESC LIMIT 1", $month ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted prefixed monthly_results table; month is prepared.
			if ( $previous && ! trim( $reason ) ) {
				throw new \DomainException( 'Corrections require a reason and create a new version.' ); }
			$id = DB::insert(
				'monthly_results',
				array(
					'month'         => $month,
					'version'       => $previous ? (int) $previous['version'] + 1 : 1,
					'rules'         => wp_json_encode( $snapshot_rules ),
					'entries'       => wp_json_encode( $entries ),
					'digest'        => hash( 'sha256', wp_json_encode( array( $snapshot_rules, $entries ) ) ),
					'supersedes_id' => $previous['id'] ?? null,
					'actor'         => get_current_user_id(),
					'created_at'    => DB::now(),
				)
			);
			DB::audit( 'leaderboard.finalized', 'snapshot:' . $id, array( 'month' => $month ), $reason );
			return $id;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
	public static function publish( int $id, string $reason ): void {
		DB::transaction(
			static function () use ( $id, $reason ) {
				global $wpdb;
				$r = DB::row( 'monthly_results', $id );
				if ( ! $r ) {
					throw new \DomainException( 'Snapshot not found.' ); }
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_result_publications' ) . " WHERE snapshot_id=%d AND action='published'", $id ) ) ) {
					return; }
				$desired_entries = json_decode( $r['entries'], true );
				$desired_slots   = array();
				foreach ( $desired_entries as $entry ) {
					$desired_slots[ $entry['category'] . ':' . $entry['award'] ] = (int) $entry['user_id']; }
				$prior = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*,c.category FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . ' a JOIN ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . " c ON c.id=a.campaign_id WHERE c.month=%s AND a.slot LIKE %s AND a.state NOT IN ('revoked','expired') FOR UPDATE", $r['month'], 'monthly:' . $r['month'] . ':%' ), ARRAY_A );
				foreach ( $prior as $assignment ) {
					if ( ( $desired_slots[ $assignment['category'] ] ?? 0 ) === (int) $assignment['user_id'] ) {
						continue; }
					if ( ! in_array( $assignment['state'], array( 'unopened', 'opened' ), true ) ) {
						throw new \DomainException( 'A corrected award was already claimed or fulfilled. Resolve fulfillment manually before publication.' ); }
					Rewards::transition( (int) $assignment['user_id'], (int) $assignment['id'], 'revoke', $reason ? $reason : 'Published result correction' );
				}
				foreach ( $desired_entries as $entry ) {
					$campaign = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_reward_campaigns' ) . ' WHERE month=%s AND category=%s ORDER BY id LIMIT 1', $r['month'], $entry['category'] . ':' . $entry['award'] ) );
					if ( ! $campaign ) {
						throw new \DomainException( 'Configure a campaign for every selected category/award before publication.' ); }
					$logical_slot   = 'monthly:' . $r['month'] . ':' . $entry['category'] . ':' . $entry['award'] . ':';
					$existing_award = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( $wpdb->prefix . 'ghr_reward_assignments' ) . " WHERE user_id=%d AND slot LIKE %s AND state NOT IN ('revoked','expired') LIMIT 1", $entry['user_id'], $logical_slot . '%' ) );
					if ( ! $existing_award ) {
						Rewards::assign( (int) $entry['user_id'], (int) $campaign, $logical_slot . 'v' . $id ); }
					Notifications::emit( (int) $entry['user_id'], 'winner:' . $r['month'] . ':' . $entry['category'] . ':' . $entry['award'], 'monthly_prize_won' );
				}
				DB::insert(
					'result_publications',
					array(
						'snapshot_id' => $id,
						'action'      => 'published',
						'reason'      => $reason,
						'actor'       => get_current_user_id(),
						'created_at'  => DB::now(),
					)
				);
				DB::audit( 'leaderboard.published', 'snapshot:' . $id, array(), $reason );
				as_enqueue_async_action( 'ghr_broadcast', array( 'monthly_results_published', 'monthly-results:' . $id, 0 ), 'ghr' );
			}
		);
	}
	public static function winners( string $month = '' ): array {
		global $wpdb;
		$where = $month ? $wpdb->prepare( ' AND r.month=%s', self::month( $month ) ) : '';
		$rows  = $wpdb->get_results( 'SELECT r.*,p.created_at announced_at FROM ' . ( $wpdb->prefix . 'ghr_monthly_results' ) . ' r JOIN ' . ( $wpdb->prefix . 'ghr_result_publications' ) . " p ON p.snapshot_id=r.id AND p.action='published' WHERE 1=1 $where ORDER BY r.month DESC,r.version DESC LIMIT 24", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Optional month WHERE clause is prepared immediately above.
		$out   = array();
		$seen  = array();
		foreach ( $rows as $r ) {
			if ( isset( $seen[ $r['month'] ] ) ) {
				continue;
			} $seen[ $r['month'] ] = true;
			foreach ( json_decode( $r['entries'], true ) as $entry ) {
				$m = Membership::get( (int) $entry['user_id'] );
				if ( ! $m || ! $m['public_consent'] ) {
					continue;
				} $profile                    = get_userdata( (int) $entry['user_id'] );
				$entry['winner_display_name'] = $profile ? mb_substr( sanitize_text_field( $profile->display_name ), 0, 1, 'UTF-8' ) . '***' : $entry['member_id'];
					unset( $entry['user_id'] );
				$entry['month']        = $r['month'];
				$entry['version']      = $r['version'];
				$entry['announced_at'] = $r['announced_at'];
				$out[]                 = $entry;
			}
		} return $out;
	}
}
