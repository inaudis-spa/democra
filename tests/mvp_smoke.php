<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Database;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;
use InaudisVote\Services\ResultService;
use InaudisVote\Services\UserService;
use InaudisVote\Services\VotingService;

function ok(bool $condition,string $message): void{if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");throw new RuntimeException($message);}fwrite(STDOUT,"PASS: {$message}\n");}
$pdo=Database::connection();$suffix=bin2hex(random_bytes(4));$organizationId=0;$organizerId=0;$electionId=0;
try{
    $organizationId=OrganizationService::create(['name'=>'Smoke '.$suffix,'slug'=>'smoke-'.$suffix]);
    $organizerId=UserService::create(['name'=>'Smoke Organizer','email'=>'smoke-'.$suffix.'@inaudis.local','password'=>'Smoke2026!','role'=>'organizer','organization_id'=>$organizationId]);
    $electionId=ElectionService::create(['organization_id'=>$organizationId,'title'=>'Smoke '.$suffix,'voting_mode'=>'secret'],$organizerId);
    $questionId=ElectionService::addQuestion($electionId,['text'=>'¿Aprueba?','options'=>"Sí\nNo",'question_type'=>'single']);
    $optionId=(int)$pdo->query('SELECT id FROM options WHERE question_id='.(int)$questionId.' ORDER BY id LIMIT 1')->fetchColumn();
    $memberId=CensusService::add($electionId,['name'=>'Votante Smoke','email'=>'voter-'.$suffix.'@inaudis.local','vote_weight'=>1]);
    $lockedMemberId=CensusService::add($electionId,['name'=>'Votante Lock','email'=>'lock-'.$suffix.'@inaudis.local','vote_weight'=>1]);
    ElectionService::setStatus($electionId,'open');
    $invitation=CensusService::generateInvitation($electionId,$memberId);ok(strlen($invitation['otp'])===6,'OTP tiene 6 dígitos');
    $context=VotingService::resolveToken($invitation['token']);ok($context!==null,'token resoluble');ok(!VotingService::canVote($context),'no permite votar antes del OTP');
    ok(!VotingService::verifyOtp($invitation['token'],'000000'),'OTP inválido rechazado');ok(VotingService::verifyOtp($invitation['token'],$invitation['otp']),'OTP válido aceptado');
    $lockedInvitation=CensusService::generateInvitation($electionId,$lockedMemberId);for($attempt=0;$attempt<8;$attempt++)VotingService::verifyOtp($lockedInvitation['token'],'000000');ok(!VotingService::verifyOtp($lockedInvitation['token'],$lockedInvitation['otp']),'OTP bloqueado después de ocho intentos');
    $receipt=VotingService::submit($invitation['token'],[(string)$questionId=>[$optionId]]);ok((bool)preg_match('/^[a-f0-9]{64}$/',$receipt),'comprobante SHA-256 generado');
    try{VotingService::submit($invitation['token'],[(string)$questionId=>[$optionId]]);ok(false,'segundo voto bloqueado');}catch(RuntimeException){ok(true,'segundo voto bloqueado');}
    $ballotColumns=$pdo->query('SHOW COLUMNS FROM ballots')->fetchAll(PDO::FETCH_COLUMN);ok(!in_array('census_member_id',$ballotColumns,true),'papeleta sin identidad directa');
    try{ResultService::certify($electionId,$organizerId);ok(false,'certificación abierta bloqueada');}catch(RuntimeException){ok(true,'certificación abierta bloqueada');}
    ElectionService::setStatus($electionId,'closed');$certificate=ResultService::certify($electionId,$organizerId);ok(strlen($certificate['sha256'])===64,'certificado definitivo generado');
    $again=ResultService::certify($electionId,$organizerId);ok($again['sha256']===$certificate['sha256'],'certificado inmutable');
    fwrite(STDOUT,"MVP SMOKE TEST: PASS\n");
}finally{
    if($electionId)$pdo->prepare('DELETE FROM result_certificates WHERE election_id=:id')->execute(['id'=>$electionId]);
    if($electionId)$pdo->prepare('DELETE FROM elections WHERE id=:id')->execute(['id'=>$electionId]);
    if($organizerId)$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$organizerId]);
    if($organizationId)$pdo->prepare('DELETE FROM organizations WHERE id=:id')->execute(['id'=>$organizationId]);
}
