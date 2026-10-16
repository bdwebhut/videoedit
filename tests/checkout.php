<?php
use GhoroaHat\Rewards\{DB,Settings,Membership,Wallet,Checkout,Orders,Money};
add_filter( 'pre_wp_mail', '__return_true' );
global $wpdb;
global $count; $count=0;
function verify_checkout($actual,$expected,$message){global $count;++$count;if($actual!==$expected){throw new RuntimeException($message.': '.var_export($actual,true).' != '.var_export($expected,true));}echo "PASS $message\n";}
$old_country=get_option('woocommerce_default_country');update_option('woocommerce_default_country','BD');$old_settings=Settings::all();$taxes=get_option('woocommerce_calc_taxes');$inc=get_option('woocommerce_prices_include_tax');$taxbase=get_option('woocommerce_tax_based_on');
$run='checkout'.bin2hex(random_bytes(5));$uid=wp_insert_user(array('user_login'=>$run,'user_email'=>$run.'@example.org','user_pass'=>wp_generate_password(),'role'=>'customer'));Membership::enroll($uid);$wpdb->update(DB::table('memberships'),array('status'=>'active','email_verified_at'=>DB::now(),'activated_at'=>DB::now()),array('user_id'=>$uid));
wp_set_current_user($uid);WC()->initialize_session();WC()->initialize_cart();WC()->customer=new WC_Customer($uid);WC()->customer->set_billing_country('BD');WC()->customer->set_shipping_country('BD');WC()->customer->set_calculated_shipping(true);
Wallet::post($uid,'trust',100000,$run,'adjustment',$run,0,'Checkout fixture');
try{
Settings::save(array('excluded_products'=>array(),'excluded_categories'=>array(),'trust_min_order'=>100000));
$p=new WC_Product_Simple();$p->set_name('Checkout integration');$p->set_regular_price('1200');$p->set_virtual(true);$p->set_tax_status('none');$p->save();
WC()->cart->empty_cart();WC()->session->__unset('ghr_reservation');WC()->cart->add_to_cart($p->get_id());WC()->cart->calculate_totals();
$state=Checkout::select($uid,array('wallet'=>'trust','units'=>10000,'intent'=>$run.'-first-checkout'));
verify_checkout(Money::minor((string)WC()->cart->get_total('edit')),110000,'Native WooCommerce cart credit total');verify_checkout(Wallet::account($uid,'trust')['reserved'],10000,'Checkout credit reserved');
$order_id=WC()->checkout()->create_order(array('billing_first_name'=>'Test','billing_last_name'=>'Member','billing_email'=>get_userdata($uid)->user_email,'billing_country'=>'BD','payment_method'=>'cod'));
if(is_wp_error($order_id)){throw new RuntimeException($order_id->get_error_message());}$order=wc_get_order($order_id);Checkout::placed($order);
verify_checkout((int)$order->get_meta('_ghr_credit_units'),10000,'Credit recorded through WooCommerce CRUD');verify_checkout(array_sum((array)$order->get_meta('_ghr_credit_allocation')),10000,'Exact order item credit allocation');verify_checkout(Wallet::account($uid,'trust')['posted'],90000,'Placed COD order debits wallet');
$order->update_status('completed');Orders::reconcile($order_id);verify_checkout(Wallet::account($uid,'seeds')['posted'],11,'Redeemed credit excluded from earning basis');
$order->update_status('cancelled');Orders::reconcile($order_id);verify_checkout(Wallet::account($uid,'trust')['posted'],100000,'Cancelled order restores credit');Orders::reconcile($order_id);verify_checkout(Wallet::account($uid,'trust')['posted'],100000,'Repeated cancellation cannot duplicate credit');
// Late payment retries require reviewed, idempotent settlement rather than free reused credits.
verify_checkout(in_array('failed',apply_filters('woocommerce_valid_order_statuses_for_payment',array('pending','failed'),$order),true),false,'Released credit cannot be repaid through an old order');
$order->update_status('processing');$reviewed=false;try{Orders::reconcile($order_id);}catch(DomainException $e){$reviewed=true;}verify_checkout($reviewed,true,'Late payment enters explicit credit review');
$new_reservation=Checkout::resolve_released_order($order_id,'Verified late COD acceptance');verify_checkout(Wallet::account($uid,'trust')['posted'],90000,'Reviewed late payment re-debits the actual credit');verify_checkout(Checkout::resolve_released_order($order_id,'Retry settlement'),$new_reservation,'Repeated settlement is idempotent');
GhoroaHat\Rewards\Membership::status($uid,'active','Late payment review resolved');$order=wc_get_order($order_id);$order->update_status('cancelled');Orders::reconcile($order_id);verify_checkout(Wallet::account($uid,'trust')['posted'],100000,'Settled late-order cancellation restores replacement credit once');
// Test tax-exclusive and tax-inclusive prices using WooCommerce's tax engine.
$rate=WC_Tax::_insert_tax_rate(array('tax_rate_country'=>'BD','tax_rate_state'=>'','tax_rate'=>'10.0000','tax_rate_name'=>'Test VAT','tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>0,'tax_rate_order'=>0,'tax_rate_class'=>''));
update_option('woocommerce_calc_taxes','yes');update_option('woocommerce_tax_based_on','billing');
foreach(array('no','yes') as $includes){
 update_option('woocommerce_prices_include_tax',$includes);WC_Cache_Helper::invalidate_cache_group('taxes');
 $p->set_regular_price('yes'===$includes?'1320':'1200');$p->set_tax_status('taxable');$p->save();
 WC()->session->__unset('ghr_reservation');WC()->session->__unset('ghr_coupon_amount');WC()->cart->empty_cart();WC()->cart->add_to_cart($p->get_id());WC()->cart->calculate_totals();
 $state=Checkout::select($uid,array('wallet'=>'trust','units'=>10000,'intent'=>$run.'-tax-'.$includes));
 verify_checkout(Money::minor((string)WC()->cart->get_discount_total()),10000,'Exact net product credit with tax prices '.$includes);
 verify_checkout(Money::minor((string)WC()->cart->get_cart_contents_tax()),11000,'WooCommerce recalculates statutory tax '.$includes);
 verify_checkout(Money::minor((string)WC()->cart->get_total('edit')),121000,'Tax order total '.$includes);
 Checkout::select($uid,array('remove'=>true));
}
WC_Tax::_delete_tax_rate($rate);
// Excluded product remains undiscounted in a mixed cart.
update_option('woocommerce_calc_taxes','no');update_option('woocommerce_prices_include_tax','no');$p->set_tax_status('none');$p->set_regular_price('1200');$p->save();$excluded=new WC_Product_Simple();$excluded->set_name('Excluded');$excluded->set_regular_price('100');$excluded->set_tax_status('none');$excluded->set_virtual(true);$excluded->save();Settings::save(array('excluded_products'=>array($excluded->get_id())));
WC()->cart->empty_cart();WC()->session->__unset('ghr_reservation');WC()->cart->add_to_cart($p->get_id());$excluded_key=WC()->cart->add_to_cart($excluded->get_id());WC()->cart->calculate_totals();Checkout::select($uid,array('wallet'=>'trust','units'=>10000,'intent'=>$run.'-excluded'));
verify_checkout(Money::minor((string)WC()->cart->get_cart_item($excluded_key)['line_total']),10000,'Excluded product receives no wallet discount');Checkout::select($uid,array('remove'=>true));
echo "CHECKOUT PASS: $count assertions\n";
}finally{WC()->cart->empty_cart();WC()->session->__unset('ghr_reservation');update_option('ghr_settings',$old_settings,false);update_option('woocommerce_default_country',$old_country);if(isset($rate)){WC_Tax::_delete_tax_rate($rate);}update_option('woocommerce_calc_taxes',$taxes);update_option('woocommerce_prices_include_tax',$inc);update_option('woocommerce_tax_based_on',$taxbase);}
