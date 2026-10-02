<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Database;
use InaudisVote\Services\ElectionService;

$user=Authorization::requireRoles(['admin','organizer']);
$pdo=Database::connection();
if($user['role']==='admin'){
    $organizations=(int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
    $active=(int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='open'")->fetchColumn();
    $members=(int)$pdo->query('SELECT COUNT(*) FROM census_members')->fetchColumn();
    $voted=(int)$pdo->query('SELECT COUNT(*) FROM census_members WHERE has_voted=1')->fetchColumn();
    $audit=(int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
}else{
    $org=(int)$user['organization_id'];$organizations=1;
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM elections WHERE organization_id=:org AND status='open'");$stmt->execute(['org'=>$org]);$active=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN elections e ON e.id=cm.election_id WHERE e.organization_id=:org');$stmt->execute(['org'=>$org]);$members=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN elections e ON e.id=cm.election_id WHERE e.organization_id=:org AND cm.has_voted=1');$stmt->execute(['org'=>$org]);$voted=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE u.organization_id=:org');$stmt->execute(['org'=>$org]);$audit=(int)$stmt->fetchColumn();
}
$pending=max(0,$members-$voted);$participation=$members>0?round(($voted/$members)*100,1):0;$recent=array_slice(ElectionService::allForUser($user),0,5);$featured=$recent[0]??null;
function statusClass(string $status):string{return $status==='open'?'v-status-open':($status==='closed'?'v-status-closed':'v-status-draft');}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | INAUDIS Vota</title><link rel="stylesheet" href="/assets/inaudis-vota.css"></head><body class="vota-ui"><div class="v-shell">
<aside class="v-sidebar"><div class="v-brand"><span class="v-brandmark"></span><span>INAUDIS<small>VOTA</small></span></div><nav class="v-nav"><a class="active" href="/dashboard.php">⌂ &nbsp; Inicio</a><a href="/elections.php">✓ &nbsp; Votaciones</a><?php if($user['role']==='admin'): ?><a href="/organizations.php">▦ &nbsp; Organizaciones</a><a href="/users.php">♙ &nbsp; Usuarios</a><?php endif; ?><a href="/audit.php">◷ &nbsp; Auditoría</a></nav><div class="v-sidebar-foot"><div style="margin-bottom:12px">Plataforma de decisiones verificables</div><strong><?=htmlspecialchars($user['name'])?></strong><br><?=htmlspecialchars($user['role'])?></div></aside>
<div class="v-main"><header class="v-topbar"><span class="v-muted" style="font-size:13px">Sesión segura</span><div class="v-avatar"><?=htmlspecialchars(strtoupper(substr($user['name'],0,1)))?></div><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Centro de votaciones</div><h1 class="v-title">Buenos días, <?=htmlspecialchars(explode(' ',$user['name'])[0])?></h1><div class="v-accent-line"></div><p class="v-subtitle">Aquí tienes el estado de tus procesos electorales.</p></div><a href="/elections.php" class="v-btn v-btn-orange">＋ Nueva votación</a></div>
<?php if($featured): ?><section class="v-card v-hero"><div><span class="v-status <?=statusClass($featured['status'])?>"><?=htmlspecialchars($featured['status']==='open'?'En curso':$featured['status'])?></span><h2 style="font-size:24px;margin:12px 0 4px"><?=htmlspecialchars($featured['title'])?></h2><p class="v-muted" style="margin:0 0 24px"><?=htmlspecialchars($featured['organization_name'])?></p><div class="v-big"><?=$participation?>%</div><p class="v-muted" style="margin:5px 0 12px"><?=$voted?> de <?=$members?> votos registrados</p><div class="v-progress"><span style="width:<?=min(100,$participation)?>%"></span></div></div><div style="display:flex;flex-direction:column;justify-content:center;gap:14px;border-left:1px solid var(--line);padding-left:24px"><div><strong><?=$active?></strong><div class="v-muted">votaciones abiertas</div></div><div><strong>🔒 Voto protegido</strong><div class="v-muted">Integridad y trazabilidad activas</div></div><a class="v-btn v-btn-primary" style="justify-content:center" href="/election.php?id=<?=$featured['id']?>">Ver proceso →</a></div></section><?php endif; ?>
<section class="v-stats"><article class="v-card v-stat"><strong><?=$members?></strong><span class="v-muted">Habilitados</span></article><article class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votaron</span></article><article class="v-card v-stat orange"><strong><?=$pending?></strong><span class="v-muted">Pendientes</span></article><article class="v-card v-stat danger"><strong>0</strong><span class="v-muted">Incidentes críticos</span></article></section>
<div class="v-section-head"><h2 style="font-size:18px">Procesos recientes</h2><a class="v-link" href="/elections.php">Ver todos →</a></div><section class="v-card v-list"><?php if(!$recent): ?><div class="v-row"><span class="v-muted">Aún no hay procesos.</span></div><?php endif; ?><?php foreach($recent as $e): ?><a class="v-row" href="/election.php?id=<?=$e['id']?>"><div><div class="v-row-title"><?=htmlspecialchars($e['title'])?></div><div class="v-muted" style="font-size:13px;margin-top:3px"><?=htmlspecialchars($e['organization_name'])?></div></div><div style="display:flex;align-items:center;gap:16px"><span class="v-status <?=statusClass($e['status'])?>"><?=htmlspecialchars($e['status'])?></span><span class="v-link">→</span></div></a><?php endforeach; ?></section>
</main></div></div></body></html>