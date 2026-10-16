<?php
require_once dirname( __DIR__ ) . '/src/class-money.php';
use GhoroaHat\Rewards\Money;
$count = 0;
function check( $actual, $expected, $message ) { global $count; ++$count; if ( $actual !== $expected ) { throw new RuntimeException( $message . ': ' . var_export( $actual, true ) . ' !== ' . var_export( $expected, true ) ); } }
check( Money::minor( '999' ), 99900, 'Whole decimal' );
check( Money::minor( '0.105' ), 11, 'Half-up rounding' );
check( Money::minor( '-9.999' ), -1000, 'Signed rounding' );
check( Money::decimal( -105 ), '-1.05', 'Format signed minor units' );
check( Money::percent( 100000, 200 ), 2000, '2 percent referral' );
check( Money::seeds( 100000, 100 ), 10, '1 percent whole Seeds' );
check( Money::seeds( 99900, 100 ), 9, 'Seeds round down' );
check( Money::allocate( 1000000000000, array( 1000000000000, 2000000000000 ) ), array( 333333333333, 666666666667 ), 'Large allocation avoids integer multiplication overflow' );
check( Money::allocate( 2, array( 1, 1, 1 ) ), array( 1, 1, 0 ), 'Deterministic remainder' );
for ( $i = 0; $i < 100; $i++ ) {
    $weights = array( random_int( 1, 100000 ), random_int( 1, 100000 ), random_int( 1, 100000 ) );
    $amount = random_int( 0, array_sum( $weights ) ); $parts = Money::allocate( $amount, $weights );
    check( array_sum( $parts ), $amount, 'Allocation conservation' );
    check( count( array_filter( $parts, static fn( $v ) => $v < 0 ) ), 0, 'No negative allocation' );
}
foreach ( array( '1e3', 'NaN', '1,000', '<script>', '1.2.3' ) as $invalid ) { try { Money::minor( $invalid ); throw new RuntimeException( 'Invalid amount accepted' ); } catch ( InvalidArgumentException $e ) { ++$count; } }
echo "PASS $count money/allocation assertions\n";
