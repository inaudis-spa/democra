<?php
declare(strict_types=1);require dirname(__DIR__).'/config/bootstrap.php';use InaudisVote\Core\Authorization;use InaudisVote\Services\CensusInsightsService;use InaudisVote\Services\ElectionService;use InaudisVote\Services\LiveControlService;use InaudisVote\Services\ResultService;
$user=Authorization::requireRoles(['admin','organizer']);$id=(int)($_GET['election_id']??0);$e=ElectionService::find($id);if(!$e||!Authorization::canReviewElection($user,$id,(int)$e['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}$s=LiveControlService::snapshot($id);$qs=ElectionService::questions($id);$current=null;foreach($qs as $q)if((int)$q['id']===$s['current_question_id'])$current=$q;$results=($e['status']==='closed'&&$s['display_results'])?ResultService::summary($id):null;
if($e['question_flow']!=='sequential' && $user['role']!=='admin'){http_response_code(403);exit('Acceso no autorizado.');}
if($e['question_flow']==='sequential'){$results=ResultService::summary($id);$current=null;foreach($qs as $q)if((int)$q['id']===$s['current_question_id'])$current=$q;}
$insights=CensusInsightsService::forDisplay($id);
?><!doctype html><html lang="es"><head><link rel="icon" type="image/png" href="/assets/democra-mark.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pantalla pública | DEMOCRA</title><link rel="stylesheet" href="/assets/quorum.css"><link rel="stylesheet" href="/assets/democra.css"><link rel="stylesheet" href="/assets/live-control.css"><link rel="stylesheet" href="/assets/display-insights.css"></head><body class="vota-ui display-body"><main class="public-display"><header><div class="v-brand" style="margin:0;color:var(--navy)"><img class="v-brandmark" src="/assets/democra-mark.png" alt="Democra"><span><span class="v-brand-name">DEMOCRA<small>VOTO DIGITAL</small></span></span></div><span id="live-status" class="v-live-pill <?=$e['status']==='open'?'is-live':''?>">● <?=htmlspecialchars(strtoupper($e['status']))?></span></header><section class="display-center"><div class="v-kicker"><?=htmlspecialchars($e['organization_name'])?></div><h1><?=htmlspecialchars($e['title'])?></h1><?php if($e['question_flow']==='sequential' && $current && $current['voting_status']==='closed'):?><div class="display-results"><h2>Resultados · Pregunta cerrada</h2><h3><?=htmlspecialchars($current['text'])?></h3><?php foreach($results['questions'] as $result):if((int)$result['id']===(int)$current['id']):$total=\InaudisVote\Services\SequentialQuestionService::participation((int)$current['id']);foreach($result['options'] as $o):$pct=$total>0?round(100*(int)$o['votes']/$total,1):0;?><div class="display-result-row"><span><?=htmlspecialchars($o['label'])?></span><b><?=$pct?>% · <?=(int)$o['votes']?> votos</b></div><div style="background:#e7edf3;border-radius:12px;height:18px;overflow:hidden"><div style="width:<?=min(100,$pct)?>%;height:100%;background:#12a6a0"></div></div><?php endforeach;endif;endforeach;?></div><?php elseif($s['display_results']&&$results&&$e['question_flow']!=='sequential'):?><div class="display-results"><h2>Resultados</h2><?php foreach($results['questions'] as $q):?><article><strong><?=htmlspecialchars($q['text'])?></strong><?php foreach($q['options'] as $o):?><div class="display-result-row"><span><?=htmlspecialchars($o['label'])?></span><b><?=htmlspecialchars((string)$o['votes'])?></b></div><?php endforeach;?></article><?php endforeach;?></div><?php else:?><div id="live-question" class="display-question"><?=$current?htmlspecialchars($current['text']):'La votación está preparada'?></div><div class="display-participation"><strong id="live-participation"><?=$s['participation']?>%</strong><span id="live-votes"><?=$s['voted']?> de <?=$s['eligible']?> votos recibidos</span><div class="v-progress"><span id="live-progress" style="width:<?=min(100,$s['participation'])?>%"></span></div></div><?php endif;?></section><footer>Proceso digital verificable · Actualización automática</footer></main><script>
(() => {
    const overview={eligible:<?=(int)$s['eligible']?>,voted:<?=(int)$s['voted']?>,insights:<?=json_encode($insights,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>};
    const center=document.querySelector('.display-center');
    if(center){
        const panel=document.createElement('section');panel.className='display-overview';panel.setAttribute('aria-label','Información agregada del padrón');
        const metrics=document.createElement('div');metrics.className='display-metric-grid';
        [['eligible','Habilitados',overview.eligible],['voted','Votos recibidos',overview.voted],['pending','Pendientes',Math.max(0,overview.eligible-overview.voted)]].forEach(([key,label,value])=>{const card=document.createElement('div');card.dataset.metric=key;const strong=document.createElement('strong');strong.textContent=value;const span=document.createElement('span');span.textContent=label;card.append(strong,span);metrics.appendChild(card)});panel.appendChild(metrics);
        if(overview.insights.length){const title=document.createElement('h2');title.textContent='Composición del padrón';panel.appendChild(title);const charts=document.createElement('div');charts.className='display-insight-grid';overview.insights.forEach(insight=>{const card=document.createElement('article');const heading=document.createElement('h3');heading.textContent=insight.label;card.appendChild(heading);insight.items.forEach(item=>{const row=document.createElement('div');row.className='display-insight-row';const label=document.createElement('span');label.textContent=item.label;const value=document.createElement('b');value.textContent=item.count+' · '+String(item.percentage).replace('.',',')+'%';const track=document.createElement('i');const fill=document.createElement('em');fill.style.width=Math.min(100,item.percentage)+'%';track.appendChild(fill);row.append(label,value,track);card.appendChild(row)});charts.appendChild(card)});panel.appendChild(charts)}
        center.appendChild(panel);
    }
    const url = '/live-status.php?election_id=<?= $id ?>';
    const originalStatus = <?= json_encode($e['status']) ?>;
    const originalResultsVisible = <?= json_encode((bool)($s['display_results'] && $e['status'] === 'closed')) ?>;
    const originalQuestionId = <?= (int)$s['current_question_id'] ?>;
    const sequential = <?=json_encode($e['question_flow']==='sequential')?>;
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(url, {credentials:'same-origin',cache:'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            if (sequential) { location.reload(); return; }
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
            const eligibleMetric=document.querySelector('[data-metric="eligible"] strong');if(eligibleMetric)eligibleMetric.textContent=data.eligible;
            const votedMetric=document.querySelector('[data-metric="voted"] strong');if(votedMetric)votedMetric.textContent=data.voted;
            const pendingMetric=document.querySelector('[data-metric="pending"] strong');if(pendingMetric)pendingMetric.textContent=Math.max(0,data.eligible-data.voted);
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
