<?php
declare(strict_types=1);

require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Core\Navigation;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ElectionManagerService;
use InaudisVote\Services\LiveControlService;

$user = Authorization::requireRoles(['admin', 'organizer']);
$id = (int) ($_GET['election_id'] ?? 0);
$election = ElectionService::find($id);
if (!$election || !Authorization::canReviewElection($user, $id, (int) $election['organization_id'])) {
    http_response_code(404);
    exit('Votación no encontrada.');
}

$isAdmin = $user['role'] === 'admin';
if ($election['question_flow']==='sequential' && !$isAdmin) {
    header('Location: /sequential-control.php?election_id='.$id);exit;
}
if (!$isAdmin) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(403);
        exit('Solo el administrador puede modificar votaciones.');
    }
    $snapshot = LiveControlService::snapshot($id);
    $statusLabels = ['draft'=>'Borrador','scheduled'=>'Programada','open'=>'En vivo','closed'=>'Finalizada','cancelled'=>'Cancelada'];
    ?>
    <!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Consulta de votación | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"></head>
    <body class="vota-ui"><div class="v-shell"><?php Navigation::sidebar($user, 'elections', $id, $election['title']); ?>
    <div class="v-main"><header class="v-topbar"><a class="v-link" href="/elections.php">← Mis votaciones</a></header><main class="v-content">
    <div class="v-headrow"><div><div class="v-kicker">Consulta de proceso</div><h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1><p class="v-subtitle">Información y participación de la votación asignada.</p></div><span class="v-status"><?=htmlspecialchars($statusLabels[$election['status']]??$election['status'])?></span></div>
    <section class="v-stats"><div class="v-card v-stat"><strong><?=$snapshot['eligible']?></strong><span>Habilitados</span></div><div class="v-card v-stat teal"><strong><?=$snapshot['voted']?></strong><span>Votos recibidos</span></div><div class="v-card v-stat orange"><strong><?=$snapshot['participation']?>%</strong><span>Participación</span></div></section>
    <section class="v-card v-pad"><h2>Información</h2><p><?=htmlspecialchars((string)$election['description'])?></p><p>Inicio: <?=htmlspecialchars((string)$election['starts_at'])?> · Término: <?=htmlspecialchars((string)$election['ends_at'])?></p><a class="v-btn v-btn-primary" href="/results.php?election_id=<?=$id?>">Consultar resultados →</a></section>
    </main></div></div><script src="/assets/quorum-confirm.js" defer></script></body></html>
    <?php
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'Solicitud inválida.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'status') {
                ElectionService::setStatus($id, (string) ($_POST['status'] ?? ''));
            } elseif ($action === 'question') {
                if($election['question_flow']==='sequential')throw new RuntimeException('Utiliza el control secuencial.');
                $questionId = ($_POST['question_id'] ?? '') !== '' ? (int) $_POST['question_id'] : null;
                LiveControlService::setCurrentQuestion($id, $questionId, (int) $user['id']);
            } elseif ($action === 'results') {
                if($election['question_flow']==='sequential')throw new RuntimeException('Los resultados se muestran al cerrar cada pregunta.');
                LiveControlService::setDisplayResults($id, !empty($_POST['show']), (int) $user['id']);
            } elseif ($action === 'manager_assign') {
                ElectionManagerService::assign($id,(int)($_POST['user_id']??0),(int)$user['id']);
            } elseif ($action === 'manager_remove') {
                ElectionManagerService::remove($id,(int)($_POST['user_id']??0),(int)$user['id']);
            } else {
                throw new RuntimeException('Acción no reconocida.');
            }
            header('Location: /control.php?election_id='.$id.'&updated=1');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$election = ElectionService::find($id);
$questions = ElectionService::questions($id);
$snapshot = LiveControlService::snapshot($id);
$activity = LiveControlService::recentActivity($id);
$managers=ElectionManagerService::forElection($id);$availableManagers=ElectionManagerService::available((int)$election['organization_id']);
$current = null;
foreach ($questions as $question) {
    if ((int) $question['id'] === $snapshot['current_question_id']) {
        $current = $question;
        break;
    }
}

$statusLabels = ['draft'=>'Borrador', 'scheduled'=>'Programada', 'open'=>'En vivo', 'closed'=>'Finalizada', 'cancelled'=>'Cancelada'];
$statusLabel = $statusLabels[$election['status']] ?? ucfirst((string) $election['status']);
?>
<!doctype html>
<html lang="es">
<head><link rel="icon" type="image/png" href="/assets/democra-mark.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Centro de control | DEMOCRA</title>
    <link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css">
    <link rel="stylesheet" href="/assets/control-center.css">
    <style>.cc-action button{display:flex;flex-direction:column;align-items:center;border:0;background:transparent;color:inherit;font:inherit;cursor:pointer}.cc-action-disabled{opacity:.58}</style>
</head>
<body class="vota-ui">
<div class="v-shell">
    <?php Navigation::sidebar($user, 'control', $id, $election['title']); ?>

    <div class="v-main">
        <header class="v-topbar">
            <a class="v-link" href="/elections.php">← Volver a votaciones</a>
            <span class="cc-top-status"><i class="<?=$election['status']==='open'?'is-live':''?>"></i><?=htmlspecialchars($statusLabel)?></span>
            <span class="v-spacer"></span>
            <a class="v-link" href="/logout.php">Salir</a>
        </header>

        <main class="v-content cc-content">
            <section class="cc-heading">
                <div>
                    <div class="v-kicker"><?=htmlspecialchars($election['organization_name'])?></div>
                    <h1 class="v-title"><?=htmlspecialchars($election['title'])?></h1>
                    <p class="v-subtitle">Todo lo necesario para preparar, ejecutar y revisar esta votación.</p>
                </div>
                <a class="cc-primary-link" href="/display.php?election_id=<?=$id?>" target="_blank"><span>↗</span> Abrir pantalla de sala</a>
            </section>

            <?php if (isset($_GET['updated'])): ?><div class="v-alert v-alert-ok">✓ Acción aplicada correctamente.</div><?php endif; ?>
            <?php if ($error): ?><div class="v-alert v-alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>

            <?php if($election['question_flow']==='sequential'):?><div class="v-card v-pad" style="margin:16px 0"><h2>Control de preguntas por etapas</h2><p>Habilitación, cierre y resultados individuales de la asamblea.</p><a class="v-btn v-btn-orange" href="/sequential-control.php?election_id=<?=$id?>">Abrir control secuencial →</a></div><?php endif;?>
            <section class="cc-overview">
                <article><span>Participación</span><strong id="live-participation"><?=$snapshot['participation']?>%</strong><div class="v-progress"><i id="live-progress" style="width:<?=min(100, $snapshot['participation'])?>%"></i></div></article>
                <article><span>Votos recibidos</span><strong id="live-votes"><?=$snapshot['voted']?> <small>de <?=$snapshot['eligible']?></small></strong></article>
                <article><span>Pendientes</span><strong id="live-pending"><?=$snapshot['pending']?></strong></article>
                <article><span>Pregunta visible</span><strong id="live-question" class="cc-question-stat"><?=$current ? htmlspecialchars($current['text']) : 'Sin seleccionar'?></strong></article>
            </section>

            <section class="cc-module">
                <header><div><span class="cc-module-step">1</span><div><h2>Preparación y enlaces</h2><p>Configura el proceso y comparte sus recursos.</p></div></div><span class="cc-module-state">Gestión</span></header>
                <div class="cc-action-grid">
                    <a class="cc-action" href="/election.php?id=<?=$id?>"><i>⚙</i><strong>Configurar votación</strong><span>Preguntas, fechas y reglas</span></a>
                    <a class="cc-action" href="/census.php?election_id=<?=$id?>"><i>♙</i><strong>Participantes</strong><span>Ver padrón e invitaciones</span></a>
                    <a class="cc-action" href="/fiscalizacion.php?election_id=<?=$id?>"><i>⚖</i><strong>Ministro de Fe</strong><span>Asignación y evidencias</span></a>
                    <a class="cc-action" href="/display.php?election_id=<?=$id?>" target="_blank"><i>▣</i><strong>Pantalla de sala</strong><span>Proyección en esta sesión segura</span></a>
                    <a class="cc-action" href="/results.php?election_id=<?=$id?>"><i>▥</i><strong>Resultados</strong><span>Conteo y participación</span></a>
                    <?php if($election['status']==='closed'):?><form class="cc-action" method="post" action="/acta.php?election_id=<?=$id?>" data-confirm="¿Generar el acta definitiva de esta votación cerrada?"><?=Csrf::input()?><button type="submit"><i>⇩</i><strong>Acta del proceso</strong><span>Documento consolidado</span></button></form><?php else:?><div class="cc-action cc-action-disabled"><i>⇩</i><strong>Acta no disponible</strong><span>Se habilita después del cierre</span></div><?php endif;?>
                </div>
                <div class="cc-panel" style="margin:0 22px 22px"><div class="cc-panel-heading"><div><span>Responsables autorizados</span><h3>Encargados de esta votación</h3></div><b><?=count($managers)?> asignado(s)</b></div><div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0"><?php foreach($managers as $manager):?><form method="post" class="v-status v-status-open" style="align-items:center;gap:8px"><?=Csrf::input()?><input type="hidden" name="action" value="manager_remove"><input type="hidden" name="user_id" value="<?=$manager['id']?>"><span><?=htmlspecialchars($manager['name'])?></span><?php if(count($managers)>1):?><button type="submit" title="Quitar encargado" data-confirm-click="¿Quitar este encargado de la votación?" style="border:0;background:transparent;cursor:pointer">×</button><?php endif;?></form><?php endforeach;?></div><form method="post" style="display:flex;gap:10px;flex-wrap:wrap"><?=Csrf::input()?><input type="hidden" name="action" value="manager_assign"><select class="v-field" name="user_id" style="max-width:360px" required><option value="">Selecciona otro encargado</option><?php foreach($availableManagers as $candidate):?><option value="<?=$candidate['id']?>"><?=htmlspecialchars($candidate['name'])?></option><?php endforeach;?></select><button class="v-btn v-btn-outline">Asignar encargado</button></form></div>
            </section>

            <section class="cc-module cc-operation">
                <header><div><span class="cc-module-step">2</span><div><h2>Control de la votación</h2><p>Acciones operativas durante la sesión.</p></div></div><span class="cc-module-state <?=$election['status']==='open'?'is-live':''?>"><?=htmlspecialchars($statusLabel)?></span></header>
                <div class="cc-operation-grid">
                    <article class="cc-panel">
                        <div class="cc-panel-heading"><div><span>Pregunta en pantalla</span><h3><?=$current ? htmlspecialchars($current['text']) : 'Selecciona qué pregunta mostrar'?></h3></div><b><?=count($questions)?> disponibles</b></div>
                        <div class="cc-question-list">
                            <?php if (!$questions): ?><p class="v-muted">La votación todavía no tiene preguntas configuradas.</p><?php endif; ?>
                            <?php foreach ($questions as $index=>$question): ?>
                                <form method="post">
                                    <?=Csrf::input()?>
                                    <input type="hidden" name="action" value="question">
                                    <button class="cc-question <?=$snapshot['current_question_id']==$question['id']?'active':''?>" name="question_id" value="<?=$question['id']?>" type="submit"><span><?=str_pad((string) ($index+1), 2, '0', STR_PAD_LEFT)?></span><strong><?=htmlspecialchars($question['text'])?></strong><i>→</i></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <aside class="cc-control-stack">
                        <?php if (in_array($election['status'], ['draft', 'scheduled'], true)): ?>
                            <form method="post" class="cc-command cc-command-start" data-confirm="¿Abrir la votación ahora? La papeleta quedará bloqueada.">
                                <?=Csrf::input()?>
                                <input type="hidden" name="action" value="status"><input type="hidden" name="status" value="open">
                                <button type="submit"><i>▶</i><span><strong>Abrir votación</strong><small>Habilita el ingreso de votos</small></span></button>
                            </form>
                        <?php elseif ($election['status'] === 'open'): ?>
                            <form method="post" class="cc-command cc-command-danger" data-confirm="¿Finalizar definitivamente la votación? Esta acción no se puede deshacer.">
                                <?=Csrf::input()?>
                                <input type="hidden" name="action" value="status"><input type="hidden" name="status" value="closed">
                                <button type="submit"><i>■</i><span><strong>Finalizar votación</strong><small>Cierra la recepción de votos</small></span></button>
                            </form>
                        <?php else: ?>
                            <div class="cc-command cc-command-disabled"><i>✓</i><span><strong>Proceso <?=htmlspecialchars(strtolower($statusLabel))?></strong><small>No admite nuevas operaciones</small></span></div>
                        <?php endif; ?>

                        <?php if($election['status']==='closed'):?><form method="post" class="cc-command">
                            <?=Csrf::input()?>
                            <input type="hidden" name="action" value="results"><input type="hidden" name="show" value="<?=$snapshot['display_results']?'0':'1'?>">
                            <button type="submit"><i><?=$snapshot['display_results']?'◉':'◎'?></i><span><strong><?=$snapshot['display_results']?'Ocultar':'Mostrar'?> resultados</strong><small>Controla la pantalla pública</small></span></button>
                        </form><?php else:?><div class="cc-command cc-command-disabled"><i>◎</i><span><strong>Resultados protegidos</strong><small>Disponibles después del cierre</small></span></div><?php endif;?>
                        <a class="cc-command" href="/control.php?election_id=<?=$id?>"><i>↻</i><span><strong>Actualizar datos</strong><small>Refresca participación y actividad</small></span></a>
                    </aside>
                </div>
            </section>

            <section class="cc-module cc-audit-module">
                <header><div><span class="cc-module-step">3</span><div><h2>Actividad reciente</h2><p>Trazabilidad de las últimas acciones.</p></div></div><a class="v-link" href="/audit.php?election_id=<?=$id?>">Ver auditoría completa →</a></header>
                <div class="cc-feed" id="live-activity">
                    <?php if (!$activity): ?><p class="v-muted">Sin actividad reciente.</p><?php endif; ?>
                    <?php foreach (array_slice($activity, 0, 6) as $event): ?><div><time><?=htmlspecialchars(date('H:i', strtotime($event['created_at'])))?></time><i></i><strong><?=htmlspecialchars(ucfirst(str_replace(['.', '_'], ' ', (string) $event['event_type'])))?></strong></div><?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</div>
<script>
(() => {
    const url = '/live-status.php?election_id=<?= $id ?>';
    const initialStatus = <?= json_encode($election['status']) ?>;
    const activity = document.getElementById('live-activity');
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            if (data.status !== initialStatus) { location.reload(); return; }
            document.getElementById('live-participation').textContent = data.participation + '%';
            document.getElementById('live-progress').style.width = Math.min(100, data.participation) + '%';
            const votes = document.getElementById('live-votes');
            votes.replaceChildren(document.createTextNode(data.voted + ' '));
            const total = document.createElement('small');
            total.textContent = 'de ' + data.eligible;
            votes.append(total);
            document.getElementById('live-pending').textContent = data.pending;
            document.getElementById('live-question').textContent = data.current_question || 'Sin seleccionar';
            activity.replaceChildren();
            if (!data.activity.length) {
                const p = document.createElement('p');
                p.className = 'v-muted';
                p.textContent = 'Sin actividad reciente.';
                activity.append(p);
            }
            for (const event of data.activity) {
                const line = document.createElement('div');
                const time = document.createElement('time');
                const date = new Date(event.created_at.replace(' ', 'T'));
                time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('es-CL', {hour:'2-digit',minute:'2-digit'});
                const dot = document.createElement('i');
                const label = document.createElement('strong');
                label.textContent = event.event_type.replace(/[._]/g, ' ').replace(/^./, x => x.toUpperCase());
                line.append(time, dot, label);
                activity.append(line);
            }
        } catch (_) {
            // Preserve the last valid values when the connection is interrupted.
        } finally { busy = false; }
    }
    setInterval(refresh, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
</body>
</html>
