<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\CensusService;

$user=Authorization::requireRoles(['admin','organizer']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}
$error=null;$message=null;$inviteUrl=null;$otp=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida.';}
    else{
        try{
            $action=(string)($_POST['action']??'');
            if($action==='add'){
                CensusService::add($electionId,$_POST);$message='Votante agregado.';
            }elseif($action==='import'){
                $csv='';
                if(isset($_FILES['csv_file'])&&($_FILES['csv_file']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){
                    $size=(int)($_FILES['csv_file']['size']??0);
                    if($size<=0||$size>2*1024*1024) throw new RuntimeException('El archivo CSV debe pesar entre 1 byte y 2 MB.');
                    $name=(string)($_FILES['csv_file']['name']??'');
                    if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='csv') throw new RuntimeException('Solo se permiten archivos .csv.');
                    $csv=(string)file_get_contents((string)$_FILES['csv_file']['tmp_name']);
                }else{
                    $csv=(string)($_POST['csv']??'');
                }
                if(trim($csv)==='') throw new RuntimeException('Selecciona un CSV o pega su contenido.');
                $result=CensusService::importCsv($electionId,$csv);
                $message='Importados: '.$result['created'].'. Errores: '.count($result['errors']);
                if($result['errors'])$error=implode(' | ',array_slice($result['errors'],0,3));
            }elseif($action==='invite'){
                $inv=CensusService::generateInvitation((int)($_POST['member_id']??0));
                $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
                $inviteUrl=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').'/vote.php?token='.$inv['token'];
                $otp=$inv['otp'];$message='Invitación demo generada para '.$inv['email'].'.';
            }
        }catch(Throwable $e){$error=$e->getMessage();}
    }
}
$members=CensusService::list($electionId);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Censo | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="max-w-7xl mx-auto p-6"><nav class="mb-8 flex gap-4 text-sm"><a href="/election.php?id=<?=$electionId?>" class="text-cyan-400">← Configuración</a><a href="/results.php?election_id=<?=$electionId?>">Resultados</a></nav><h1 class="text-3xl font-semibold mb-2">Censo electoral</h1><p class="text-slate-400 mb-6"><?=htmlspecialchars($election['title'])?></p>
<?php if($message): ?><div class="mb-4 p-3 rounded bg-emerald-950 border border-emerald-800"><?=htmlspecialchars($message)?></div><?php endif; ?><?php if($error): ?><div class="mb-4 p-3 rounded bg-red-950 border border-red-800"><?=htmlspecialchars($error)?></div><?php endif; ?><?php if($inviteUrl): ?><div class="mb-4 p-4 rounded bg-cyan-950 border border-cyan-800 break-all"><strong>Enlace demo:</strong><br><?=htmlspecialchars($inviteUrl)?><br><br><strong>OTP demo (10 min):</strong> <span class="text-2xl"><?=htmlspecialchars((string)$otp)?></span><p class="mt-2 text-xs text-slate-400">En producción este OTP se enviará por email/SMS y no se mostrará al administrador.</p></div><?php endif; ?>
<div class="grid lg:grid-cols-3 gap-6"><aside class="space-y-5"><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-4">Agregar votante</h2><form method="post" class="space-y-3"><?=Csrf::input()?><input type="hidden" name="action" value="add"><input name="rut" placeholder="RUT" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="name" required placeholder="Nombre" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="email" type="email" required placeholder="Email" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="phone" placeholder="Teléfono" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="vote_weight" type="number" step="0.0001" value="1" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="segment" placeholder="Segmento" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><button class="w-full bg-cyan-400 text-slate-950 font-semibold rounded-lg p-3">Agregar</button></form></section><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-3">Importar CSV</h2><p class="text-xs text-slate-500 mb-3">RUT;Nombre;Email;Teléfono;Peso;Segmento</p><form method="post" enctype="multipart/form-data" class="space-y-3"><?=Csrf::input()?><input type="hidden" name="action" value="import"><input type="file" name="csv_file" accept=".csv,text/csv" class="w-full text-sm text-slate-300"><textarea name="csv" rows="5" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3" placeholder="O pega aquí el contenido CSV"></textarea><button class="w-full border border-cyan-500 text-cyan-300 rounded-lg p-3">Importar</button></form></section></aside><section class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-x-auto"><div class="flex justify-between mb-4"><h2 class="font-semibold">Votantes</h2><span class="text-sm text-slate-400"><?=count($members)?> registros</span></div><table class="w-full text-sm"><thead class="text-slate-500"><tr><th class="text-left p-2">Nombre</th><th>Email</th><th>Peso</th><th>Votó</th><th></th></tr></thead><tbody><?php foreach($members as $m): ?><tr class="border-t border-slate-800"><td class="p-2"><?=htmlspecialchars($m['name'])?></td><td class="p-2"><?=htmlspecialchars($m['email'])?></td><td class="text-center"><?=htmlspecialchars((string)$m['vote_weight'])?></td><td class="text-center"><?=$m['has_voted']?'Sí':'No'?></td><td class="p-2 text-right"><?php if(!$m['has_voted']): ?><form method="post" class="inline"><?=Csrf::input()?><input type="hidden" name="action" value="invite"><input type="hidden" name="member_id" value="<?=$m['id']?>"><button class="text-cyan-300 hover:underline">Generar invitación + OTP</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section></div></main></body></html>