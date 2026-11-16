<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;

Authorization::requireRoles(['admin']);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="quorum_plantilla_votantes.csv"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

// UTF-8 BOM and semicolon delimiter: compatible with Excel in Chile.
echo "\xEF\xBB\xBF";
$stream=fopen('php://output','wb');
if ($stream === false) throw new RuntimeException('No fue posible generar la plantilla.');
fputcsv($stream,['rut','nombre','email','telefono','peso','segmento'],';','"', '');
fclose($stream);
