<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Database;

$options = getopt('', ['database:', 'admin-email::', 'confirm:']);
$expectedDatabase = trim((string) ($options['database'] ?? ''));
$adminEmail = mb_strtolower(trim((string) ($options['admin-email'] ?? '')));
$confirmation = (string) ($options['confirm'] ?? '');

if ($expectedDatabase === '' || $confirmation !== 'DELETE-ALL-DATA') {
    fwrite(STDERR, "Uso obligatorio:\n");
    fwrite(STDERR, "php database/reset_production.php --database=NOMBRE_REAL --admin-email=correo@dominio.cl --confirm=DELETE-ALL-DATA\n");
    fwrite(STDERR, "La opción --admin-email puede omitirse solamente cuando existe un único administrador activo.\n");
    exit(2);
}

$pdo = Database::connection();
$actualDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDatabase === '' || !hash_equals($expectedDatabase, $actualDatabase)) {
    fwrite(STDERR, "ABORTADO: la conexión apunta a '{$actualDatabase}', no a '{$expectedDatabase}'.\n");
    exit(3);
}

if ($adminEmail !== '') {
    $adminStatement = $pdo->prepare("SELECT id,name,email,password_hash,status,created_at FROM users WHERE role='admin' AND email=:email LIMIT 2");
    $adminStatement->execute(['email' => $adminEmail]);
} else {
    $adminStatement = $pdo->query("SELECT id,name,email,password_hash,status,created_at FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 2");
}
$admins = $adminStatement->fetchAll(PDO::FETCH_ASSOC);
if (count($admins) !== 1) {
    fwrite(STDERR, 'ABORTADO: se esperaba exactamente un administrador'.($adminEmail !== '' ? " con correo {$adminEmail}" : ' activo').'. Encontrados: '.count($admins).PHP_EOL);
    exit(4);
}
$admin = $admins[0];

$schemaPath = __DIR__.'/schema.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    fwrite(STDERR, "ABORTADO: no se pudo leer {$schemaPath}.\n");
    exit(5);
}
$schema = preg_replace('/^\s*CREATE DATABASE.*?;\s*/mis', '', $schema) ?? $schema;
$schema = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $schema) ?? $schema;
$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [])));
if (!$statements) {
    fwrite(STDERR, "ABORTADO: el esquema no contiene instrucciones ejecutables.\n");
    exit(6);
}

echo "Base confirmada: {$actualDatabase}\n";
echo "Administrador preservado: {$admin['email']}\n";
echo "Eliminando todas las tablas y reconstruyendo el esquema...\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    $tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Nombre de tabla inesperado.');
        $pdo->exec('DROP TABLE IF EXISTS `'.$table.'`');
    }
    foreach ($statements as $statement) $pdo->exec($statement);

    $restore = $pdo->prepare("INSERT INTO users (organization_id,name,email,password_hash,role,status,created_at) VALUES (NULL,:name,:email,:password_hash,'admin','active',:created_at)");
    $restore->execute([
        'name' => $admin['name'],
        'email' => $admin['email'],
        'password_hash' => $admin['password_hash'],
        'created_at' => $admin['created_at'],
    ]);
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
if ($userCount !== 1 || $adminCount !== 1) {
    fwrite(STDERR, "ERROR: la reconstrucción terminó con una cantidad inesperada de usuarios.\n");
    exit(7);
}

echo "RESET COMPLETADO: esquema reconstruido y único administrador restaurado ({$admin['email']}).\n";
echo "Ejecuta ahora: php database/production_preflight.php\n";
