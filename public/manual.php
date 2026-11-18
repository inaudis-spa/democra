<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Auth\AuthService;

$manualUser=AuthService::user();
$allowedProfiles=['admin','organizer','witness','voter'];
$requestedProfile=(string)($_GET['perfil']??'');
$sessionProfile=$manualUser&&in_array((string)($manualUser['role']??''),$allowedProfiles,true)?(string)$manualUser['role']:'';
$defaultProfile=in_array($requestedProfile,$allowedProfiles,true)?$requestedProfile:$sessionProfile;
$returnPath=$manualUser?AuthService::landingPath($manualUser):'/login.php';
$returnLabel=$manualUser?'Volver al sistema':'Ir al sistema';
?>
<!doctype html>
<html lang="es">
<head><link rel="icon" type="image/png" href="/assets/democra-mark.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Manual interactivo de DEMOCRA para administradores, organizadores, ministros de fe y votantes.">
    <title>Centro de aprendizaje | DEMOCRA</title>
    <link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css">
    <link rel="stylesheet" href="/assets/manual.css">
    <link rel="stylesheet" href="/assets/manual-learning.css">
    <script src="/assets/manual.js" defer></script>
</head>
<body class="vota-ui manual-page" data-default-profile="<?=htmlspecialchars($defaultProfile,ENT_QUOTES,'UTF-8')?>" data-return-path="<?=htmlspecialchars($returnPath,ENT_QUOTES,'UTF-8')?>">
<header class="manual-topbar">
    <a class="manual-brand" href="<?=htmlspecialchars($returnPath,ENT_QUOTES,'UTF-8')?>" aria-label="DEMOCRA, volver al sistema">
        <img src="/assets/democra-mark.png" alt="Democra">
        <span>DEMOCRA<small>VOTO DIGITAL</small></span>
    </a>
    <nav aria-label="Acciones del manual">
        <button type="button" class="manual-plain-button" data-reset-progress>Reiniciar progreso</button>
        <a class="v-btn v-btn-outline" href="<?=htmlspecialchars($returnPath,ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($returnLabel,ENT_QUOTES,'UTF-8')?></a>
    </nav>
</header>

<main>
    <section class="manual-hero">
        <div class="manual-hero-copy">
            <span class="manual-eyebrow">Centro de aprendizaje DEMOCRA</span>
            <h1>Aprende a usar el sistema según tu perfil</h1>
            <p>No necesitas experiencia previa. Elige tu función y te mostraremos solamente las opciones que puedes ver, qué hace cada una y el orden correcto para trabajar.</p>
            <div class="manual-hero-actions">
                <a class="v-btn v-btn-orange" href="#elegir-perfil">Comenzar recorrido</a>
                <button type="button" class="manual-plain-button manual-video-button" data-open-overview>Ver flujo general</button>
            </div>
        </div>
        <div class="manual-hero-panel" aria-label="Resumen del aprendizaje">
            <div><strong>4</strong><span>perfiles explicados</span></div>
            <div><strong data-total-lessons>0</strong><span>lecciones simples</span></div>
            <div><strong>100%</strong><span>sin afectar datos reales</span></div>
        </div>
    </section>

    <section class="manual-section profile-section" id="elegir-perfil">
        <div class="manual-section-heading">
            <div><span class="manual-eyebrow">Paso 1</span><h2>¿Cuál es tu perfil?</h2><p>Selecciona la función con la que ingresas a DEMOCRA.</p></div>
        </div>
        <div class="profile-grid" role="list">
            <button class="profile-card" type="button" data-profile="admin" role="listitem"><i>AD</i><span><strong>Administrador</strong><small>Configuro clientes, usuarios y procesos.</small></span><b>Elegir →</b></button>
            <button class="profile-card" type="button" data-profile="organizer" role="listitem"><i>OR</i><span><strong>Organizador</strong><small>Preparo y opero votaciones asignadas.</small></span><b>Elegir →</b></button>
            <button class="profile-card" type="button" data-profile="witness" role="listitem"><i>MF</i><span><strong>Ministro de Fe</strong><small>Fiscalizo y certifico los hitos.</small></span><b>Elegir →</b></button>
            <button class="profile-card" type="button" data-profile="voter" role="listitem"><i>VO</i><span><strong>Votante</strong><small>Valido mi acceso y emito mi voto.</small></span><b>Elegir →</b></button>
        </div>
    </section>

    <section class="manual-learning" data-learning hidden>
        <aside class="manual-course-sidebar">
            <div class="manual-course-profile"><span data-profile-badge>AD</span><div><small>Estás aprendiendo como</small><strong data-profile-name>Administrador</strong></div><button type="button" data-change-profile>Cambiar</button></div>
            <div class="manual-progress-block"><div><span>Tu avance</span><strong data-progress-label>0%</strong></div><div class="manual-progress"><i data-progress-bar></i></div></div>
            <nav class="manual-course-nav" aria-label="Lecciones del perfil" data-course-nav></nav>
            <div class="manual-course-help"><strong>¿Te perdiste?</strong><p>Vuelve a la lección anterior o consulta las palabras importantes.</p><button type="button" data-open-glossary>Ver glosario</button></div>
        </aside>

        <section class="manual-lesson" aria-live="polite">
            <div class="lesson-breadcrumb"><span data-lesson-position>Lección 1</span><span>·</span><span data-profile-name-copy>Administrador</span></div>
            <div class="lesson-heading"><div><span class="lesson-icon" data-lesson-icon>⌂</span><div><span class="manual-eyebrow" data-lesson-category>Plataforma</span><h2 data-lesson-title>Inicio</h2><p data-lesson-purpose></p></div></div><span class="lesson-access" data-lesson-access>Disponible para tu perfil</span></div>

            <div class="lesson-location"><span>Estás aprendiendo esta pantalla:</span><strong data-lesson-location></strong></div>
            <div class="profile-permissions"><div><span>✓</span><p><strong>Con este perfil puedes</strong><small data-profile-can></small></p></div><div class="cannot"><span>—</span><p><strong>Con este perfil no puedes</strong><small data-profile-cannot></small></p></div></div>

            <div class="lesson-layout">
                <article class="lesson-content">
                    <div class="lesson-callout"><strong>Objetivo de esta pantalla</strong><p data-lesson-objective></p></div>
                    <h3>Hazlo paso a paso</h3>
                    <ol class="lesson-steps" data-lesson-steps></ol>
                    <div class="lesson-result"><span>✓</span><div><strong>¿Cómo sé que lo hice bien?</strong><p data-lesson-result></p></div></div>
                    <div class="lesson-warning" data-warning-wrap><span>!</span><div><strong>Ten presente</strong><p data-lesson-warning></p></div></div>
                </article>

                <aside class="lesson-preview">
                    <div class="preview-browser"><span></span><span></span><span></span><small>DEMOCRA · Vista orientativa</small></div>
                    <div class="preview-shell">
                        <div class="preview-sidebar"><div class="preview-logo">Q</div><div data-preview-items></div></div>
                        <div class="preview-content"><small data-preview-kicker>PLATAFORMA</small><strong data-preview-title>Inicio</strong><p data-preview-description></p><div class="preview-focus"><i>→</i><span data-preview-focus></span></div></div>
                    </div>
                    <p class="preview-caption">La flecha indica dónde encontrarás esta opción dentro del sistema.</p>
                </aside>
            </div>

            <section class="lesson-quiz" data-quiz>
                <div><span class="manual-eyebrow">Comprueba lo aprendido</span><h3 data-quiz-question></h3></div>
                <div class="quiz-options" data-quiz-options></div>
                <div class="quiz-feedback" data-quiz-feedback hidden></div>
            </section>

            <div class="lesson-actions">
                <button type="button" class="v-btn v-btn-outline" data-previous>← Anterior</button>
                <button type="button" class="lesson-complete" data-complete><span>○</span> Marcar como aprendida</button>
                <button type="button" class="v-btn v-btn-primary" data-next>Siguiente →</button>
            </div>
        </section>
    </section>

    <section class="manual-section manual-help-section">
        <div class="manual-section-heading"><div><span class="manual-eyebrow">Ayuda rápida</span><h2>Respuestas para avanzar sin miedo</h2></div></div>
        <div class="help-grid">
            <details><summary>No veo una votación</summary><p>El Administrador debe asignarte como encargado o Ministro de Fe en ese proceso.</p></details>
            <details><summary>No puedo editar la papeleta o el padrón</summary><p>La votación probablemente ya fue abierta. Desde ese momento ambos quedan protegidos.</p></details>
            <details><summary>No aparecen los resultados</summary><p>El detalle se habilita solamente después de finalizar la votación.</p></details>
            <details><summary>No puedo ingresar</summary><p>Comprueba el correo, la contraseña y que tu usuario esté activo. El votante ingresa mediante su enlace y OTP.</p></details>
            <details><summary>¿Qué hago ante un error?</summary><p>Anota el código mostrado en pantalla y entrégalo al administrador técnico.</p></details>
            <details><summary>¿Puedo repetir este manual?</summary><p>Sí. Tu avance queda guardado en este navegador y puedes reiniciarlo cuando quieras.</p></details>
        </div>
    </section>
</main>

<footer class="manual-footer"><div><strong>DEMOCRA</strong><span>Decisiones digitales verificables</span></div><a href="<?=htmlspecialchars($returnPath,ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($returnLabel,ENT_QUOTES,'UTF-8')?> →</a></footer>

<dialog class="manual-dialog" data-dialog>
    <button type="button" class="dialog-close" data-close-dialog aria-label="Cerrar">×</button>
    <div data-dialog-content></div>
</dialog>
</body>
</html>
