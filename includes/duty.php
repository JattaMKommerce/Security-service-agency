<?php
declare(strict_types=1);

/**
 * Duty rules shared by the guard app and the admin portal:
 * shift timings, late arrival, overtime, monthly attendance, live tracking
 * health checks (no signal / GPS off / not moving / late) and duty selfies.
 */

const ALERT_TYPES = ['sos', 'geofence_exit', 'late', 'no_movement', 'gps_off', 'offline', 'battery_low', 'phone_off'];
const EARLY_CHECKIN_SEC = 3 * 3600;      // a check-in this long before shift start still counts for that shift
const STILL_RADIUS_M = 30;               // "not moving" = every point stays within this distance
const PATROL_MIN_SPEED = 1.0;            // m/s (about 3.6 km/h, walking pace)
const PATROL_WINDOW_SEC = 300;           // ...or moved this far in the last 5 minutes:
const PATROL_MIN_METERS = 50;

function alert_type_meta(string $type): array
{
    return [
        'sos'           => ['SOS Emergency',       'bi-exclamation-octagon-fill', 'danger'],
        'geofence_exit' => ['Left Site',           'bi-sign-turn-right-fill',     'warning'],
        'late'          => ['Late / Not Reported', 'bi-alarm-fill',               'warning'],
        'no_movement'   => ['Not Moving',          'bi-pause-circle-fill',        'warning'],
        'gps_off'       => ['GPS Turned Off',      'bi-geo-alt',                  'danger'],
        'offline'       => ['No Internet / Signal', 'bi-wifi-off',                'danger'],
        'battery_low'   => ['Battery Low',         'bi-battery-half',             'warning'],
        'phone_off'     => ['Phone Switched Off',  'bi-power',                    'danger'],
    ][$type] ?? [label($type), 'bi-bell-fill', 'secondary'];
}

// ---- Weekly off & leave ---------------------------------------------------

const LEAVE_TYPES = ['casual' => 'Casual leave', 'sick' => 'Sick leave', 'emergency' => 'Emergency leave', 'other' => 'Other leave'];
const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/** Weekly off days stored as "0,6" (0 = Sunday) → [0, 6]. */
function weekly_off_days(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $days = array_unique(array_map('intval', array_filter(explode(',', $value), fn($x) => ctype_digit(trim($x)) && (int)$x <= 6)));
    sort($days);
    return $days;
}

function weekly_off_label(?string $value, bool $short = false): string
{
    $days = weekly_off_days($value);
    if (!$days) return 'No weekly off';
    return implode(', ', array_map(fn($d) => $short ? substr(WEEKDAY_NAMES[$d], 0, 3) : WEEKDAY_NAMES[$d], $days));
}

function leave_type_label(string $type): string
{
    return LEAVE_TYPES[$type] ?? label($type);
}

/** Approved leaves of these guards that overlap [from, to]: guard id => list of rows. */
function approved_leaves(array $guardIds, string $from, string $to): array
{
    $guardIds = array_values(array_unique(array_map('intval', $guardIds)));
    if (!$guardIds) return [];
    $in = implode(',', array_fill(0, count($guardIds), '?'));
    $out = [];
    foreach (q_all("SELECT guard_id, from_date, to_date, leave_type FROM guard_leaves
                     WHERE status = 'approved' AND guard_id IN ($in) AND from_date <= ? AND to_date >= ?",
                   array_merge($guardIds, [$to, $from])) as $r) {
        $out[(int)$r['guard_id']][] = $r;
    }
    return $out;
}

/**
 * Why a guard is not expected on duty on $day: ['leave', type] (approved leave wins),
 * ['weekly_off', null], or null for a normal working day.
 */
function guard_day_off(?string $weeklyOff, array $leaves, string $day): ?array
{
    foreach ($leaves as $l) {
        if ($l['from_date'] <= $day && $day <= $l['to_date']) {
            return ['leave', $l['leave_type']];
        }
    }
    if (in_array((int)date('w', strtotime($day)), weekly_off_days($weeklyOff), true)) {
        return ['weekly_off', null];
    }
    return null;
}

// ---- Shift timings --------------------------------------------------------

function asg_has_shift(?array $asg): bool
{
    return $asg && !empty($asg['shift_start']) && !empty($asg['shift_end']);
}

/**
 * The shift periods of an assignment around $ts (yesterday, today, tomorrow),
 * limited to the assignment's date range. Overnight shifts end the next day.
 * @return list<array{day: string, start: int, end: int}>
 */
function shift_occurrences(array $asg, int $ts): array
{
    $out = [];
    foreach ([-1, 0, 1] as $offset) {
        $day = date('Y-m-d', strtotime($offset . ' day', $ts));
        if ($day < $asg['start_date'] || (!empty($asg['end_date']) && $day > $asg['end_date'])) {
            continue;
        }
        $start = strtotime($day . ' ' . $asg['shift_start']);
        $end = strtotime($day . ' ' . $asg['shift_end']);
        if ($end <= $start) {
            $end += 86400;
        }
        $out[] = ['day' => $day, 'start' => $start, 'end' => $end];
    }
    return $out;
}

/** The shift period happening right now (between its start and end), or null. */
function current_shift_occurrence(array $asg, int $now): ?array
{
    foreach (shift_occurrences($asg, $now) as $o) {
        if ($now >= $o['start'] && $now < $o['end']) {
            return $o;
        }
    }
    return null;
}

/**
 * Scheduled start/end for a shift that begins at $ts, plus minutes late.
 * Without shift timings the default shift length from Settings is used.
 * @return array{start: int, end: int, late_minutes: int}
 */
function shift_window_for_checkin(?array $asg, int $ts): array
{
    if (asg_has_shift($asg)) {
        $occ = shift_occurrences($asg, $ts);
        // Prefer the shift the check-in falls into (allowing early arrival), else the nearest one.
        $inside = array_values(array_filter($occ, fn($o) => $ts >= $o['start'] - EARLY_CHECKIN_SEC && $ts < $o['end']));
        $pool = $inside ?: $occ;
        if ($pool) {
            usort($pool, fn($a, $b) => abs($ts - $a['start']) <=> abs($ts - $b['start']));
            $o = $pool[0];
            $late = (int)floor(max(0, $ts - $o['start']) / 60);
            if ($late <= (int)setting('late_grace_min', '15')) {
                $late = 0;
            }
            return ['start' => $o['start'], 'end' => $o['end'], 'late_minutes' => $late];
        }
    }
    $hours = max(1, min(24, (float)setting('default_shift_hours', '8')));
    return ['start' => $ts, 'end' => $ts + (int)round($hours * 3600), 'late_minutes' => 0];
}

/** Approved extra minutes for a shift. */
function approved_extension_minutes(int $attendanceId): int
{
    return (int)q_val("SELECT COALESCE(SUM(approved_minutes), 0) FROM duty_extensions WHERE attendance_id = ? AND status = 'approved'", [$attendanceId]);
}

/**
 * Overtime for a shift ending at $outTs: time beyond the scheduled shift end.
 * Approved overtime is capped by the approved duty extensions.
 * @return array{0: ?int, 1: ?int} [overtime minutes, approved overtime minutes]
 */
function compute_overtime(array $att, int $outTs): array
{
    if (empty($att['shift_end_at'])) {
        return [null, null];
    }
    $ot = intdiv(max(0, $outTs - strtotime($att['shift_end_at'])), 60);
    if ($ot < (int)setting('ot_min_minutes', '15')) {
        $ot = 0;
    }
    return [$ot, min($ot, approved_extension_minutes((int)$att['id']))];
}

// ---- Monthly attendance ---------------------------------------------------

/**
 * Day-by-day attendance of one guard for a month (YYYY-MM).
 * Day status: present, late, absent (deployed but no check-in), weekly_off,
 * leave (approved), pending (today, not yet checked in), upcoming, none (not deployed).
 * Working on a weekly off / leave day counts as present (the day keeps 'off' for display).
 */
function attendance_month(int $guardId, string $ym): array
{
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $today = date('Y-m-d');
    $now = time();

    $rows = q_all('SELECT at.*, c.name AS client_name, a.site_name
                     FROM attendance at LEFT JOIN clients c ON c.id = at.client_id LEFT JOIN assignments a ON a.id = at.assignment_id
                    WHERE at.guard_id = ? AND at.check_in_at >= ? AND at.check_in_at < ?
                    ORDER BY at.check_in_at', [$guardId, $first . ' 00:00:00', date('Y-m-d', strtotime($last . ' +1 day')) . ' 00:00:00']);
    $weeklyOff = q_val('SELECT weekly_off FROM guards WHERE id = ?', [$guardId]);
    $leaves = approved_leaves([$guardId], $first, $last)[$guardId] ?? [];
    $asgs = q_all("SELECT start_date, end_date, ended_at FROM assignments
                    WHERE guard_id = ? AND status <> 'cancelled' AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)",
                  [$guardId, $last, $first]);

    $byDay = [];
    foreach ($rows as $r) {
        $open = $r['status'] === 'open';
        $mins = $open ? intdiv(max(0, $now - strtotime($r['check_in_at'])), 60) : (int)$r['duration_minutes'];
        if ($open) {
            [$ot, $otOk] = compute_overtime($r, $now);
        } else {
            [$ot, $otOk] = [$r['overtime_minutes'], $r['approved_ot_minutes']];
        }
        $byDay[substr($r['check_in_at'], 0, 10)][] = [
            'id'          => (int)$r['id'],
            'in'          => fmt_time(substr($r['check_in_at'], 11)),
            'out'         => $r['check_out_at'] ? fmt_time(substr($r['check_out_at'], 11)) : null,
            'out_day'     => $r['check_out_at'] && substr($r['check_out_at'], 0, 10) !== substr($r['check_in_at'], 0, 10) ? fmt_date($r['check_out_at'], 'd M') : null,
            'minutes'     => $mins,
            'late'        => (int)$r['late_minutes'],
            'ot'          => (int)$ot,
            'ot_approved' => (int)$otOk,
            'status'      => $r['status'],
            'client'      => $r['client_name'],
            'site'        => $r['site_name'],
            'has_selfie'  => !empty($r['check_in_selfie']),
        ];
    }

    $sum = ['present' => 0, 'late' => 0, 'absent' => 0, 'weekly_off' => 0, 'leave' => 0, 'worked_off' => 0,
            'minutes' => 0, 'ot' => 0, 'ot_approved' => 0, 'ot_unapproved' => 0, 'shifts' => count($rows)];
    $days = [];
    for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        $shifts = $byDay[$d] ?? [];
        $deployed = false;
        foreach ($asgs as $a) {
            if ($a['start_date'] <= $d && (!$a['end_date'] || $d <= $a['end_date']) && (!$a['ended_at'] || $d <= substr($a['ended_at'], 0, 10))) {
                $deployed = true;
                break;
            }
        }
        $off = guard_day_off($weeklyOff, $leaves, $d);
        if ($shifts) {
            $status = max(array_column($shifts, 'late')) > 0 ? 'late' : 'present';
            $sum['present']++;
            if ($status === 'late') $sum['late']++;
            if ($off) $sum['worked_off']++;
        } elseif ($off && ($deployed || $off[0] === 'leave')) {
            $status = $off[0];
            $sum[$off[0]]++;
        } elseif ($d > $today) {
            $status = $deployed ? 'upcoming' : 'none';
        } elseif ($d === $today) {
            $status = $deployed ? 'pending' : 'none';
        } else {
            $status = $deployed ? 'absent' : 'none';
            if ($deployed) $sum['absent']++;
        }
        $mins = array_sum(array_column($shifts, 'minutes'));
        $ot = array_sum(array_column($shifts, 'ot'));
        $otOk = array_sum(array_column($shifts, 'ot_approved'));
        $sum['minutes'] += $mins;
        $sum['ot'] += $ot;
        $sum['ot_approved'] += $otOk;
        $days[] = ['date' => $d, 'day' => (int)substr($d, 8, 2), 'dow' => (int)date('w', strtotime($d)),
                   'status' => $status, 'minutes' => $mins, 'ot' => $ot, 'ot_approved' => $otOk, 'shifts' => $shifts,
                   'off' => $off[0] ?? null, 'off_label' => $off ? ($off[0] === 'leave' ? leave_type_label((string)$off[1]) : 'Weekly off') : null];
    }
    $sum['ot_unapproved'] = $sum['ot'] - $sum['ot_approved'];
    return ['month' => $ym, 'label' => date('F Y', strtotime($first)), 'days' => $days, 'summary' => $sum];
}

function valid_month(?string $ym): bool
{
    return is_string($ym) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym) === 1;
}

// ---- Alerts ---------------------------------------------------------------

/** Opens an alert unless the same kind is already open for this guard. Returns true if created. */
function open_guard_alert(string $type, array $guard, ?array $asg, ?float $lat, ?float $lng, string $message): bool
{
    $exists = q_val("SELECT id FROM alerts WHERE guard_id = ? AND type = ? AND status <> 'resolved' LIMIT 1", [$guard['id'], $type]);
    if ($exists) {
        return false;
    }
    q("INSERT INTO alerts (type, guard_id, assignment_id, client_id, lat, lng, message, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?)",
      [$type, $guard['id'], $asg['id'] ?? null, $asg['client_id'] ?? null, $lat, $lng, mb_substr($message, 0, 255), now()]);
    notify_alert((int)db()->lastInsertId());
    return true;
}

function resolve_guard_alerts(int $guardId, array $types, string $note): int
{
    $types = array_values(array_intersect($types, ALERT_TYPES));
    if (!$types) return 0;
    $in = implode(',', array_fill(0, count($types), '?'));
    return q("UPDATE alerts SET status = 'resolved', resolved_at = ?, resolution_note = ?
               WHERE guard_id = ? AND type IN ($in) AND status <> 'resolved'",
             array_merge([now(), mb_substr($note, 0, 255), $guardId], $types))->rowCount();
}

/** Runs $fn at most once every $seconds across all requests (uses the settings table as a lock). */
function claim_interval(string $key, int $seconds): bool
{
    $v = q_val('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    if ($v === null) {
        q("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, '0')", [$key]);
        $v = '0';
    }
    if (time() - (int)$v < $seconds) {
        return false;
    }
    return q('UPDATE settings SET setting_value = ? WHERE setting_key = ? AND setting_value = ?', [(string)time(), $key, (string)$v])->rowCount() === 1;
}

function maybe_run_live_checks(): void
{
    if (!claim_interval('last_live_check_at', 60)) {
        return;
    }
    try {
        run_live_checks();
    } catch (Throwable $e) {
        error_log('live checks failed: ' . $e->getMessage());
    }
}

/**
 * Raises / clears the tracking health alerts:
 *  offline     – on-duty phone stopped contacting the server (internet off, phone off, app closed)
 *  gps_off     – phone is online but GPS / location permission is off
 *  no_movement – on-duty guard stayed within a few metres for too long
 *  late        – deployed guard has not started duty after shift start + grace
 *  battery_low – on-duty phone battery at or below the Settings threshold (phone may switch off soon)
 */
function run_live_checks(): void
{
    $now = time();
    $offlineSec = max(1, (int)setting('offline_after_min', '3')) * 60;
    $stillMin = (int)setting('no_movement_min', '60');
    $grace = (int)setting('late_grace_min', '15') * 60;

    // Off-duty guards can't have duty-health alerts.
    q("UPDATE alerts al JOIN guards g ON g.id = al.guard_id
          SET al.status = 'resolved', al.resolved_at = ?, al.resolution_note = 'Guard is off duty'
        WHERE g.is_on_duty = 0 AND al.type IN ('offline','gps_off','no_movement','battery_low','phone_off') AND al.status <> 'resolved'", [now()]);

    $guards = q_all("SELECT g.*, at.id AS att_id, at.check_in_at
                       FROM guards g LEFT JOIN attendance at ON at.guard_id = g.id AND at.status = 'open'
                      WHERE g.status = 'active'");
    $openLate = array_flip(array_map('intval', array_column(q_all("SELECT guard_id FROM alerts WHERE type = 'late' AND status <> 'resolved'"), 'guard_id')));
    // Leaves around today (overnight shifts may have started yesterday).
    $leaves = approved_leaves(array_column($guards, 'id'), date('Y-m-d', $now - 86400), date('Y-m-d', $now));

    foreach ($guards as $g) {
        $gid = (int)$g['id'];
        $asg = guard_active_assignment($gid);
        $where = $asg ? ' at ' . $asg['site_name'] : '';
        $lat = $g['last_lat'] !== null ? (float)$g['last_lat'] : null;
        $lng = $g['last_lng'] !== null ? (float)$g['last_lng'] : null;

        if ((int)$g['is_on_duty'] === 1 && $g['att_id']) {
            $onFor = $now - strtotime($g['check_in_at']);
            $lastContact = max($g['last_ping_at'] ? strtotime($g['last_ping_at']) : 0, $g['last_seen_at'] ? strtotime($g['last_seen_at']) : 0);
            $online = $now - $lastContact <= $offlineSec;

            // 1) No internet / no contact
            if (!$online && $onFor > $offlineSec) {
                $mins = $lastContact ? (int)floor(($now - $lastContact) / 60) : (int)floor($onFor / 60);
                open_guard_alert('offline', $g, $asg, $lat, $lng,
                    sprintf('No signal from %s\'s phone for %d min%s — mobile internet off, phone switched off, or app closed', $g['full_name'], $mins, $where));
            } elseif ($online) {
                resolve_guard_alerts($gid, ['offline', 'phone_off'], 'Phone is back online (automatic)');
            }

            // 2) GPS / location permission off
            $gpsBad = in_array($g['gps_state'], ['off', 'denied'], true) && $g['gps_state_at'] && $now - strtotime($g['gps_state_at']) >= 60;
            if ($online && $gpsBad) {
                open_guard_alert('gps_off', $g, $asg, $lat, $lng, $g['gps_state'] === 'denied'
                    ? sprintf('%s turned off location permission for the guard app%s', $g['full_name'], $where)
                    : sprintf('GPS / Location is turned off on %s\'s phone%s', $g['full_name'], $where));
            } elseif ($online && $g['gps_state'] === 'ok') {
                resolve_guard_alerts($gid, ['gps_off'], 'GPS is back on (automatic)');
            }

            // 3) Battery low: the phone may switch off soon (level is reported with every GPS update)
            $batPct = (int)setting('battery_alert_pct', '15');
            if ($batPct > 0 && $g['last_battery'] !== null && $online) {
                $bat = (int)$g['last_battery'];
                if ($bat <= $batPct) {
                    open_guard_alert('battery_low', $g, $asg, $lat, $lng,
                        sprintf('%s\'s phone battery is at %d%%%s — it may switch off soon. Ask the guard to charge it.', $g['full_name'], $bat, $where));
                } elseif ($bat >= $batPct + 5) {
                    resolve_guard_alerts($gid, ['battery_low'], 'Phone is charging / battery back to ' . $bat . '% (automatic)');
                }
            }

            // 4) Not moving for too long
            if ($stillMin > 0 && $online && $onFor >= $stillMin * 60) {
                $pts = q_all('SELECT lat, lng, recorded_at FROM guard_locations WHERE guard_id = ? AND recorded_at >= ? ORDER BY recorded_at',
                             [$gid, date('Y-m-d H:i:s', $now - $stillMin * 60)]);
                $fresh = $pts && $now - strtotime(end($pts)['recorded_at']) <= $offlineSec;
                $covers = $pts && strtotime($pts[0]['recorded_at']) <= $now - $stillMin * 60 + 600;
                if ($fresh && $covers && count($pts) >= 3) {
                    $cLat = array_sum(array_map(fn($p) => (float)$p['lat'], $pts)) / count($pts);
                    $cLng = array_sum(array_map(fn($p) => (float)$p['lng'], $pts)) / count($pts);
                    $spread = max(array_map(fn($p) => distance_m($cLat, $cLng, (float)$p['lat'], (float)$p['lng']), $pts));
                    if ($spread < STILL_RADIUS_M) {
                        open_guard_alert('no_movement', $g, $asg, $lat, $lng,
                            sprintf('%s has not moved for over %d min%s', $g['full_name'], $stillMin, $where));
                    } else {
                        resolve_guard_alerts($gid, ['no_movement'], 'Guard is moving again (automatic)');
                    }
                }
            }
        }

        // 5) Late / not reported for the shift
        $occ = asg_has_shift($asg) ? current_shift_occurrence($asg, $now) : null;
        if ($occ && guard_day_off($g['weekly_off'], $leaves[$gid] ?? [], $occ['day'])) {
            // Weekly off or approved leave: nobody expects this guard today.
            if (isset($openLate[$gid])) resolve_guard_alerts($gid, ['late'], 'Weekly off / on leave today');
            $occ = null;
            continue;
        }
        if ((int)$g['is_on_duty'] === 0 && $occ && $now >= $occ['start'] + $grace) {
            $checkedIn = q_val('SELECT 1 FROM attendance WHERE guard_id = ? AND check_in_at >= ? LIMIT 1',
                               [$gid, date('Y-m-d H:i:s', $occ['start'] - EARLY_CHECKIN_SEC)]);
            if (!$checkedIn) {
                open_guard_alert('late', $g, $asg, $lat, $lng, sprintf('%s has not started duty%s. Shift started at %s (%d min ago)',
                    $g['full_name'], $where, date('h:i A', $occ['start']), (int)floor(($now - $occ['start']) / 60)));
            }
        } elseif (isset($openLate[$gid]) && !$occ && (int)$g['is_on_duty'] === 0) {
            resolve_guard_alerts($gid, ['late'], 'Shift time is over — guard did not check in (absent)');
        }
    }
}

// ---- Live map status ------------------------------------------------------

/**
 * Map status for one guard row from guards_live_sql():
 * emergency (red), late (orange), patrol (blue, on duty and moving),
 * on_duty (green), off_duty (grey); plus warning flags and sharing status.
 */
function guard_map_status(array $g): array
{
    $now = time();
    $offlineSec = max(1, (int)setting('offline_after_min', '3')) * 60;
    $types = $g['open_types'] ? explode(',', (string)$g['open_types']) : [];
    $onDuty = (int)$g['is_on_duty'] === 1;
    $lastContact = max(!empty($g['last_ping_at']) ? strtotime($g['last_ping_at']) : 0, $g['last_seen_at'] ? strtotime($g['last_seen_at']) : 0);
    $online = $now - $lastContact <= $offlineSec;
    $fixFresh = $g['last_seen_at'] && $now - strtotime($g['last_seen_at']) <= $offlineSec;
    $gps = $g['gps_state'] ?? 'unknown';

    $flags = [];
    if ($onDuty) {
        if (in_array('geofence_exit', $types, true)) $flags[] = 'outside';
        if (!$online) $flags[] = 'no_signal';
        elseif ($gps === 'off' || $gps === 'denied') $flags[] = 'gps_off';
        if (in_array('no_movement', $types, true)) $flags[] = 'idle';
        if (in_array('battery_low', $types, true)) $flags[] = 'battery_low';
    }

    $moving = false;
    if ($onDuty && $fixFresh && $g['last_lat'] !== null) {
        if ($g['last_speed'] !== null && (float)$g['last_speed'] >= PATROL_MIN_SPEED) {
            $moving = true;
        } else {
            // Farthest point in the last 5 minutes from where the guard is now (also catches rounds that loop back).
            $min = max(PATROL_MIN_METERS, 2 * (float)($g['last_accuracy'] ?? 0));
            foreach (q_all('SELECT lat, lng FROM guard_locations WHERE guard_id = ? AND recorded_at >= ? LIMIT 100',
                           [$g['id'], date('Y-m-d H:i:s', $now - PATROL_WINDOW_SEC)]) as $p) {
                if (distance_m((float)$p['lat'], (float)$p['lng'], (float)$g['last_lat'], (float)$g['last_lng']) >= $min) {
                    $moving = true;
                    break;
                }
            }
        }
    }

    if (in_array('sos', $types, true)) $status = 'emergency';
    elseif ($onDuty) $status = $moving ? 'patrol' : 'on_duty';
    elseif (in_array('late', $types, true)) $status = 'late';
    else $status = 'off_duty';

    $offToday = !empty($g['leave_today']) ? leave_type_label((string)$g['leave_today']) . ' today'
              : (in_array((int)date('w'), weekly_off_days($g['weekly_off'] ?? null), true) ? 'Weekly off today' : null);
    if (!$onDuty && $status !== 'emergency') $sharing = ['off', $offToday ? 'Not sharing · ' . $offToday : 'Not sharing (off duty)'];
    elseif (!$online) $sharing = ['offline', 'No internet / phone off'];
    elseif ($gps === 'denied') $sharing = ['denied', 'Location permission off'];
    elseif ($gps === 'off') $sharing = ['gps_off', 'GPS turned off'];
    elseif (!$fixFresh) $sharing = ['waiting', 'Online, waiting for GPS'];
    else $sharing = ['live', 'Sharing live'];

    return ['status' => $status, 'flags' => $flags, 'sharing' => $sharing[0], 'sharing_label' => $sharing[1], 'moving' => $moving, 'off_today' => $offToday];
}

function map_status_meta(string $s): array
{
    return [
        'emergency' => ['Emergency (SOS)', '#dc2626'],
        'late'      => ['Late',            '#ea580c'],
        'patrol'    => ['On Patrol',       '#2563eb'],
        'on_duty'   => ['On Duty',         '#16a34a'],
        'off_duty'  => ['Off Duty',        '#94a3b8'],
    ][$s] ?? [label($s), '#94a3b8'];
}

// ---- Duty selfie ----------------------------------------------------------

/** Saves a base64 data-URL selfie into storage/selfies/YYYY-MM/. Returns the stored name. */
function store_selfie(string $dataUrl): string
{
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $m)) {
        throw new RuntimeException('Selfie photo is missing. Please take your selfie again.');
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) < 2000 || strlen($bin) > 2_000_000) {
        throw new RuntimeException('Selfie photo could not be read. Please take it again.');
    }
    $info = @getimagesizefromstring($bin);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$info || !$ext || $info[0] < 80 || $info[1] < 80) {
        throw new RuntimeException('Selfie photo is not a valid image. Please take it again.');
    }
    $sub = date('Y-m');
    $dir = APP_ROOT . '/storage/selfies/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not save the selfie (server storage is not writable).');
    }
    $name = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (@file_put_contents(APP_ROOT . '/storage/selfies/' . $name, $bin) === false) {
        throw new RuntimeException('Could not save the selfie (server storage is not writable).');
    }
    return $name;
}

/** Absolute path of a stored selfie, or null if the name is invalid / missing. */
function selfie_file(?string $name): ?string
{
    if (!$name || !preg_match('#^\d{4}-\d{2}/[a-f0-9]{24}\.(jpg|png|webp)$#', $name)) {
        return null;
    }
    $path = APP_ROOT . '/storage/selfies/' . $name;
    return is_file($path) ? $path : null;
}

function purge_old_selfies(): void
{
    $days = max(7, (int)setting('selfie_retention_days', '90'));
    $rows = q_all('SELECT id, check_in_selfie FROM attendance WHERE check_in_selfie IS NOT NULL AND check_in_at < ? LIMIT 500',
                  [date('Y-m-d H:i:s', time() - $days * 86400)]);
    foreach ($rows as $r) {
        if ($p = selfie_file($r['check_in_selfie'])) {
            @unlink($p);
        }
        q('UPDATE attendance SET check_in_selfie = NULL WHERE id = ?', [$r['id']]);
    }
}

// ---- Admin view helpers ---------------------------------------------------

/**
 * [late minutes (1 = late, minutes unknown for older records), overtime, approved overtime]
 * for an attendance row (needs ext_approved for open shifts).
 */
function attendance_row_flags(array $r): array
{
    if ($r['late_minutes'] !== null) {
        $late = (int)$r['late_minutes'];
    } else {
        $late = $r['shift_start'] && strtotime(date('H:i:s', strtotime($r['check_in_at']))) > strtotime($r['shift_start']) + 900 ? 1 : 0;
    }
    if ($r['status'] === 'open') {
        $ot = $r['shift_end_at'] ? intdiv(max(0, time() - strtotime($r['shift_end_at'])), 60) : 0;
        if ($ot < (int)setting('ot_min_minutes', '15')) $ot = 0;
        $ok = min($ot, (int)($r['ext_approved'] ?? 0));
    } else {
        $ot = (int)$r['overtime_minutes'];
        $ok = (int)$r['approved_ot_minutes'];
    }
    return [$late, $ot, $ok];
}

function ot_badge(int $ot, int $approved): string
{
    if ($ot <= 0) return '';
    $un = $ot - $approved;
    return '<div class="small fw-semibold text-nowrap" style="color:#7c3aed">OT ' . fmt_minutes($ot) . '</div>'
         . ($un > 0 ? '<span class="badge badge-soft-warning text-nowrap">' . fmt_minutes($un) . ' unapproved</span>'
                    : '<span class="badge badge-soft-success">Approved</span>');
}

function selfie_thumb(array $r, string $size = 'sm'): string
{
    if (empty($r['check_in_selfie'])) return '';
    $u = url('admin/selfie.php?id=' . (int)$r['id']);
    return '<a href="' . e($u) . '" target="_blank" rel="noopener" title="Duty selfie" class="d-inline-block mt-1">'
         . '<img src="' . e($u) . '" alt="Duty selfie" loading="lazy" class="selfie-thumb selfie-' . $size . '"></a>';
}
