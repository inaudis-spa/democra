<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use InaudisVote\Auth\AuthService;

if (AuthService::check()) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Ingresa email y contraseña.';
    } elseif (AuthService::attempt($email, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Credenciales inválidas o usuario inactivo.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | INAUDIS Vote</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
    <main class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 p-8 shadow-2xl">
        <div class="mb-8">
            <p class="text-sm uppercase tracking-[0.25em] text-cyan-400">INAUDIS</p>
            <h1 class="text-3xl font-semibold mt-2">Vote</h1>
            <p class="text-slate-400 mt-2">Plataforma de votación electrónica.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 rounded-lg border border-red-800 bg-red-950/60 p-3 text-sm text-red-200">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5" autocomplete="off">
            <div>
                <label for="email" class="block text-sm font-medium mb-2">Email</label>
                <input id="email" name="email" type="email" required
                       class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none focus:border-cyan-400"
                       value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium mb-2">Contraseña</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none focus:border-cyan-400">
            </div>
            <button type="submit"
                    class="w-full rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-slate-950 hover:bg-cyan-300 transition">
                Ingresar
            </button>
        </form>
    </main>
</body>
</html>
