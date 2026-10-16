<?php
/** Synthetic SQL aggregate benchmark. Inserts are rolled back; no payments or messages. */
use GhoroaHat\Rewards\{DB,Leaderboard};
global $wpdb;$month='2098-11';$size=10000;$base=random_int(4000000000,5000000000);$rules=get_option('ghr_rules_'.$month);$updated=get_option('ghr_scores_updated_'.$month);$result=array();
try{
DB::transaction(static function()use($wpdb,$month,$size,$base,&$result){
for($start=0;$start<$size;$start+=500){$members=array();$effects=array();for($i=$start;$i<min($size,$start+500);$i++){$id=$base+$i;$members[]=$wpdb->prepare("(%d,%s,%s,'active',%s,%s)",$id,'SYN-'.$id,'SYN'.$id,DB::now(),DB::now());$effects[]=$wpdb->prepare("(%d,'personal',%d,'seeds',10,10,100000,'{}','2098-11-15 12:00:00',%s)",$id,$id,DB::now());}DB::query('INSERT INTO '.DB::table('memberships').' (user_id,member_id,referral_code,status,registered_at,email_verified_at) VALUES '.implode(',',$members));DB::query('INSERT INTO '.DB::table('order_effects').' (order_id,effect_key,user_id,wallet,desired,posted,eligible,policy,created_at,updated_at) VALUES '.implode(',',$effects));}
$memory=memory_get_usage(true);$time=microtime(true);$rows=Leaderboard::prepare($month);$result=array('synthetic_members'=>$size,'synthetic_order_effects'=>$size,'elapsed_seconds'=>round(microtime(true)-$time,4),'php_memory_delta_bytes'=>memory_get_usage(true)-$memory,'candidate_rows'=>count($rows),'score_rows'=>(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.DB::table('monthly_scores').' WHERE month=%s',$month)));if($result['score_rows']!==$size||count($rows)>300||!count($rows)){throw new RuntimeException('Aggregate benchmark failed');}throw new DomainException('ROLLBACK SYNTHETIC BENCHMARK');
});
}catch(DomainException $e){if($e->getMessage()!=='ROLLBACK SYNTHETIC BENCHMARK'){throw $e;}}
finally{foreach(array('ghr_rules_'.$month=>$rules,'ghr_scores_updated_'.$month=>$updated)as$key=>$old){if(false===$old){delete_option($key);}else{update_option($key,$old,false);}}}
if((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.DB::table('memberships').' WHERE user_id BETWEEN %d AND %d',$base,$base+$size-1))!==0){throw new RuntimeException('Benchmark did not roll back');}
echo wp_json_encode($result,JSON_PRETTY_PRINT)."\nPERFORMANCE PASS: 3 assertions (synthetic aggregates; rolled back)\n";
