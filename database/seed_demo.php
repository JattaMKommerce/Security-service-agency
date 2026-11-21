<?php
/**
 * Demo data for testing (command line only):
 *   php database/seed_demo.php
 * Adds 40 bodyguards and 6 clients around Mumbai, deploys most of them and
 * puts some on duty with live positions. Demo guard password: guard123
 * Remove demo data afterwards with:  php database/seed_demo.php --remove
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

if (in_array('--remove', $argv, true)) {
    $ids = array_column(q_all("SELECT id FROM guards WHERE employee_code LIKE 'DEMO-%'"), 'id');
    $cids = array_column(q_all("SELECT id FROM clients WHERE client_code LIKE 'DEMO-%'"), 'id');
    db_tx(function () use ($ids, $cids) {
        if ($cids) {
            $in = implode(',', array_map('intval', $cids));
            q("DELETE FROM invoices WHERE client_id IN ($in)");
        }
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            q("DELETE FROM assignments WHERE guard_id IN ($in)");
            q("DELETE FROM guards WHERE id IN ($in)");
        }
        if ($cids) {
            $in = implode(',', array_map('intval', $cids));
            q("DELETE FROM assignments WHERE client_id IN ($in)");
            q("DELETE FROM clients WHERE id IN ($in)");
        }
    });
    echo 'Removed ' . count($ids) . " demo guards and " . count($cids) . " demo clients.\n";
    exit;
}

if (q_val("SELECT COUNT(*) FROM guards WHERE employee_code LIKE 'DEMO-%'") > 0) {
    echo "Demo data already exists. Run with --remove first.\n";
    exit(1);
}

mt_srand(42);
$first = ['Arjun', 'Vikram', 'Rohit', 'Sanjay', 'Imran', 'Karan', 'Deepak', 'Amit', 'Rahul', 'Suresh', 'Manoj', 'Ajay', 'Ravi', 'Nitin', 'Farhan',
          'Priya', 'Neha', 'Kavita', 'Sunita', 'Pooja', 'Harpreet', 'Gurdeep', 'Rakesh', 'Anil', 'Joseph', 'Thomas', 'Mahesh', 'Ganesh', 'Prakash', 'Vijay'];
$last = ['Singh', 'Sharma', 'Patil', 'Khan', 'Yadav', 'Naik', 'Shinde', 'Gill', 'Pawar', 'Thakur', 'Fernandes', 'Rao', 'Iyer', 'Jadhav', 'Chauhan'];
$skills = ['Close protection', 'First aid', 'Defensive driving', 'Crowd control', 'Martial arts', 'CCTV monitoring', 'Fire safety'];

$clients = [
    ['Mehra Group of Industries', 'corporate', 'Anil Mehra', 'BKC Corporate Office', 'Bandra Kurla Complex', 19.0656, 72.8650, 250],
    ['Kapoor Family Residence', 'residential', 'Sunil Kapoor', 'Juhu Bungalow', 'Juhu Tara Road', 19.1006, 72.8270, 150],
    ['Star Events & Weddings', 'event', 'Ritika Desai', 'Grand Ballroom', 'Lower Parel', 18.9953, 72.8258, 300],
    ['Oberoi Diamonds', 'corporate', 'Karan Oberoi', 'Zaveri Bazaar Showroom', 'Zaveri Bazaar', 18.9500, 72.8320, 120],
    ['Mr. Rajiv Malhotra (VIP)', 'individual', 'Rajiv Malhotra', 'Worli Sea Face Apartment', 'Worli', 19.0176, 72.8162, 200],
    ['Horizon Tech Park', 'corporate', 'Meera Nair', 'Powai Campus', 'Hiranandani, Powai', 19.1176, 72.9060, 400],
];

$pw = password_hash('guard123', PASSWORD_DEFAULT);
$demoCats = ['bodyguard', 'building_security', 'agency_security', 'event_security', 'patrol'];
$guardIds = [];
for ($i = 1; $i <= 40; $i++) {
    $name = $first[($i * 7) % count($first)] . ' ' . $last[($i * 11) % count($last)];
    $cat = $demoCats[($i - 1) % count($demoCats)];
    $isArmed = $i % 3 === 0 ? 1 : 0;
    $lic = $isArmed ? sprintf('WPN-%04d', 1000 + $i) : null;
    q('INSERT INTO guards (employee_code, category, full_name, phone, password_hash, gender, city, experience_years, is_armed, weapon_license_no, skills, joined_on, status, blood_group)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        sprintf('DEMO-%03d', $i), $cat, $name, '98' . str_pad((string)(20000000 + $i * 7919), 8, '0', STR_PAD_LEFT), $pw,
        $i % 6 === 0 ? 'female' : 'male', 'Mumbai', mt_rand(1, 15), $isArmed, $lic,
        implode(', ', array_slice($skills, $i % 5, 2)), date('Y-m-d', strtotime('-' . mt_rand(30, 1500) . ' days')),
        $i > 38 ? 'on_leave' : 'active', ['A+', 'B+', 'O+', 'AB+', 'O-'][$i % 5],
    ]);
    $guardIds[] = (int)db()->lastInsertId();
}

$cid = [];
foreach ($clients as $n => [$name, $type, $contact, $site, $area, $lat, $lng, $radius]) {
    q('INSERT INTO clients (client_code, name, client_type, contact_person, phone, address, city, state) VALUES (?,?,?,?,?,?,?,?)',
      [sprintf('DEMO-C%02d', $n + 1), $name, $type, $contact, '91' . (67000000 + $n * 1234567), $area, 'Mumbai', 'Maharashtra']);
    $cid[] = [(int)db()->lastInsertId(), $site, $area, $lat, $lng, $radius];
}

// Deploy 30 of the guards across the clients.
$plan = [8, 3, 6, 4, 2, 7];
$gi = 0;
$now = time();
foreach ($plan as $ci => $count) {
    [$clientId, $site, $area, $lat, $lng, $radius] = $cid[$ci];
    for ($k = 0; $k < $count; $k++, $gi++) {
        $gid = $guardIds[$gi];
        q("INSERT INTO assignments (client_id, guard_id, site_name, site_address, site_lat, site_lng, geofence_radius_m, start_date, shift_start, shift_end, rate_type, rate, status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'active')",
          [$clientId, $gid, $site, $area . ', Mumbai', $lat, $lng, $radius, date('Y-m-d', strtotime('-20 days')),
           $k % 2 ? '20:00' : '08:00', $k % 2 ? '08:00' : '20:00', $ci === 2 ? 'hourly' : 'daily', $ci === 2 ? 350 : 1800]);
        $asgId = (int)db()->lastInsertId();

        // Past attendance for the last 10 days.
        for ($d = 10; $d >= 1; $d--) {
            if (mt_rand(1, 10) > 8) continue;
            $in = strtotime(date('Y-m-d', strtotime("-$d days")) . ($k % 2 ? ' 20:00' : ' 08:00')) + mt_rand(-600, 1500);
            $mins = 12 * 60 + mt_rand(-40, 30);
            q("INSERT INTO attendance (guard_id, assignment_id, client_id, check_in_at, check_in_lat, check_in_lng, check_in_distance_m, check_out_at, duration_minutes, status)
               VALUES (?,?,?,?,?,?,?,?,?, 'closed')",
              [$gid, $asgId, $clientId, date('Y-m-d H:i:s', $in), $lat, $lng, mt_rand(5, 80), date('Y-m-d H:i:s', $in + $mins * 60), $mins]);
        }

        // About two thirds are on duty right now with a live position.
        if ($k % 3 !== 2) {
            $outside = ($gi === 4);
            $offline = ($gi === 9);
            $off = $outside ? 0.012 : 0.0012;
            $glat = $lat + (mt_rand(-100, 100) / 100) * $off;
            $glng = $lng + (mt_rand(-100, 100) / 100) * $off;
            $seen = $offline ? $now - 900 : $now - mt_rand(3, 40);
            q("INSERT INTO attendance (guard_id, assignment_id, client_id, check_in_at, check_in_lat, check_in_lng, check_in_distance_m, status)
               VALUES (?,?,?,?,?,?,?, 'open')", [$gid, $asgId, $clientId, date('Y-m-d H:i:s', $now - mt_rand(3600, 6 * 3600)), $lat, $lng, mt_rand(5, 60)]);
            q('UPDATE guards SET is_on_duty = 1, last_lat = ?, last_lng = ?, last_accuracy = ?, last_battery = ?, last_seen_at = ? WHERE id = ?',
              [$glat, $glng, mt_rand(4, 25), mt_rand(25, 98), date('Y-m-d H:i:s', $seen), $gid]);
            // A short route history.
            for ($p = 30; $p >= 0; $p--) {
                q('INSERT INTO guard_locations (guard_id, assignment_id, lat, lng, accuracy, recorded_at) VALUES (?,?,?,?,?,?)',
                  [$gid, $asgId, $glat + sin($p / 4) * 0.0008, $glng + cos($p / 4) * 0.0008, 10, date('Y-m-d H:i:s', $seen - $p * 60)]);
            }
            if ($outside) {
                $dist = (int)round(distance_m($lat, $lng, $glat, $glng));
                q("INSERT INTO alerts (type, guard_id, assignment_id, client_id, lat, lng, distance_m, message, status, created_at)
                   VALUES ('geofence_exit', ?, ?, ?, ?, ?, ?, ?, 'open', ?)",
                  [$gid, $asgId, $clientId, $glat, $glng, $dist, "Demo guard is $dist m away from $site (allowed $radius m)", date('Y-m-d H:i:s', $now - 300)]);
            }
        } else {
            q('UPDATE guards SET last_lat = ?, last_lng = ?, last_seen_at = ? WHERE id = ?',
              [$lat + 0.01, $lng + 0.01, date('Y-m-d H:i:s', $now - 86400), $gid]);
        }
    }
}
echo "Demo data created: 40 guards (DEMO-001..040, password guard123), 6 clients, 30 deployments.\n";
