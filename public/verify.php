<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Csrf;
use InaudisVote\Core\Database;

$error=null;$certificate=null;$hash='';
if(isset($_GET['hash']))$hash=strtolower(trim((string)$_GET['hash']));
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null))$error='La sesión del formulario venció. Recarga la página.';
    else{
        $hash=strtolower(trim((string)($_POST['hash']??'')));
        if(isset($_FILES['acta'])&&($_FILES['acta']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            if(($_FILES['acta']['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)$error='No fue posible cargar el archivo.';
            elseif((int)($_FILES['acta']['size']??0)>5*1024*1024)$error='El PDF no puede superar 5 MB.';
            else{
                $content=(string)file_get_contents((string)$_FILES['acta']['tmp_name']);
                if(!str_starts_with($content,'%PDF-'))$error='El archivo seleccionado no es un PDF válido.';
                elseif(preg_match_all('/[a-f0-9]{64}/i',$content,$matches)&&!empty($matches[0])){
                    $candidates=array_slice(array_values(array_unique(array_map('strtolower',$matches[0]))),0,50);
                    $placeholders=implode(',',array_fill(0,count($candidates),'?'));
                    $stmt=Database::connection()->prepare("SELECT rc.sha256_hash,e.id election_id,e.title,e.status,o.name organization_name,rc.generated_at FROM result_certificates rc JOIN elections e ON e.id=rc.election_id JOIN organizations o ON o.id=e.organization_id WHERE rc.sha256_hash IN ({$placeholders}) LIMIT 1");
                    $stmt->execute($candidates);$certificate=$stmt->fetch();$hash=$certificate['sha256_hash']??$candidates[0];
                }else $error='No encontramos una huella SHA-256 verificable dentro del PDF.';
            }
        }
    }
}
if(!$error&&$hash!==''&&!$certificate){
    if(!preg_match('/^[a-f0-9]{64}$/',$hash))$error='La huella debe contener exactamente 64 caracteres hexadecimales.';
    else{$stmt=Database::connection()->prepare('SELECT rc.sha256_hash,e.id election_id,e.title,e.status,o.name organization_name,rc.generated_at FROM result_certificates rc JOIN elections e ON e.id=rc.election_id JOIN organizations o ON o.id=e.organization_id WHERE rc.sha256_hash=:hash LIMIT 1');$stmt->execute(['hash'=>$hash]);$certificate=$stmt->fetch()?:null;}
}
$checked=$hash!==''&&!$error;
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verificar acta | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"></head><body class="vota-ui verify-page">
<header class="verify-header"><a class="v-brand" href="/login.php"><img class="v-brandmark" src="/assets/democra-mark.png" alt="Democra"><span class="v-brand-name">DEMOCRA<small>VOTO DIGITAL</small></span></a><a class="v-link" href="/login.php">Acceso administrativo</a></header>
<main class="verify-main"><section class="verify-intro"><div class="verify-seal">✓</div><div><div class="v-kicker">Centro de confianza</div><h1>Verifica la autenticidad de un acta</h1><p>Compara la huella SHA-256 del documento con la certificación registrada por DEMOCRA.</p></div></section>
<?php if($error):?><div class="v-alert v-alert-error"><strong>No fue posible verificar.</strong><br><?=htmlspecialchars($error)?></div><?php elseif($checked&&$certificate):?><section class="verify-result verify-valid"><div class="verify-result-icon">✓</div><div><div class="v-kicker">Verificación exitosa</div><h2>Acta auténtica e íntegra</h2><p>La huella coincide exactamente con una certificación registrada en DEMOCRA.</p></div></section><section class="v-card v-pad verify-details"><dl><div><dt>Proceso</dt><dd><?=htmlspecialchars($certificate['title'])?></dd></div><div><dt>Organización</dt><dd><?=htmlspecialchars($certificate['organization_name'])?></dd></div><div><dt>Estado</dt><dd>Terminado</dd></div><div><dt>Certificada</dt><dd><?=htmlspecialchars(date('d-m-Y H:i',strtotime((string)$certificate['generated_at'])))?></dd></div><div class="verify-hash-row"><dt>Huella SHA-256</dt><dd class="v-hash"><?=htmlspecialchars($certificate['sha256_hash'])?></dd></div></dl></section><?php elseif($checked):?><section class="verify-result verify-invalid"><div class="verify-result-icon">!</div><div><div class="v-kicker">Sin coincidencia</div><h2>Acta no reconocida</h2><p>La huella no coincide con ninguna certificación registrada. El documento puede haber sido modificado o pertenecer a otro sistema.</p></div></section><?php endif;?>
<section class="v-card v-pad verify-form-card"><h2>Realizar una verificación</h2><p class="v-muted">Puedes cargar el acta PDF o copiar el código de 64 caracteres que aparece en “Integridad y trazabilidad”.</p><form method="post" enctype="multipart/form-data" class="verify-form"><?=Csrf::input()?><label><span>Cargar acta PDF</span><input class="v-field" type="file" name="acta" accept="application/pdf,.pdf"></label><div class="verify-divider"><span>o</span></div><label><span>Huella SHA-256</span><input class="v-field verify-code" name="hash" value="<?=htmlspecialchars($hash,ENT_QUOTES,'UTF-8')?>" maxlength="64" pattern="[A-Fa-f0-9]{64}" placeholder="Ej. 88380073dfd3148d…"></label><button class="v-btn v-btn-orange" type="submit">Verificar autenticidad</button></form><p class="verify-privacy">La verificación no revela votos individuales ni datos de votantes.</p></section></main></body></html>
