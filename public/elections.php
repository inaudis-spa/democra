<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Navigation;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;

$user=Authorization::requireRoles(['admin','organizer','witness']);
$error=null;
$canCreate=$user['role']==='admin';
$organizations=$user['role']==='admin'?OrganizationService::all():[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canCreate){http_response_code(403);exit('Acceso no autorizado.');}
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida.';}
    else try{
        $id=ElectionService::create($_POST,(int)$user['id']);
        header('Location: /election.php?id='.$id);exit;
    }catch(Throwable $exception){$error=$exception->getMessage();}
}

$elections=ElectionService::allForUser($user);
$filterOrganizations=[];foreach($elections as $election){$filterOrganizations[(int)$election['organization_id']]=$election['organization_name'];}asort($filterOrganizations,SORT_NATURAL|SORT_FLAG_CASE);

function electionStatusClass(string $status):string{return $status==='open'?'v-status-open':($status==='closed'?'v-status-closed':'v-status-draft');}
function electionStatusLabel(string $status):string{return ['draft'=>'Borrador','scheduled'=>'Programada','open'=>'En vivo','closed'=>'Finalizada','cancelled'=>'Cancelada'][$status]??$status;}
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votaciones | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css?v=d79dcf91"><link rel="stylesheet" href="/assets/democra.css"><script src="/assets/elections.js?v=4f8b672c" defer></script></head>
<body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user, 'elections'); ?>
<div class="v-main"><header class="v-topbar"><span class="v-muted"><?=$user['role']==='admin'?'Gestión electoral':'Procesos asignados'?></span><span style="flex:1"></span><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<div class="v-headrow"><div><div class="v-kicker"><?=$user['role']==='admin'?'Gestión electoral':($user['role']==='witness'?'Fiscalización':'Consulta de votaciones')?></div><h1 class="v-title"><?=$user['role']==='admin'?'Votaciones':'Mis procesos asignados'?></h1><div class="v-accent-line"></div><p class="v-subtitle"><?=$user['role']==='witness'?'Accede únicamente a las votaciones donde debes fiscalizar y certificar hitos.':($user['role']==='admin'?'Crea, prepara y opera cada proceso desde un centro de control.':'Consulta el estado, la participación y los resultados de tus votaciones asignadas.')?></p></div><?php if($canCreate):?><button class="v-btn v-btn-orange" type="button" onclick="const panel=document.getElementById('nueva-votacion');panel.open=true;panel.scrollIntoView({behavior:'smooth'});">＋ Nueva votación</button><?php endif;?></div>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>

<?php if($canCreate):?><details class="v-disclosure" id="nueva-votacion"><summary>Crear una nueva votación</summary><div><form method="post" class="v-stack"><?=Csrf::input()?><?php if($user['role']==='admin'):?><label class="v-label">Cliente<select name="organization_id" required class="v-field"><option value="">Selecciona una organización</option><?php foreach($organizations as $organization):?><option value="<?=$organization['id']?>"><?=htmlspecialchars($organization['name'])?></option><?php endforeach;?></select></label><?php endif;?><label class="v-label">Título<input class="v-field" name="title" required placeholder="Ej. Elección de Directorio 2027"></label><label class="v-label">Descripción<textarea class="v-field" name="description" rows="3" placeholder="Objetivo y contexto del proceso"></textarea></label><div class="v-grid-2" style="grid-template-columns:1fr 1fr"><label class="v-label">Inicio<input class="v-field" type="datetime-local" name="starts_at"></label><label class="v-label">Término<input class="v-field" type="datetime-local" name="ends_at"></label></div><label class="v-label">Modalidad<select class="v-field" name="voting_mode"><option value="secret">Voto secreto</option><option value="public">Voto identificado</option></select></label><label class="v-label">Flujo de preguntas<select class="v-field" name="question_flow"><option value="traditional">Tradicional · papeleta completa</option><option value="sequential">Asamblea · una pregunta a la vez</option></select></label><label class="v-check"><input type="checkbox" name="allow_weighted_vote" value="1"> Permitir voto ponderado</label><div><button class="v-btn v-btn-orange">Crear y configurar →</button></div></form></div></details><?php endif;?>

<section class="v-page-module" data-election-list><header><div><h2><?=$user['role']==='witness'?'Votaciones para fiscalizar':'Procesos registrados'?></h2><p><span data-visible-count><?=count($elections)?></span> de <?=count($elections)?> proceso(s) disponibles.</p></div><span data-visible-badge><?=count($elections)?></span></header><div class="v-page-module-body">
<form class="election-filters" data-election-filters role="search"><label class="election-search"><span>Buscar</span><input class="v-field" type="search" name="query" placeholder="Nombre, descripción o cliente…" autocomplete="off"></label><label><span>Estado</span><select class="v-field" name="status"><option value="">Todos los estados</option><option value="open">En vivo</option><option value="scheduled">Programada</option><option value="draft">Sin comenzar</option><option value="closed">Finalizada</option><option value="cancelled">Cancelada</option></select></label><?php if($user['role']==='admin'&&count($filterOrganizations)>1):?><label><span>Cliente</span><select class="v-field" name="organization"><option value="">Todos los clientes</option><?php foreach($filterOrganizations as $organizationId=>$organizationName):?><option value="<?=$organizationId?>"><?=htmlspecialchars($organizationName)?></option><?php endforeach;?></select></label><?php endif;?><label><span>Ordenar</span><select class="v-field" name="sort"><option value="recent">Más recientes</option><option value="start_asc">Próximos a comenzar</option><option value="name">Nombre A–Z</option></select></label><button class="v-btn v-btn-outline election-filter-clear" type="reset">Limpiar</button></form>
<div class="v-process-grid v-process-list" data-election-grid><div class="process-list-head" aria-hidden="true"><span>Proceso</span><span>Descripción</span><span>Inicio</span><span>Estado</span><span>Acción</span></div>
<?php if(!$elections):?><div class="v-empty">No tienes votaciones disponibles en este momento.</div><?php endif;?>
<?php foreach($elections as $election):$target=$user['role']==='witness'?'/fiscalizacion.php?election_id='.$election['id']:'/control.php?election_id='.$election['id'];$searchText=$election['title'].' '.$election['organization_name'].' '.($election['description']??'');?><a class="v-process-card v-process-row" href="<?=$target?>" data-election-card data-election-id="<?=(int)$election['id']?>" data-search="<?=htmlspecialchars($searchText,ENT_QUOTES,'UTF-8')?>" data-status="<?=htmlspecialchars($election['status'],ENT_QUOTES,'UTF-8')?>" data-organization="<?=(int)$election['organization_id']?>" data-created="<?=htmlspecialchars((string)$election['created_at'],ENT_QUOTES,'UTF-8')?>" data-start="<?=htmlspecialchars((string)($election['starts_at']??'9999-12-31'),ENT_QUOTES,'UTF-8')?>"><div class="process-row-main"><div class="v-kicker"><?=htmlspecialchars($election['organization_name'])?></div><h3><?=htmlspecialchars($election['title'])?></h3></div><p class="v-muted process-row-description"><?=htmlspecialchars((string)($election['description']??'Sin descripción'))?></p><time class="process-row-date" datetime="<?=htmlspecialchars((string)($election['starts_at']??''),ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars((string)($election['starts_at']??'Fecha por definir'))?></time><span class="v-status <?=electionStatusClass($election['status'])?>"><?=htmlspecialchars(electionStatusLabel($election['status']))?></span><b class="process-row-action"><?=$user['role']==='witness'?'Fiscalizar →':($user['role']==='admin'?'Abrir control →':'Consultar →')?></b></a><?php endforeach;?>
<div class="v-empty election-filter-empty" data-filter-empty hidden>No encontramos procesos que coincidan con los filtros.</div></div></div></section>
</main></div></div></body></html>
