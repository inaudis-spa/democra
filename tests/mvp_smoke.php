<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use InaudisVote\Core\Database;
use InaudisVote\Services\CensusService;
use InaudisVote\Services\ResultService;
use InaudisVote\Services\VotingService;

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

$pdo = Database::connection();

$electionId = (int) $pdo->query("SELECT id FROM elections WHERE title='Elección Directorio Demo 2026' ORDER BY id DESC LIMIT 1")->fetchColumn();
ok($electionId > 0, 'elección demo disponible');

$memberId = (int) $pdo->query("SELECT id FROM census_members WHERE election_id={$electionId} ORDER BY id LIMIT 1")->fetchColumn();
ok($memberId > 0, 'votante demo disponible');

$question = $pdo->query("SELECT id FROM questions WHERE election_id={$electionId} ORDER BY id LIMIT 1")->fetch();
ok((bool) $question, 'pregunta demo disponible');
$questionId = (int) $question['id'];

$optionId = (int) $pdo->query("SELECT id FROM options WHERE question_id={$questionId} ORDER BY id LIMIT 1")->fetchColumn();
ok($optionId > 0, 'alternativa demo disponible');

$invitation = CensusService::generateInvitation($memberId);
ok(isset($invitation['token'], $invitation['otp']), 'invitación y OTP generados');
ok(strlen((string) $invitation['otp']) === 6, 'OTP tiene 6 dígitos');

$token = (string) $invitation['token'];
$otp = (string) $invitation['otp'];

$context = VotingService::resolveToken($token);
ok($context !== null, 'token resoluble');
ok(VotingService::canVote($context) === false, 'no permite votar antes de validar OTP');

ok(VotingService::verifyOtp($token, '000000') === false, 'OTP inválido rechazado');
ok(VotingService::verifyOtp($token, $otp) === true, 'OTP válido aceptado');

$context = VotingService::resolveToken($token);
ok($context !== null && VotingService::canVote($context), 'votante habilitado tras OTP');

$receipt = VotingService::submit($token, [(string) $questionId => [$optionId]]);
ok((bool) preg_match('/^[a-f0-9]{64}$/', $receipt), 'comprobante SHA-256 generado');

$hasVoted = (int) $pdo->query("SELECT has_voted FROM census_members WHERE id={$memberId}")->fetchColumn();
ok($hasVoted === 1, 'censo marca voto emitido');

$ballots = (int) $pdo->query("SELECT COUNT(*) FROM ballots WHERE election_id={$electionId}")->fetchColumn();
ok($ballots === 1, 'papeleta anónima registrada una sola vez');

$ballotColumns = $pdo->query("SHOW COLUMNS FROM ballots")->fetchAll(PDO::FETCH_COLUMN);
ok(!in_array('census_member_id', $ballotColumns, true) && !in_array('user_id', $ballotColumns, true) && !in_array('email', $ballotColumns, true), 'papeleta no contiene identificadores directos del votante');

$secondVoteBlocked = false;
try {
    VotingService::submit($token, [(string) $questionId => [$optionId]]);
} catch (Throwable $e) {
    $secondVoteBlocked = true;
}
ok($secondVoteBlocked, 'segundo voto bloqueado');

$summary = ResultService::summary($electionId);
ok((int) $summary['participation']['voted'] === 1, 'participación refleja voto emitido');

$adminId = (int) $pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
ok($adminId > 0, 'administrador demo disponible');
$certificate = ResultService::certify($electionId, $adminId);
ok((bool) preg_match('/^[a-f0-9]{64}$/', (string) $certificate['sha256']), 'certificado de resultados SHA-256 generado');

$auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type IN ('voter.invitation_generated','voter.otp_verified','vote.submitted','results.certified')")->fetchColumn();
ok($auditCount >= 4, 'eventos críticos registrados en auditoría');

fwrite(STDOUT, "\nMVP SMOKE TEST: PASS\n");
