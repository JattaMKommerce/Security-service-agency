<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/billing.php';
require_admin(true);

$clientId = get_int('client_id');
$from = get_str('from', 10);
$to = get_str('to', 10);
$basis = get_str('basis', 12) === 'attended' ? 'attended' : 'scheduled';
if ($clientId <= 0 || !valid_date($from) || !valid_date($to) || $to < $from) {
    json_error('Choose a client and a valid billing period.');
}
json_out(['ok' => true, 'lines' => build_invoice_lines($clientId, $from, $to, $basis)]);
