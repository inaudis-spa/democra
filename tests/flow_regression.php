<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Database;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;
use InaudisVote\Services\LiveControlService;
use InaudisVote\Services\UserService;
function assertFlow(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('FAIL: '.$message);echo "PASS: {$message}\n";}
$pdo=Database::connection();$suffix=substr(bin2hex(random_bytes(4)),0,8);$organizationId=0;$userId=0;$electionId=0;
try{
    $organizationId=OrganizationService::create(['name'=>'Organización Flujo '.$suffix,'tax_id'=>'','slug'=>'organizacion-flujo-'.$suffix]);
    $row=$pdo->query('SELECT status,slug FROM organizations WHERE id='.(int)$organizationId)->fetch();
    assertFlow($row['status']==='active','organización usa estado activo predeterminado');
    try{OrganizationService::create(['name'=>'Duplicada','slug'=>$row['slug']]);assertFlow(false,'slug duplicado rechazado');}catch(InvalidArgumentException $e){assertFlow(str_contains($e->getMessage(),'ya está'),'slug duplicado entrega mensaje claro');}
    $userId=UserService::create(['name'=>'Organizador Prueba','email'=>'flujo-'.$suffix.'@inaudis.local','password'=>'Prueba2026!','role'=>'organizer','organization_id'=>$organizationId]);
    assertFlow($userId>0,'usuario organizador creado');
    try{ElectionService::create(['organization_id'=>$organizationId,'title'=>'Fechas inválidas','starts_at'=>'2026-10-03 10:00','ends_at'=>'2026-10-02 10:00'],$userId);assertFlow(false,'fechas inválidas rechazadas');}catch(InvalidArgumentException $e){assertFlow(str_contains($e->getMessage(),'posterior'),'fechas inválidas entregan mensaje claro');}
    $electionId=ElectionService::create(['organization_id'=>$organizationId,'title'=>'Elección Flujo '.$suffix,'voting_mode'=>'secret'],$userId);
    $questionId=ElectionService::addQuestion($electionId,['text'=>'¿Aprueba esta prueba?','options'=>"Sí\nNo",'question_type'=>'single']);
    assertFlow($questionId>0,'votación y pregunta creadas');
    $memberId=CensusService::add($electionId,['name'=>'Votante Prueba','email'=>'votante-'.$suffix.'@inaudis.local','vote_weight'=>1]);
    try{CensusService::add($electionId,['name'=>'Duplicado','email'=>'votante-'.$suffix.'@inaudis.local','vote_weight'=>1]);assertFlow(false,'votante duplicado rechazado');}catch(InvalidArgumentException $e){assertFlow(str_contains($e->getMessage(),'ya está'),'votante duplicado entrega mensaje claro');}
    ElectionService::setStatus($electionId,'open');$invitation=CensusService::generateInvitation($memberId);
    assertFlow(strlen($invitation['otp'])===6&&$invitation['expires_in_seconds']>0,'invitación genera OTP con vigencia configurable');
    LiveControlService::setCurrentQuestion($electionId,$questionId,$userId);$live=LiveControlService::snapshot($electionId);
    assertFlow($live['current_question_id']===$questionId,'control en vivo proyecta una pregunta válida');
    assertFlow($live['eligible']===1&&$live['voted']===0,'control en vivo calcula participación');
    LiveControlService::setDisplayResults($electionId,true,$userId);$live=LiveControlService::snapshot($electionId);
    assertFlow($live['display_results']===true,'control en vivo gestiona visibilidad de resultados');
    echo "FLOW_REGRESSION: PASS\n";
}finally{
    if($electionId)$pdo->prepare('DELETE FROM elections WHERE id=:id')->execute(['id'=>$electionId]);
    if($userId)$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$userId]);
    if($organizationId)$pdo->prepare('DELETE FROM organizations WHERE id=:id')->execute(['id'=>$organizationId]);
}
