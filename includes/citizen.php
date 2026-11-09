<?php
declare(strict_types=1);

/*
 * Citizen (public) accounts. Kept separate from staff users so a citizen
 * session can never reach staff pages, and staff/citizen sign-outs don't
 * affect each other.
 */

const CITIZEN_MIN_PASSWORD = 8;

function current_citizen(): ?array
{
    static $cache = [];
    $id = $_SESSION['citizen_id'] ?? null;
    if (!$id) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = q_one('SELECT id, full_name, email, phone, created_at, last_login_at FROM citizens WHERE id = ? AND active = 1', [$id]);
    }
    if (!$cache[$id]) {
        unset($_SESSION['citizen_id']);
        return null;
    }
    // Idle timeout: 2 hours.
    if (isset($_SESSION['citizen_seen']) && time() - $_SESSION['citizen_seen'] > 7200) {
        logout_citizen();
        flash('info', 'You were signed out after a period of inactivity. Please sign in again.');
        return null;
    }
    $_SESSION['citizen_seen'] = time();
    return $cache[$id];
}

/**
 * Only allow redirects back into this site's public pages (never staff, never off-site).
 */
function safe_next(?string $next): ?string
{
    if (!$next) {
        return null;
    }
    $base = base_path() . '/';
    if (!str_starts_with($next, $base) || str_contains($next, '//') || str_contains($next, '\\')
        || str_starts_with($next, $base . 'staff/') || preg_match('/[\r\n]/', $next)) {
        return null;
    }
    return $next;
}

function redirect_after_citizen_auth(?string $next): never
{
    $next = safe_next($next);
    if ($next) {
        header('Location: ' . $next);
        exit;
    }
    redirect('account/index.php');
}

/** Send the visitor to sign in, then bring them back to where they were. */
function require_citizen(string $message = 'Please sign in or create an account to continue.'): array
{
    $c = current_citizen();
    if ($c) {
        return $c;
    }
    flash('info', $message);
    redirect('account/login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']);
}

function citizen_sign_in(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['citizen_id'] = $id;
    $_SESSION['citizen_seen'] = time();
    q('UPDATE citizens SET last_login_at = NOW() WHERE id = ?', [$id]);
}

function attempt_citizen_login(string $email, string $password): bool
{
    $c = q_one('SELECT id, password_hash FROM citizens WHERE email = ? AND active = 1', [$email]);
    if (!$c || !password_verify($password, $c['password_hash'])) {
        q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), mb_substr($email, 0, 160)]);
        return false;
    }
    if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE citizens SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
    }
    citizen_sign_in((int)$c['id']);
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    return true;
}

/**
 * Validate and create an account.
 * @return array{0:?int,1:array<string,string>} [new id or null, errors]
 */
function register_citizen(array $src): array
{
    $errors = [];
    $name = trim((string)($src['full_name'] ?? ''));
    $email = strtolower(trim((string)($src['email'] ?? '')));
    $phone = trim((string)($src['phone'] ?? ''));
    $password = (string)($src['password'] ?? '');
    $confirm = (string)($src['password_confirm'] ?? '');

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        $errors['full_name'] = 'Enter your full name (3–120 characters).';
    }
    if (!valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (q_val('SELECT id FROM citizens WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists. Sign in instead.';
    }
    if ($phone !== '' && !valid_phone($phone)) {
        $errors['phone'] = 'Enter a valid phone number, e.g. 0712 345 678.';
    }
    if (strlen($password) < CITIZEN_MIN_PASSWORD) {
        $errors['password'] = 'Use a password of at least ' . CITIZEN_MIN_PASSWORD . ' characters.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
    if (empty($src['consent'])) {
        $errors['consent'] = 'Please confirm you agree to how we use your details.';
    }
    if ($errors) {
        return [null, $errors];
    }
    q('INSERT INTO citizens (full_name, email, phone, password_hash) VALUES (?,?,?,?)', [
        $name, $email, $phone !== '' ? normalise_phone($phone) : null, password_hash($password, PASSWORD_DEFAULT),
    ]);
    return [(int)db()->lastInsertId(), []];
}

function logout_citizen(): void
{
    unset($_SESSION['citizen_id'], $_SESSION['citizen_seen']);
    session_regenerate_id(true);
}
