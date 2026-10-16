#!/usr/bin/env python3
"""Sequential integration runner for an isolated Docker WordPress test site."""
import argparse,json,pathlib,re,subprocess,time,sys
parser=argparse.ArgumentParser();parser.add_argument('--container',default='ghr-wp');parser.add_argument('--output',required=True);args=parser.parse_args()
out=pathlib.Path(args.output);out.mkdir(parents=True,exist_ok=True)
plugin='/var/www/html/wp-content/plugins/ghoroa-hat-rewards'
base=['docker','exec',args.container,'php','/usr/local/bin/wp','--allow-root']
subprocess.run(base+['eval','GhoroaHat\\Rewards\\Installation::migrate();'],check=True)
versions=subprocess.run(base+['eval','echo wp_json_encode(array("wordpress"=>get_bloginfo("version"),"woocommerce"=>WC_VERSION,"php"=>PHP_VERSION,"hpos"=>Automattic\\WooCommerce\\Utilities\\OrderUtil::custom_orders_table_usage_is_enabled(),"database"=>$GLOBALS["wpdb"]->db_server_info()));'],capture_output=True,text=True,check=True).stdout
results=[]
for name in ['unit','integration','checkout','advanced','referrals','expiry','competition','reconciliation','shortcodes','sms','lifecycle']:
 cmd=['docker','exec',args.container,'php',plugin+'/tests/unit.php'] if name=='unit' else base+['eval-file',plugin+'/tests/'+name+'.php']
 start=time.monotonic();run=subprocess.run(cmd,capture_output=True,text=True);text=run.stdout+run.stderr;(out/(name+'.log')).write_text(text)
 match=re.findall(r'(?:PASS:\s*|PASS\s+)(\d+)\s+(?:assertions|money)',text);count=int(match[-1]) if match else None
 warnings=bool(re.search(r'(?:PHP Warning:|PHP Fatal error:|WordPress database error|Warning: Undefined)',text))
 passed=run.returncode==0 and not warnings and count is not None
 item={'suite':name,'passed':passed,'assertions':count,'elapsed_seconds':round(time.monotonic()-start,3),'exit_code':run.returncode,'warnings':warnings};results.append(item);print(json.dumps(item),flush=True)
 if not passed:
  print(text[-7000:],file=sys.stderr);break
payload={'versions':json.loads(versions),'results':results,'passed':len(results)==11 and all(r['passed'] for r in results),'total_assertions':sum(r['assertions'] or 0 for r in results)}
(out/'results.json').write_text(json.dumps(payload,indent=2));sys.exit(0 if payload['passed'] else 1)
