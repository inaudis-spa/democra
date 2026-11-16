<?php
declare(strict_types=1);

require dirname(__DIR__).'/config/bootstrap.php';

use InaudisVote\Core\Authorization;
use InaudisVote\Services\ElectionService;
use InaudisVote\Services\LiveControlService;
use InaudisVote\Services\ResultService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$user = Authorization::requireRoles(['admin', 'organizer']);
$id = (int) ($_GET['election_id'] ?? 0);
$election = ElectionService::find($id);
if (!$election || !Authorization::canReviewElection($user, $id, (int) $election['organization_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Votación no encontrada.']);
    exit;
}

$snapshot = LiveControlService::snapshot($id);
$questions = ElectionService::questions($id);
$current = null;
foreach ($questions as $question) {
    if ((int) $question['id'] === $snapshot['current_question_id']) {
        $current = $question['text'];
        break;
    }
}
$showResults = $election['status'] === 'closed' && $snapshot['display_results'];
echo json_encode([
    'status' => $election['status'],
    'eligible' => $snapshot['eligible'],
    'voted' => $snapshot['voted'],
    'pending' => $snapshot['pending'],
    'participation' => $snapshot['participation'],
    'current_question' => $current,
    'current_question_id' => $snapshot['current_question_id'],
    'display_results' => $showResults,
    'results' => $showResults ? ResultService::summary($id) : null,
    'activity' => $user['role']==='admin' ? LiveControlService::recentActivity($id, 6) : [],
], JSON_UNESCAPED_UNICODE);
