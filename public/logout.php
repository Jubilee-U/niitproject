<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// logout() itself starts the session so it can clear and destroy it.
logout();

// Send the (now logged-out) visitor back to the login page.
header('Location: ' . LOGIN_URL);
exit;
