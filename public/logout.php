<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use InaudisVote\Auth\AuthService;

if (AuthService::check()) {
    AuthService::logout();
}

header('Location: /login.php');
exit;
