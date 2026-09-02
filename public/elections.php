<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;

$user=Authorization::requireRoles(['admin','organizer']);
$error=null;
$organizations=($user['role']==='admin')?OrganizationService::all():[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!Csrf::validate($_POST['_csrf']??null)) {
        $error='Solicitud inválida.';
    } else {
        try {
            if ($user['role']==='organizer') $_POST['organization_id']=$user['organization_id'];
            $id=ElectionService::create($_POST,(int)$user['id']);
            header('Location: /election.php?id='.$id); exit;
        } catch (Throwable $e) { $error=$e->getMessage(); }
    }
}
$elections=ElectionService::allForUser($user);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votaciones | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-950 text-slate-100 min-h-screen"><main class="max-w-7xl mx-auto p-6">
<nav class="mb-8 flex flex-wrap gap-4 text-sm"><a href="/dashboard.php" class="text-cyan-400">Dashboard</a><?php if($user['role']==='admin'): ?><a href="/organizations.php">Organizaciones</a><?php endif; ?><a href="/audit.php">Auditoría</a><a href="/logout.php" class="text-red-300">Salir</a></nav>
<div class="flex items-center justify-between mb-6"><div><p class="text-cyan-400 text-sm">DEMO MVP</p><h1 class="text-3xl font-semibold">Votaciones</h1></div></div>
<?php if($error): ?><div class="mb-5 p-3 rounded bg-red-950 border border-red-800"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="grid lg:grid-cols-3 gap-6"><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-4">Nueva votación</h2><form method="post" class="space-y-3"><?=Csrf::input()?><?php if($user['role']==='admin'): ?><select name="organization_id" required class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><option value="">Organización</option><?php foreach($organizations as $o): ?><option value="<?=$o['id']?>"><?=htmlspecialchars($o['name'])?></option><?php endforeach; ?></select><?php endif; ?><input name="title" required placeholder="Título de la votación" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><textarea name="description" placeholder="Descripción" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"></textarea><select name="voting_mode" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><option value="secret">Voto secreto</option><option value="public">Voto público</option></select><label class="text-sm block">Inicio<input type="datetime-local" name="starts_at" class="mt-1 w-full bg-slate-950 border border-slate-700 rounded-lg p-3"></label><label class="text-sm block">Término<input type="datetime-local" name="ends_at" class="mt-1 w-full bg-slate-950 border border-slate-700 rounded-lg p-3"></label><label class="flex gap-2 text-sm"><input type="checkbox" name="allow_weighted_vote" value="1">Permitir voto ponderado</label><button class="w-full bg-cyan-400 text-slate-950 rounded-lg p-3 font-semibold">Crear votación</button></form></section>
<section class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5"><div class="space-y-3"><?php if(!$elections): ?><p class="text-slate-400">Aún no hay votaciones.</p><?php endif; ?><?php foreach($elections as $e): ?><a href="/election.php?id=<?=$e['id']?>" class="block rounded-xl border border-slate-800 bg-slate-950 p-4 hover:border-cyan-600"><div class="flex justify-between gap-4"><div><h3 class="font-semibold"><?=htmlspecialchars($e['title'])?></h3><p class="text-sm text-slate-400"><?=htmlspecialchars($e['organization_name'])?></p></div><span class="text-xs uppercase text-cyan-300"><?=htmlspecialchars($e['status'])?></span></div></a><?php endforeach; ?></div></section></div>
</main></body></html>