<?php
namespace GhoroaHat\Rewards;

final class Calculator {
	public static function eligible_product( $product, array $policy ): bool {
		if ( ! $product ) {
			return false; }
		$ids = array( $product->get_id(), $product->get_parent_id() );
		if ( array_intersect( $ids, $policy['excluded_products'] ) || array_intersect( $product->get_category_ids(), $policy['excluded_categories'] ) ) {
			return false; }
		if ( $product->get_parent_id() && array_intersect( wc_get_product_term_ids( $product->get_parent_id(), 'product_cat' ), $policy['excluded_categories'] ) ) {
			return false; }
		return (bool) apply_filters( 'ghr_product_eligible', true, $product, $policy );
	}
	public static function order( \WC_Order $order, array $policy ): array {
		$base        = 0;
		$bonus       = false;
		$lines       = array();
		$item_policy = (array) $order->get_meta( '_ghr_item_policy' );
		foreach ( $order->get_items( 'line_item' ) as $id => $item ) {
			$p         = $item->get_product();
			$signature = $item->get_product_id() . ':' . $item->get_variation_id();
			if ( ! isset( $item_policy[ $id ] ) || $item_policy[ $id ]['signature'] !== $signature ) {
				$item_policy[ $id ] = array(
					'signature' => $signature,
					'eligible'  => self::eligible_product( $p, $policy ),
					'bonus'     => (bool) array_intersect( array( $item->get_product_id(), $item->get_variation_id() ), $policy['bonus_products'] ),
				);
			}
			if ( ! $item_policy[ $id ]['eligible'] ) {
				continue; }
			$original = max( 0, Money::minor( (string) $item->get_total() ) );
			$refunded = abs( Money::minor( (string) $order->get_total_refunded_for_item( $id ) ) );
			foreach ( (array) $order->get_meta( '_ghr_manual_refunds' ) as $reviewed ) {
				$refunded += (int) ( $reviewed[ $id ] ?? 0 ); }
			$remaining    = max( 0, $original - $refunded );
			$base        += $remaining;
			$lines[ $id ] = array(
				'original'  => $original,
				'remaining' => $remaining,
				'refunded'  => $refunded,
			);
			if ( $item_policy[ $id ]['bonus'] && $remaining > 0 && 0 === $refunded ) {
				$bonus = true; }
		}
		return array(
			'eligible'    => $base,
			'bonus'       => $bonus,
			'lines'       => $lines,
			'item_policy' => $item_policy,
		);
	}
	public static function cart(): array {
		$policy  = Settings::all();
		$lines   = array();
		$taxable = false;
		if ( ! WC()->cart ) {
			throw new \DomainException( 'WooCommerce cart unavailable.' ); }
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! self::eligible_product( $item['data'], $policy ) ) {
				continue; }
			$lines[ $key ] = max( 0, Money::minor( (string) $item['line_total'] ) );
			if ( $item['data']->is_taxable() && wc_tax_enabled() ) {
				$taxable = true; }
		}
		return array(
			'eligible' => array_sum( $lines ),
			'lines'    => $lines,
			'taxable'  => $taxable,
		);
	}
	public static function redeemable( string $wallet, int $balance, int $subtotal ): int {
		if ( ! in_array( $wallet, Settings::get( 'checkout_wallets' ), true ) || ! Settings::get( $wallet . '_enabled' ) || $subtotal < (int) Settings::get( 'min_eligible' ) ) {
			return 0; }
		$capacity = max( 0, $subtotal - (int) Settings::get( 'minimum_payable' ) );
		return match ( $wallet ) {
			'trust' => $subtotal >= Settings::get( 'trust_min_order' ) ? min( $balance, $capacity ) : 0,
			'referral' => $balance >= Settings::get( 'referral_min' ) ? min( $balance, $capacity ) : 0,
			'seeds' => $balance >= Settings::get( 'seeds_min' ) && $subtotal >= Settings::get( 'seeds_min_order' ) ? min( $balance, (int) Settings::get( 'seeds_max' ), intdiv( $capacity, 100 ) ) : 0,
			default => 0,
		};
	}
}
