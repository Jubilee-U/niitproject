<?php

declare(strict_types=1);

/**
 * Authentication helpers for the School Admission Management System.
 *
 * Depends on a $pdo (PDO) instance from /config/database.php for the
 * functions that touch the database. Any function that starts or reads the
 * session must be called BEFORE the script sends output, because starting a
 * session (and regenerating its ID) sends a Set-Cookie header.
 */

// Where unauthenticated users are sent. Guarded so including this file more
// than once in a request doesn't re-define the constant.
if (!defined('LOGIN_URL')) {
    define('LOGIN_URL', '/niitproject/public/login.php');
}

// A valid bcrypt hash of an unknown random string. It is NEVER a real
// credential — it exists only so attemptLogin() can run password_verify()
// even when the email doesn't exist, keeping response timing uniform.
if (!defined('DUMMY_PASSWORD_HASH')) {
    define('DUMMY_PASSWORD_HASH', '$2y$10$AimBjlYfF96AEEFsz1nz7uOhtgrl0XyXzeRTaUtThrhTkHKdx/BGa');
}

/**
 * Start a session with hardened cookie settings.
 *
 * Idempotent: if a session is already active we return immediately, so
 * several includes in one request won't trigger a "session already started"
 * warning.
 *
 * Why each cookie flag matters:
 *  - httponly: JavaScript can't read the cookie, so an XSS flaw can't steal
 *    the session ID and ship it to an attacker.
 *  - secure: the cookie is only sent over HTTPS, so it can't be sniffed on a
 *    plaintext connection. We enable it automatically when the request is
 *    HTTPS; on plain-HTTP local dev (e.g. XAMPP) it stays off so the cookie
 *    is still delivered — there's nothing to protect on a local HTTP link.
 *  - samesite=Lax: the browser withholds the cookie from most cross-site
 *    requests, which blunts CSRF at the transport layer (a forged request
 *    from another origin won't automatically carry the session).
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps =
        (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    session_set_cookie_params([
        'lifetime' => 0,        // session cookie: cleared when the browser closes
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Attempt to log a user in with an email + password.
 *
 * Returns true on success, false on ANY failure. Callers must show one
 * identical message for every false result: revealing whether the email
 * existed or the password was wrong lets an attacker enumerate accounts.
 */
function attemptLogin(PDO $pdo, string $email, string $password): bool
{
    startSecureSession();

    $stmt = $pdo->prepare(
        'SELECT id, password_hash FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(); // associative array, or false if no match

    // Pick the real hash when the account exists, otherwise a fixed dummy.
    $hash = ($user !== false && isset($user['password_hash']))
        ? (string) $user['password_hash']
        : DUMMY_PASSWORD_HASH;

    // Run the (deliberately slow) bcrypt comparison UNCONDITIONALLY. Whether
    // or not the email exists, the request spends roughly the same time here,
    // so an attacker can't use timing to discover which emails are registered.
    $passwordValid = password_verify($password, $hash);

    if ($user === false || !$passwordValid) {
        return false;
    }

    // ── Session fixation defense ──────────────────────────────────────────
    // Regenerate the session ID at the exact moment privilege changes
    // (anonymous -> authenticated). If an attacker had planted or learned the
    // visitor's pre-login session ID, it is now useless: passing `true`
    // deletes the old server-side session, so that known ID can't be used to
    // ride the newly authenticated session.
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['logged_in'] = true;

    return true;
}

/**
 * Destroy the session completely.
 */
function logout(): void
{
    startSecureSession();

    // Clear the in-memory copy first.
    $_SESSION = [];

    // Expire the browser's session cookie. session_destroy() removes the
    // server-side data but leaves the cookie (and its ID string) in the
    // client; actively expiring it stops that dead ID from lingering.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    // Wipe the server-side session storage.
    session_destroy();
}

/**
 * Return the logged-in user's full row (minus the password hash), or null.
 *
 * The row is read FRESH from the database on every call rather than cached
 * in the session, so account changes take effect immediately.
 */
function getCurrentUser(PDO $pdo): ?array
{
    startSecureSession();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    // Note: password_hash is intentionally NOT selected — a "who am I" lookup
    // has no reason to pull a secret into scope.
    $stmt = $pdo->prepare(
        'SELECT id, applicant_id, username, email, role, must_change_password, created_at
           FROM users
          WHERE id = :id
          LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    // If the row is gone (account deleted), treat the visitor as logged out.
    return $user === false ? null : $user;
}

/**
 * Guard a page so only users with a given role may view it.
 *
 * Signature note: this takes $pdo in addition to $role. Your spec listed
 * requireRole(string $role), but the requirement to check the role FRESH
 * from the database means the function needs the connection — there's no way
 * to do the DB-backed check without it.
 *
 * We compare against the DB role (via getCurrentUser), never a role copied
 * into $_SESSION. If an admin later demotes or revokes this account, the
 * change is enforced on the very next request — a stale cached role can't be
 * used to hold onto access the user should no longer have.
 */
function requireRole(PDO $pdo, string $role): void
{
    startSecureSession();

    $user = getCurrentUser($pdo);

    // One response for both "not logged in" and "wrong role" so we don't hint
    // that the page exists to users who shouldn't reach it.
    if ($user === null || $user['role'] !== $role) {
        header('Location: ' . LOGIN_URL);
        exit; // stop the protected page from running after the redirect
    }
}

/**
 * Return the session's CSRF token, creating one on first use.
 *
 * A CSRF token is a secret, unpredictable value bound to the session and
 * embedded in our own forms. Because the same-origin policy stops a
 * third-party page from reading it, a forged cross-site POST can't include
 * the right value — which is how we tell "the user submitted our form" apart
 * from "another site tricked the user's browser into submitting".
 */
function generateCsrfToken(): string
{
    startSecureSession();

    if (empty($_SESSION['csrf_token'])) {
        // random_bytes() is a cryptographically secure RNG — the token must
        // be unguessable, so a normal rand()/mt_rand() would not be safe here.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the session's token.
 *
 * hash_equals() does a constant-time comparison, so response timing doesn't
 * leak how many leading bytes of a guessed token were correct — closing off
 * a byte-by-byte brute force of the token.
 */
function verifyCsrfToken(?string $token): bool
{
    startSecureSession();

    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}
