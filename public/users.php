<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\OrganizationService;
use InaudisVote\Services\UserService;

$user=Authorization::requireRoles(['admin']);
$error=null;$message=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){$error='Solicitud inválida.';}
    else{
        try{
            $action=(string)($_POST['action']??'create');
            if($action==='toggle'){UserService::toggleStatus((int)($_POST['user_id']??0));$message='Estado actualizado.';}
            else{UserService::create($_POST);$message='Usuario creado.';}
        }catch(Throwable $e){$error=$e->getMessage();}
    }
}
$users=UserService::all();$organizations=OrganizationService::all();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Usuarios | INAUDIS Vote</title><script src="https://cdn.tailwindcss.com"></script></head><body class="min-h-screen bg-slate-950 text-slate-100"><main class="max-w-7xl mx-auto p-6"><nav class="mb-8 flex flex-wrap gap-4 text-sm"><a href="/dashboard.php" class="text-cyan-400">Dashboard</a><a href="/organizations.php">Organizaciones</a><a href="/elections.php">Votaciones</a><a href="/audit.php">Auditoría</a><a href="/logout.php" class="text-red-300">Salir</a></nav><h1 class="text-3xl font-semibold mb-6">Usuarios administrativos</h1><?php if($message): ?><div class="mb-4 p-3 rounded bg-emerald-950 border border-emerald-800"><?=htmlspecialchars($message)?></div><?php endif; ?><?php if($error): ?><div class="mb-4 p-3 rounded bg-red-950 border border-red-800"><?=htmlspecialchars($error)?></div><?php endif; ?><div class="grid lg:grid-cols-3 gap-6"><section class="bg-slate-900 border border-slate-800 rounded-2xl p-5"><h2 class="font-semibold mb-4">Nuevo usuario</h2><form method="post" class="space-y-3"><?=Csrf::input()?><input type="hidden" name="action" value="create"><input name="name" required placeholder="Nombre" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="email" type="email" required placeholder="Email" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><input name="password" type="password" minlength="8" required placeholder="Contraseña inicial" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><select name="role" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><option value="organizer">Organizador</option><option value="admin">Administrador</option></select><select name="organization_id" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-3"><option value="">Sin organización / Admin</option><?php foreach($organizations as $o): ?><option value="<?=$o['id']?>"><?=htmlspecialchars($o['name'])?></option><?php endforeach; ?></select><button class="w-full bg-cyan-400 text-slate-950 font-semibold rounded-lg p-3">Crear usuario</button></form></section><section class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-500"><tr><th class="text-left p-2">Nombre</th><th>Email</th><th>Rol</th><th>Organización</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($users as $u): ?><tr class="border-t border-slate-800"><td class="p-2"><?=htmlspecialchars($u['name'])?></td><td class="p-2"><?=htmlspecialchars($u['email'])?></td><td class="text-center"><?=htmlspecialchars($u['role'])?></td><td class="text-center"><?=htmlspecialchars((string)$u['organization_name'])?></td><td class="text-center"><?=htmlspecialchars($u['status'])?></td><td class="p-2 text-right"><?php if((int)$u['id']!==(int)$user['id']): ?><form method="post" class="inline"><?=Csrf::input()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?=$u['id']?>"><button class="text-cyan-300 hover:underline">Activar/Desactivar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section></div></main></body></html>