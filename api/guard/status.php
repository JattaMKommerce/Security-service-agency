<?php
require __DIR__ . '/_common.php';
$guard = require_guard(true);
json_out(guard_state($guard));
