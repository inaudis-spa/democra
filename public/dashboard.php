<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Database;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\ElectionService;

$user=Authorization::requireRoles(['admin','organizer','witness']);
$pdo=Database::connection();
if($user['role']==='witness'){header('Location: /elections.php');exit;}elseif($user['role']==='admin'){
    $organizations=(int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
    $active=(int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='open'")->fetchColumn();
    $members=(int)$pdo->query('SELECT COUNT(*) FROM census_members')->fetchColumn();
    $voted=(int)$pdo->query('SELECT COUNT(*) FROM census_members WHERE has_voted=1')->fetchColumn();
    $audit=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
}else{
    $uid=(int)$user['id'];$organizations=1;
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM elections e JOIN election_managers em ON em.election_id=e.id WHERE em.user_id=:uid AND e.status='open'");$stmt->execute(['uid'=>$uid]);$active=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN election_managers em ON em.election_id=cm.election_id WHERE em.user_id=:uid');$stmt->execute(['uid'=>$uid]);$members=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN election_managers em ON em.election_id=cm.election_id WHERE em.user_id=:uid AND cm.has_voted=1');$stmt->execute(['uid'=>$uid]);$voted=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(DISTINCT a.id) FROM audit_logs a LEFT JOIN census_members cm ON a.entity_type="census_member" AND cm.id=a.entity_id JOIN election_managers em ON em.user_id=:uid WHERE (a.entity_type="election" AND a.entity_id=em.election_id) OR JSON_EXTRACT(a.metadata,"$.election_id")=em.election_id OR cm.election_id=em.election_id');$stmt->execute(['uid'=>$uid]);$audit=(int)$stmt->fetchColumn();
}
$pending=max(0,$members-$voted);$participation=$members>0?round(($voted/$members)*100,1):0;$allElections=ElectionService::allForUser($user);$recent=array_slice($allElections,0,8);$featured=null;foreach($allElections as $candidate){if($candidate['status']==='open'){$featured=$candidate;break;}}$featured??=$recent[0]??null;$featuredMembers=0;$featuredVoted=0;$featuredParticipation=0;
if($featured){$stmt=$pdo->prepare('SELECT COUNT(*) total,SUM(has_voted=1) voted FROM census_members WHERE election_id=:id');$stmt->execute(['id'=>$featured['id']]);$featuredStats=$stmt->fetch(PDO::FETCH_ASSOC)?:[];$featuredMembers=(int)($featuredStats['total']??0);$featuredVoted=(int)($featuredStats['voted']??0);$featuredParticipation=$featuredMembers>0?round($featuredVoted*100/$featuredMembers,1):0;}
function statusClass(string $status):string{return $status==='open'?'v-status-open':($status==='closed'?'v-status-closed':'v-status-draft');}
function statusLabel(string $status):string{return ['open'=>'En curso','scheduled'=>'Programada','draft'=>'Sin comenzar','closed'=>'Terminada','cancelled'=>'Cancelada'][$status]??$status;}
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | DEMOCRA</title><link rel="stylesheet" href="/assets/democra-base.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/guide.css"><script src="/assets/guide.js" defer></script></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'dashboard'); ?>
<div class="v-main"><header class="v-topbar"><button class="v-help-trigger" type="button" data-start-tour>¿Cómo funciona?</button><span class="v-muted" style="font-size:13px">Sesión segura</span><div class="v-avatar"><?=htmlspecialchars(strtoupper(substr($user['name'],0,1)))?></div><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Centro de votaciones</div><h1 class="v-title">Buenos días, <?=htmlspecialchars(explode(' ',$user['name'])[0])?></h1><div class="v-accent-line"></div><p class="v-subtitle">Aquí tienes el estado de tus procesos electorales.</p></div><?php if($user['role']==='admin'):?><a data-tour="new-election" href="/elections.php" class="v-btn v-btn-orange">＋ Nueva votación</a><?php endif;?></div>
<section class="v-onboarding" data-tour="welcome" data-onboarding-banner><div><span class="v-onboarding-icon">?</span><div><strong>¿Primera vez en DEMOCRA?</strong><p>Conoce el flujo completo para crear y ejecutar una votación segura.</p></div></div><div class="v-onboarding-actions"><button class="v-btn v-btn-primary" type="button" data-start-tour>Ver recorrido guiado</button><a class="v-link" href="/manual.php?perfil=<?=htmlspecialchars((string)$user['role'],ENT_QUOTES,'UTF-8')?>">Abrir manual interactivo</a><button class="v-onboarding-hide" type="button" data-hide-onboarding aria-label="Ocultar ayuda">Ocultar</button></div></section>
<?php if($featured): ?><section class="v-card v-hero"><div><span class="v-status <?=statusClass($featured['status'])?>"><?=htmlspecialchars(statusLabel($featured['status']))?></span><h2 style="font-size:24px;margin:12px 0 4px"><?=htmlspecialchars($featured['title'])?></h2><p class="v-muted" style="margin:0 0 24px"><?=htmlspecialchars($featured['organization_name'])?></p><div class="v-big"><?=$featuredParticipation?>%</div><p class="v-muted" style="margin:5px 0 12px"><?=$featuredVoted?> de <?=$featuredMembers?> votos registrados en este proceso</p><div class="v-progress"><span style="width:<?=min(100,$featuredParticipation)?>%"></span></div></div><div style="display:flex;flex-direction:column;justify-content:center;gap:14px;border-left:1px solid var(--line);padding-left:24px"><div><strong><?=$active?></strong><div class="v-muted">votaciones abiertas</div></div><div><strong>🔒 Voto protegido</strong><div class="v-muted">Integridad y trazabilidad activas</div></div><a data-tour="control" class="v-btn v-btn-primary" style="justify-content:center" href="/control.php?election_id=<?=$featured['id']?>">Abrir centro de control →</a></div></section><?php endif; ?>
<section class="v-stats"><article class="v-card v-stat"><strong><?=$members?></strong><span class="v-muted">Habilitados</span></article><article class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votaron</span></article><article class="v-card v-stat orange"><strong><?=$pending?></strong><span class="v-muted">Pendientes</span></article><article class="v-card v-stat danger"><strong>0</strong><span class="v-muted">Incidentes críticos</span></article></section>
<div class="v-section-head"><h2 style="font-size:18px">Procesos recientes</h2><a class="v-link" href="/elections.php">Ver todos →</a></div><section class="v-card v-list"><?php if(!$recent): ?><div class="v-row"><span class="v-muted">Aún no hay procesos.</span></div><?php endif; ?><?php foreach($recent as $e): ?><a class="v-row" href="/control.php?election_id=<?=$e['id']?>"><div><div class="v-row-title"><?=htmlspecialchars($e['title'])?></div><div class="v-muted" style="font-size:13px;margin-top:3px"><?=htmlspecialchars($e['organization_name'])?></div></div><div style="display:flex;align-items:center;gap:16px"><span class="v-status <?=statusClass($e['status'])?>"><?=htmlspecialchars(statusLabel($e['status']))?></span><span class="v-link">→</span></div></a><?php endforeach; ?></section>
</main></div></div></body></html>
