<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Database;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;
use InaudisVote\Services\SequentialQuestionService;
use InaudisVote\Services\UserService;
use InaudisVote\Services\VotingService;
use InaudisVote\Services\ResultService;

function checkSequential(bool $ok,string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    echo "PASS: {$message}\n";
}
$pdo=Database::connection();
$suffix=bin2hex(random_bytes(5));$organizationId=0;$userId=0;$electionId=0;
try {
    $organizationId=OrganizationService::create(['name'=>'Secuencial '.$suffix,'slug'=>'sequential-'.$suffix]);
    $userId=UserService::create(['name'=>'Organizador','email'=>'seq-'.$suffix.'@inaudis.local','password'=>'Prueba2026!','role'=>'organizer','organization_id'=>$organizationId]);
    $electionId=ElectionService::create(['organization_id'=>$organizationId,'title'=>'Asamblea '.$suffix,'question_flow'=>'sequential'],$userId);
    $q1=ElectionService::addQuestion($electionId,['text'=>'Pregunta 1','options'=>"Sí\nNo"]);
    $q2=ElectionService::addQuestion($electionId,['text'=>'Pregunta 2','options'=>"Sí\nNo"]);
    $option1=(int)$pdo->query('SELECT id FROM options WHERE question_id='.$q1.' ORDER BY id LIMIT 1')->fetchColumn();
    $option2=(int)$pdo->query('SELECT id FROM options WHERE question_id='.$q2.' ORDER BY id LIMIT 1')->fetchColumn();
    $memberId=CensusService::add($electionId,['name'=>'Votante','email'=>'seq-voter-'.$suffix.'@inaudis.local','vote_weight'=>1]);
    ElectionService::setStatus($electionId,'open');
    $inv=CensusService::generateInvitation($electionId,$memberId);
    checkSequential(VotingService::verifyOtp($inv['token'],$inv['otp']),'OTP aceptado');
    try {VotingService::submit($inv['token'],[(string)$q1=>[$option1]]);throw new RuntimeException('Voto sin apertura aceptado');}
    catch (RuntimeException $e) {checkSequential($e->getMessage()!=='Voto sin apertura aceptado','No permite votar antes de abrir pregunta');}
    try {SequentialQuestionService::change($electionId,$q2,'open',$userId);throw new RuntimeException('Desorden permitido');}
    catch (RuntimeException $e) {checkSequential($e->getMessage()!=='Desorden permitido','Orden de preguntas protegido');}
    SequentialQuestionService::change($electionId,$q1,'open',$userId);
    try {SequentialQuestionService::change($electionId,$q2,'open',$userId);throw new RuntimeException('Dos abiertas');}
    catch (RuntimeException $e) {checkSequential($e->getMessage()!=='Dos abiertas','Solo una pregunta abierta');}
    $receipt1=VotingService::submit($inv['token'],[(string)$q1=>[$option1]]);
    checkSequential(strlen($receipt1)===64,'Primera respuesta registrada');
    try {VotingService::submit($inv['token'],[(string)$q1=>[$option1]]);throw new RuntimeException('Duplicado permitido');}
    catch (PDOException $e) {checkSequential(true,'Duplicado rechazado por índice único');}
    checkSequential(SequentialQuestionService::participation($q1)===1,'Participación sin duplicados');
    SequentialQuestionService::change($electionId,$q1,'close',$userId);
    try {VotingService::submit($inv['token'],[(string)$q1=>[$option1]]);throw new RuntimeException('Pregunta cerrada acepta votos');}
    catch (RuntimeException $e) {checkSequential($e->getMessage()!=='Pregunta cerrada acepta votos','Pregunta cerrada rechaza votos');}
    SequentialQuestionService::change($electionId,$q2,'open',$userId);
    $receipt2=VotingService::submit($inv['token'],[(string)$q2=>[$option2]]);
    checkSequential(strlen($receipt2)===64,'Mismo votante responde segunda pregunta');
    SequentialQuestionService::change($electionId,$q2,'close',$userId);
    $summary=ResultService::summary($electionId);
    checkSequential((int)$summary['questions'][0]['options'][0]['votes']===1,'Conteo de primera pregunta correcto');
    checkSequential((int)$summary['questions'][1]['options'][0]['votes']===1,'Conteo de segunda pregunta correcto');
    ElectionService::setStatus($electionId,'closed');
    checkSequential(true,'Cierre final de asamblea correcto');
    echo "SEQUENTIAL_REGRESSION: PASS\n";
} finally {
    if($electionId)$pdo->prepare('DELETE FROM elections WHERE id=:id')->execute(['id'=>$electionId]);
    if($userId)$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$userId]);
    if($organizationId)$pdo->prepare('DELETE FROM organizations WHERE id=:id')->execute(['id'=>$organizationId]);
}
