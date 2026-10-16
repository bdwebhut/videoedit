<?php
use GhoroaHat\Rewards\{Membership,DB,Wallet};
add_filter( 'pre_wp_mail', '__return_true' );
global $wpdb;
$run = 'race' . bin2hex(random_bytes(6));
$user = wp_insert_user(array('user_login'=>$run,'user_email'=>$run.'@example.org','user_pass'=>wp_generate_password(),'role'=>'customer'));
Membership::enroll($user); $wpdb->update(DB::table('memberships'),array('email_verified_at'=>DB::now(),'status'=>'active','activated_at'=>DB::now()),array('user_id'=>$user));
Wallet::post($user,'trust',10000,$run,'adjustment',$run,0,'Concurrent spend test fixture');
update_option('ghr_race_context',array('user'=>$user,'run'=>$run,'start'=>microtime(true)+3),false);
echo "Race prepared\n";
