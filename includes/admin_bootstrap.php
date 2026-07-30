<?php

declare(strict_types=1);

/**
 * Admin page bootstrap.
 *
 * Include this ONCE at the very top of any admin page, in the global scope
 * (not inside a function). It will:
 *   - load the database ($pdo) and the auth helpers,
 *   - start the hardened session,
 *   - enforce that the visitor is a logged-in admin (redirecting to the login
 *     page and exiting if not),
 *   - expose $pdo and $currentUser to the page that included it.
 *
 * Scope note: a PHP include executes in the scope of the line that included
 * it, so the $pdo and $currentUser defined below land in the including page's
 * scope — provided you include this file at the top level, not within a
 * function or method.
 *
 *   Usage from /public/admin/dashboard.php:
 *     require_once __DIR__ . '/../../includes/admin_bootstrap.php';
 *     // $pdo and $currentUser are now available here.
 */

// This file lives in /includes. database.php is one level up in /config;
// auth.php is right beside this file.
require_once __DIR__ . '/../config/database.php'; // provides $pdo
require_once __DIR__ . '/auth.php';               // provides startSecureSession(), requireRole(), getCurrentUser()

startSecureSession();

// Redirects to the login page and exits if the visitor is not a logged-in
// admin. The role is read fresh from the database inside requireRole().
requireRole($pdo, 'admin');

// Guaranteed non-null here: if the visitor weren't a valid admin,
// requireRole() would already have redirected and exited above.
$currentUser = getCurrentUser($pdo);
