<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Database;
use InaudisVote\Services\CensusTemplateService;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;
use InaudisVote\Services\UserService;
function check(bool $condition,string $label):void {if(!$condition)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label.PHP_EOL;}
$pdo=Database::connection();$suffix=bin2hex(random_bytes(5));$org=0;$user=0;$election=0;
try{
 $org=OrganizationService::create(['name'=>'Census '.$suffix,'slug'=>'census-'.$suffix]);
 $fields=CensusTemplateService::defaults();$fields[]=['key'=>'member_number','label'=>'Número de socio','type'=>'text','required'=>true];
 CensusTemplateService::save('organization',$org,$fields);
 $user=UserService::create(['name'=>'Census Admin','email'=>'census-'.$suffix.'@inaudis.local','password'=>'Smoke2026!','role'=>'organizer','organization_id'=>$org]);
 $election=ElectionService::create(['organization_id'=>$org,'title'=>'Census '.$suffix],$user);
 check(count(CensusTemplateService::election($election))===7,'template inherited');
 $headers=implode(';',array_column($fields,'key'));
 $csv=$headers."\n".'12345678-5;Ana;census-a-'.$suffix.'@example.cl;;1;;SOC-1'."\n";
 $preview=CensusService::previewCsv($election,$csv);
 check($preview['valid']===1&&!$preview['errors'],'preview validates configured fields');
 $result=CensusService::importCsv($election,$csv);
 check($result['created']===1&&!$result['errors'],'imports configured fields');
 $members=CensusService::list($election);
 check(($members[0]['custom_values']['member_number']??'')==='SOC-1','custom values visible');
 $duplicate=CensusService::importCsv($election,$csv);
 check($duplicate['created']===0&&count($duplicate['errors'])===1,'duplicate prevents import');
 try{CensusTemplateService::save('election',$election,$fields);check(false,'template locked after import');}
 catch(RuntimeException){check(true,'template locked after import');}
 echo "CENSUS TEMPLATE REGRESSION: PASS\n";
}finally{
 if($election)$pdo->prepare('DELETE FROM elections WHERE id=?')->execute([$election]);
 if($user)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user]);
 if($org)$pdo->prepare('DELETE FROM organizations WHERE id=?')->execute([$org]);
}
