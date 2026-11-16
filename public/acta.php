<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Core\Csrf;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\ResultService;
use InaudisVote\Services\ActaPdfService;

$user=Authorization::requireRoles(['admin']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::validate($_POST['_csrf']??null)){http_response_code(405);header('Allow: POST');exit('El acta debe generarse desde la acción confirmada del sistema.');}
$electionId=(int)($_GET['election_id']??0);
$election=ElectionService::find($electionId);
if(!$election||!Authorization::canManageElection($user,(int)$election['organization_id'],$electionId)){http_response_code(404);exit('Votación no encontrada.');}
if($election['status']!=='closed'){http_response_code(409);exit('El acta solo puede generarse después de cerrar la votación.');}
$certificate=ResultService::certify($electionId,(int)$user['id']);
$pdf=ActaPdfService::build($election,$certificate['results'],$certificate['sha256']);
$filename='acta-eleccion-'.$electionId.'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
