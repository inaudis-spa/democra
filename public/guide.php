<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use InaudisVote\Auth\AuthService;
use InaudisVote\Core\Navigation;
$user=AuthService::user();
$profile=!empty($forceVoterGuide)?'voter':($user ? (string)$user['role'] : 'voter');
if(empty($forceVoterGuide)&&!in_array($profile,['admin','organizer','witness'],true)&&$user)$profile='organizer';
$guides=[
'admin'=>[
 ['Antes de comenzar','Confirma quién será responsable del proceso, las fechas, el padrón de votantes y las preguntas. Ten esos datos a mano antes de configurar la plataforma.','No publiques una votación mientras todavía estés probando su papeleta.'],
 ['Crea la organización','En Organizaciones selecciona “Nueva organización”. Escribe el nombre legal o reconocible, el RUT y un identificador corto. La organización agrupa sus usuarios y votaciones.','Ejemplo: Sindicato Demo DEMOCRA.'],
 ['Crea al organizador','En Usuarios crea a la persona que operará la elección. Selecciona el rol Organizador y asígnala a la organización correcta. Entrégale su correo y contraseña inicial por un canal seguro.','Un organizador solo debe acceder a los procesos de su organización.'],
 ['Crea y revisa la votación','En Votaciones completa título, descripción, fechas y modalidad. Entra al proceso, agrega todas las preguntas y alternativas, y revisa ortografía y reglas de selección.','Mientras esté en borrador puedes editar con seguridad.'],
 ['Carga el padrón','En el proceso abre Padrón. Puedes agregar personas una por una o importar un CSV con RUT, nombre, correo, teléfono, peso y segmento. Corrige duplicados antes de continuar.','Prueba primero con 2 o 3 personas del equipo.'],
 ['Abre e invita','Cambia el estado a Abierta solamente cuando la papeleta esté aprobada. Luego genera la invitación de cada votante. En esta demo se muestra el enlace y el OTP en pantalla.','En producción, el OTP debe enviarse por un canal privado.'],
 ['Supervisa y cierra','Mira la participación sin intervenir en el voto. Al terminar, cierra la elección, revisa Resultados, genera el certificado y descarga el acta. Comprueba también el registro de Auditoría.','Guarda el acta y su hash SHA-256 como respaldo.']
],
'organizer'=>[
 ['Ubícate en el panel','El Inicio resume procesos abiertos, personas habilitadas, votos emitidos y pendientes. Tu cuenta solo muestra la organización que tienes asignada.','Si no ves una elección esperada, consulta al administrador.'],
 ['Prepara la votación','En Votaciones crea el proceso. Define un título fácil de reconocer, explica su objetivo, configura fechas y elige voto secreto o público.','Usa voto secreto cuando no deba relacionarse identidad y preferencia.'],
 ['Construye la papeleta','Dentro del proceso agrega cada pregunta y sus alternativas. Define cuántas opciones puede marcar la persona. Lee la papeleta completa como si fueras votante.','No abras el proceso hasta terminar esta revisión.'],
 ['Carga y valida el padrón','En Padrón agrega o importa participantes. Revisa nombres, correos, peso de voto y estado. Un correo duplicado dentro de la misma elección será rechazado.','Haz una prueba interna antes del envío general.'],
 ['Abre y entrega invitaciones','Cambia el proceso a Abierto. Genera para cada persona un enlace único y un OTP temporal. El votante abre el enlace, ingresa el código y accede a su papeleta.','Cada invitación sirve para un solo voto.'],
 ['Acompaña sin influir','Si alguien tiene problemas, confirma que use el enlace correcto, que el OTP no haya vencido y que la elección siga abierta. Nunca pidas conocer su preferencia.','La identidad y la papeleta se procesan por separado.'],
 ['Finaliza el proceso','Cierra la votación a la hora acordada. Revisa participación y resultados, solicita o genera el certificado y descarga el acta.','Verifica los eventos importantes en Auditoría.']
],
'witness'=>[
 ['Revisa tus procesos asignados','En Votaciones solo verás los procesos donde fuiste designado como Ministro de Fe. Selecciona uno para entrar directamente a Fiscalización.','Si falta una votación, solicita al administrador u organizador que revise tu asignación.'],
 ['Comprueba la configuración','Revisa el estado, la pregunta, las alternativas y la información general antes de certificar hitos.','Tu función es fiscalizar; no puedes modificar la papeleta ni el padrón.'],
 ['Verifica el padrón','Comprueba los totales e integridad del padrón sin consultar preferencias individuales.','En voto secreto nunca debe relacionarse una identidad con una alternativa.'],
 ['Certifica los hitos','Registra cada etapa constatada. El sistema guarda un snapshot y genera una evidencia SHA-256.','Certifica únicamente hechos que hayas revisado personalmente.'],
 ['Revisa resultados y auditoría','Cuando corresponda, consulta resultados en modo de solo lectura y verifica el historial del proceso.','El Ministro de Fe revisa evidencia; la certificación administrativa corresponde al encargado.']
],
'voter'=>[
 ['Recibe tu invitación','Recibirás un enlace personal y un código OTP de seis dígitos. No compartas ninguno: juntos permiten entrar a tu papeleta.','Abre el enlace en un navegador actualizado.'],
 ['Comprueba el proceso','Antes de escribir el código, confirma que la pantalla indique DEMOCRA y muestre la elección esperada. Si el enlace aparece inválido, pide una nueva invitación al organizador.','El enlace es único para ti.'],
 ['Verifica tu identidad','Escribe los seis números del OTP y presiona “Validar y continuar”. El código es temporal; si venció, solicita otro.','Esto habilita la papeleta, pero todavía no registra un voto.'],
 ['Lee toda la papeleta','Revisa la pregunta, las alternativas y cuántas puedes seleccionar. Marca tu opción. Puedes cambiarla mientras no presiones Confirmar voto.','Si algo no está claro, detente y consulta al organizador.'],
 ['Confirma tu voto','Revisa la selección y presiona “Confirmar voto”. Acepta la confirmación final solo cuando estés seguro. Después de este punto no podrás modificarlo ni volver a votar.','Nadie debería observar o indicarte qué seleccionar.'],
 ['Guarda el comprobante','La pantalla final confirma el registro y muestra una huella SHA-256. Puedes copiarla o tomar una captura como respaldo. La huella no revela tu preferencia.','No cierres la ventana antes de ver la confirmación.']
]];
$titles=['admin'=>'Administrador','organizer'=>'Organizador','witness'=>'Ministro de Fe','voter'=>'Votante final'];
$introductions=[
 'admin'=>'Aquí encontrarás las tareas de administración y control general de la plataforma.',
 'organizer'=>'Aquí encontrarás las tareas necesarias para preparar y ejecutar tus votaciones.',
 'witness'=>'Aquí encontrarás las tareas de revisión, fiscalización y certificación de hitos.',
 'voter'=>'Aquí encontrarás lo necesario para verificar tu identidad, votar y guardar tu comprobante.'
];
?>
<!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cómo funciona | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/guide.css"><link rel="stylesheet" href="/assets/ballot.css"></head><body class="vota-ui">
<?php if($user&&empty($forceVoterGuide)): ?><div class="v-shell"><?php Navigation::sidebar($user, 'guide'); ?><div class="v-main"><header class="v-topbar"><a class="v-link" href="/dashboard.php">Volver al inicio</a><a class="v-link" href="/logout.php">Salir</a></header><main class="v-content">
<?php else: ?><main class="v-public-guide"><header class="v-voter-head"><div class="v-brand" style="margin:0;color:var(--navy)"><img class="v-brandmark" src="/assets/democra-mark.png" alt="Democra"><span><span class="v-brand-name">DEMOCRA<small>VOTO DIGITAL</small></span></span></div><a class="v-link" href="/login.php">Acceso administrativo</a></header>
<?php endif; ?>
<section class="v-guide-hero"><div><div class="v-kicker">Guía para <?=$titles[$profile]?></div><h1 class="v-title"><?=$profile==='voter'?'Cómo emitir tu voto':'Cómo usar DEMOCRA'?></h1><p><?=htmlspecialchars($introductions[$profile])?></p></div><div class="v-guide-time"><strong><?=count($guides[$profile])?> pasos</strong><span>Perfil: <?=$titles[$profile]?></span></div></section>
<div class="v-guide-layout"><section class="v-guide-steps"><?php foreach($guides[$profile] as $index=>$step):?><article class="v-guide-step"><span><?=$index+1?></span><div><small>PASO <?=$index+1?></small><h2><?=htmlspecialchars($step[0])?></h2><p><?=htmlspecialchars($step[1])?></p><div class="v-tip"><strong>Ten presente:</strong> <?=htmlspecialchars($step[2])?></div></div></article><?php endforeach;?></section>
<aside class="v-guide-aside"><div class="v-card v-pad"><h2>Antes de avanzar</h2><ul class="v-check-list"><li>Lee el paso completo.</li><li>Revisa los datos antes de guardar.</li><li>No compartas contraseñas ni OTP.</li><li>Ante una duda, detente y consulta.</li></ul></div><div class="v-card v-pad"><h2>Palabras importantes</h2><dl><dt>Padrón</dt><dd>Lista de personas autorizadas para votar.</dd><dt>OTP</dt><dd>Código temporal de seis números.</dd><dt>Papeleta</dt><dd>Pantalla donde se marca la preferencia.</dd><dt>Acta</dt><dd>Documento final de resultados.</dd><dt>Hash SHA-256</dt><dd>Huella digital para comprobar integridad.</dd></dl></div><?php if($profile==='voter'):?><div class="v-support-card v-demo-callout"><span class="v-demo-icon">✓</span><strong>¿Quieres ver una votación?</strong><p>Abre una papeleta de ejemplo, selecciona una alternativa y conoce el proceso sin registrar un voto real.</p><a class="v-btn v-btn-primary" href="/vote-demo.php">Ver votación de ejemplo →</a><small>Es una simulación segura. No necesitas enlace ni OTP.</small></div><?php else:?><div class="v-support-card"><strong>Practica antes de publicar</strong><p>Usa la elección demo y emite un voto de prueba de principio a fin.</p><a href="/elections.php">Abrir votaciones</a></div><?php endif;?></aside></div>
<?php if($user&&empty($forceVoterGuide)):?></main></div></div><?php else:?></main><?php endif;?></body></html>
