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
    $org=(int)$user['organization_id'];
    $organizations=1;
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM elections WHERE organization_id=:org AND status='open'");$stmt->execute(['org'=>$org]);$active=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN elections e ON e.id=cm.election_id WHERE e.organization_id=:org');$stmt->execute(['org'=>$org]);$members=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM census_members cm JOIN elections e ON e.id=cm.election_id WHERE e.organization_id=:org AND cm.has_voted=1');$stmt->execute(['org'=>$org]);$voted=(int)$stmt->fetchColumn();
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE u.organization_id=:org');$stmt->execute(['org'=>$org]);$audit=(int)$stmt->fetchColumn();
}
$participation=$members>0?round(($voted/$members)*100,1):0;
$recent=array_slice(ElectionService::allForUser($user),0,5);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100">
<header class="border-b border-slate-800 bg-slate-900"><div class="mx-auto max-w-7xl px-6 py-4 flex flex-wrap items-center justify-between gap-4"><div><p class="text-xs uppercase tracking-[0.25em] text-cyan-400">INAUDIS</p><h1 class="text-xl font-semibold">Vote</h1></div><nav class="flex flex-wrap gap-4 text-sm"><a href="/elections.php" class="text-cyan-300">Votaciones</a><?php if($user['role']==='admin'): ?><a href="/organizations.php">Organizaciones</a><?php endif; ?><a href="/audit.php">Auditoría</a><a href="/logout.php" class="text-red-300">Salir</a></nav><div class="text-xs text-slate-500"><?=htmlspecialchars($user['name'])?> · <?=htmlspecialchars($user['role'])?></div></div></header>
<main class="mx-auto max-w-7xl px-6 py-10"><div class="mb-8"><p class="text-cyan-400 text-sm">DEMO MVP</p><h2 class="text-3xl font-semibold">Dashboard</h2><p class="text-slate-400 mt-2">Resumen operacional con datos reales del sistema.</p></div>
<section class="grid gap-5 md:grid-cols-2 lg:grid-cols-5 mb-8"><article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Organizaciones</p><p class="mt-3 text-3xl font-semibold"><?=$organizations?></p></article><article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Votaciones abiertas</p><p class="mt-3 text-3xl font-semibold"><?=$active?></p></article><article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Censo acumulado</p><p class="mt-3 text-3xl font-semibold"><?=$members?></p></article><article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Participación</p><p class="mt-3 text-3xl font-semibold"><?=$participation?>%</p></article><article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Eventos auditados</p><p class="mt-3 text-3xl font-semibold"><?=$audit?></p></article></section>
<section class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><div class="flex justify-between items-center mb-4"><h3 class="font-semibold">Procesos recientes</h3><a href="/elections.php" class="text-sm text-cyan-300">Ver todos →</a></div><div class="space-y-3"><?php if(!$recent): ?><p class="text-slate-400">Aún no hay procesos.</p><?php endif; ?><?php foreach($recent as $e): ?><a href="/election.php?id=<?=$e['id']?>" class="flex justify-between gap-4 p-4 rounded-xl border border-slate-800 bg-slate-950 hover:border-cyan-600"><div><p class="font-medium"><?=htmlspecialchars($e['title'])?></p><p class="text-sm text-slate-500"><?=htmlspecialchars($e['organization_name'])?></p></div><span class="text-xs uppercase text-cyan-300"><?=htmlspecialchars($e['status'])?></span></a><?php endforeach; ?></div></section></main></body></html>