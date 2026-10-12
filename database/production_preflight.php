<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Database;

$failures = [];
$warnings = [];

if (PHP_VERSION_ID < 80100) $failures[] = 'Se requiere PHP 8.1 o superior. Versión detectada: '.PHP_VERSION;
foreach (['pdo_mysql', 'mbstring', 'json'] as $extension) {
    if (!extension_loaded($extension)) $failures[] = 'Falta la extensión PHP '.$extension.'.';
}

try {
    $pdo = Database::connection();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Base seleccionada: {$database}".PHP_EOL;

    $requiredTables = [
        'organizations', 'users', 'elections', 'election_managers', 'election_witnesses',
        'questions', 'options', 'census_members', 'voter_tokens', 'ballots', 'ballot_answers',
        'audit_logs', 'result_certificates', 'election_live_state', 'witness_checkpoints',
    ];
    $tableStatement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    foreach ($requiredTables as $table) {
        $tableStatement->execute(['table' => $table]);
        if (!(int) $tableStatement->fetchColumn()) $failures[] = 'Falta la tabla '.$table.'.';
    }

    $requiredColumns = [
        'organizations' => ['primary_color', 'secondary_color'],
        'elections' => ['ballot_locked_at', 'census_locked_at'],
        'voter_tokens' => ['otp_attempts', 'otp_locked_at'],
    ];
    $columnStatement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $columnStatement->execute(['table' => $table, 'column' => $column]);
            if (!(int) $columnStatement->fetchColumn()) $failures[] = "Falta {$table}.{$column}.";
        }
    }

    if (!in_array('Falta la tabla election_managers.', $failures, true)) {
        $unassignedManagers = (int) $pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='organizer' AND u.status='active' AND NOT EXISTS (SELECT 1 FROM election_managers em WHERE em.user_id=u.id)")->fetchColumn();
        if ($unassignedManagers > 0) $warnings[] = $unassignedManagers.' organizador(es) activos no tienen votaciones asignadas.';
    }
    if (!in_array('Falta la tabla election_witnesses.', $failures, true)) {
        $unassignedWitnesses = (int) $pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='witness' AND u.status='active' AND NOT EXISTS (SELECT 1 FROM election_witnesses ew WHERE ew.user_id=u.id)")->fetchColumn();
        if ($unassignedWitnesses > 0) $warnings[] = $unassignedWitnesses.' Ministro(s) de Fe activos no tienen votaciones asignadas.';
    }
} catch (Throwable $exception) {
    $failures[] = 'No fue posible verificar la base: '.$exception->getMessage();
}

foreach ($warnings as $warning) echo '[ADVERTENCIA] '.$warning.PHP_EOL;
foreach ($failures as $failure) echo '[ERROR] '.$failure.PHP_EOL;
if ($failures) {
    echo 'PRE-FLIGHT: FALLÓ. No publiques hasta corregir los errores.'.PHP_EOL;
    exit(1);
}
echo 'PRE-FLIGHT: OK. Esquema compatible con la aplicación.'.PHP_EOL;
