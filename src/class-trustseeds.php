<?php
namespace GhoroaHat\Rewards;

/** Personal purchase rewards; referred purchases never enter this calculator. */
final class TrustSeeds {
	public static function desired( bool $earning, int $eligible, array $policy ): int {
		return $earning && $policy['seeds_enabled'] ? Money::seeds( $eligible, $policy['seeds_bps'] ) : 0; }
}
