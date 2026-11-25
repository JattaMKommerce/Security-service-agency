<?php
require __DIR__ . '/_common.php';
$guard = require_guard(true);
touch_guard_ping($guard, $_GET['gps'] ?? null);
session_write_close();
maybe_run_live_checks();
json_out(guard_state($guard));
