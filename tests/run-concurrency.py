#!/usr/bin/env python3
"""Run against the documented local Docker WordPress test container."""
import subprocess, concurrent.futures
base=['docker','exec','ghr-wp','php','/usr/local/bin/wp','--allow-root','eval-file']
path='/var/www/html/wp-content/plugins/ghoroa-hat-rewards/tests/'
subprocess.run(base+[path+'concurrency-prepare.php'],check=True)
def worker(number):
 return subprocess.run(['docker','exec','-e','GHR_WORKER='+str(number),'ghr-wp','php','/usr/local/bin/wp','--allow-root','eval-file',path+'concurrency-worker.php'],text=True,capture_output=True,check=True).stdout
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool: results=list(pool.map(worker,[1,2]))
assert sum('RESERVED' in r for r in results)==1,results
assert sum('REJECTED: Insufficient' in r for r in results)==1,results
print('PASS real concurrent database workers: one reservation committed, one overspend rejected')
