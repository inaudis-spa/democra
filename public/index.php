<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use InaudisVote\Auth\AuthService;

if (AuthService::check()) {
    header('Location: /dashboard.php');
    exit;
}

header('Location: /login.php');
exit;
