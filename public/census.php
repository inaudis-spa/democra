<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ElectionService;

$user=Authorization::requireRoles(['admin']);$electionId=(int)($_GET['election_id']??0);$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'],$electionId)){http_response_code(404);exit('Votación no encontrada.');}
$editable=in_array($election['status'],['draft','scheduled'],true)&&empty($election['census_locked_at']);
$error=null;$message=null;$inviteUrl=null;$otp=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
    else try{
        $action=(string)($_POST['action']??'');
        if($action==='add'){CensusService::add($electionId,$_POST);$message='Votante agregado.';}
        elseif($action==='import'){
            $csv='';if(isset($_FILES['csv_file'])&&($_FILES['csv_file']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){$size=(int)($_FILES['csv_file']['size']??0);if($size<=0||$size>2*1024*1024)throw new RuntimeException('El archivo CSV debe pesar entre 1 byte y 2 MB.');if(strtolower(pathinfo((string)$_FILES['csv_file']['name'],PATHINFO_EXTENSION))!=='csv')throw new RuntimeException('Solo se permiten archivos .csv.');$csv=(string)file_get_contents((string)$_FILES['csv_file']['tmp_name']);}else{$csv=(string)($_POST['csv']??'');}
            if(trim($csv)==='')throw new RuntimeException('Selecciona un CSV o pega su contenido.');$result=CensusService::importCsv($electionId,$csv);$message='Importados: '.$result['created'].'. Errores: '.count($result['errors']);if($result['errors'])$error=implode(' | ',array_slice($result['errors'],0,3));
        }elseif($action==='invite'){$inv=CensusService::generateInvitation($electionId,(int)($_POST['member_id']??0));$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$inviteUrl=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').'/vote.php?token='.$inv['token'];$otp=$inv['otp'];$message='Invitación demo generada para '.$inv['email'].'.';}
    }catch(Throwable $exception){$error=$exception->getMessage();}
}
$members=CensusService::list($electionId);$voted=count(array_filter($members,fn($member)=>(bool)$member['has_voted']));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Participantes | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'census', $electionId, $election['title']); ?>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="/control.php?election_id=<?=$electionId?>">← Volver al centro de control</a><span style="flex:1"></span><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker">Padrón electoral</div><h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1><div class="v-accent-line"></div><p class="v-subtitle">Administra las personas habilitadas e invitaciones del proceso.</p></div><span class="v-status <?=$editable?'v-status-draft':'v-status-open'?>"><?=$editable?'Editable':'Padrón bloqueado'?></span></div>
<?php if($message):?><div class="v-alert v-alert-ok"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($inviteUrl):?><div class="v-alert" style="background:#eef6ff;border:1px solid #cfe2ff"><strong>Invitación demo</strong><div style="word-break:break-all;margin:8px 0"><?=htmlspecialchars($inviteUrl)?></div><strong style="color:var(--orange)">OTP: <?=htmlspecialchars((string)$otp)?></strong></div><?php endif;?>
<section class="v-stats" style="grid-template-columns:repeat(3,1fr)"><div class="v-card v-stat"><strong><?=count($members)?></strong><span class="v-muted">Habilitados</span></div><div class="v-card v-stat teal"><strong><?=$voted?></strong><span class="v-muted">Votaron</span></div><div class="v-card v-stat orange"><strong><?=count($members)-$voted?></strong><span class="v-muted">Pendientes</span></div></section>
<div class="v-grid-2"><?php if($editable):?><aside class="v-stack"><section class="v-card v-pad"><h2 style="margin-top:0">Agregar votante</h2><form method="post" class="v-stack"><?=Csrf::input()?><input type="hidden" name="action" value="add"><input class="v-field" name="rut" placeholder="RUT"><input class="v-field" name="name" required placeholder="Nombre"><input class="v-field" name="email" type="email" required placeholder="Email"><input class="v-field" name="phone" placeholder="Teléfono"><input class="v-field" name="vote_weight" type="number" step="0.0001" value="1"><input class="v-field" name="segment" placeholder="Segmento"><button class="v-btn v-btn-orange">Agregar persona</button></form></section><section class="v-card v-pad"><h2 style="margin-top:0">Importar CSV</h2><form method="post" enctype="multipart/form-data" class="v-stack"><?=Csrf::input()?><input type="hidden" name="action" value="import"><input type="file" name="csv_file" accept=".csv,text/csv"><textarea class="v-field" name="csv" rows="4" placeholder="O pega contenido CSV"></textarea><button class="v-btn v-btn-outline">Importar archivo</button></form></section></aside><?php else:?><aside><div class="v-alert v-alert-warn"><strong>Edición cerrada.</strong><br>La votación ya fue abierta y el padrón no admite altas ni importaciones.</div></aside><?php endif;?>
<section class="v-card v-pad v-table-wrap"><div class="v-section-head"><h2 style="margin:0">Personas habilitadas</h2><span class="v-muted"><?=count($members)?> registros</span></div><table class="v-table"><thead><tr><th>Nombre</th><th>Email</th><th>Peso</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach($members as $member):?><tr><td><strong><?=htmlspecialchars($member['name'])?></strong></td><td><?=htmlspecialchars($member['email'])?></td><td><?=htmlspecialchars((string)$member['vote_weight'])?></td><td><span class="v-status <?=$member['has_voted']?'v-status-open':'v-status-draft'?>"><?=$member['has_voted']?'Votó':'Pendiente'?></span></td><td><?php if(!$member['has_voted']&&$election['status']==='open'):?><form method="post"><?=Csrf::input()?><input type="hidden" name="action" value="invite"><input type="hidden" name="member_id" value="<?=$member['id']?>"><button class="v-btn v-btn-outline">Generar invitación</button></form><?php else:?><span class="v-muted">—</span><?php endif;?></td></tr><?php endforeach;?></tbody></table></section></div>
</main></div></div></body></html>
