<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Navigation;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Database;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;

$user=Authorization::requireRoles(['admin','organizer','witness']);
$error=null;
$canCreate=in_array($user['role'],['admin','organizer'],true);
$organizations=$user['role']==='admin'?OrganizationService::all():[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canCreate){http_response_code(403);exit('Acceso no autorizado.');}
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida.';}
    else try{
        if($user['role']==='organizer')$_POST['organization_id']=$user['organization_id'];
        $id=ElectionService::create($_POST,(int)$user['id']);
        header('Location: /election.php?id='.$id);exit;
    }catch(Throwable $exception){$error=$exception->getMessage();}
}

if($user['role']==='witness'){
    $stmt=Database::connection()->prepare('SELECT e.*,o.name organization_name FROM elections e JOIN organizations o ON o.id=e.organization_id JOIN election_witnesses ew ON ew.election_id=e.id WHERE ew.user_id=:uid ORDER BY e.created_at DESC');
    $stmt->execute(['uid'=>$user['id']]);$elections=$stmt->fetchAll(PDO::FETCH_ASSOC);
}else{$elections=ElectionService::allForUser($user);}

function electionStatusClass(string $status):string{return $status==='open'?'v-status-open':($status==='closed'?'v-status-closed':'v-status-draft');}
function electionStatusLabel(string $status):string{return ['draft'=>'Borrador','scheduled'=>'Programada','open'=>'En vivo','closed'=>'Finalizada','cancelled'=>'Cancelada'][$status]??$status;}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votaciones | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"></head>
<body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'elections'); ?>
<div class="v-main"><header class="v-topbar"><span class="v-muted"><?=$user['role']==='witness'?'Procesos asignados':'Gestión electoral'?></span><span style="flex:1"></span><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker"><?=$user['role']==='witness'?'Fiscalización':'Gestión electoral'?></div><h1 class="v-title"><?=$user['role']==='witness'?'Mis procesos asignados':'Votaciones'?></h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$user['role']==='witness'?'Accede únicamente a las votaciones donde debes fiscalizar y certificar hitos.':'Crea, prepara y opera cada proceso desde un centro de control.'?></p></div><?php if($canCreate):?><button class="v-btn v-btn-orange" type="button" onclick="const panel=document.getElementById('nueva-votacion');panel.open=true;panel.scrollIntoView({behavior:'smooth'});">＋ Nueva votación</button><?php endif;?></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>

<?php if($canCreate):?><details class="v-disclosure" id="nueva-votacion"><summary>Crear una nueva votación</summary><div><form method="post" class="v-stack"><?=Csrf::input()?><?php if($user['role']==='admin'):?><label class="v-label">Cliente<select name="organization_id" required class="v-field"><option value="">Selecciona una organización</option><?php foreach($organizations as $organization):?><option value="<?=$organization['id']?>"><?=htmlspecialchars($organization['name'])?></option><?php endforeach;?></select></label><?php endif;?><label class="v-label">Título<input class="v-field" name="title" required placeholder="Ej. Elección de Directorio 2027"></label><label class="v-label">Descripción<textarea class="v-field" name="description" rows="3" placeholder="Objetivo y contexto del proceso"></textarea></label><div class="v-grid-2" style="grid-template-columns:1fr 1fr"><label class="v-label">Inicio<input class="v-field" type="datetime-local" name="starts_at"></label><label class="v-label">Término<input class="v-field" type="datetime-local" name="ends_at"></label></div><label class="v-label">Modalidad<select class="v-field" name="voting_mode"><option value="secret">Voto secreto</option><option value="public">Voto público</option></select></label><label class="v-check"><input type="checkbox" name="allow_weighted_vote" value="1"> Permitir voto ponderado</label><div><button class="v-btn v-btn-orange">Crear y configurar →</button></div></form></div></details><?php endif;?>

<section class="v-page-module"><header><div><h2><?=$user['role']==='witness'?'Votaciones para fiscalizar':'Procesos registrados'?></h2><p><?=count($elections)?> proceso(s) disponibles para tu perfil.</p></div><span><?=count($elections)?></span></header><div class="v-page-module-body"><div class="v-process-grid">
<?php if(!$elections):?><div class="v-empty">No tienes votaciones disponibles en este momento.</div><?php endif;?>
<?php foreach($elections as $election):$target=$user['role']==='witness'?'/fiscalizacion.php?election_id='.$election['id']:'/control.php?election_id='.$election['id'];?><a class="v-process-card" href="<?=$target?>"><header><div><div class="v-kicker"><?=htmlspecialchars($election['organization_name'])?></div><h3><?=htmlspecialchars($election['title'])?></h3></div><span class="v-status <?=electionStatusClass($election['status'])?>"><?=htmlspecialchars(electionStatusLabel($election['status']))?></span></header><p class="v-muted"><?=htmlspecialchars((string)($election['description']??'Sin descripción'))?></p><footer><span><?=htmlspecialchars((string)($election['starts_at']??'Fecha por definir'))?></span><b><?=$user['role']==='witness'?'Fiscalizar →':'Abrir centro de control →'?></b></footer></a><?php endforeach;?>
</div></div></section>
</main></div></div></body></html>
