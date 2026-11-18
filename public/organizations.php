<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\OrganizationService;

$user=Authorization::requireRoles(['admin']);
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){
        $error='La sesión del formulario venció. Recarga la página e intenta nuevamente.';
    }else{
        try{
            if(($_POST['action']??'')==='logo'){
                OrganizationService::saveLogo((int)($_POST['organization_id']??0),$_FILES['logo']??[]);
                header('Location: /organizations.php?ok=logo');exit;
            }
            $id=OrganizationService::create($_POST);
            if(($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                OrganizationService::saveLogo($id,$_FILES['logo']);
            }
            header('Location: /organizations.php?ok=created');exit;
        }catch(Throwable $exception){$error=user_error_message($exception);}
    }
}
$organizations=OrganizationService::all();
?>
<!doctype html>
<html lang="es">
<head><link rel="icon" type="image/png" href="/assets/democra-mark.png">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Organizaciones | DEMOCRA</title>
    <link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/organizations.css">
</head>
<body class="vota-ui"><div class="v-shell">
<?php Navigation::sidebar($user,'organizations'); ?>
<div class="v-main"><header class="v-topbar"><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content org-page">
    <div class="org-heading">
        <div><div class="v-kicker">Administración DEMOCRA</div><h1 class="v-title">Organizaciones</h1><div class="v-accent-line"></div><p class="v-subtitle">Gestiona clientes, plantillas y logos desde una vista compacta.</p></div>
        <span class="org-total"><?=count($organizations)?> <?=count($organizations)===1?'organización':'organizaciones'?></span>
    </div>

    <?php if(($_GET['ok']??'')==='created'):?><div class="v-alert v-alert-ok">✓ Organización creada correctamente.</div><?php elseif(($_GET['ok']??'')==='logo'):?><div class="v-alert v-alert-ok">✓ Logo actualizado correctamente.</div><?php endif;?>
    <?php if($error):?><div class="v-alert v-alert-error"><strong>No fue posible guardar el cambio.</strong><br><?=htmlspecialchars($error)?></div><?php endif;?>

    <details class="org-create" <?=$error?'open':''?>>
        <summary><span><b>＋ Nueva organización</b><small>Crear cliente y cargar su logo</small></span><i>⌄</i></summary>
        <form method="post" enctype="multipart/form-data" class="org-create-form">
            <?=Csrf::input()?>
            <label class="v-label">Nombre *<input id="organization-name" class="v-field" name="name" maxlength="150" required placeholder="Ej.: Sindicato Los Robles" value="<?=htmlspecialchars((string)($_POST['name']??''),ENT_QUOTES)?>"></label>
            <label class="v-label">RUT / ID tributario<input class="v-field" name="tax_id" maxlength="32" placeholder="Ej.: 76.000.000-0" value="<?=htmlspecialchars((string)($_POST['tax_id']??''),ENT_QUOTES)?>"></label>
            <label class="v-label">Identificador web *<input id="organization-slug" class="v-field" name="slug" maxlength="120" pattern="[a-z0-9-]+" required placeholder="sindicato-los-robles" value="<?=htmlspecialchars((string)($_POST['slug']??''),ENT_QUOTES)?>"><small>Se completa automáticamente.</small></label>
            <label class="v-label">Logo opcional<input class="v-field" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG o WebP, hasta 2 MB.</small></label>
            <button class="v-btn v-btn-orange" type="submit">Crear organización</button>
        </form>
    </details>

    <section class="org-list" aria-label="Organizaciones registradas">
        <header class="org-list-head"><div><h2>Clientes registrados</h2><p>Información principal y acciones de administración.</p></div><span><?=count($organizations)?></span></header>
        <?php if(!$organizations):?><div class="org-empty">Todavía no hay organizaciones registradas.</div><?php endif;?>
        <?php foreach($organizations as $organization):?>
            <article class="org-row">
                <div class="org-summary">
                    <div class="org-logo"><?php if($organization['logo_path']):?><img src="<?=htmlspecialchars($organization['logo_path'],ENT_QUOTES)?>" alt="Logo de <?=htmlspecialchars($organization['name'],ENT_QUOTES)?>"><?php else:?><span><?=htmlspecialchars(mb_strtoupper(mb_substr($organization['name'],0,2)))?></span><?php endif;?></div>
                    <div class="org-identity"><strong><?=htmlspecialchars($organization['name'])?></strong><small><?=htmlspecialchars($organization['slug'])?></small></div>
                    <div class="org-data"><small>RUT / ID</small><span><?=htmlspecialchars((string)($organization['tax_id']?:'Sin registrar'))?></span></div>
                    <span class="v-status <?=$organization['status']==='active'?'v-status-open':'v-status-draft'?>"><?=$organization['status']==='active'?'Activa':'Inactiva'?></span>
                    <button class="v-btn v-btn-outline org-toggle" type="button" aria-expanded="false">Administrar <span>⌄</span></button>
                </div>
                <div class="org-actions" hidden>
                    <a class="v-btn v-btn-primary" href="/census_fields.php?scope=organization&amp;id=<?=(int)$organization['id']?>">Configurar campos del padrón</a>
                    <form method="post" enctype="multipart/form-data" class="org-logo-form">
                        <?=Csrf::input()?><input type="hidden" name="action" value="logo"><input type="hidden" name="organization_id" value="<?=(int)$organization['id']?>">
                        <label class="v-label" for="logo-<?=(int)$organization['id']?>">Cambiar logo <small>PNG, JPG o WebP · máximo 2 MB</small></label>
                        <input id="logo-<?=(int)$organization['id']?>" class="v-field" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
                        <button class="v-btn v-btn-orange" type="submit">Guardar logo</button>
                    </form>
                </div>
            </article>
        <?php endforeach;?>
    </section>
</main></div></div>
<script>
(()=>{const name=document.getElementById('organization-name');const slug=document.getElementById('organization-slug');let edited=slug.value!=='';slug.addEventListener('input',()=>edited=slug.value!=='');name.addEventListener('input',()=>{if(edited)return;slug.value=name.value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')});document.querySelectorAll('.org-toggle').forEach(button=>button.addEventListener('click',()=>{const panel=button.closest('.org-row').querySelector('.org-actions');const open=panel.hidden;panel.hidden=!open;button.setAttribute('aria-expanded',String(open));button.querySelector('span').textContent=open?'⌃':'⌄'}))})();
</script>
</body></html>
