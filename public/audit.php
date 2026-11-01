<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Database;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\ElectionService;
$user=Authorization::requireRoles(['admin','organizer','witness']);
$pdo=Database::connection();
$electionId=(int)($_GET['election_id']??0);
$page=max(1,min(100000,(int)($_GET['page']??1)));
$offset=($page-1)*200;
if($electionId>0){
    $election=ElectionService::find($electionId);
    if(!$election||!Authorization::canReviewElection($user,$electionId,(int)$election['organization_id'])){http_response_code(403);exit('No autorizado.');}
    $stmt=$pdo->prepare('SELECT DISTINCT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN census_members cm ON a.entity_type="census_member" AND cm.id=a.entity_id WHERE (a.entity_type="election" AND a.entity_id=:eid) OR JSON_EXTRACT(a.metadata,"$.election_id")=:json_eid OR cm.election_id=:census_eid ORDER BY a.created_at DESC LIMIT 201 OFFSET {$offset}');
    $stmt->execute(['eid'=>$electionId,'json_eid'=>$electionId,'census_eid'=>$electionId]);
}elseif($user['role']==='witness'){
    $stmt=$pdo->prepare('SELECT DISTINCT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id JOIN election_witnesses ew ON ew.user_id=:uid WHERE (a.entity_type="election" AND a.entity_id=ew.election_id) OR JSON_EXTRACT(a.metadata,"$.election_id")=ew.election_id ORDER BY a.created_at DESC LIMIT 201 OFFSET {$offset}');$stmt->execute(['uid'=>$user['id']]);
}elseif($user['role']==='admin'){
    $stmt=$pdo->query('SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 201 OFFSET {$offset}');
}else{
    $stmt=$pdo->prepare(
        'SELECT DISTINCT a.*,u.name user_name
         FROM audit_logs a
         LEFT JOIN users u ON u.id=a.user_id
         LEFT JOIN census_members cm ON a.entity_type="census_member" AND cm.id=a.entity_id
         JOIN election_managers em ON em.user_id=:uid
         WHERE (a.entity_type="election" AND a.entity_id=em.election_id)
            OR JSON_EXTRACT(a.metadata,"$.election_id")=em.election_id
            OR cm.election_id=em.election_id
         ORDER BY a.created_at DESC LIMIT 201 OFFSET {$offset}'
    );
    $stmt->execute(['uid'=>(int)$user['id']]);
}
$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);
$hasNext=count($logs)>200;
if($hasNext)array_pop($logs);
$pageUrl=static fn(int $target): string => '/audit.php?'.http_build_query(['election_id'=>$electionId,'page'=>$target]);
$eventLabels=[
    'auth.login_success'=>'Inicio de sesión',
    'organization.created'=>'Organización creada',
    'user.created'=>'Usuario creado',
    'election.created'=>'Votación creada',
    'election.status_changed'=>'Estado de votación actualizado',
    'question.created'=>'Pregunta agregada',
    'census.member_added'=>'Votante agregado al padrón',
    'census.csv_imported'=>'Padrón importado',
    'voter.invitation_generated'=>'Invitación generada',
    'voter.otp_verified'=>'Identidad del votante verificada',
    'vote.submitted'=>'Voto registrado',
    'results.certified'=>'Resultados certificados',
    'acta.pdf_generated'=>'Acta PDF generada',
    'manager.assigned'=>'Encargado asignado',
    'manager.removed'=>'Encargado retirado',
    'witness.assigned'=>'Ministro de Fe asignado',
    'witness.removed'=>'Ministro de Fe retirado',
    'witness.checkpoint'=>'Hito fiscalizado',
    'live.question_changed'=>'Pregunta proyectada actualizada',
    'live.results_visibility_changed'=>'Visibilidad de resultados actualizada',
    'user.status_toggled'=>'Estado de usuario actualizado',
];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auditoría | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, $electionId ? 'election-audit' : 'audit', $electionId ?: null, $election['title'] ?? null); ?>
<div class="v-main"><header class="v-topbar"><?php if($electionId):?><a class="v-link" href="<?=$user['role']==='witness'?'/fiscalizacion.php?election_id='.$electionId:'/control.php?election_id='.$electionId?>">← Volver al proceso</a><span style="flex:1"></span><?php endif;?><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content"><div class="v-headrow"><div><div class="v-kicker">Trazabilidad</div><h1 class="v-title">Auditoría<?=$electionId?' de la votación':''?></h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$electionId?'Registro limitado al proceso seleccionado.':($user['role']==='admin'?'Registro de los últimos 200 eventos de la plataforma.':($user['role']==='witness'?'Actividad de tus procesos asignados.':'Actividad de tu organización.'))?></p></div><span class="v-status v-status-open">✓ Registro activo</span></div>
<div class="v-alert" style="background:#eef6ff;border:1px solid #cfe2ff"><strong>¿Para qué sirve?</strong> Permite revisar quién realizó cada acción, cuándo ocurrió y sobre qué elemento. No permite modificar ni eliminar eventos.</div>
<section class="v-card v-pad v-table-wrap"><?php if(!$logs):?><div style="text-align:center;padding:35px"><h2>Sin actividad registrada</h2><p class="v-muted">Las acciones realizadas en tu organización aparecerán aquí automáticamente.</p></div><?php else:?><table class="v-table"><thead><tr><th>Fecha</th><th>Actividad</th><th>Elemento</th><th>Realizado por</th><th>Dirección IP</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td style="white-space:nowrap"><?=htmlspecialchars($log['created_at'])?></td><td><strong style="color:var(--blue)"><?=htmlspecialchars($eventLabels[$log['event_type']]??$log['event_type'])?></strong></td><td><?=htmlspecialchars($log['entity_type'])?> #<?=htmlspecialchars((string)$log['entity_id'])?></td><td><?=htmlspecialchars((string)($log['user_name']?:'Sistema / votante'))?></td><td class="v-muted"><?=htmlspecialchars((string)($log['ip_address']?:'—'))?></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
<nav aria-label="Páginas de auditoría" style="display:flex;gap:16px;margin-top:18px">
<?php if($page>1):?><a class="v-link" href="<?=htmlspecialchars($pageUrl($page-1),ENT_QUOTES,'UTF-8')?>">← Anteriores</a><?php endif;?>
<span>Página <?=$page?></span>
<?php if($hasNext):?><a class="v-link" href="<?=htmlspecialchars($pageUrl($page+1),ENT_QUOTES,'UTF-8')?>">Siguientes →</a><?php endif;?>
</nav>
</main></div></div></body></html>
