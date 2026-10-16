<?php
namespace GhoroaHat\Rewards;

final class Money {
	public static function minor( string $decimal ): int {
		if ( ! preg_match( '/^(-?)([0-9]{1,12})(?:\.([0-9]+))?$/D', trim( $decimal ), $m ) ) {
			throw new \InvalidArgumentException( 'Invalid decimal amount.' ); }
		$fraction = str_pad( $m[3] ?? '', 3, '0' );
		$n        = (int) $m[2] * 100 + (int) substr( $fraction, 0, 2 ) + ( (int) $fraction[2] >= 5 ? 1 : 0 );
		return '-' === $m[1] ? -$n : $n;
	}
	public static function decimal( int $minor ): string {
		return ( $minor < 0 ? '-' : '' ) . intdiv( abs( $minor ), 100 ) . '.' . str_pad( (string) ( abs( $minor ) % 100 ), 2, '0', STR_PAD_LEFT ); }
	public static function percent( int $minor, int $bps ): int {
		return intdiv( $minor * $bps + 5000, 10000 ); }
	public static function seeds( int $minor, int $bps ): int {
		return intdiv( $minor * $bps, 1000000 ); }
	public static function allocate( int $amount, array $weights ): array {
		$sum = array_sum( $weights );
		$out = array_fill_keys( array_keys( $weights ), 0 );
		if ( $amount < 0 || $amount > $sum ) {
			throw new \DomainException( 'Invalid allocation.' ); }
		if ( ! $sum ) {
			return $out; }
		$rem = array();
		foreach ( $weights as $key => $weight ) {
			[ $out[ $key ], $rem[ $key ] ] = self::multiply_divide( $amount, $weight, $sum ); }
		arsort( $rem, SORT_NUMERIC );
		$left = $amount - array_sum( $out );
		foreach ( $rem as $key => $_ ) {
			if ( ! $left-- ) {
				break;
			} ++$out[ $key ]; }
		return $out;
	}    /** Exact multiplication/division without intermediate floating-point overflow. */
	private static function multiply_divide( int $a, int $b, int $divisor ): array {
		if ( $a < 0 || $b < 0 || $divisor <= 0 || $divisor > intdiv( PHP_INT_MAX, 3 ) ) {
			throw new \DomainException( 'Amount exceeds the supported allocation range.' ); }
		$quotient  = 0;
		$remainder = 0;
		foreach ( str_split( decbin( $b ) ) as $bit ) {
			$quotient  *= 2;
			$remainder  = $remainder * 2 + ( '1' === $bit ? $a : 0 );
			$quotient  += intdiv( $remainder, $divisor );
			$remainder %= $divisor;
		}
		return array( $quotient, $remainder );
	}
}
