<?php
/**
 * Polled by every admin page: open alert count, latest open alerts, and
 * alerts newer than ?since_id= (for toast + sound notifications).
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin(true);
session_write_close(); // don't block other requests while polling
maybe_run_live_checks(); // raise / clear late, no-signal, GPS-off and not-moving alerts

$since = get_int('since_id');
$base = "SELECT al.id, al.type, al.message, al.status, al.created_at, al.lat, al.lng,
                g.full_name AS guard_name, g.employee_code, c.name AS client_name
           FROM alerts al
           JOIN guards g ON g.id = al.guard_id
           LEFT JOIN clients c ON c.id = al.client_id";

$fmt = function (array $r): array {
    return [
        'id' => (int)$r['id'], 'type' => $r['type'], 'message' => $r['message'], 'status' => $r['status'],
        'guard_name' => $r['guard_name'], 'guard_code' => $r['employee_code'], 'client_name' => $r['client_name'],
        'lat' => $r['lat'] !== null ? (float)$r['lat'] : null, 'lng' => $r['lng'] !== null ? (float)$r['lng'] : null,
        'created_at' => $r['created_at'], 'ago' => time_ago($r['created_at']),
    ];
};

$open = array_map($fmt, q_all("$base WHERE al.status <> 'resolved' ORDER BY (al.type = 'sos') DESC, al.id DESC LIMIT 10"));
$new = isset($_GET['since_id']) ? array_map($fmt, q_all("$base WHERE al.id > ? ORDER BY al.id ASC LIMIT 20", [$since])) : [];

json_out([
    'ok'         => true,
    'open_count' => (int)q_val("SELECT COUNT(*) FROM alerts WHERE status <> 'resolved'"),
    'open'       => $open,
    'new'        => $new,
    'max_id'     => (int)q_val('SELECT COALESCE(MAX(id), 0) FROM alerts'),
    // Duty extension requests waiting for approval (toast when a new one arrives).
    'ext_pending' => (int)q_val("SELECT COUNT(*) FROM duty_extensions WHERE status = 'pending'"),
    'ext_new'     => isset($_GET['ext_since']) ? array_map(fn($x) => [
        'id' => (int)$x['id'], 'guard_name' => $x['full_name'], 'minutes' => fmt_minutes((int)$x['requested_minutes']), 'reason' => $x['reason'],
    ], q_all("SELECT x.id, x.requested_minutes, x.reason, g.full_name FROM duty_extensions x JOIN guards g ON g.id = x.guard_id
               WHERE x.id > ? AND x.status = 'pending' ORDER BY x.id LIMIT 10", [get_int('ext_since')])) : [],
    'ext_max_id'  => (int)q_val('SELECT COALESCE(MAX(id), 0) FROM duty_extensions'),
]);
