<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use InaudisVote\Auth\AuthService;

if (!AuthService::check()) {
    header('Location: /login.php');
    exit;
}

$user = AuthService::user();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | INAUDIS Vote</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <header class="border-b border-slate-800 bg-slate-900/80">
        <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-cyan-400">INAUDIS</p>
                <h1 class="text-xl font-semibold">Vote</h1>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="text-right">
                    <p class="font-medium"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-slate-400"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a href="/logout.php" class="rounded-lg border border-slate-700 px-3 py-2 hover:border-cyan-400">Salir</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-10">
        <div class="mb-8">
            <h2 class="text-3xl font-semibold">Dashboard</h2>
            <p class="text-slate-400 mt-2">Base funcional del Demo v1. Los siguientes módulos se irán habilitando sobre esta estructura.</p>
        </div>

        <section class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Organizaciones</p>
                <p class="mt-3 text-3xl font-semibold">—</p>
                <p class="mt-2 text-xs text-cyan-400">Módulo siguiente</p>
            </article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Votaciones activas</p>
                <p class="mt-3 text-3xl font-semibold">—</p>
                <p class="mt-2 text-xs text-slate-500">Sin datos aún</p>
            </article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Participación</p>
                <p class="mt-3 text-3xl font-semibold">—%</p>
                <p class="mt-2 text-xs text-slate-500">Sin proceso seleccionado</p>
            </article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Auditoría</p>
                <p class="mt-3 text-3xl font-semibold">Activa</p>
                <p class="mt-2 text-xs text-emerald-400">Login registrado</p>
            </article>
        </section>
    </main>
</body>
</html>
