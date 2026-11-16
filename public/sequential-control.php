<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\SequentialQuestionService;
use InaudisVote\Services\ResultService;
$user=Authorization::requireRoles(['admin','organizer']);
$id=(int)($_GET['election_id']??0);
$e=ElectionService::find($id);
if(!$e || $e['question_flow']!=='sequential' || !Authorization::canReviewElection($user,$id,(int)$e['organization_id'])){http_response_code(404);exit('Proceso no disponible.');}
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
    else try{
        SequentialQuestionService::change($id,(int)($_POST['question_id']??0),(string)($_POST['action']??''),(int)$user['id']);
        header('Location: /sequential-control.php?election_id='.$id.'&updated=1');exit;
    }catch(Throwable $ex){$error=$ex->getMessage();}
}
$questions=ElectionService::questions($id);
$summary=ResultService::summary($id);
$results=[];foreach($summary['questions'] as $q)$results[(int)$q['id']]=$q;
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Control de preguntas | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"><style>
.sq-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.sq-option{margin:12px 0}.sq-bar{height:13px;background:#e7edf3;border-radius:12px;overflow:hidden}.sq-bar span{display:block;height:100%;background:#12a6a0}.sq-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.sq-controls{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}.sq-controls button{min-height:52px;font-weight:700}.sq-label{font-size:13px;color:#586579}@media(max-width:850px){.sq-grid{grid-template-columns:1fr}}
</style></head><body class="vota-ui"><div class="v-shell"><?php Navigation::sidebar($user,'control',$id,$e['title']);?><div class="v-main"><header class="v-topbar"><a class="v-link" href="/control.php?election_id=<?=$id?>">← Centro de control</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Asamblea · Control secuencial</div><h1 class="v-title"><?=htmlspecialchars($e['title'])?></h1><p class="v-subtitle">Habilita una pregunta, espera la participación y ciérrala para proyectar sus resultados.</p></div><a class="v-btn v-btn-primary" target="_blank" href="/display.php?election_id=<?=$id?>">Pantalla de sala ↗</a></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if(isset($_GET['updated'])):?><div class="v-alert v-alert-ok">Cambio aplicado correctamente.</div><?php endif;?>
<?php if($e['status']!=='open'):?><div class="v-alert v-alert-warn">El administrador debe abrir primero la votación desde el Centro de Control. Estado: <?=htmlspecialchars($e['status'])?>.</div><?php endif;?>
<div class="sq-grid"><?php foreach($questions as $index=>$q):$state=$q['voting_status'];$count=SequentialQuestionService::participation((int)$q['id']);$res=$results[(int)$q['id']]??['options'=>[]];?><section class="v-card v-pad">
<div class="sq-head"><div class="v-kicker">Pregunta <?=$index+1?> de <?=count($questions)?></div><span class="v-status <?=$state==='open'?'v-status-open':($state==='closed'?'v-status-closed':'v-status-draft')?>"><?=htmlspecialchars(['pending'=>'Pendiente','open'=>'En votación','closed'=>'Cerrada'][$state]??$state)?></span></div>
<h2><?=htmlspecialchars($q['text'])?></h2><p class="sq-label"><?=$count?> respuesta(s) recibida(s)</p>
<?php if($state==='closed'):?>
<?php foreach($res['options'] as $o):$pct=$count>0?round(100*(int)$o['votes']/$count,1):0;?><div class="sq-option"><div class="sq-head"><strong><?=htmlspecialchars($o['label'])?></strong><strong><?=$pct?>% (<?=(int)$o['votes']?>)</strong></div><div class="sq-bar"><span style="width:<?=min(100,$pct)?>%"></span></div></div><?php endforeach;?>
<?php else:?><?php foreach($q['options'] as $o):?><p class="v-muted">○ <?=htmlspecialchars($o['label'])?></p><?php endforeach;?><?php endif;?>
<div class="sq-controls"><?php if($e['status']==='open' && $state==='pending'):?><form method="post"><?=Csrf::input()?><input type="hidden" name="question_id" value="<?=$q['id']?>"><button class="v-btn v-btn-orange" name="action" value="open" onclick="return confirm('¿Habilitar esta pregunta?');">▶ Iniciar pregunta</button></form><?php elseif($e['status']==='open' && $state==='open'):?><form method="post"><?=Csrf::input()?><input type="hidden" name="question_id" value="<?=$q['id']?>"><button class="v-btn v-btn-danger" name="action" value="close" onclick="return confirm('¿Cerrar definitivamente esta pregunta?');">■ Cerrar y mostrar resultados</button></form><?php endif;?></div></section><?php endforeach;?></div>
<p class="v-muted">La información se actualiza cada 5 segundos. Los resultados solo se muestran después del cierre de cada pregunta.</p>
</main></div></div><script>setTimeout(()=>{if(!document.hidden)location.reload()},5000)</script></body></html>