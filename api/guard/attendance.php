<?php
/** Guard's own attendance history for one month. GET ?month=YYYY-MM */
require __DIR__ . '/_common.php';
$guard = require_guard(true);
session_write_close();
$ym = get_str('month', 7);
if (!valid_month($ym) || $ym > date('Y-m')) {
    $ym = date('Y-m');
}
json_out(['ok' => true] + attendance_month((int)$guard['id'], $ym) + [
    'prev' => date('Y-m', strtotime($ym . '-01 -1 month')),
    'next' => $ym < date('Y-m') ? date('Y-m', strtotime($ym . '-01 +1 month')) : null,
]);
