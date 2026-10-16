<?php
/**
 * Plugin Name: Ghoroa Hat Smart Membership & Rewards
 * Description: Membership, separate reward wallets, referrals and monthly rewards for WooCommerce.
 * Version: 1.0.0-rc.1
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 9.6
 * Author: BD WEBHUT PRIVATE LIMITED
 * Text Domain: ghoroa-hat-rewards
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>Ghoroa Hat Rewards requires PHP 8.2 or newer.</p></div>';
		}
	);
	return; }
define( 'GHR_VERSION', '1.0.0-rc.1' );
define( 'GHR_FILE', __FILE__ );
define( 'GHR_PATH', plugin_dir_path( __FILE__ ) );
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'GhoroaHat\\Rewards\\';
		if ( str_starts_with( $class_name, $prefix ) ) {
			$file = GHR_PATH . 'src/class-' . strtolower( str_replace( '\\', '-', substr( $class_name, strlen( $prefix ) ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file; }
		}
	}
);
register_activation_hook( __FILE__, array( GhoroaHat\Rewards\Installation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( GhoroaHat\Rewards\Installation::class, 'deactivate' ) );
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GHR_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GHR_FILE, true );
		}
	}
);
add_action(
	'plugins_loaded',
	static function () {
		if ( version_compare( get_bloginfo( 'version' ), '6.7', '<' ) || ! class_exists( 'WooCommerce' ) || version_compare( WC_VERSION, '9.6', '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Ghoroa Hat Rewards requires WooCommerce 9.6 or newer.', 'ghoroa-hat-rewards' ) . '</p></div>';
				}
			);
			return;
		}
		GhoroaHat\Rewards\Plugin::instance()->boot();
	}
);
