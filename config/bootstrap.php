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
    $baseDir = dirname(__DIR__).'/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $baseDir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($file)) require $file;
});

function env_load(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }
    }
}

$secureEnvFile = getenv('APP_SECURE_ENV_FILE') ?: '/var/www/secure/vote.php';
if (is_file($secureEnvFile)) {
    $secureValues = require $secureEnvFile;
    if (is_array($secureValues)) {
        foreach ($secureValues as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null) && getenv($key) === false) {
                putenv($key.'='.(string) $value);
                $_ENV[$key] = (string) $value;
            }
        }
    }
}
env_load(dirname(__DIR__).'/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Santiago');

function user_error_message(Throwable $exception): string
{
    if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) return $exception->getMessage();
    if ($exception instanceof PDOException) {
        return match ((string) $exception->getCode()) {
            '23000' => 'No fue posible guardar porque existe un dato duplicado o relacionado que no es válido.',
            '22001' => 'Uno de los textos supera el largo permitido.',
            default => 'No fue posible guardar la información. Revisa los datos e intenta nuevamente.',
        };
    }
    return 'Ocurrió un problema inesperado. Intenta nuevamente.';
}

function application_error_reference(Throwable $exception): string
{
    $reference = strtoupper(substr(hash('sha256', microtime(true).random_bytes(16)), 0, 10));
    $message = sprintf('[%s] [%s] %s in %s:%d%s%s', date('c'), $reference, $exception->getMessage(), $exception->getFile(), $exception->getLine(), PHP_EOL, $exception->getTraceAsString());
    error_log($message);
    $logDirectory = dirname(__DIR__).'/storage/logs';
    if (is_dir($logDirectory) && is_writable($logDirectory)) error_log($message.PHP_EOL, 3, $logDirectory.'/application.log');
    return $reference;
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $exception): void {
        $reference = application_error_reference($exception);
        $databaseMismatch = $exception instanceof PDOException && in_array((string) $exception->getCode(), ['42S02', '42S22'], true);
        http_response_code($databaseMismatch ? 503 : 500);
        if (headers_sent()) return;
        header('Content-Type: text/html; charset=UTF-8');
        $title = $databaseMismatch ? 'Actualización pendiente' : 'No pudimos completar la solicitud';
        $description = $databaseMismatch
            ? 'La aplicación fue actualizada, pero la base de datos aún requiere una migración. Informa este código al administrador.'
            : 'Ocurrió un error interno. Intenta nuevamente y, si continúa, informa este código al administrador.';
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error | QUORUM</title><link rel="stylesheet" href="/assets/quorum.css"></head><body class="vota-ui"><main class="v-voter" style="display:grid;place-items:center"><section class="v-card v-pad" style="max-width:620px;text-align:center"><img src="/assets/quorum-mark.svg" alt="" style="width:58px"><div class="v-kicker" style="margin-top:18px">QUORUM</div><h1 class="v-title">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h1><p class="v-subtitle">'.htmlspecialchars($description, ENT_QUOTES, 'UTF-8').'</p><div class="v-hash" style="margin:18px 0">Código: '.htmlspecialchars($reference, ENT_QUOTES, 'UTF-8').'</div><a class="v-btn v-btn-primary" href="/login.php">Volver al acceso</a></section></main></body></html>';
    });
}
