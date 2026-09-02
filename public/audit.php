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
    $stmt=$pdo->prepare('SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN elections e ON a.entity_type="election" AND e.id=a.entity_id WHERE u.organization_id=:org OR e.organization_id=:org ORDER BY a.created_at DESC LIMIT 200');
    $stmt->execute(['org'=>(int)$user['organization_id']]);
}
$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auditoría | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="max-w-7xl mx-auto p-6"><nav class="mb-8 flex gap-4 text-sm"><a href="/dashboard.php" class="text-cyan-400">Dashboard</a><a href="/elections.php">Votaciones</a><?php if($user['role']==='admin'): ?><a href="/organizations.php">Organizaciones</a><?php endif; ?><a href="/logout.php" class="text-red-300">Salir</a></nav><div class="mb-6"><p class="text-cyan-400 text-sm">TRAZABILIDAD</p><h1 class="text-3xl font-semibold">Auditoría</h1><p class="text-slate-400 mt-2">Últimos 200 eventos registrados por la plataforma.</p></div><section class="bg-slate-900 border border-slate-800 rounded-2xl overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-500"><tr><th class="text-left p-3">Fecha</th><th class="text-left">Evento</th><th class="text-left">Entidad</th><th class="text-left">Usuario</th><th class="text-left">IP</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr class="border-t border-slate-800"><td class="p-3 whitespace-nowrap"><?=htmlspecialchars($log['created_at'])?></td><td class="p-3 text-cyan-300"><?=htmlspecialchars($log['event_type'])?></td><td class="p-3"><?=htmlspecialchars($log['entity_type'])?> #<?=htmlspecialchars((string)$log['entity_id'])?></td><td class="p-3"><?=htmlspecialchars((string)$log['user_name'])?></td><td class="p-3 text-slate-500"><?=htmlspecialchars((string)$log['ip_address'])?></td></tr><?php endforeach; ?></tbody></table></section></main></body></html>