<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Services\CensusTemplateService;
use InaudisVote\Services\ElectionService;

$user=Authorization::requireRoles(['admin']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election || !Authorization::canManageElection($user,(int)$election['organization_id'],$electionId)){http_response_code(404);exit('Votación no encontrada.');}
$fields=CensusTemplateService::election($electionId);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="quorum_plantilla_votantes.csv"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

// UTF-8 BOM and semicolon delimiter: compatible with Excel in Chile.
echo "\xEF\xBB\xBF";
$stream=fopen('php://output','wb');
if ($stream === false) throw new RuntimeException('No fue posible generar la plantilla.');
fputcsv($stream,array_column($fields,'key'),';','"', '');
fclose($stream);
