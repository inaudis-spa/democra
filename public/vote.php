<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\VotingService;

$token=(string)($_GET['token']??$_POST['token']??'');
$context=$token!==''?VotingService::resolveToken($token):null;
$error=null;$receipt=null;$questions=[];
if($context){$questions=ElectionService::questions((int)$context['election_id']);}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida. Recarga la página.';}
    else{
        try{$receipt=VotingService::submit($token,(array)($_POST['answers']??[]));$context=VotingService::resolveToken($token);}catch(Throwable $e){$error=$e->getMessage();}
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votar | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="max-w-3xl mx-auto p-6 py-10"><div class="mb-8"><p class="text-cyan-400 text-sm tracking-widest">INAUDIS VOTE</p><h1 class="text-3xl font-semibold mt-2"><?=htmlspecialchars((string)($context['election_title']??'Acceso a votación'))?></h1><?php if($context): ?><p class="text-slate-400 mt-2">Votante habilitado: <?=htmlspecialchars($context['voter_name'])?></p><?php endif; ?></div>
<?php if(!$context): ?><div class="p-5 rounded-2xl bg-red-950 border border-red-800">Enlace de votación inválido.</div><?php elseif($receipt): ?><div class="p-6 rounded-2xl bg-emerald-950 border border-emerald-800"><h2 class="text-xl font-semibold">Voto registrado correctamente</h2><p class="text-sm text-emerald-200 mt-2">Comprobante SHA-256:</p><code class="block mt-2 break-all text-xs bg-slate-950 p-3 rounded"><?=htmlspecialchars($receipt)?></code><p class="text-xs text-slate-400 mt-3">Este comprobante no contiene tu identidad ni la alternativa elegida.</p></div><?php elseif(!VotingService::canVote($context)): ?><div class="p-5 rounded-2xl bg-amber-950 border border-amber-800">Esta invitación no está habilitada para votar. Verifica que el proceso esté abierto y que no hayas votado previamente.</div><?php else: ?>
<?php if($error): ?><div class="mb-5 p-4 rounded-xl bg-red-950 border border-red-800"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post" class="space-y-5"><?=Csrf::input()?><input type="hidden" name="token" value="<?=htmlspecialchars($token,ENT_QUOTES)?>"><?php foreach($questions as $q): ?><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-4"><?=htmlspecialchars($q['text'])?></h2><div class="space-y-3"><?php foreach($q['options'] as $o): ?><label class="flex gap-3 items-start rounded-xl border border-slate-800 p-3 hover:border-cyan-700"><input class="mt-1" type="<?=$q['question_type']==='multiple'?'checkbox':'radio'?>" name="answers[<?=$q['id']?>][]" value="<?=$o['id']?>" <?=((int)$q['min_choices']>0)?'required':''?>><span><?=htmlspecialchars($o['label'])?></span></label><?php endforeach; ?></div><?php if($q['question_type']==='multiple'): ?><p class="text-xs text-slate-500 mt-3">Selecciona entre <?=$q['min_choices']?> y <?=$q['max_choices']?> alternativas.</p><?php endif; ?></section><?php endforeach; ?><div class="p-4 rounded-xl bg-slate-900 border border-slate-800 text-sm text-slate-400">Al confirmar, el sistema registra una papeleta separada de tu identidad y bloquea esta invitación para impedir doble voto.</div><button class="w-full bg-cyan-400 text-slate-950 rounded-xl p-4 font-semibold text-lg">Confirmar voto</button></form><?php endif; ?></main></body></html>