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
$pdo=Database::connection();$suffix=bin2hex(random_bytes(4));$organizationId=0;$organizerId=0;$electionId=0;$publicElectionId=0;
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
    $repeated=VotingService::submit($invitation['token'],[(string)$questionId=>[$optionId]]);ok(hash_equals($receipt,$repeated),'reintento devuelve el mismo comprobante sin duplicar');
    $ballotCount=(int)$pdo->query('SELECT COUNT(*) FROM ballots WHERE election_id='.(int)$electionId)->fetchColumn();ok($ballotCount===1,'reintento conserva una sola papeleta');
    $participationCount=(int)$pdo->query('SELECT COUNT(*) FROM election_participations WHERE election_id='.(int)$electionId.' AND census_member_id='.(int)$memberId)->fetchColumn();ok($participationCount===1,'participación tradicional tiene unicidad declarativa');
    $ballotColumns=$pdo->query('SHOW COLUMNS FROM ballots')->fetchAll(PDO::FETCH_COLUMN);ok(in_array('census_member_id',$ballotColumns,true),'papeleta admite identidad controlada');
    $secretIdentity=$pdo->query('SELECT census_member_id FROM ballots WHERE election_id='.(int)$electionId.' LIMIT 1')->fetchColumn();ok($secretIdentity===null||$secretIdentity===false,'voto secreto conserva identidad separada');
    try{ResultService::certify($electionId,$organizerId);ok(false,'certificación abierta bloqueada');}catch(RuntimeException){ok(true,'certificación abierta bloqueada');}
    ElectionService::setStatus($electionId,'closed');$certificate=ResultService::certify($electionId,$organizerId);ok(strlen($certificate['sha256'])===64,'certificado definitivo generado');
    $again=ResultService::certify($electionId,$organizerId);ok($again['sha256']===$certificate['sha256'],'certificado inmutable');
    $publicElectionId=ElectionService::create(['organization_id'=>$organizationId,'title'=>'Public '.$suffix,'voting_mode'=>'public'],$organizerId);
    $publicQuestionId=ElectionService::addQuestion($publicElectionId,['text'=>'¿Aprueba voto identificado?','options'=>"Sí\nNo",'question_type'=>'single']);
    $publicOptionId=(int)$pdo->query('SELECT id FROM options WHERE question_id='.(int)$publicQuestionId.' ORDER BY id LIMIT 1')->fetchColumn();
    $publicMemberId=CensusService::add($publicElectionId,['name'=>'Votante Identificado','email'=>'public-'.$suffix.'@inaudis.local','vote_weight'=>1]);
    ElectionService::setStatus($publicElectionId,'open');$publicInvitation=CensusService::generateInvitation($publicElectionId,$publicMemberId);
    ok(VotingService::verifyOtp($publicInvitation['token'],$publicInvitation['otp']),'OTP de voto identificado aceptado');
    VotingService::submit($publicInvitation['token'],[(string)$publicQuestionId=>[$publicOptionId]]);
    $storedMember=(int)$pdo->query('SELECT census_member_id FROM ballots WHERE election_id='.(int)$publicElectionId.' LIMIT 1')->fetchColumn();ok($storedMember===$publicMemberId,'voto identificado vincula al votante');
    ElectionService::setStatus($publicElectionId,'closed');$identified=ResultService::identifiedVotes($publicElectionId,$organizerId);
    ok(count($identified)===1&&$identified[0]['voter_name']==='Votante Identificado','detalle identificado disponible después del cierre');
    fwrite(STDOUT,"MVP SMOKE TEST: PASS\n");
}finally{
    if($publicElectionId)$pdo->prepare('DELETE FROM elections WHERE id=:id')->execute(['id'=>$publicElectionId]);
    if($electionId)$pdo->prepare('DELETE FROM result_certificates WHERE election_id=:id')->execute(['id'=>$electionId]);
    if($electionId)$pdo->prepare('DELETE FROM elections WHERE id=:id')->execute(['id'=>$electionId]);
    if($organizerId)$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$organizerId]);
    if($organizationId)$pdo->prepare('DELETE FROM organizations WHERE id=:id')->execute(['id'=>$organizationId]);
}
