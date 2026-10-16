#!/usr/bin/env python3
"""Native WordPress deactivate/reactivate preservation check on an isolated site."""
import json,subprocess,sys
container=sys.argv[1] if len(sys.argv)>1 else 'ghr-wp-min'
base=['docker','exec',container,'php','/usr/local/bin/wp','--allow-root']
def wp(*args):return subprocess.run(base+list(args),text=True,capture_output=True,check=True).stdout.strip()
query='global $wpdb; echo wp_json_encode(array("members"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".GhoroaHat\\Rewards\\DB::table("memberships")),"ledger"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".GhoroaHat\\Rewards\\DB::table("wallet_transactions")),"pages"=>get_option("ghr_pages"),"sequence"=>get_option("ghr_member_sequence")));'
before=json.loads(wp('eval',query))
wp('plugin','deactivate','ghoroa-hat-rewards');wp('plugin','activate','ghoroa-hat-rewards')
after=json.loads(wp('eval',query));assert before==after,(before,after)
print('PASS native deactivate/reactivate preserves members, ledger entries, page mappings and permanent ID sequence')
# WooCommerce resets its shared AS migration flag on plugin deactivation.
print(wp('action-scheduler','migrate'))
# Run a specifically queued score action through the real Action Scheduler CLI worker.
action=int(wp('eval','echo as_enqueue_async_action("ghr_score_refresh",array("2098-12"),"ghr");'))
print(wp('action-scheduler','run','--hooks=ghr_score_refresh','--group=ghr','--batch-size=100','--batches=1'))
state=wp('eval',f'echo ActionScheduler::store()->get_status({action});')
assert state=='complete',state
print('PASS native Action Scheduler CLI executes the queued monthly score job')
