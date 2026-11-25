<?php
declare(strict_types=1);

/**
 * Location, geofence, duty and live-status logic shared by the guard app
 * and the admin portal.
 */

const LOC_HISTORY_MIN_SECONDS = 45;   // store a history point at least this often...
const LOC_HISTORY_MIN_METERS  = 20;   // ...or sooner when the guard moved this far
const MAX_SHIFT_HOURS         = 16;   // open shifts older than this are auto-closed

/** Distance in metres between two coordinates (haversine). */
function distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

function valid_coords($lat, $lng): bool
{
    return is_numeric($lat) && is_numeric($lng)
        && (float)$lat >= -90 && (float)$lat <= 90
        && (float)$lng >= -180 && (float)$lng <= 180
        && !((float)$lat == 0.0 && (float)$lng == 0.0);
}

/** The guard's current active assignment joined with the client, or null. */
function guard_active_assignment(int $guardId): ?array
{
    return q_one(
        'SELECT a.*, c.name AS client_name, c.client_code, c.phone AS client_phone, c.contact_person
           FROM assignments a JOIN clients c ON c.id = a.client_id
          WHERE a.guard_id = ? AND a.status = \'active\' LIMIT 1',
        [$guardId]
    );
}

function guard_open_shift(int $guardId): ?array
{
    return q_one('SELECT * FROM attendance WHERE guard_id = ? AND status = \'open\' ORDER BY id DESC LIMIT 1', [$guardId]);
}

/**
 * Save a GPS fix from the guard's phone.
 * Always updates the guard's live position; history is thinned out so the
 * database stays small on shared hosting. Runs the geofence check on duty.
 *
 * @return array{stored: bool, inside: ?bool, distance_m: ?int, alert: ?string}
 */
function record_guard_location(array $guard, float $lat, float $lng, ?float $accuracy, ?float $speed, ?float $heading, ?int $battery, ?string $recordedAt = null): array
{
    $gid = (int)$guard['id'];
    $ts = $recordedAt ?? now();
    $assignment = guard_active_assignment($gid);
    $onDuty = (int)$guard['is_on_duty'] === 1;
    // Points sent late (queued while the phone had no internet) only go to history.
    $fresh = (time() - strtotime($ts)) <= 120;

    q('UPDATE guards SET last_lat = ?, last_lng = ?, last_accuracy = ?, last_speed = ?, last_battery = COALESCE(?, last_battery), last_seen_at = ?
        WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at <= ?)',
      [$lat, $lng, $accuracy, $speed, $battery, $ts, $gid, $ts]);

    $last = q_one('SELECT lat, lng, recorded_at FROM guard_locations WHERE guard_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1', [$gid]);
    $store = true;
    if ($last && $last['recorded_at'] <= $ts) {
        $secs = strtotime($ts) - strtotime($last['recorded_at']);
        $moved = distance_m((float)$last['lat'], (float)$last['lng'], $lat, $lng);
        $store = $secs >= LOC_HISTORY_MIN_SECONDS || ($moved >= LOC_HISTORY_MIN_METERS && $moved > (float)($accuracy ?? 0));
    }
    if ($store) {
        q('INSERT INTO guard_locations (guard_id, assignment_id, lat, lng, accuracy, speed, heading, battery, recorded_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$gid, $assignment['id'] ?? null, $lat, $lng, $accuracy, $speed, $heading, $battery, $ts]);
    }

    $result = ['stored' => $store, 'inside' => null, 'distance_m' => null, 'alert' => null];
    if ($onDuty && $assignment && $fresh) {
        $result = array_merge($result, geofence_check($guard, $assignment, $lat, $lng, $accuracy));
    }
    return $result;
}

/**
 * Raises a "left site" alert when an on-duty guard goes outside the client
 * site radius, and resolves it automatically when they return.
 */
function geofence_check(array $guard, array $assignment, float $lat, float $lng, ?float $accuracy): array
{
    if ($assignment['site_lat'] === null || $assignment['site_lng'] === null) {
        return ['inside' => null, 'distance_m' => null, 'alert' => null];
    }
    $radius = max(50, (int)$assignment['geofence_radius_m']);
    $dist = distance_m((float)$assignment['site_lat'], (float)$assignment['site_lng'], $lat, $lng);
    // Don't trust a fix that is too inaccurate to decide: allow its error margin (capped).
    $margin = min(150.0, max(0.0, (float)($accuracy ?? 0)));
    $inside = ($dist - $margin) <= $radius;

    $open = q_one('SELECT id FROM alerts WHERE guard_id = ? AND assignment_id = ? AND type = \'geofence_exit\' AND status <> \'resolved\' LIMIT 1',
                  [$guard['id'], $assignment['id']]);
    $alert = null;
    if (!$inside && !$open) {
        q('INSERT INTO alerts (type, guard_id, assignment_id, client_id, lat, lng, distance_m, message, status, created_at)
           VALUES (\'geofence_exit\', ?, ?, ?, ?, ?, ?, ?, \'open\', ?)',
          [$guard['id'], $assignment['id'], $assignment['client_id'], $lat, $lng, (int)round($dist),
           sprintf('%s is %d m away from %s (allowed %d m)', $guard['full_name'], (int)round($dist), $assignment['site_name'], $radius), now()]);
        $alert = 'geofence_exit';
    } elseif ($inside && $open) {
        q('UPDATE alerts SET status = \'resolved\', resolved_at = ?, resolution_note = \'Guard returned to site (automatic)\' WHERE id = ?',
          [now(), $open['id']]);
        $alert = 'geofence_return';
    }
    return ['inside' => $inside, 'distance_m' => (int)round($dist), 'alert' => $alert];
}

/** Runs run_maintenance() at most once every 10 minutes (called from normal requests). */
function maybe_run_maintenance(): void
{
    $last = (int)setting('last_maintenance_at', '0');
    if (time() - $last < 600) {
        return;
    }
    // Claim the slot first so parallel requests don't all run it.
    $claimed = q("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_maintenance_at' AND setting_value = ?",
                 [(string)time(), (string)$last])->rowCount();
    if ($claimed === 0) {
        if ($last === 0 && !q_val("SELECT 1 FROM settings WHERE setting_key = 'last_maintenance_at'")) {
            save_setting('last_maintenance_at', (string)time());
        } else {
            return;
        }
    }
    try {
        run_maintenance();
    } catch (Throwable $e) {
        error_log('maintenance failed: ' . $e->getMessage());
    }
}

/** Cleanup tasks: run occasionally from requests, or from a cron job. */
function run_maintenance(): void
{
    // 0) Deployments whose end date has passed are completed automatically
    //    (unless the guard is still on duty there).
    q("UPDATE assignments a JOIN guards g ON g.id = a.guard_id
          SET a.status = 'completed', a.ended_at = ?
        WHERE a.status = 'active' AND a.end_date IS NOT NULL AND a.end_date < CURDATE() AND g.is_on_duty = 0", [now()]);

    // 1) Purge old location history.
    $days = max(7, (int)setting('location_retention_days', '60'));
    q('DELETE FROM guard_locations WHERE recorded_at < ? LIMIT 20000', [date('Y-m-d H:i:s', time() - $days * 86400)]);

    // 2) Auto-close shifts the guard forgot to end.
    $cut = date('Y-m-d H:i:s', time() - MAX_SHIFT_HOURS * 3600);
    $stale = q_all('SELECT a.id, a.guard_id, a.check_in_at, a.shift_end_at, g.last_seen_at
                      FROM attendance a JOIN guards g ON g.id = a.guard_id
                     WHERE a.status = \'open\' AND a.check_in_at < ?', [$cut]);
    foreach ($stale as $s) {
        $out = $s['last_seen_at'] && $s['last_seen_at'] > $s['check_in_at'] ? $s['last_seen_at'] : $s['check_in_at'];
        $maxOut = date('Y-m-d H:i:s', strtotime($s['check_in_at']) + MAX_SHIFT_HOURS * 3600);
        if ($out > $maxOut) $out = $maxOut;
        $mins = (int)round((strtotime($out) - strtotime($s['check_in_at'])) / 60);
        [$ot, $otOk] = compute_overtime($s, strtotime($out));
        q('UPDATE attendance SET check_out_at = ?, duration_minutes = ?, overtime_minutes = ?, approved_ot_minutes = ?, status = \'auto_closed\',
                  notes = \'Auto-closed: guard did not check out\' WHERE id = ? AND status = \'open\'', [$out, $mins, $ot, $otOk, $s['id']]);
        q('UPDATE guards SET is_on_duty = 0 WHERE id = ?', [$s['guard_id']]);
        q("UPDATE duty_extensions SET status = 'cancelled', admin_note = 'Shift auto-closed' WHERE attendance_id = ? AND status = 'pending'", [$s['id']]);
    }
    purge_old_selfies();
    q('DELETE FROM activity_log WHERE created_at < ? LIMIT 20000', [date('Y-m-d H:i:s', time() - 365 * 86400)]);
}

/**
 * Live status of a guard row (needs is_on_duty, last_seen_at, assignment_id,
 * open_sos, open_geofence columns).
 * Returns one of: sos, outside, on_duty, offline_duty, assigned, available, unavailable
 */
function guard_live_status(array $g): string
{
    $offlineAfter = max(1, (int)setting('offline_after_min', '3')) * 60;
    $online = $g['last_seen_at'] && (time() - strtotime($g['last_seen_at'])) <= $offlineAfter;
    if (!empty($g['open_sos'])) return 'sos';
    if ($g['status'] !== 'active') return 'unavailable';
    if ((int)$g['is_on_duty'] === 1) {
        if (!empty($g['open_geofence'])) return 'outside';
        return $online ? 'on_duty' : 'offline_duty';
    }
    return !empty($g['assignment_id']) ? 'assigned' : 'available';
}

function live_status_meta(string $s): array
{
    return [
        'sos'          => ['SOS Alert',          '#dc2626'],
        'outside'      => ['Outside Site',       '#ea580c'],
        'on_duty'      => ['On Duty (Live)',     '#16a34a'],
        'offline_duty' => ['On Duty (No Signal)', '#ca8a04'],
        'assigned'     => ['Assigned, Off Duty', '#2563eb'],
        'available'    => ['Available',          '#64748b'],
        'unavailable'  => ['On Leave / Inactive', '#94a3b8'],
    ][$s] ?? [label($s), '#64748b'];
}

/** SQL fragment selecting guards with their live context for maps and lists. */
function guards_live_sql(string $where = '1=1'): string
{
    return "SELECT g.id, g.employee_code, g.category, g.full_name, g.phone, g.photo, g.status, g.is_on_duty, g.is_armed,
                   g.last_lat, g.last_lng, g.last_accuracy, g.last_speed, g.last_battery, g.last_seen_at,
                   g.last_ping_at, g.gps_state, g.gps_state_at,
                   a.id AS assignment_id, a.site_name, a.site_lat, a.site_lng, a.geofence_radius_m,
                   a.shift_start, a.shift_end, c.id AS client_id, c.name AS client_name, c.client_code,
                   (SELECT COUNT(*) FROM alerts s WHERE s.guard_id = g.id AND s.type = 'sos' AND s.status <> 'resolved') AS open_sos,
                   (SELECT COUNT(*) FROM alerts f WHERE f.guard_id = g.id AND f.type = 'geofence_exit' AND f.status <> 'resolved') AS open_geofence,
                   (SELECT GROUP_CONCAT(DISTINCT x.type) FROM alerts x WHERE x.guard_id = g.id AND x.status <> 'resolved') AS open_types,
                   (SELECT t.check_in_at FROM attendance t WHERE t.guard_id = g.id AND t.status = 'open' ORDER BY t.id DESC LIMIT 1) AS duty_since
              FROM guards g
              LEFT JOIN assignments a ON a.guard_id = g.id AND a.status = 'active'
              LEFT JOIN clients c ON c.id = a.client_id
             WHERE $where";
}

/**
 * Start a duty shift for a guard. Returns the attendance row id.
 * Throws RuntimeException with a user-facing message.
 */
function start_shift(array $guard, ?float $lat, ?float $lng, ?float $accuracy, ?string $selfie = null): array
{
    return db_tx(function () use ($guard, $lat, $lng, $accuracy, $selfie) {
        $gid = (int)$guard['id'];
        // Lock the guard row so a double-tap can't open two shifts.
        q('SELECT id FROM guards WHERE id = ? FOR UPDATE', [$gid]);
        if (guard_open_shift($gid)) {
            throw new RuntimeException('You are already on duty.');
        }
        $asg = guard_active_assignment($gid);
        $dist = null;
        if ($asg && $asg['site_lat'] !== null && $lat !== null) {
            $dist = (int)round(distance_m((float)$asg['site_lat'], (float)$asg['site_lng'], $lat, $lng));
            $max = max((int)$asg['geofence_radius_m'], (int)setting('checkin_max_distance_m', '500'));
            $margin = min(150, (int)($accuracy ?? 0));
            if ($max > 0 && ($dist - $margin) > $max) {
                throw new RuntimeException(sprintf('You are %s away from %s. Go to the duty location to check in (allowed %d m).',
                    $dist >= 1000 ? round($dist / 1000, 1) . ' km' : $dist . ' m', $asg['site_name'], $max));
            }
        }
        $win = shift_window_for_checkin($asg, time());
        q('INSERT INTO attendance (guard_id, assignment_id, client_id, check_in_at, check_in_lat, check_in_lng, check_in_accuracy, check_in_distance_m, status,
                                   check_in_selfie, shift_start_at, shift_end_at, late_minutes)
           VALUES (?,?,?,?,?,?,?,?, \'open\', ?,?,?,?)',
          [$gid, $asg['id'] ?? null, $asg['client_id'] ?? null, now(), $lat, $lng, $accuracy, $dist,
           $selfie, date('Y-m-d H:i:s', $win['start']), date('Y-m-d H:i:s', $win['end']), $win['late_minutes']]);
        $attId = (int)db()->lastInsertId();
        q('UPDATE guards SET is_on_duty = 1 WHERE id = ?', [$gid]);
        resolve_guard_alerts($gid, ['late'], 'Guard started duty' . ($win['late_minutes'] ? ' (' . $win['late_minutes'] . ' min late)' : ''));
        return ['attendance_id' => $attId, 'distance_m' => $dist, 'assignment' => $asg, 'late_minutes' => $win['late_minutes']];
    });
}

/** Close the guard's open shift (by the guard, or forced by an admin). */
function end_shift(int $guardId, ?float $lat, ?float $lng, ?int $adminId = null, ?string $note = null): ?array
{
    return db_tx(function () use ($guardId, $lat, $lng, $adminId, $note) {
        q('SELECT id FROM guards WHERE id = ? FOR UPDATE', [$guardId]);
        $shift = guard_open_shift($guardId);
        if (!$shift) {
            q('UPDATE guards SET is_on_duty = 0 WHERE id = ?', [$guardId]);
            return null;
        }
        $dist = null;
        if ($shift['assignment_id'] && $lat !== null) {
            $site = q_one('SELECT site_lat, site_lng FROM assignments WHERE id = ?', [$shift['assignment_id']]);
            if ($site && $site['site_lat'] !== null) {
                $dist = (int)round(distance_m((float)$site['site_lat'], (float)$site['site_lng'], $lat, $lng));
            }
        }
        $out = now();
        $mins = max(0, (int)round((strtotime($out) - strtotime($shift['check_in_at'])) / 60));
        [$ot, $otOk] = compute_overtime($shift, strtotime($out));
        q('UPDATE attendance SET check_out_at = ?, check_out_lat = ?, check_out_lng = ?, check_out_distance_m = ?,
                  duration_minutes = ?, overtime_minutes = ?, approved_ot_minutes = ?,
                  status = \'closed\', closed_by_admin = ?, notes = COALESCE(?, notes) WHERE id = ?',
          [$out, $lat, $lng, $dist, $mins, $ot, $otOk, $adminId, $note, $shift['id']]);
        q('UPDATE guards SET is_on_duty = 0 WHERE id = ?', [$guardId]);
        // Leaving duty clears the shift's "left site" and tracking-health alerts.
        resolve_guard_alerts($guardId, ['geofence_exit', 'offline', 'gps_off', 'no_movement'], 'Shift ended');
        // A request still waiting for approval no longer applies.
        q("UPDATE duty_extensions SET status = 'cancelled', admin_note = COALESCE(admin_note, 'Shift ended before a decision')
            WHERE attendance_id = ? AND status = 'pending'", [$shift['id']]);
        return ['attendance_id' => (int)$shift['id'], 'duration_minutes' => $mins, 'overtime_minutes' => $ot, 'approved_ot_minutes' => $otOk];
    });
}
