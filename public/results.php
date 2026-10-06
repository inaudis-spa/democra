<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ResultService;
$user=Authorization::requireRoles(['admin','organizer','witness']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}
$certificate=null;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
    else try{$certificate=ResultService::certify($electionId,(int)$user['id']);}catch(Throwable $e){$error=$e->getMessage();}
}
$summary=$certificate['results']??ResultService::summary($electionId);
$total=(int)($summary['participation']['total']??0);$voted=(int)($summary['participation']['voted']??0);$pct=$total>0?round(($voted/$total)*100,1):0;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultados | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/results-help.css"></head><body class="vota-ui"><div class="v-shell">
<aside class="v-sidebar"><div class="v-brand"><img class="v-brandmark" src="/assets/quorum-mark.svg" alt=""><span><span class="v-brand-name">QUORUM<small>by INAUDIS</small></span></span></div><nav class="v-nav"><a href="/dashboard.php">⌂ &nbsp; Inicio</a><a href="/elections.php">✓ &nbsp; Votaciones</a><a href="/census.php?election_id=<?=$electionId?>">♙ &nbsp; Padrón</a><a class="active" href="#">▥ &nbsp; Resultados</a><a href="/audit.php">◷ &nbsp; Auditoría</a></nav><div class="v-sidebar-foot"><?=htmlspecialchars($user['name'])?></div></aside>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="/election.php?id=<?=$electionId?>">← Volver al proceso</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Resultados e integridad</div><h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1><div class="v-accent-line"></div><p class="v-subtitle">Participación, escrutinio y evidencia verificable del proceso.</p></div><div class="v-result-actions"><a class="v-btn v-btn-outline" href="/acta.php?election_id=<?=$electionId?>">↓ Acta PDF</a><div class="v-certify-wrap"><details class="v-info-popover"><summary aria-label="Información sobre la certificación">i</summary><div class="v-info-box"><strong>¿Qué hace esta certificación?</strong><p>Genera una huella digital SHA-256 de los resultados actuales. Sirve para comprobar posteriormente que la información no fue modificada.</p><p><b>Cuándo usarla:</b> cuando la votación haya finalizado y los resultados sean definitivos.</p><p class="v-info-note">Es una evidencia de integridad técnica; no reemplaza una certificación legal.</p></div></details><form method="post"><?=Csrf::input()?><button class="v-btn v-btn-orange">✓ Certificar SHA-256</button></form></div></div></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($certificate):?><div class="v-alert v-alert-ok"><strong>✓ Elección certificada</strong><div class="v-hash" style="margin-top:9px"><?=htmlspecialchars($certificate['sha256'])?></div></div><?php endif;?>
<section class="v-grid-3" style="margin-bottom:22px"><div class="v-card v-stat"><strong><?=$total?></strong><span class="v-muted">Habilitados</span></div><div class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votos emitidos</span></div><div class="v-card v-stat orange"><strong><?=$pct?>%</strong><span class="v-muted">Participación</span></div></section>
<div class="v-stack"><?php foreach($summary['questions'] as $q):?><section class="v-card v-pad"><h2 style="margin-top:0"><?=htmlspecialchars($q['text'])?></h2><?php $max=0;foreach($q['options'] as $o)$max=max($max,(float)$o['weighted_votes']);foreach($q['options'] as $o):$value=(float)$o['weighted_votes'];$width=$max>0?round(($value/$max)*100):0;?><div style="margin:18px 0"><div class="v-section-head" style="font-size:13px"><strong><?=htmlspecialchars($o['label'])?></strong><span class="v-muted"><?=htmlspecialchars((string)$o['votes'])?> voto(s) · peso <?=htmlspecialchars((string)$o['weighted_votes'])?></span></div><div class="v-bar"><span style="width:<?=$width?>%"></span></div></div><?php endforeach;?></section><?php endforeach;?></div>
<section class="v-card v-pad" style="margin-top:22px"><div class="v-kicker" style="color:var(--teal)">Centro de confianza</div><h2>Integridad del proceso</h2><div class="v-grid-3"><div>✓ Padrón controlado</div><div>✓ Voto único</div><div>✓ Evidencia SHA-256</div></div></section>
</main></div></div></body></html>
