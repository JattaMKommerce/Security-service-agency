<?php
declare(strict_types=1);

/**
 * Builds invoice lines for a client and period from deployments + attendance.
 * $basis: 'scheduled' bills daily guards for every day of the deployment in
 * the period; 'attended' bills only days the guard actually checked in.
 * Hourly deployments are always billed from attendance hours.
 */
function build_invoice_lines(int $clientId, string $from, string $to, string $basis = 'scheduled'): array
{
    $rows = q_all("SELECT a.*, g.full_name, g.employee_code
                     FROM assignments a JOIN guards g ON g.id = a.guard_id
                    WHERE a.client_id = ? AND a.status <> 'cancelled'
                      AND a.start_date <= ?
                      AND COALESCE(a.end_date, DATE(a.ended_at), ?) >= ?
                    ORDER BY a.site_name, g.full_name", [$clientId, $to, $to, $from]);
    $lines = [];
    foreach ($rows as $a) {
        $end = $a['end_date'] ?: ($a['ended_at'] ? substr($a['ended_at'], 0, 10) : $to);
        $s = max($from, $a['start_date']);
        $e = min($to, $end);
        if ($e < $s) continue;
        $days = (int)((strtotime($e) - strtotime($s)) / 86400) + 1;
        $range = [$s . ' 00:00:00', date('Y-m-d', strtotime($e . ' +1 day')) . ' 00:00:00'];

        if ($a['rate_type'] === 'hourly') {
            $mins = (int)q_val('SELECT COALESCE(SUM(duration_minutes),0) FROM attendance
                                 WHERE assignment_id = ? AND check_in_at >= ? AND check_in_at < ? AND status <> \'open\'',
                               [$a['id'], ...$range]);
            $qty = round($mins / 60, 2);
            $unit = 'hour';
        } elseif ($a['rate_type'] === 'monthly') {
            $qty = 0.0;
            $cursor = strtotime($s);
            $last = strtotime($e);
            while ($cursor <= $last) {
                $monthEnd = strtotime(date('Y-m-t', $cursor));
                $segEnd = min($monthEnd, $last);
                $segDays = (int)(($segEnd - $cursor) / 86400) + 1;
                $qty += $segDays / (int)date('t', $cursor);
                $cursor = $monthEnd + 86400;
            }
            $qty = round($qty, 2);
            $unit = 'month';
        } else {
            if ($basis === 'attended') {
                $qty = (float)q_val('SELECT COUNT(DISTINCT DATE(check_in_at)) FROM attendance
                                      WHERE assignment_id = ? AND check_in_at >= ? AND check_in_at < ?', [$a['id'], ...$range]);
            } else {
                $qty = (float)$days;
            }
            $unit = 'day';
        }
        if ($qty <= 0) continue;
        $lines[] = [
            'assignment_id' => (int)$a['id'],
            'description'   => sprintf('Bodyguard service — %s (%s) at %s, %s to %s',
                                       $a['full_name'], $a['employee_code'], $a['site_name'], date('d M Y', strtotime($s)), date('d M Y', strtotime($e))),
            'quantity'      => $qty,
            'unit'          => $unit,
            'rate'          => (float)$a['rate'],
            'amount'        => round($qty * (float)$a['rate'], 2),
        ];
    }
    return $lines;
}

/** INV-2026-0001 style number, unique per year. */
function next_invoice_no(): string
{
    $prefix = setting('invoice_prefix', 'INV-') . date('Y') . '-';
    $max = q_val('SELECT MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)) FROM invoices WHERE invoice_no LIKE ?',
                 [strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string)((int)$max + 1), 4, '0', STR_PAD_LEFT);
}

/** Recalculate subtotal, tax and total for an invoice from its items. */
function invoice_totals(array $items, float $taxPercent, float $discount): array
{
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += round((float)$it['quantity'] * (float)$it['rate'], 2);
    }
    $discount = min(max(0, $discount), $subtotal);
    $taxable = $subtotal - $discount;
    $tax = round($taxable * $taxPercent / 100, 2);
    return ['subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'tax_amount' => $tax, 'total' => round($taxable + $tax, 2)];
}
