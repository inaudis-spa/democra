<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Database;
$user=Authorization::requireRoles(['admin','organizer']);
$pdo=Database::connection();
if($user['role']==='admin'){
    $stmt=$pdo->query('SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 200');
}else{
    $stmt=$pdo->prepare(
        'SELECT DISTINCT a.*,u.name user_name
         FROM audit_logs a
         LEFT JOIN users u ON u.id=a.user_id
         LEFT JOIN elections e ON a.entity_type="election" AND e.id=a.entity_id
         LEFT JOIN census_members cm ON a.entity_type="census_member" AND cm.id=a.entity_id
         LEFT JOIN elections ce ON ce.id=cm.election_id
         WHERE u.organization_id=:org_user
            OR e.organization_id=:org_election
            OR ce.organization_id=:org_census
            OR (a.entity_type="organization" AND a.entity_id=:org_entity)
         ORDER BY a.created_at DESC LIMIT 200'
    );
    $organizationId=(int)$user['organization_id'];
    $stmt->execute(['org_user'=>$organizationId,'org_election'=>$organizationId,'org_census'=>$organizationId,'org_entity'=>$organizationId]);
}
$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);
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
];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auditoría | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"></head><body class="vota-ui"><div class="v-shell">
<aside class="v-sidebar"><div class="v-brand"><img class="v-brandmark" src="/assets/quorum-mark.svg" alt=""><span><span class="v-brand-name">QUORUM<small>by INAUDIS</small></span></span></div><nav class="v-nav"><a href="/dashboard.php">⌂ &nbsp; Inicio</a><a href="/elections.php">✓ &nbsp; Votaciones</a><?php if($user['role']==='admin'):?><a href="/organizations.php">▦ &nbsp; Organizaciones</a><a href="/users.php">♙ &nbsp; Usuarios</a><?php endif;?><a class="active" href="/audit.php">◷ &nbsp; Auditoría</a></nav><div class="v-sidebar-foot"><?=htmlspecialchars($user['name'])?></div></aside>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content"><div class="v-headrow"><div><div class="v-kicker">Trazabilidad</div><h1 class="v-title">Auditoría</h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$user['role']==='admin'?'Registro de los últimos 200 eventos de la plataforma.':'Registro de actividad correspondiente únicamente a tu organización.'?></p></div><span class="v-status v-status-open">✓ Registro activo</span></div>
<div class="v-alert" style="background:#eef6ff;border:1px solid #cfe2ff"><strong>¿Para qué sirve?</strong> Permite revisar quién realizó cada acción, cuándo ocurrió y sobre qué elemento. No permite modificar ni eliminar eventos.</div>
<section class="v-card v-pad v-table-wrap"><?php if(!$logs):?><div style="text-align:center;padding:35px"><h2>Sin actividad registrada</h2><p class="v-muted">Las acciones realizadas en tu organización aparecerán aquí automáticamente.</p></div><?php else:?><table class="v-table"><thead><tr><th>Fecha</th><th>Actividad</th><th>Elemento</th><th>Realizado por</th><th>Dirección IP</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td style="white-space:nowrap"><?=htmlspecialchars($log['created_at'])?></td><td><strong style="color:var(--blue)"><?=htmlspecialchars($eventLabels[$log['event_type']]??$log['event_type'])?></strong></td><td><?=htmlspecialchars($log['entity_type'])?> #<?=htmlspecialchars((string)$log['entity_id'])?></td><td><?=htmlspecialchars((string)($log['user_name']?:'Sistema / votante'))?></td><td class="v-muted"><?=htmlspecialchars((string)($log['ip_address']?:'—'))?></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
</main></div></div></body></html>
