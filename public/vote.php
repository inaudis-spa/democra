<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\VotingService;
$token=(string)($_GET['token']??$_POST['token']??'');
$submissionKey=(string)($_POST['idempotency_key']??bin2hex(random_bytes(32)));
$context=$token!==''?VotingService::resolveToken($token):null;
$error=null;$receipt=null;$questions=[];
if($_SERVER['REQUEST_METHOD']==='POST'&&Csrf::validate($_POST['_csrf']??null)){
    try{
        $action=(string)($_POST['action']??'vote');
        if($action==='verify_otp'){
            if(!VotingService::verifyOtp($token,(string)($_POST['otp']??'')))throw new RuntimeException('OTP inválido o vencido.');
            $context=VotingService::resolveToken($token);
        }else{
            $receipt=VotingService::submit($token,(array)($_POST['answers']??[]),$submissionKey);
            $context=VotingService::resolveToken($token);
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}elseif($_SERVER['REQUEST_METHOD']==='POST'){$error='Solicitud inválida. Recarga la página.';}
if($context)$questions=ElectionService::questions((int)$context['election_id']);
$isSequential=$context && ($context['question_flow']??'traditional')==='sequential';
$answered=$isSequential?VotingService::answeredQuestions((int)$context['census_member_id']):[];
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Votar | DEMOCRA</title><link rel="stylesheet" href="/assets/democra-base.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/guide.css"><link rel="stylesheet" href="/assets/ballot.css"><link rel="stylesheet" href="/assets/vote-real.css"><link rel="stylesheet" href="/assets/vote-help.css"></head><body class="vota-ui"><main class="v-voter">
<header class="v-voter-head"><div class="v-brand" style="margin:0;color:var(--navy)"><img class="v-brandmark" src="/assets/democra-mark.png" alt="Democra"><span><span class="v-brand-name">DEMOCRA<small>VOTO DIGITAL</small></span></span></div><div style="display:flex;align-items:center;gap:14px"><button class="v-link v-help-button" type="button" data-open-vote-help>¿Cómo votar?</button><?php if($context&&!empty($context['organization_logo_path'])):?><img src="<?=htmlspecialchars((string)$context['organization_logo_path'],ENT_QUOTES)?>" alt="Logo de la organización" style="max-height:60px;max-width:130px;object-fit:contain" loading="eager"><?php endif;?><span class="v-status v-status-open">🔒 Sesión segura</span></div></header>
<section class="v-voter-card">
<?php if($context&&($context['voting_mode']??'secret')==='public'):?><div class="v-alert v-alert-warn"><strong>Votación identificada.</strong> Al confirmar, tu nombre quedará asociado a tus selecciones y podrá ser consultado por los administradores y organizadores responsables después del cierre.</div><?php endif;?>
<?php if($error):?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if(!$context):?><div class="v-kicker">Acceso</div><h1 class="v-title">Enlace no válido</h1><p class="v-muted">La invitación no existe o ya no está disponible. Solicita un nuevo enlace al organizador.</p>
<?php elseif($receipt && !$isSequential):?><div class="v-receipt"><div class="v-checkmark">✓</div><div class="v-kicker" style="color:var(--teal)">Proceso completado</div><h1 class="v-title">¡Tu voto fue registrado correctamente!</h1><p class="v-muted">Gracias por participar. Conserva este comprobante como respaldo.</p><div class="v-card v-pad" style="text-align:left;margin-top:24px"><strong>Comprobante de voto</strong><p class="v-muted" style="font-size:12px">Huella criptográfica SHA-256</p><div class="v-hash"><?=htmlspecialchars($receipt)?></div></div><div class="v-security" style="justify-content:center"><span>🔒 Voto seguro</span><span>✓ Identidad separada</span><span>✓ Verificable</span></div></div>
<?php elseif(empty($context['verified_at'])&&!$context['consumed_at']):?><div class="v-kicker">Paso 1 de 2 · Identidad</div><h1 class="v-title">Verifica tu identidad</h1><div class="v-accent-line"></div><p class="v-muted">Escribe el código de 6 dígitos entregado junto con este enlace. Esto solo habilita la papeleta; todavía no registra tu voto.</p><div class="v-tip"><strong>Importante:</strong> no compartas este enlace ni el código. Si el OTP venció, solicita uno nuevo.</div><form method="post" style="margin-top:26px"><?=Csrf::input()?><input type="hidden" name="action" value="verify_otp"><input type="hidden" name="token" value="<?=htmlspecialchars($token,ENT_QUOTES)?>"><label class="v-label" for="otp">Código de verificación</label><input id="otp" class="v-field" style="font-size:25px;text-align:center;letter-spacing:.45em" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000"><button class="v-btn v-btn-orange" style="width:100%;margin-top:18px" type="submit">Validar y continuar →</button></form><div class="v-security"><span>🔒 Conexión segura</span><span>◷ Código temporal</span></div>
<?php elseif(!VotingService::canVote($context)):?><div class="v-alert v-alert-warn">Esta invitación no está habilitada. El proceso puede estar cerrado, fuera de horario o el voto ya fue emitido.</div>
<?php elseif($isSequential):?><?php
$currentQuestion=null;
foreach($questions as $q){
    if($q['voting_status']==='open'){
        $currentQuestion=$q;
        break;
    }
}
$currentAnswered=$currentQuestion ? in_array((int)$currentQuestion['id'],$answered,true) : false;
?>
<div class="v-kicker">Asamblea · Votación secuencial</div>
<h1 class="v-title"><?=htmlspecialchars((string)$context['election_title'])?></h1>
<div class="v-accent-line"></div>
<?php if($receipt):?><div class="v-alert v-alert-ok">✓ Tu respuesta fue registrada correctamente. Espera la siguiente pregunta.</div><?php endif;?>
<?php if(!$currentQuestion):?>
    <div class="v-card v-pad" style="margin-top:24px;text-align:center">
        <div class="v-kicker">Votación en espera</div>
        <h2 style="font-size:22px">La siguiente pregunta aún no ha sido abierta</h2>
        <p class="v-muted">Cuando el organizador abra la próxima pregunta, aparecerá automáticamente en esta misma papeleta.</p>
    </div>
<?php elseif($currentAnswered):?>
    <div class="v-card v-pad" style="margin-top:24px;text-align:center">
        <div class="v-kicker">Respuesta registrada</div>
        <h2 style="font-size:22px">Esta pregunta ya fue respondida</h2>
        <p class="v-muted">La pregunta actual permanecerá fuera de tu papeleta. Espera a que el organizador abra la siguiente.</p>
    </div>
<?php else:?>
    <section class="v-card v-pad" style="margin-top:24px">
        <div class="v-kicker">Pregunta activa</div>
        <h2 style="font-size:22px"><?=htmlspecialchars($currentQuestion['text'])?></h2>
        <form method="post" style="margin-top:20px">
            <?=Csrf::input()?>
            <input type="hidden" name="action" value="vote">
            <input type="hidden" name="idempotency_key" value="<?=htmlspecialchars($submissionKey,ENT_QUOTES)?>">
            <input type="hidden" name="token" value="<?=htmlspecialchars($token,ENT_QUOTES)?>">
            <?php foreach($currentQuestion['options'] as $o):?>
                <label class="v-option">
                    <input type="<?=$currentQuestion['question_type']==='multiple'?'checkbox':'radio'?>" name="answers[<?=$currentQuestion['id']?>][]" value="<?=$o['id']?>">
                    <span><strong><?=htmlspecialchars($o['label'])?></strong></span>
                </label>
            <?php endforeach;?>
            <?php if($currentQuestion['question_type']==='multiple'):?>
                <p class="v-muted">Selecciona entre <?=$currentQuestion['min_choices']?> y <?=$currentQuestion['max_choices']?> alternativas.</p>
            <?php endif;?>
            <button class="v-btn v-btn-orange" style="width:100%;margin-top:16px">Confirmar respuesta →</button>
        </form>
    </section>
<?php endif;?>
<p class="v-muted" style="margin-top:18px">La papeleta se actualiza automáticamente. Las preguntas anteriores y futuras no se muestran.</p>
<script>
(()=> {
    let busy=false;
    async function refreshBallot(){
        if(busy || document.hidden) return;
        busy=true;
        try{
            const url=new URL(window.location.href);
            url.searchParams.set('_refresh',Date.now().toString());
            const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store'});
            if(!response.ok) return;
            const html=await response.text();
            const parser=new DOMParser();
            const next=parser.parseFromString(html,'text/html');
            const current=document.querySelector('main.v-voter');
            const incoming=next.querySelector('main.v-voter');
            if(current&&incoming&&current.innerHTML!==incoming.innerHTML) location.reload();
        }catch(_){}
        finally{busy=false;}
    }
    setInterval(refreshBallot,5000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshBallot()});
})();
</script>
<?php else:?><div class="v-kicker">Paso 2 de 2 · Papeleta</div><h1 class="v-title"><?=htmlspecialchars((string)$context['election_title'])?></h1><div class="v-accent-line"></div><p class="v-muted">Lee cada pregunta y selecciona tu preferencia. Puedes cambiarla antes de confirmar. Tu identidad y tu papeleta se procesan por separado.</p><form method="post" style="margin-top:24px"><?=Csrf::input()?><input type="hidden" name="action" value="vote"><input type="hidden" name="idempotency_key" value="<?=htmlspecialchars($submissionKey,ENT_QUOTES)?>"><input type="hidden" name="token" value="<?=htmlspecialchars($token,ENT_QUOTES)?>"><?php foreach($questions as $q):?><section style="margin-bottom:28px"><h2 style="font-size:18px"><?=htmlspecialchars($q['text'])?></h2><?php foreach($q['options'] as $o):?><label class="v-option"><input type="<?=$q['question_type']==='multiple'?'checkbox':'radio'?>" name="answers[<?=$q['id']?>][]" value="<?=$o['id']?>"><span><strong><?=htmlspecialchars($o['label'])?></strong></span></label><?php endforeach;?><?php if($q['question_type']==='multiple'):?><p class="v-muted" style="font-size:12px">Selecciona entre <?=$q['min_choices']?> y <?=$q['max_choices']?> alternativas.</p><?php endif;?></section><?php endforeach;?><div class="v-orange-note" style="margin-bottom:16px"><strong>Revisa antes de confirmar.</strong> Una vez emitido, el voto no podrá modificarse.</div><button class="v-btn v-btn-orange" style="width:100%;font-size:16px">Confirmar voto →</button></form>
<?php endif;?></section></main>
<div class="v-vote-help-overlay" data-vote-help hidden><section class="v-vote-help-modal" role="dialog" aria-modal="true" aria-labelledby="vote-help-title"><button class="v-vote-help-close" type="button" data-close-vote-help aria-label="Cerrar ayuda">×</button><div class="v-vote-help-head"><span class="v-demo-icon">?</span><div><div class="v-kicker">Ayuda para votantes</div><h2 id="vote-help-title">¿Cómo emitir tu voto?</h2></div></div><div class="v-vote-help-steps"><article><span>1</span><div><strong>Ingresa tu código</strong><p>Escribe el OTP de seis números recibido con tu invitación.</p></div></article><article><span>2</span><div><strong>Lee y selecciona</strong><p>Revisa la pregunta completa y marca la alternativa que prefieras.</p></div></article><article><span>3</span><div><strong>Revisa tu elección</strong><p>Puedes cambiar la selección antes de confirmar.</p></div></article><article><span>4</span><div><strong>Deposita tu voto</strong><p>Confirma solo cuando estés seguro. Después no podrá modificarse.</p></div></article></div><div class="v-vote-help-note">La simulación no registra votos ni utiliza tu invitación actual.</div><div class="v-vote-help-actions"><button class="v-btn v-btn-outline" type="button" data-close-vote-help>Cerrar y continuar</button><a class="v-btn v-btn-orange" href="/vote-demo.php" target="_blank">Ver voto de ejemplo →</a></div></section></div>
<div id="vote-review" hidden style="position:fixed;inset:0;z-index:9999;background:rgba(10,30,50,.75);padding:20px;overflow:auto;display:none;align-items:center;justify-content:center">
<section role="dialog" aria-modal="true" aria-labelledby="vote-review-title" class="v-card v-pad" style="width:min(100%,560px);max-height:90vh;overflow:auto;background:white">
<div class="v-kicker">Último paso</div><h2 id="vote-review-title" class="v-title" style="font-size:24px">Revisa tu voto antes de enviarlo</h2>
<p class="v-muted">Comprueba tus respuestas. Todavía no se ha registrado ningún voto.</p>
<div id="vote-review-answers" class="v-stack" style="margin:20px 0"></div>
<p class="v-alert v-alert-warn">Una vez confirmado, no podrás cambiar esta respuesta ni volver a emitirla.</p>
<div style="display:flex;gap:12px;flex-wrap:wrap"><button type="button" id="vote-review-back" class="v-btn v-btn-outline" style="flex:1">Volver y corregir</button><button type="button" id="vote-review-confirm" class="v-btn v-btn-orange" style="flex:1">Confirmar mi voto</button></div>
</section></div>
<script>(()=>{const modal=document.getElementById('vote-review');const answers=document.getElementById('vote-review-answers');const back=document.getElementById('vote-review-back');const confirm=document.getElementById('vote-review-confirm');let pending=null;let confirmed=false;let previousFocus=null;function close(){modal.hidden=true;modal.style.display='none';pending=null;previousFocus?.focus()}document.querySelectorAll('form').forEach(form=>{if(form.querySelector('input[name="action"][value="vote"]'))form.addEventListener('submit',event=>{if(confirmed)return;event.preventDefault();answers.replaceChildren();const nestedSections=[...form.querySelectorAll('section')];const sections=nestedSections.length?nestedSections:[form.closest('section')||form];let count=0;sections.forEach(section=>{const selected=section.querySelectorAll('input[type="radio"]:checked,input[type="checkbox"]:checked');if(!selected.length)return;count++;const block=document.createElement('div');block.className='v-card v-pad';const heading=document.createElement('strong');heading.textContent=section.querySelector('h2')?.textContent?.trim()||'Pregunta';block.appendChild(heading);const list=document.createElement('ul');selected.forEach(input=>{const item=document.createElement('li');item.textContent=input.closest('label')?.querySelector('strong')?.textContent?.trim()||input.value;list.appendChild(item)});block.appendChild(list);answers.appendChild(block)});if(!count){const p=document.createElement('p');p.textContent='No hay respuestas seleccionadas. Vuelve a la papeleta y completa tu selección.';answers.appendChild(p)}confirm.disabled=!count;pending=form;previousFocus=document.activeElement;modal.hidden=false;modal.style.display='flex';back.focus()})});back.addEventListener('click',close);modal.addEventListener('click',event=>{if(event.target===modal)close()});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)close()});confirm.addEventListener('click',()=>{if(!pending||confirmed)return;confirmed=true;confirm.disabled=true;confirm.textContent='Registrando voto…';pending.requestSubmit()})})();</script>
<script>(()=>{const modal=document.querySelector('[data-vote-help]');const open=()=>{modal.hidden=false;document.body.classList.add('v-no-scroll')};const close=()=>{modal.hidden=true;document.body.classList.remove('v-no-scroll')};document.querySelector('[data-open-vote-help]').addEventListener('click',open);document.querySelectorAll('[data-close-vote-help]').forEach(button=>button.addEventListener('click',close));modal.addEventListener('click',event=>{if(event.target===modal)close()});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)close()})})();</script></body></html>
