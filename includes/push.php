<?php
declare(strict_types=1);

/**
 * Push notifications to the native Android app through Firebase Cloud Messaging (HTTP v1).
 * Plain PHP + curl + openssl, works on shared hosting.
 *
 * Setup (Admin > Settings > Notifications):
 *  - the four public Firebase values the app needs (project id, API key, app id, sender id)
 *  - the service-account key file, stored in storage/ (never in the public repo / web root)
 *
 * Notifications are queued during the request and sent after the response has gone out,
 * so a slow Google API never delays the guard app or the admin page.
 */

const FCM_SA_FILE = APP_ROOT . '/storage/fcm-service-account.json';
const PUSH_CHANNELS = ['sos', 'alerts', 'requests', 'guard'];   // must match the Android app's channels

/** The public Firebase values the app uses to start Firebase, or null when not set up. */
function push_public_config(): ?array
{
    $c = [
        'project_id' => setting('fcm_project_id'),
        'api_key'    => setting('fcm_api_key'),
        'app_id'     => setting('fcm_app_id'),
        'sender_id'  => setting('fcm_sender_id'),
    ];
    return in_array('', $c, true) ? null : $c;
}

function fcm_service_account(): ?array
{
    static $sa = false;
    if ($sa !== false) return $sa;
    $sa = null;
    if (is_file(FCM_SA_FILE)) {
        $j = json_decode((string)file_get_contents(FCM_SA_FILE), true);
        if (is_array($j) && ($j['type'] ?? '') === 'service_account' && !empty($j['client_email']) && !empty($j['private_key']) && !empty($j['project_id'])) {
            $sa = $j;
        }
    }
    return $sa;
}

function push_ready(): bool
{
    return push_public_config() !== null && fcm_service_account() !== null && function_exists('curl_init');
}

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function http_post(string $url, array $headers, string $body, int $timeout = 8): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $res === false ? '' : (string)$res, $err];
}

/** OAuth access token for FCM (cached for ~55 minutes). */
function fcm_access_token(): ?string
{
    $cached = setting('fcm_access_token');
    if ($cached !== '' && (int)setting('fcm_token_expires', '0') > time() + 60) {
        return $cached;
    }
    $sa = fcm_service_account();
    if (!$sa) return null;
    $now = time();
    $jwt = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
    ]));
    if (!openssl_sign($jwt, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        error_log('FCM: could not sign with the service-account key');
        return null;
    }
    [$code, $body] = http_post($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . b64url($sig)]));
    $j = json_decode($body, true);
    if ($code !== 200 || empty($j['access_token'])) {
        error_log('FCM token request failed: HTTP ' . $code . ' ' . substr($body, 0, 300));
        return null;
    }
    save_setting('fcm_access_token', $j['access_token']);
    save_setting('fcm_token_expires', (string)($now + (int)($j['expires_in'] ?? 3600) - 300));
    return $j['access_token'];
}

/**
 * Sends one notification to one device. Returns 'ok', 'invalid' (token no longer valid: removed) or 'error'.
 */
function fcm_send(string $deviceToken, array $n): string
{
    $sa = fcm_service_account();
    $access = fcm_access_token();
    if (!$sa || !$access) return 'error';
    $channel = in_array($n['channel'] ?? '', PUSH_CHANNELS, true) ? $n['channel'] : 'alerts';
    $android = ['priority' => 'HIGH', 'ttl' => ($channel === 'sos' ? '3600s' : '86400s'),
                'notification' => ['channel_id' => $channel, 'tag' => (string)($n['tag'] ?? ''), 'default_vibrate_timings' => true]];
    if (isset($n['badge'])) $android['notification']['notification_count'] = max(0, (int)$n['badge']);
    if ($android['notification']['tag'] === '') unset($android['notification']['tag']);
    $msg = ['message' => [
        'token' => $deviceToken,
        'notification' => ['title' => mb_substr((string)$n['title'], 0, 120), 'body' => mb_substr((string)$n['body'], 0, 400)],
        // Data is read by the app (open this page when tapped, and show it itself while the app is open).
        'data' => array_map('strval', ['url' => $n['url'] ?? '', 'channel' => $channel, 'title' => $n['title'], 'body' => $n['body'], 'tag' => $n['tag'] ?? '']),
        'android' => $android,
    ]];
    [$code, $body, $err] = http_post('https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send',
        ['Authorization: Bearer ' . $access, 'Content-Type: application/json'], json_encode($msg, JSON_UNESCAPED_UNICODE));
    if ($code === 200) return 'ok';
    if ($code === 404 || ($code === 400 && str_contains($body, 'registration token')) || str_contains($body, 'UNREGISTERED')) {
        q('DELETE FROM push_devices WHERE token = ?', [$deviceToken]);
        return 'invalid';
    }
    if ($code === 401) save_setting('fcm_token_expires', '0');   // refresh the access token next time
    error_log('FCM send failed: HTTP ' . $code . ' ' . $err . ' ' . substr($body, 0, 300));
    return 'error';
}

/** Open alerts + requests waiting: the number shown on the admin app icon. */
function admin_badge_count(): int
{
    return (int)q_val("SELECT COUNT(*) FROM alerts WHERE status <> 'resolved'")
         + (int)q_val("SELECT COUNT(*) FROM duty_extensions WHERE status = 'pending'")
         + (int)q_val("SELECT COUNT(*) FROM guard_leaves WHERE status = 'pending'");
}

/**
 * Queue a notification. $to: 'admins' (every admin device) or ['guard', id].
 * $n: title, body, url (path on this site), channel (sos|alerts|requests|guard), tag (replaces an older one with the same tag)
 */
function push_queue($to, array $n): void
{
    static $registered = false;
    if (!push_ready()) return;
    $GLOBALS['__push_queue'][] = [$to, $n];
    if (!$registered) {
        $registered = true;
        register_shutdown_function('push_flush');
    }
}

function push_flush(): void
{
    $queue = $GLOBALS['__push_queue'] ?? [];
    $GLOBALS['__push_queue'] = [];
    if (!$queue) return;
    // Let the browser / app get its answer first, then talk to Google.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    try {
        $badge = null;
        foreach ($queue as [$to, $n]) {
            if ($to === 'admins') {
                $devices = q_all("SELECT d.token FROM push_devices d JOIN admins a ON a.id = d.user_id AND a.is_active = 1 WHERE d.user_type = 'admin'");
                $n['badge'] = $badge ??= admin_badge_count();
            } else {
                $devices = q_all("SELECT token FROM push_devices WHERE user_type = 'guard' AND user_id = ?", [(int)$to[1]]);
            }
            $n['url'] = $n['url'] ?? '';
            foreach ($devices as $d) {
                fcm_send($d['token'], $n);
            }
        }
    } catch (Throwable $e) {
        error_log('push failed: ' . $e->getMessage());
    }
}

// ---- What gets sent -------------------------------------------------------

/** New alert → every admin. SOS uses the siren channel. */
function notify_alert(int $alertId): void
{
    $a = q_one('SELECT al.id, al.type, al.message, g.full_name FROM alerts al JOIN guards g ON g.id = al.guard_id WHERE al.id = ?', [$alertId]);
    if (!$a) return;
    $title = alert_type_meta($a['type'])[0];
    push_queue('admins', [
        'title'   => ($a['type'] === 'sos' ? '🚨 SOS — ' : '⚠️ ' . $title . ' — ') . $a['full_name'],
        'body'    => (string)($a['message'] ?: $title),
        'url'     => 'admin/alerts.php?id=' . $a['id'],
        'channel' => $a['type'] === 'sos' ? 'sos' : 'alerts',
        'tag'     => 'alert-' . $a['id'],
    ]);
}

function notify_admins_request(string $title, string $body, string $url, string $tag): void
{
    push_queue('admins', ['title' => $title, 'body' => $body, 'url' => $url, 'channel' => 'requests', 'tag' => $tag]);
}

function notify_guard(int $guardId, string $title, string $body, string $tag = ''): void
{
    push_queue(['guard', $guardId], ['title' => $title, 'body' => $body, 'url' => 'guard/index.php', 'channel' => 'guard', 'tag' => $tag]);
}
