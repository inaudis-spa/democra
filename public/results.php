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
    if($user['role']!=='admin'){http_response_code(403);exit('Solo el administrador puede certificar resultados.');}
    if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
    else try{$certificate=ResultService::certify($electionId,(int)$user['id']);}catch(Throwable $e){$error=$e->getMessage();}
}
$summary=$certificate['results']??ResultService::summary($electionId);
$total=(int)($summary['participation']['total']??0);$voted=(int)($summary['participation']['voted']??0);$pct=$total>0?round(($voted/$total)*100,1):0;
$identifiedVotes=[];
$showIdentified=isset($_GET['show_identified'])&&$_GET['show_identified']==='1';
if($showIdentified&&$election['status']==='closed'&&$election['voting_mode']==='public'&&in_array($user['role'],['admin','organizer'],true)){
    $identifiedVotes=ResultService::identifiedVotes($electionId,(int)$user['id']);
}
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultados | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/results-help.css"></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'results', $electionId, $election['title']); ?>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="<?=$user['role']==='witness'?'/fiscalizacion.php?election_id='.$electionId:'/control.php?election_id='.$electionId?>">← Volver al proceso</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Resultados e integridad</div><h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$election['status']==='closed'?'Escrutinio definitivo y evidencia verificable del proceso.':'Durante la votación solo se muestra participación; el escrutinio se habilita después del cierre.'?></p></div><?php if($user['role']==='admin'&&$election['status']==='closed'):?><div class="v-result-actions"><form method="post" action="/acta.php?election_id=<?=$electionId?>" data-confirm="¿Generar el acta definitiva?"><?=Csrf::input()?><button class="v-btn v-btn-outline">↓ Acta PDF</button></form><div class="v-certify-wrap"><details class="v-info-popover"><summary aria-label="Información sobre la certificación">i</summary><div class="v-info-box"><strong>¿Qué hace esta certificación?</strong><p>Genera una huella digital SHA-256 inmutable de los resultados definitivos.</p></div></details><form method="post"><?=Csrf::input()?><button class="v-btn v-btn-orange">✓ Certificar SHA-256</button></form></div></div><?php elseif($user['role']!=='admin'):?><span class="v-status v-status-open">Solo lectura</span><?php else:?><span class="v-status v-status-draft">Escrutinio bloqueado</span><?php endif;?></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($certificate):?><div class="v-alert v-alert-ok"><strong>✓ Elección certificada</strong><div class="v-hash" style="margin-top:9px"><?=htmlspecialchars($certificate['sha256'])?></div></div><?php endif;?>
<section class="v-grid-3" style="margin-bottom:22px"><div class="v-card v-stat"><strong><?=$total?></strong><span class="v-muted">Habilitados</span></div><div class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votos emitidos</span></div><div class="v-card v-stat orange"><strong><?=$pct?>%</strong><span class="v-muted">Participación</span></div></section>
<?php if($election['status']==='closed'):?><div class="v-stack"><?php foreach($summary['questions'] as $q):?><section class="v-card v-pad"><h2 style="margin-top:0"><?=htmlspecialchars($q['text'])?></h2><?php $max=0;foreach($q['options'] as $o)$max=max($max,(float)$o['weighted_votes']);foreach($q['options'] as $o):$value=(float)$o['weighted_votes'];$width=$max>0?round(($value/$max)*100):0;?><div style="margin:18px 0"><div class="v-section-head" style="font-size:13px"><strong><?=htmlspecialchars($o['label'])?></strong><span class="v-muted"><?=htmlspecialchars((string)$o['votes'])?> voto(s) · peso <?=htmlspecialchars((string)$o['weighted_votes'])?></span></div><div class="v-bar"><span style="width:<?=$width?>%"></span></div></div><?php endforeach;?></section><?php endforeach;?></div><?php else:?><div class="v-alert v-alert-warn"><strong>Resultados protegidos.</strong> El detalle del escrutinio permanecerá oculto hasta cerrar la votación.</div><?php endif;?>
<section class="v-card v-pad" style="margin-top:22px"><div class="v-kicker" style="color:var(--teal)">Centro de confianza</div><h2>Integridad del proceso</h2><div class="v-grid-3"><div>✓ Padrón controlado</div><div>✓ Voto único</div><div>✓ Evidencia SHA-256</div></div><div style="margin-top:18px"><a class="v-btn v-btn-outline" href="/verify.php">Verificar un acta →</a></div></section>
<?php if($election['status']==='closed'&&$election['voting_mode']==='public'):?>
<section class="v-card v-pad" style="margin-top:22px">
<div class="v-section-head"><div><div class="v-kicker">Votación identificada</div><h2>Detalle por votante</h2></div><span class="v-status v-status-open">Acceso auditado</span></div>
<?php if(in_array($user['role'],['admin','organizer'],true)):?>
<p class="v-muted">Este proceso fue informado como público. La consulta relaciona cada participante con sus selecciones y queda registrada en auditoría.</p>
<?php if(!$showIdentified):?><a class="v-btn v-btn-primary" href="/results.php?election_id=<?=$electionId?>&show_identified=1">Ver qué votó cada participante</a><?php else:?><a class="v-link" href="/results.php?election_id=<?=$electionId?>">Ocultar detalle nominal</a>
<div class="v-table-wrap"><table class="v-table"><thead><tr><th>Votante</th><th>RUT / correo</th><th>Pregunta</th><th>Selección</th><th>Fecha</th></tr></thead><tbody><?php foreach($identifiedVotes as $vote):?><tr><td><strong><?=htmlspecialchars($vote['voter_name'])?></strong></td><td><?=htmlspecialchars($vote['rut']?:$vote['email'])?></td><td><?=htmlspecialchars($vote['question_text'])?></td><td><strong><?=htmlspecialchars($vote['option_label'])?></strong></td><td><?=htmlspecialchars(date('d-m-Y H:i',strtotime((string)$vote['submitted_at'])))?></td></tr><?php endforeach;?><?php if(!$identifiedVotes):?><tr><td colspan="5" class="v-muted">No existen votos identificados registrados para este proceso.</td></tr><?php endif;?></tbody></table></div>
<?php endif;?>
<?php else:?><div class="v-alert v-alert-warn">El detalle nominal está restringido a administradores y organizadores responsables.</div><?php endif;?>
</section>
<?php endif;?>
</main></div></div><script src="/assets/quorum-confirm.js" defer></script></body></html>
