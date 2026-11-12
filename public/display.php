<?php
declare(strict_types=1);require dirname(__DIR__).'/config/bootstrap.php';use InaudisVote\Core\Authorization;use InaudisVote\Services\ElectionService;use InaudisVote\Services\LiveControlService;use InaudisVote\Services\ResultService;
$user=Authorization::requireRoles(['admin','organizer']);$id=(int)($_GET['election_id']??0);$e=ElectionService::find($id);if(!$e||!Authorization::canManageElection($user,(int)$e['organization_id'],$id)){http_response_code(404);exit('Votación no encontrada.');}$s=LiveControlService::snapshot($id);$qs=ElectionService::questions($id);$current=null;foreach($qs as $q)if((int)$q['id']===$s['current_question_id'])$current=$q;$results=($e['status']==='closed'&&$s['display_results'])?ResultService::summary($id):null;
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pantalla pública | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/live-control.css"></head><body class="vota-ui display-body"><main class="public-display"><header><div class="v-brand" style="margin:0;color:var(--navy)"><img class="v-brandmark" src="/assets/quorum-mark.svg" alt=""><span><span class="v-brand-name">QUORUM<small>by INAUDIS</small></span></span></div><span id="live-status" class="v-live-pill <?=$e['status']==='open'?'is-live':''?>">● <?=htmlspecialchars(strtoupper($e['status']))?></span></header><section class="display-center"><div class="v-kicker"><?=htmlspecialchars($e['organization_name'])?></div><h1><?=htmlspecialchars($e['title'])?></h1><?php if($s['display_results']&&$results):?><div class="display-results"><h2>Resultados</h2><?php foreach($results['questions'] as $q):?><article><strong><?=htmlspecialchars($q['text'])?></strong><?php foreach($q['options'] as $o):?><div class="display-result-row"><span><?=htmlspecialchars($o['label'])?></span><b><?=htmlspecialchars((string)$o['votes'])?></b></div><?php endforeach;?></article><?php endforeach;?></div><?php else:?><div id="live-question" class="display-question"><?=$current?htmlspecialchars($current['text']):'La votación está preparada'?></div><div class="display-participation"><strong id="live-participation"><?=$s['participation']?>%</strong><span id="live-votes"><?=$s['voted']?> de <?=$s['eligible']?> votos recibidos</span><div class="v-progress"><span id="live-progress" style="width:<?=min(100,$s['participation'])?>%"></span></div></div><?php endif;?></section><footer>Proceso digital verificable · Actualización automática</footer></main><script>
(() => {
    const url = '/live-status.php?election_id=<?= $id ?>';
    const originalStatus = <?= json_encode($e['status']) ?>;
    const originalResultsVisible = <?= json_encode((bool)($s['display_results'] && $e['status'] === 'closed')) ?>;
    const originalQuestionId = <?= (int)$s['current_question_id'] ?>;
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(url, {credentials:'same-origin',cache:'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            if (data.status !== originalStatus ||
                data.display_results !== originalResultsVisible ||
                data.current_question_id !== originalQuestionId) {
                location.reload();
                return;
            }
            const participation = document.getElementById('live-participation');
            if (!participation) return;
            participation.textContent = data.participation + '%';
            document.getElementById('live-votes').textContent = data.voted + ' de ' + data.eligible + ' votos recibidos';
            document.getElementById('live-progress').style.width = Math.min(100,data.participation) + '%';
            const question = document.getElementById('live-question');
            if (question) question.textContent = data.current_question || 'La votación está preparada';
        } catch (_) {
            // Keep the last valid state if the network is unavailable.
        } finally { busy = false; }
    }
    setInterval(refresh,5000);
    document.addEventListener('visibilitychange', () => {if (!document.hidden) refresh();});
})();
</script></body></html>
