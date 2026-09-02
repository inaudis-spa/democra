<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ResultService;
use InaudisVote\Services\ActaPdfService;

$user=Authorization::requireRoles(['admin','organizer']);
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'])){http_response_code(404);exit('Votación no encontrada.');}
$certificate=ResultService::certify($electionId,(int)$user['id']);
$pdf=ActaPdfService::build($election,$certificate['results'],$certificate['sha256']);
$filename='acta-eleccion-'.$electionId.'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
