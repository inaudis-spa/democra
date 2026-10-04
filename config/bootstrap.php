<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'InaudisVote\\';
    $baseDir = dirname(__DIR__) . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function env_load(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

env_load(dirname(__DIR__) . '/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Santiago');

function user_error_message(Throwable $exception): string
{
    if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
        return $exception->getMessage();
    }
    if ($exception instanceof PDOException) {
        return match ((string) $exception->getCode()) {
            '23000' => 'No fue posible guardar porque existe un dato duplicado o relacionado que no es válido.',
            '22001' => 'Uno de los textos supera el largo permitido.',
            default => 'No fue posible guardar la información. Revisa los datos e intenta nuevamente.',
        };
    }
    return 'Ocurrió un problema inesperado. Intenta nuevamente.';
}
