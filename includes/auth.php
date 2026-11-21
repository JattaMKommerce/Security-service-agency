<?php
declare(strict_types=1);

const LOGIN_WINDOW_MIN = 15;
const LOGIN_MAX_PER_ID = 5;
const LOGIN_MAX_PER_IP = 40;   // guards often share one office Wi-Fi / mobile carrier IP
const ADMIN_IDLE_TIMEOUT = 4 * 3600;   // 4 hours without activity logs an admin out

// ---- Brute-force protection -----------------------------------------------

function login_locked(string $identifier): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
    $byId = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?', [$identifier, $since]);
    $byIp = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [client_ip(), $since]);
    return $byId >= LOGIN_MAX_PER_ID || $byIp >= LOGIN_MAX_PER_IP;
}

function login_failed(string $identifier): void
{
    q('INSERT INTO login_attempts (ip, identifier, attempted_at) VALUES (?,?,?)', [client_ip(), $identifier, now()]);
    // Keep the table small.
    if (random_int(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function login_succeeded(string $identifier): void
{
    q('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
}

/**
 * Checks a password. Also accepts it with accidental spaces removed from the
 * start/end (phone keyboards often add a trailing space after autocomplete).
 */
function password_matches(string $password, string $hash): bool
{
    if (password_verify($password, $hash)) {
        return true;
    }
    $trimmed = trim($password);
    return $trimmed !== $password && $trimmed !== '' && password_verify($trimmed, $hash);
}

// ---- Admin ----------------------------------------------------------------

/** @return string|null error message, or null on success */
function admin_attempt_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    $id = 'admin:' . $email;
    if (login_locked($id)) {
        return 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MIN . ' minutes and try again.';
    }
    $admin = q_one('SELECT * FROM admins WHERE email = ?', [$email]);
    if (!$admin || !password_matches($password, $admin['password_hash'])) {
        login_failed($id);
        return 'Invalid email or password.';
    }
    if (!(int)$admin['is_active']) {
        return 'This account is disabled. Contact the administrator.';
    }
    login_succeeded($id);
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_last_active'] = time();
    q('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $admin['id']]);
    log_activity('admin.login', $admin['email'], 'admin', (int)$admin['id']);
    return null;
}

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) return $admin;
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        $row = q_one('SELECT id, name, email, role, is_active FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
        if ($row && (int)$row['is_active']) {
            $admin = $row;
        }
    }
    return $admin;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_last_active']);
    session_regenerate_id(true);
}

/** Protects admin pages and admin APIs. */
function require_admin(bool $json = false): array
{
    $idle = isset($_SESSION['admin_last_active']) && (time() - (int)$_SESSION['admin_last_active']) > ADMIN_IDLE_TIMEOUT;
    $admin = $idle ? null : current_admin();
    if (!$admin) {
        if ($idle) admin_logout();
        if ($json) json_error('Not logged in', 401);
        if ($idle) flash('warning', 'You were logged out after a period of inactivity.');
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    // Background polling should not keep a session alive forever.
    if (!$json) {
        $_SESSION['admin_last_active'] = time();
        maybe_run_maintenance();
    }
    return $admin;
}

function require_superadmin(): array
{
    $admin = require_admin();
    if ($admin['role'] !== 'superadmin') {
        flash('danger', 'Only a super admin can open that page.');
        redirect('admin/index.php');
    }
    return $admin;
}

// ---- Guard ----------------------------------------------------------------

/**
 * Finds a guard by employee code or phone number. The phone may be typed with
 * spaces, dashes, a +91 / 0 prefix: the last 10 digits are compared.
 */
function find_guard_by_login(string $login): ?array
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }
    $guard = q_one('SELECT * FROM guards WHERE employee_code = ? LIMIT 1', [strtoupper($login)]);
    if ($guard) {
        return $guard;
    }
    $digits = preg_replace('/\D/', '', $login);
    if (strlen($digits) < 10) {
        return null;
    }
    $last10 = substr($digits, -10);
    $rows = q_all("SELECT * FROM guards WHERE RIGHT(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 2", [$last10]);
    return count($rows) === 1 ? $rows[0] : null;   // never guess between two guards
}

function guard_attempt_login(string $login, string $password): ?string
{
    $login = trim($login);
    $guard = find_guard_by_login($login);
    // Count failures per guard account (code and phone share one counter).
    $id = $guard ? 'guard:#' . $guard['id'] : 'guard:' . mb_strtolower($login);
    if (login_locked($id)) {
        return 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MIN . ' minutes and try again, or ask your supervisor to reset your password.';
    }
    if (!$guard) {
        login_failed($id);
        return 'Invalid employee code / phone or password. Check the number carefully.';
    }
    if (!password_matches($password, $guard['password_hash'])) {
        login_failed($id);
        return 'Invalid employee code / phone or password. Tap the eye icon to check your password.';
    }
    if ($guard['status'] !== 'active') {
        return 'Your account is ' . label($guard['status']) . '. Contact your supervisor.';
    }
    login_succeeded($id);
    session_regenerate_id(true);
    $_SESSION['guard_id'] = (int)$guard['id'];
    guard_issue_remember((int)$guard['id']);
    log_activity('guard.login', $guard['employee_code'], 'guard', (int)$guard['id']);
    return null;
}

/** Called when an admin sets a new password, so the guard isn't still locked out. */
function clear_guard_login_lock(int $guardId): void
{
    q('DELETE FROM login_attempts WHERE identifier = ?', ['guard:#' . $guardId]);
}

const GUARD_REMEMBER_COOKIE = 'sag_guard';
const GUARD_REMEMBER_DAYS = 30;

/**
 * Keeps the guard signed in on their phone for 30 days, so a closed browser
 * doesn't stop tracking mid-shift. Logging in on a new phone signs out the old one.
 */
function guard_issue_remember(int $guardId): void
{
    $token = bin2hex(random_bytes(32));
    $expires = time() + GUARD_REMEMBER_DAYS * 86400;
    q('UPDATE guards SET remember_hash = ?, remember_expires = ? WHERE id = ?', [hash('sha256', $token), date('Y-m-d H:i:s', $expires), $guardId]);
    setcookie(GUARD_REMEMBER_COOKIE, $guardId . ':' . $token, [
        'expires'  => $expires,
        'path'     => rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/') . '/',
        'secure'   => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function guard_clear_remember(?int $guardId): void
{
    if ($guardId) {
        q('UPDATE guards SET remember_hash = NULL, remember_expires = NULL WHERE id = ?', [$guardId]);
    }
    setcookie(GUARD_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600, 'path' => rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/') . '/',
        'secure' => IS_HTTPS, 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function guard_login_from_cookie(): ?int
{
    $raw = $_COOKIE[GUARD_REMEMBER_COOKIE] ?? '';
    if (!is_string($raw) || !preg_match('/^(\d+):([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    $row = q_one('SELECT id, remember_hash, remember_expires, status FROM guards WHERE id = ?', [(int)$m[1]]);
    if (!$row || !$row['remember_hash'] || $row['status'] !== 'active' || strtotime((string)$row['remember_expires']) < time()
        || !hash_equals($row['remember_hash'], hash('sha256', $m[2]))) {
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['guard_id'] = (int)$row['id'];
    return (int)$row['id'];
}

function current_guard(): ?array
{
    static $guard = false;
    if ($guard !== false) return $guard;
    $guard = null;
    if (empty($_SESSION['guard_id'])) {
        guard_login_from_cookie();
    }
    if (!empty($_SESSION['guard_id'])) {
        $row = q_one('SELECT * FROM guards WHERE id = ?', [(int)$_SESSION['guard_id']]);
        if ($row && $row['status'] === 'active') {
            $guard = $row;
        }
    }
    return $guard;
}

function require_guard(bool $json = false): array
{
    $guard = current_guard();
    if (!$guard) {
        unset($_SESSION['guard_id']);
        if ($json) json_error('Not logged in', 401);
        redirect('guard/login.php');
    }
    return $guard;
}
