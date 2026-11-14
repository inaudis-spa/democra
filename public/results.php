<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Navigation;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ResultService;
$user=Authorization::requireRoles(['admin','organizer','witness']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canReviewElection($user,$electionId,(int)$election['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}
$certificate=null;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if($user['role']==='witness'){http_response_code(403);exit('El Ministro de Fe puede revisar resultados, pero no certificarlos desde esta pantalla.');}
    if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
    else try{$certificate=ResultService::certify($electionId,(int)$user['id']);}catch(Throwable $e){$error=$e->getMessage();}
}
$summary=$certificate['results']??ResultService::summary($electionId);
$total=(int)($summary['participation']['total']??0);$voted=(int)($summary['participation']['voted']??0);$pct=$total>0?round(($voted/$total)*100,1):0;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultados | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/results-help.css"></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'results', $electionId, $election['title']); ?>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="<?=$user['role']==='witness'?'/fiscalizacion.php?election_id='.$electionId:'/control.php?election_id='.$electionId?>">← Volver al proceso</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Resultados e integridad</div><h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$election['status']==='closed'?'Escrutinio definitivo y evidencia verificable del proceso.':'Durante la votación solo se muestra participación; el escrutinio se habilita después del cierre.'?></p></div><?php if($user['role']!=='witness'&&$election['status']==='closed'):?><div class="v-result-actions"><form method="post" action="/acta.php?election_id=<?=$electionId?>" onsubmit="return confirm('¿Generar el acta definitiva?');"><?=Csrf::input()?><button class="v-btn v-btn-outline">↓ Acta PDF</button></form><div class="v-certify-wrap"><details class="v-info-popover"><summary aria-label="Información sobre la certificación">i</summary><div class="v-info-box"><strong>¿Qué hace esta certificación?</strong><p>Genera una huella digital SHA-256 inmutable de los resultados definitivos.</p></div></details><form method="post"><?=Csrf::input()?><button class="v-btn v-btn-orange">✓ Certificar SHA-256</button></form></div></div><?php elseif($user['role']==='witness'):?><span class="v-status v-status-open">Solo lectura fiscalizadora</span><?php else:?><span class="v-status v-status-draft">Escrutinio bloqueado</span><?php endif;?></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($certificate):?><div class="v-alert v-alert-ok"><strong>✓ Elección certificada</strong><div class="v-hash" style="margin-top:9px"><?=htmlspecialchars($certificate['sha256'])?></div></div><?php endif;?>
<section class="v-grid-3" style="margin-bottom:22px"><div class="v-card v-stat"><strong><?=$total?></strong><span class="v-muted">Habilitados</span></div><div class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votos emitidos</span></div><div class="v-card v-stat orange"><strong><?=$pct?>%</strong><span class="v-muted">Participación</span></div></section>
<?php if($election['status']==='closed'):?><div class="v-stack"><?php foreach($summary['questions'] as $q):?><section class="v-card v-pad"><h2 style="margin-top:0"><?=htmlspecialchars($q['text'])?></h2><?php $max=0;foreach($q['options'] as $o)$max=max($max,(float)$o['weighted_votes']);foreach($q['options'] as $o):$value=(float)$o['weighted_votes'];$width=$max>0?round(($value/$max)*100):0;?><div style="margin:18px 0"><div class="v-section-head" style="font-size:13px"><strong><?=htmlspecialchars($o['label'])?></strong><span class="v-muted"><?=htmlspecialchars((string)$o['votes'])?> voto(s) · peso <?=htmlspecialchars((string)$o['weighted_votes'])?></span></div><div class="v-bar"><span style="width:<?=$width?>%"></span></div></div><?php endforeach;?></section><?php endforeach;?></div><?php else:?><div class="v-alert v-alert-warn"><strong>Resultados protegidos.</strong> El detalle del escrutinio permanecerá oculto hasta cerrar la votación.</div><?php endif;?>
<section class="v-card v-pad" style="margin-top:22px"><div class="v-kicker" style="color:var(--teal)">Centro de confianza</div><h2>Integridad del proceso</h2><div class="v-grid-3"><div>✓ Padrón controlado</div><div>✓ Voto único</div><div>✓ Evidencia SHA-256</div></div><div style="margin-top:18px"><a class="v-btn v-btn-outline" href="/verify.php">Verificar un acta →</a></div></section>
</main></div></div></body></html>
