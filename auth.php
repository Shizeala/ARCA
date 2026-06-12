<?php
// ================================================================
//  auth.php — Session bootstrap, login helpers, RBAC guards
// ================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Timeout check ─────────────────────────────────────────────────
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?timeout=1');
    exit;
}
$_SESSION['last_active'] = time();

// ── Public helpers ────────────────────────────────────────────────

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $user = fetch_one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }
    return $user;
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}

/**
 * Redirect to login unless the user is logged in with one of $roles.
 * Call at the top of every protected page.
 */
function require_role(string ...$roles): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (!empty($roles) && !in_array(current_role(), $roles, true)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>');
    }
}

function login(string $username, string $password): bool {
    $user = fetch_one('SELECT * FROM users WHERE username = ?', [trim($username)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['full_name']= $user['full_name'] ?? $user['username'];
    return true;
}

function logout(): void {
    session_unset();
    session_destroy();
}

function redirect_by_role(): void {
    $map = [
        'admin'   => BASE_URL . '/admin/index.php',
        'teacher' => BASE_URL . '/teacher/index.php',
        'student' => BASE_URL . '/student/index.php',
    ];
    header('Location: ' . ($map[current_role()] ?? BASE_URL . '/login.php'));
    exit;
}

// ── CSRF token helpers ────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('CSRF token mismatch.');
    }
}