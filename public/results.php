<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ResultService;

$user=Authorization::requireRoles(['admin','organizer']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}
$certificate=null;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida.';}
    else{
        try{$certificate=ResultService::certify($electionId,(int)$user['id']);}catch(Throwable $e){$error=$e->getMessage();}
    }
}
$summary=$certificate['results']??ResultService::summary($electionId);
$total=(int)($summary['participation']['total']??0);$voted=(int)($summary['participation']['voted']??0);$pct=$total>0?round(($voted/$total)*100,1):0;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultados | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="max-w-6xl mx-auto p-6">
<nav class="mb-8 flex flex-wrap gap-4 text-sm"><a href="/election.php?id=<?=$electionId?>" class="text-cyan-400">← Configuración</a><a href="/census.php?election_id=<?=$electionId?>">Censo</a><a href="/dashboard.php">Dashboard</a></nav>
<div class="flex flex-wrap items-start justify-between gap-5 mb-6"><div><p class="text-sm text-cyan-400">RESULTADOS</p><h1 class="text-3xl font-semibold"><?=htmlspecialchars($election['title'])?></h1><p class="text-slate-400 mt-2">Estado: <?=htmlspecialchars($election['status'])?></p></div><form method="post"><?=Csrf::input()?><button class="bg-cyan-400 text-slate-950 px-5 py-3 rounded-lg font-semibold">Generar certificado SHA-256</button></form></div>
<?php if($error): ?><div class="mb-4 p-4 bg-red-950 border border-red-800 rounded-xl"><?=htmlspecialchars($error)?></div><?php endif; ?><?php if($certificate): ?><div class="mb-5 p-4 bg-emerald-950 border border-emerald-800 rounded-xl"><strong>Certificado generado</strong><code class="block mt-2 text-xs break-all bg-slate-950 p-3 rounded"><?=htmlspecialchars($certificate['sha256'])?></code></div><?php endif; ?>
<div class="grid md:grid-cols-3 gap-4 mb-6"><div class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><p class="text-slate-400 text-sm">Censo</p><p class="text-3xl font-semibold mt-2"><?=$total?></p></div><div class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><p class="text-slate-400 text-sm">Votos emitidos</p><p class="text-3xl font-semibold mt-2"><?=$voted?></p></div><div class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><p class="text-slate-400 text-sm">Participación</p><p class="text-3xl font-semibold mt-2"><?=$pct?>%</p></div></div>
<div class="space-y-5"><?php foreach($summary['questions'] as $q): ?><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold text-lg mb-4"><?=htmlspecialchars($q['text'])?></h2><div class="space-y-3"><?php $max=0;foreach($q['options'] as $o){$max=max($max,(float)$o['weighted_votes']);}foreach($q['options'] as $o): $value=(float)$o['weighted_votes'];$width=$max>0?round(($value/$max)*100):0; ?><div><div class="flex justify-between text-sm mb-1"><span><?=htmlspecialchars($o['label'])?></span><span><?=htmlspecialchars((string)$o['votes'])?> voto(s) · peso <?=htmlspecialchars((string)$o['weighted_votes'])?></span></div><div class="h-2 bg-slate-800 rounded"><div class="h-2 bg-cyan-400 rounded" style="width: <?=$width?>%"></div></div></div><?php endforeach; ?></div></section><?php endforeach; ?></div>
</main></body></html>