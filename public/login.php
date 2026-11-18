<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Auth\AuthService;
use InaudisVote\Core\Csrf;

if (AuthService::check()) { header('Location: '.AuthService::landingPath()); exit; }
$error = null;
$_SESSION['login_guard'] = $_SESSION['login_guard'] ?? ['attempts'=>0, 'window_started'=>time()];
if (time()-(int) $_SESSION['login_guard']['window_started'] > 900) $_SESSION['login_guard'] = ['attempts'=>0, 'window_started'=>time()];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email=(string) ($_POST['email']??''); $password=(string) ($_POST['password']??'');
    if (!Csrf::validate($_POST['_csrf']??null)) $error='Solicitud inválida. Recarga la página.';
    elseif ((int) $_SESSION['login_guard']['attempts'] >= 8) $error='Demasiados intentos. Espera 15 minutos.';
    elseif ($email==='' || $password==='') $error='Ingresa email y contraseña.';
    elseif (AuthService::attempt($email,$password)) { unset($_SESSION['login_guard']); header('Location: '.AuthService::landingPath()); exit; }
    else { $_SESSION['login_guard']['attempts']=(int) $_SESSION['login_guard']['attempts']+1; $error='Credenciales inválidas o usuario inactivo.'; }
}
?>
<!doctype html>
<html lang="es">
<head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso | DEMOCRA</title><link rel="stylesheet" href="/assets/democra-base.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/password-toggle.css"></head>
<body class="vota-ui">
<main class="v-login">
<section class="v-login-art"><img class="v-login-logo" src="/assets/democra-logo.png" alt="Democra · Voto Digital"><div style="max-width:540px;position:relative;z-index:1"><div class="orb"></div><p style="margin-top:28px;text-transform:uppercase;letter-spacing:.16em;font-size:12px;color:#8bc0ff">Decisiones digitales verificables</p><h1 style="font-size:46px;line-height:1.06;margin:12px 0 18px">Confianza en cada decisión.</h1><p style="font-size:18px;line-height:1.6;color:#c8d9e8">Gestiona procesos de votación seguros, simples y trazables desde una plataforma diseñada para generar confianza.</p></div><div style="font-size:12px;color:#a9bfd1">Seguridad · Integridad · Trazabilidad</div></section>
<section class="v-login-panel"><div class="v-login-card"><div class="v-kicker">Acceso administrativo</div><h2 class="v-title" style="font-size:32px">Bienvenido</h2><div class="v-accent-line"></div><p class="v-subtitle" style="margin-bottom:28px">Ingresa tus credenciales para continuar.</p>
<?php if ($error): ?><div class="v-error" style="margin-bottom:18px"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<form method="post" autocomplete="off"><?=Csrf::input()?>
<div style="margin-bottom:18px"><label class="v-label" for="email">Email</label><input class="v-field" id="email" name="email" type="email" required placeholder="nombre@organizacion.cl" value="<?=htmlspecialchars((string) ($_POST['email']??''),ENT_QUOTES,'UTF-8')?>"></div>
<div style="margin-bottom:22px"><label class="v-label" for="password">Contraseña</label><div class="v-password-field"><input class="v-field" id="password" name="password" type="password" required placeholder="••••••••"><button class="v-password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false" data-password-toggle><span aria-hidden="true">&#128065;</span></button></div></div>
<button class="v-btn v-btn-orange" style="width:100%;justify-content:center;font-size:15px" type="submit">Ingresar →</button></form>
<div class="v-security"><span>🔒 Sesión segura</span><span>✓ CSRF</span><span>◷ Protección de intentos</span></div><div style="margin-top:22px;text-align:center;display:grid;gap:10px"><a class="v-link" href="/manual.php">¿Primera vez? Aprende a usar DEMOCRA →</a><a class="v-link" href="/verify.php">Verificar autenticidad de un acta →</a></div></div></section>
</main>
<script>document.querySelector('[data-password-toggle]').addEventListener('click',function(){const input=document.getElementById('password');const visible=input.type==='text';input.type=visible?'password':'text';this.setAttribute('aria-pressed',String(!visible));this.setAttribute('aria-label',visible?'Mostrar contraseña':'Ocultar contraseña');});</script>
</body></html>
