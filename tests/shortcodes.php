<?php
use GhoroaHat\Rewards\{DB,Membership,Wallet,Frontend};
global $ghr_shortcode_count;$ghr_shortcode_count=0;
function shortcode_check($ok,$message){global $ghr_shortcode_count;++$ghr_shortcode_count;if(!$ok){throw new RuntimeException($message);}echo "PASS $message\n";}
$run='components'.bin2hex(random_bytes(4));$u=wp_insert_user(array('user_login'=>$run,'user_email'=>$run.'@example.org','user_pass'=>wp_generate_password()));Membership::enroll($u);DB::update(DB::table('memberships'),array('status'=>'active','email_verified_at'=>DB::now(),'activated_at'=>DB::now()),array('user_id'=>$u));Wallet::post($u,'trust',10000,$run,'adjustment',$run,0,'Shortcode fixture');wp_set_current_user($u);
foreach(Frontend::SHORTCODES as$name){$html=do_shortcode('[ghr_'.$name.']');shortcode_check(is_string($html)&&strlen($html)>0,'Owner component renders '.$name);shortcode_check(!str_contains($html,'role="alert"'),'No component error '.$name);}
$card=do_shortcode('[ghr_membership_card]');shortcode_check(substr_count($card,'ghr-member')===1&&!str_contains($card,'monthly_purchase'),'Membership card stays compact');$balance=do_shortcode('[ghr_trust_wallet_balance currency="code" label="Balance"]');shortcode_check(str_contains($balance,'BDT'),'Approved currency attribute');$unsafe=do_shortcode('[ghr_trust_wallet_balance label="<script>alert(1)</script>"]');shortcode_check(!str_contains($unsafe,'<script>'),'Shortcode attributes cannot inject scripts');
$id=Membership::get($u)['member_id'];wp_set_current_user(0);foreach(Frontend::SHORTCODES as$name){$html=do_shortcode('[ghr_'.$name.']');shortcode_check(!str_contains($html,$id),'Anonymous component never exposes owner '.$name);}
echo "SHORTCODES PASS: $ghr_shortcode_count assertions\n";
