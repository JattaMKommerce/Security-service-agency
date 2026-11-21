<?php
declare(strict_types=1);

// ---- Output / URLs --------------------------------------------------------

/** HTML-escape for output. Use on every dynamic value printed in a page. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Asset URL with cache-busting version based on file modification time. */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    // REMOTE_ADDR cannot be spoofed; Hostinger sets it to the real visitor IP.
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from POST (or null when empty). */
function post_str(string $key, ?int $maxLen = 255): ?string
{
    $v = $_POST[$key] ?? null;
    if (!is_string($v)) return null;
    $v = trim($v);
    if ($v === '') return null;
    return $maxLen ? mb_substr($v, 0, $maxLen) : $v;
}

function get_str(string $key, int $maxLen = 100): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maxLen) : '';
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int)$v : $default;
}

function post_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    if (is_string($v) && preg_match('/^-?\d+$/', trim($v))) return (int)trim($v);
    return $default;
}

function post_float(string $key): ?float
{
    $v = $_POST[$key] ?? null;
    if (is_string($v) && is_numeric(trim($v))) return (float)trim($v);
    return null;
}

/** Validate Y-m-d date string. */
function valid_date(?string $d): bool
{
    if (!$d) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function valid_time(?string $t): bool
{
    return $t !== null && (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
}

// ---- JSON -----------------------------------------------------------------

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function json_error(string $message, int $code = 400): never
{
    json_out(['ok' => false, 'error' => $message], $code);
}

/** Reads a JSON request body (falls back to form POST). */
function request_data(): array
{
    static $data = null;
    if ($data !== null) return $data;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $decoded = json_decode((string)$raw, true);
        $data = is_array($decoded) ? $decoded : [];
    } else {
        $data = $_POST;
    }
    return $data;
}

// ---- CSRF -----------------------------------------------------------------

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

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? (request_data()['_csrf'] ?? ''));
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Stop the request if the CSRF token is missing or wrong. */
function require_csrf(bool $json = false): void
{
    if (csrf_valid()) return;
    if ($json) json_error('Session expired. Please reload the page.', 419);
    flash('danger', 'Your session expired. Please try again.');
    redirect(safe_back_url());
}

/** The referring page if it is on this site, otherwise the home page. */
function safe_back_url(string $fallback = ''): string
{
    $back = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $host = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
    if ($back !== '' && parse_url($back, PHP_URL_HOST) === $host) {
        return $back;
    }
    return url($fallback);
}

// ---- Flash messages -------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Keep submitted form values to re-fill a form after a validation error. */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(): void
{
    $_SESSION['_old'] = $_POST;
    unset($_SESSION['_old']['_csrf'], $_SESSION['_old']['password']);
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---- Settings -------------------------------------------------------------

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (Throwable $e) {
            // Not installed yet.
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}

function company_name(): string
{
    return setting('company_name', $GLOBALS['config']['app']['name'] ?? 'Security Agency');
}

// ---- Formatting -----------------------------------------------------------

function money($amount): string
{
    return setting('currency_symbol', '₹') . number_format((float)$amount, 2);
}

function fmt_date(?string $d, string $format = 'd M Y'): string
{
    if (!$d || str_starts_with($d, '0000')) return '—';
    $ts = strtotime($d);
    return $ts ? date($format, $ts) : '—';
}

function fmt_datetime(?string $d): string
{
    return fmt_date($d, 'd M Y, h:i A');
}

function fmt_time(?string $t): string
{
    if (!$t) return '—';
    $ts = strtotime('1970-01-01 ' . $t);
    return $ts !== false ? date('h:i A', $ts) : '—';
}

function fmt_minutes(?int $mins): string
{
    if ($mins === null) return '—';
    return sprintf('%dh %02dm', intdiv($mins, 60), $mins % 60);
}

function time_ago(?string $d): string
{
    if (!$d) return 'never';
    $diff = time() - strtotime($d);
    if ($diff < 0) $diff = 0;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}

function rate_unit(string $rateType): string
{
    return ['daily' => 'day', 'hourly' => 'hour', 'monthly' => 'month'][$rateType] ?? $rateType;
}

function label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

/** Bootstrap badge for any status value used in the app. */
function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'on_leave' => 'warning', 'inactive' => 'secondary',
        'completed' => 'primary', 'cancelled' => 'secondary',
        'open' => 'danger', 'acknowledged' => 'warning', 'resolved' => 'success',
        'closed' => 'success', 'auto_closed' => 'secondary',
        'draft' => 'secondary', 'sent' => 'info', 'paid' => 'success', 'partially_paid' => 'warning',
        'on_duty' => 'success', 'off_duty' => 'secondary', 'available' => 'info', 'assigned' => 'primary',
        'sos' => 'danger', 'geofence_exit' => 'warning',
    ];
    $cls = $map[$status] ?? 'secondary';
    $text = $status === 'geofence_exit' ? 'Left Site' : ($status === 'sos' ? 'SOS' : label($status));
    return '<span class="badge badge-soft-' . $cls . '">' . e($text) . '</span>';
}

function guard_photo_url(?string $photo): ?string
{
    return $photo ? url('uploads/guards/' . rawurlencode($photo)) : null;
}

function avatar(string $name, ?string $photo, string $size = 'md'): string
{
    $src = guard_photo_url($photo);
    if ($src) {
        return '<img src="' . e($src) . '" class="avatar avatar-' . $size . '" alt="">';
    }
    return '<span class="avatar avatar-' . $size . ' avatar-initials">' . e(initials($name)) . '</span>';
}

// ---- Pagination -----------------------------------------------------------

/**
 * @return array{rows: array, page: int, pages: int, total: int, per_page: int}
 */
function paginate(string $fromWhere, array $params, string $select, string $orderBy, int $perPage = 25): array
{
    $total = (int)q_val("SELECT COUNT(*) $fromWhere", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, get_int('page', 1)), $pages);
    $offset = ($page - 1) * $perPage;
    $rows = q_all("SELECT $select $fromWhere ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => $perPage];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '<div class="small text-muted">' . $p['total'] . ' record(s)</div>';
    }
    $qs = $_GET;
    $link = function (int $n) use ($qs) {
        $qs['page'] = $n;
        return '?' . http_build_query($qs);
    };
    $from = ($p['page'] - 1) * $p['per_page'] + 1;
    $to = min($p['total'], $p['page'] * $p['per_page']);
    $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    $html .= '<div class="small text-muted">Showing ' . $from . '–' . $to . ' of ' . $p['total'] . '</div>';
    $html .= '<ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] - 1)) . '">&laquo;</a></li>';
    $start = max(1, $p['page'] - 2);
    $end = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] + 1)) . '">&raquo;</a></li>';
    $html .= '</ul></div>';
    return $html;
}

// ---- Uploads --------------------------------------------------------------

/**
 * Validates and stores an uploaded image. Returns the stored filename,
 * null when no file was sent, or throws RuntimeException with a user message.
 */
function store_image_upload(string $field, string $dir, int $maxBytes = 3_000_000): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed (error ' . (int)$f['error'] . ').');
    }
    if ($f['size'] > $maxBytes) {
        throw new RuntimeException('Photo must be smaller than ' . round($maxBytes / 1_000_000, 1) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Photo must be a JPG, PNG or WEBP image.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $target)) {
        throw new RuntimeException('Could not save the photo. Check folder permissions for uploads/.');
    }
    @chmod($target, 0644);
    return $name;
}

// ---- Activity log ---------------------------------------------------------

function log_activity(string $action, string $details = '', ?string $actorType = null, ?int $actorId = null): void
{
    if ($actorType === null) {
        if (!empty($_SESSION['admin_id'])) {
            $actorType = 'admin';
            $actorId = (int)$_SESSION['admin_id'];
        } elseif (!empty($_SESSION['guard_id'])) {
            $actorType = 'guard';
            $actorId = (int)$_SESSION['guard_id'];
        } else {
            $actorType = 'system';
        }
    }
    try {
        q('INSERT INTO activity_log (actor_type, actor_id, action, details, ip, created_at) VALUES (?,?,?,?,?,?)',
          [$actorType, $actorId, mb_substr($action, 0, 60), mb_substr($details, 0, 500), client_ip(), now()]);
    } catch (Throwable $e) {
        error_log('activity_log failed: ' . $e->getMessage());
    }
}

// ---- Codes ----------------------------------------------------------------

/** Next sequential code such as GRD-0001 / CLT-0001. */
function next_code(string $table, string $column, string $prefix): string
{
    $allowed = ['guards.employee_code', 'clients.client_code'];
    if (!in_array("$table.$column", $allowed, true)) {
        throw new InvalidArgumentException('Invalid code target');
    }
    $max = q_val("SELECT MAX(CAST(SUBSTRING($column, ?) AS UNSIGNED)) FROM $table WHERE $column LIKE ?",
                 [strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string)(((int)$max) + 1), 4, '0', STR_PAD_LEFT);
}
