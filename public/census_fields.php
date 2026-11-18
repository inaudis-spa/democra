<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\CensusTemplateService;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\OrganizationService;
$user=Authorization::requireRoles(['admin']);
$scope=($_GET['scope']??'')==='organization'?'organization':'election';$id=(int)($_GET['id']??0);
$election=$scope==='election'?ElectionService::find($id):null;
if($id<1||($scope==='election'&&(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'],$id)))){http_response_code(404);exit('No encontrado.');}
if($scope==='organization'&&!array_filter(OrganizationService::all(),fn($o)=>(int)$o['id']===$id)){http_response_code(404);exit('Organización no encontrada.');}
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::validate($_POST['_csrf']??null))$error='Solicitud inválida.';
 else try{
  $keys=$_POST['key']??[];$labels=$_POST['label']??[];$types=$_POST['type']??[];$required=$_POST['required']??[];$show=$_POST['show_on_display']??[];
  if(!is_array($keys)||!is_array($labels)||!is_array($types)||!is_array($required)||!is_array($show))throw new InvalidArgumentException('Formulario inválido.');
  $fields=[];
  foreach($keys as $i=>$key)$fields[]=['key'=>$key,'label'=>$labels[$i]??'','type'=>$types[$i]??'text','required'=>isset($required[$i]),'show_on_display'=>isset($show[$i])];
  CensusTemplateService::save($scope,$id,$fields);
  header('Location: /census_fields.php?scope='.$scope.'&id='.$id.'&ok=1');exit;
 }catch(Throwable $e){$error=user_error_message($e);}
}
$fields=$scope==='organization'?CensusTemplateService::organization($id):CensusTemplateService::election($id);
$editable=$scope==='organization'||(in_array($election['status'],['draft','scheduled'],true)&&empty($election['census_locked_at']));
if($scope==='election'){$members=\InaudisVote\Services\CensusService::list($id);$editable=$editable&&!$members;}
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Campos del padrón | DEMOCRA</title><link rel="stylesheet" href="/assets/democra-base.css"><link rel="stylesheet" href="/assets/democra.css"></head><body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user,'census',$scope==='election'?$id:null,$election['title']??null); ?>
<div class="v-main"><main class="v-content"><a class="v-link" href="<?=$scope==='election'?'/census.php?election_id='.$id:'/organizations.php'?>">← Volver</a><h1 class="v-title">Campos del padrón</h1><p class="v-muted">Plantilla de <?=$scope==='organization'?'organización':'votación'?>. Nombre y Email son obligatorios. No se puede modificar una votación con votantes cargados o padrón bloqueado.</p>
<?php if(isset($_GET['ok'])):?><div class="v-alert v-alert-ok">Configuración guardada.</div><?php endif;?>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="v-card v-pad"><form method="post" id="fields-form" class="v-stack"><?=Csrf::input()?><div id="fields">
<?php foreach($fields as $i=>$f):$publicAllowed=$f['type']==='text'&&!in_array($f['key'],['rut','name','email','phone','vote_weight'],true);?><div class="v-grid-2" style="grid-template-columns:1fr 2fr 1fr auto;align-items:center;margin-bottom:12px">
<input class="v-field" name="key[<?=$i?>]" value="<?=htmlspecialchars($f['key'],ENT_QUOTES)?>" <?=in_array($f['key'],['name','email'],true)?'readonly':''?> required maxlength="40" pattern="[a-z][a-z0-9_]*" title="Identificador sin espacios">
<input class="v-field" name="label[<?=$i?>]" value="<?=htmlspecialchars($f['label'],ENT_QUOTES)?>" required maxlength="80" <?=$editable?'':'readonly'?>>
<select class="v-field" name="type[<?=$i?>]" <?=$editable&&!in_array($f['key'],['name','email'],true)?'':'style="pointer-events:none"'?>><?php foreach(['text'=>'Texto','number'=>'Número','date'=>'Fecha','email'=>'Email'] as $value=>$label):?><option value="<?=$value?>" <?=$f['type']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select>
<?php if($editable&&!in_array($f['key'],['name','email'],true)):?><button type="button" class="v-btn v-btn-outline" onclick="this.parentNode.remove()">Eliminar</button><?php endif;?><label><input type="checkbox" name="required[<?=$i?>]" <?=!empty($f['required'])?'checked':''?> <?=$editable&&!in_array($f['key'],['name','email'],true)?'':'onclick="return false"'?>> Obligatorio</label><?php if($publicAllowed):?><label title="Publica solo cantidades y porcentajes, nunca identidades"><input type="checkbox" name="show_on_display[<?=$i?>]" <?=!empty($f['show_on_display'])?'checked':''?> <?=$editable?'':'onclick="return false"'?>> Mostrar agregado en pantalla</label><?php endif;?>
</div><?php endforeach;?></div>
<?php if($editable):?><button type="button" class="v-btn v-btn-outline" id="add-field">＋ Agregar campo</button><button class="v-btn v-btn-orange">Guardar campos</button><?php else:?><p class="v-alert v-alert-warn">Configuración de solo lectura.</p><?php endif;?></form></section></main></div></div>
<?php if($editable):?><script>
let index=<?=count($fields)?>;
document.getElementById('add-field').addEventListener('click',()=>{
 if(index>=30){alert('Máximo 30 campos');return;}
 const i=index++;const div=document.createElement('div');div.className='v-grid-2';div.style='grid-template-columns:1fr 2fr 1fr auto;align-items:center;margin-bottom:12px';
 div.innerHTML='<input class="v-field" name="key['+i+']" placeholder="codigo_campo" pattern="[a-z][a-z0-9_]*" maxlength="40" required><input class="v-field" name="label['+i+']" placeholder="Nombre visible" maxlength="80" required><select class="v-field" name="type['+i+']"><option value="text">Texto</option><option value="number">Número</option><option value="date">Fecha</option><option value="email">Email</option></select><label><input type="checkbox" name="required['+i+']"> Obligatorio</label><label><input type="checkbox" name="show_on_display['+i+']"> Mostrar agregado en pantalla</label><button type="button" class="v-btn v-btn-outline" onclick="this.parentNode.remove()">Eliminar</button>';
 document.getElementById('fields').appendChild(div);
});
</script><?php endif;?></body></html>
