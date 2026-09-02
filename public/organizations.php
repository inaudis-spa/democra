<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\OrganizationService;

$user=Authorization::requireRoles(['admin']);
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!Csrf::validate($_POST['_csrf']??null)) {
        $error='Solicitud inválida. Recarga la página e intenta nuevamente.';
    } else {
        try {
            OrganizationService::create($_POST);
            header('Location: /organizations.php?ok=1'); exit;
        } catch (Throwable $e) { $error=$e->getMessage(); }
    }
}
$organizations=OrganizationService::all();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Organizaciones | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen"><main class="max-w-6xl mx-auto p-6">
<nav class="mb-8 flex gap-4 text-sm"><a href="/dashboard.php" class="text-cyan-400">Dashboard</a><a href="/elections.php">Votaciones</a><a href="/audit.php">Auditoría</a><a href="/logout.php" class="text-red-300">Salir</a></nav>
<h1 class="text-3xl font-semibold mb-6">Organizaciones</h1>
<?php if(isset($_GET['ok'])): ?><div class="mb-5 p-3 rounded bg-emerald-950 border border-emerald-800">Organización creada.</div><?php endif; ?>
<?php if($error): ?><div class="mb-5 p-3 rounded bg-red-950 border border-red-800"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="grid md:grid-cols-3 gap-6"><section class="md:col-span-1 bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-4">Nueva organización</h2><form method="post" class="space-y-3"><?=Csrf::input()?><input name="name" required placeholder="Nombre" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="tax_id" placeholder="RUT / ID tributario" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="slug" required placeholder="slug-organizacion" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><button class="w-full bg-cyan-400 text-slate-950 rounded-lg p-3 font-semibold">Crear</button></form></section>
<section class="md:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-400"><tr><th class="text-left p-2">Nombre</th><th>RUT</th><th>Estado</th></tr></thead><tbody><?php foreach($organizations as $o): ?><tr class="border-t border-slate-800"><td class="p-2"><?=htmlspecialchars($o['name'])?></td><td class="text-center"><?=htmlspecialchars((string)$o['tax_id'])?></td><td class="text-center"><?=htmlspecialchars($o['status'])?></td></tr><?php endforeach; ?></tbody></table></section></div>
</main></body></html>