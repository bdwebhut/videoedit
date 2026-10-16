<?php
use GhoroaHat\Rewards\Wallet;
$c=get_option('ghr_race_context'); $wait=$c['start']-microtime(true); if($wait>0){usleep((int)($wait*1000000));}
try{Wallet::reserve($c['user'],'trust',6000,$c['run'].'-worker-'.getenv('GHR_WORKER'),hash('sha256','race-cart')); echo "RESERVED\n";}
catch(DomainException $e){echo 'REJECTED: '.$e->getMessage()."\n";}
