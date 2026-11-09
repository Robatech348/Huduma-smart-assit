<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    $user = $id ? q_one('SELECT u.*, c.name AS centre_name FROM users u LEFT JOIN centres c ON c.id = u.centre_id WHERE u.id = ? AND u.active = 1', [$id]) : null;
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_staff(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect('staff/login.php');
    }
    // Idle timeout: 60 minutes.
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 3600) {
        logout_user();
        flash('info', 'You were signed out after a period of inactivity.');
        redirect('staff/login.php');
    }
    $_SESSION['last_seen'] = time();
    return $u;
}

function require_admin(): array
{
    $u = require_staff();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        render_header('Not allowed', 'staff');
        echo '<div class="card"><h2>Administrators only</h2><p>You do not have permission to open this page.</p></div>';
        render_footer('staff');
        exit;
    }
    return $u;
}

/**
 * Centre scope for queries: admins see all centres (null), staff only their own.
 * Staff without an assigned centre see nothing (-1).
 */
function staff_centre_scope(): ?int
{
    $u = current_user();
    if (!$u || $u['role'] === 'admin') {
        return null;
    }
    return $u['centre_id'] ? (int)$u['centre_id'] : -1;
}

function can_access_centre(int $centreId): bool
{
    $scope = staff_centre_scope();
    return $scope === null || $scope === $centreId;
}

function too_many_login_attempts(string $email): bool
{
    $count = (int)q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR email = ?) AND attempted_at > (NOW() - INTERVAL 15 MINUTE)',
        [client_ip(), $email]
    );
    return $count >= 8;
}

function attempt_login(string $email, string $password): bool
{
    $user = q_one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), mb_substr($email, 0, 160)]);
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['last_seen'] = time();
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
}

/** Centres the signed-in user may work with. */
function centres_in_scope(): array
{
    $scope = staff_centre_scope();
    return $scope === null
        ? q_all('SELECT * FROM centres ORDER BY name')
        : q_all('SELECT * FROM centres WHERE id = ?', [$scope]);
}

/** SQL fragment + params restricting a centre_id column to the user's scope. */
function scope_sql(string $column): array
{
    $scope = staff_centre_scope();
    return $scope === null ? ['1=1', []] : ["{$column} = ?", [$scope]];
}
