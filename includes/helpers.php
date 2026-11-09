<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the app relative to the web root, e.g. /huduma%20smart%20assist */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
        $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        $base = implode('/', array_map('rawurlencode', explode('/', $rel)));
    } else {
        $base = (string)parse_url(config('app.url'), PHP_URL_PATH);
    }
    $base = rtrim($base, '/');
    return $base;
}

/** URL for links inside the site. */
function url(string $path = '', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** Absolute URL, for emails. */
function abs_url(string $path = '', array $query = []): string
{
    $u = rtrim(config('app.url'), '/') . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired or the form was tampered with. Please go back, refresh and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_string($v) && preg_match('/^-?\d+$/', trim($v))) ? (int)$v : $default;
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/** Random reference such as HSA-7K3QXP (no ambiguous characters). */
function make_reference(string $prefix, string $table): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $ref = $prefix . '-' . $code;
        $exists = q_val("SELECT 1 FROM {$table} WHERE reference = ?", [$ref]);
    } while ($exists);
    return $ref;
}

function make_token(): string
{
    return bin2hex(random_bytes(20));
}

function normalise_reference(string $ref): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $ref));
}

function valid_email(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function normalise_phone(string $phone): string
{
    $p = preg_replace('/[^\d+]/', '', $phone);
    if (preg_match('/^0(7|1)\d{8}$/', $p)) {
        $p = '+254' . substr($p, 1);
    }
    return $p;
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^\+?\d{9,15}$/', normalise_phone($phone));
}

function fmt_date(string $date): string
{
    return date('D, j M Y', strtotime($date));
}

function fmt_time(string $time): string
{
    return date('g:i a', strtotime($time));
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('j M Y, g:i a', strtotime($dt)) : '—';
}

function lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), 'strlen'));
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}

function audit(string $action, string $details = ''): void
{
    q('INSERT INTO audit_log (user_id, action, details, ip) VALUES (?,?,?,?)', [
        current_user()['id'] ?? null, $action, mb_substr($details, 0, 500), client_ip(),
    ]);
}

const APPOINTMENT_STATUSES = [
    'booked'     => ['Booked', 'blue'],
    'checked_in' => ['Checked in', 'yellow'],
    'served'     => ['Served', 'green'],
    'no_show'    => ['No-show', 'grey'],
    'cancelled'  => ['Cancelled', 'coral'],
];

const REQUEST_STATUSES = [
    'submitted'     => ['Submitted', 'blue', 'We have received your request.'],
    'in_progress'   => ['In progress', 'yellow', 'Your request is being processed.'],
    'action_needed' => ['Action needed', 'coral', 'We need something from you before we can continue.'],
    'ready'         => ['Ready for collection', 'green', 'Your request is ready. Please visit the centre to collect.'],
    'completed'     => ['Completed', 'green', 'Your request is complete.'],
    'rejected'      => ['Not approved', 'grey', 'Your request could not be approved. See the note for details.'],
];

function status_badge(string $status, array $map): string
{
    [$label, $tone] = $map[$status] ?? [ucfirst($status), 'grey'];
    return '<span class="badge badge-' . e($tone) . '">' . e($label) . '</span>';
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Simple fixed-window rate limit stored in the session (per visitor). */
function rate_limited(string $bucket, int $max, int $windowSeconds): bool
{
    $now = time();
    $hits = array_filter($_SESSION['rl'][$bucket] ?? [], fn($t) => $t > $now - $windowSeconds);
    if (count($hits) >= $max) {
        $_SESSION['rl'][$bucket] = $hits;
        return true;
    }
    $hits[] = $now;
    $_SESSION['rl'][$bucket] = $hits;
    return false;
}

/** Short badge text for an agency, e.g. "National Transport and Safety Authority" -> "NTSA". */
function agency_initials(string $agency): string
{
    if (strcasecmp($agency, 'eCitizen') === 0) {
        return 'eC';
    }
    $words = preg_split('/\s+/', preg_replace('/[^\p{L}\s]/u', ' ', $agency)) ?: [];
    $letters = '';
    foreach ($words as $w) {
        if ($w !== '' && !in_array(strtolower($w), ['of', 'and', 'the', 'for', 'on', 'in'], true)) {
            $letters .= mb_strtoupper(mb_substr($w, 0, 1));
        }
    }
    return mb_substr($letters, 0, 4) ?: '•';
}

/** Host name of an external link, for "Apply on ecitizen.go.ke" labels. */
function link_host(?string $u): string
{
    return $u ? preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST)) : '';
}
