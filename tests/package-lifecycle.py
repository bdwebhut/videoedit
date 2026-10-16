#!/usr/bin/env python3
"""Destructive lifecycle test: ONLY the disposable ghr-wp-install ZIP-test container."""
import json,subprocess
base=['docker','exec','ghr-wp-install','php','/fixtures/wp-cli.phar','--allow-root','--user=1']
def wp(*args):
 p=subprocess.run(base+list(args),text=True,capture_output=True)
 if p.returncode:raise RuntimeError(p.stdout+p.stderr)
 return p.stdout.strip()
# This assertion deliberately prevents use against another site.
assert wp('option','get','blogname')=='Ghoroa Hat Package Smoke'
seed=json.loads(wp('eval','add_filter("pre_wp_mail","__return_true"); $u=wp_create_user("package_".bin2hex(random_bytes(4)),wp_generate_password(),"fixture_".bin2hex(random_bytes(4))."@example.org"); GhoroaHat\\Rewards\\Membership::enroll($u); GhoroaHat\\Rewards\\Wallet::post($u,"trust",1000,"package-fixture","adjustment","package-fixture",0,"Isolated lifecycle fixture");$o=wc_create_order(array("customer_id"=>$u));echo wp_json_encode(array("user"=>$u,"order"=>$o->get_id(),"sequence"=>get_option("ghr_member_sequence"),"pages"=>get_option("ghr_pages")));'))
wp('plugin','uninstall','ghoroa-hat-rewards','--deactivate')
remaining=json.loads(wp('eval','global $wpdb;echo wp_json_encode(array("members"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ghr_memberships"),"ledger"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ghr_wallet_transactions")));'))
assert remaining=={'members':1,'ledger':1},remaining
print('PASS ordinary WordPress uninstall preserves membership and financial records')
wp('plugin','install','/release/ghoroa-hat-rewards-1.0.0-rc.1.zip','--activate')
restored=json.loads(wp('eval','echo wp_json_encode(array("sequence"=>get_option("ghr_member_sequence"),"pages"=>get_option("ghr_pages")));'))
assert restored['sequence']==seed['sequence'] and restored['pages']==seed['pages']
print('PASS ZIP reinstall retains permanent ID sequence and existing page mappings')
wp('eval','$p=GhoroaHat\\Rewards\\Removal::preview();GhoroaHat\\Rewards\\Removal::confirm($p["ticket"],$p["confirmation"],"BACKUP VERIFIED");')
wp('plugin','uninstall','ghoroa-hat-rewards','--deactivate')
check=json.loads(wp('eval',f'global $wpdb;echo wp_json_encode(array("user"=>(bool)get_userdata({seed["user"]}),"order"=>(bool)wc_get_order({seed["order"]}),"tables"=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s",$wpdb->esc_like($wpdb->prefix."ghr_")."%")),"sequence"=>get_option("ghr_member_sequence")));'))
assert check['user'] and check['order'] and check['tables']==0 and check['sequence']==seed['sequence'],check
print('PASS separately confirmed removal deletes only plugin tables and retains WordPress users, WooCommerce orders and non-reused ID sequence')
