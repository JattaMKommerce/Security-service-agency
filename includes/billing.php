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

/**
 * Converts Indian Rupee amounts into Words (crores, lakhs, thousands, hundreds)
 * per RBI / Indian statutory accounting standards.
 */
function amount_in_words(float $amount): string
{
    $amount = round($amount, 2);
    $rupees = (int)floor($amount);
    $paise = (int)round(($amount - $rupees) * 100);

    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    ];

    $convertChunk = function(int $num) use (&$convertChunk, $words): string {
        if ($num === 0) return '';
        if ($num < 20) return trim($words[$num]) . ' ';
        if ($num < 100) return trim($words[(int)floor($num / 10) * 10] . ' ' . $words[$num % 10]) . ' ';
        return trim($words[(int)floor($num / 100)]) . ' Hundred ' . $convertChunk($num % 100);
    };

    if ($rupees === 0) {
        $str = 'Rupees Zero';
    } else {
        $crores = (int)floor($rupees / 10000000);
        $rupees %= 10000000;
        $lakhs = (int)floor($rupees / 100000);
        $rupees %= 100000;
        $thousands = (int)floor($rupees / 1000);
        $rupees %= 1000;
        $remaining = $rupees;

        $parts = [];
        if ($crores > 0) $parts[] = trim($convertChunk($crores)) . ' Crore';
        if ($lakhs > 0) $parts[] = trim($convertChunk($lakhs)) . ' Lakh';
        if ($thousands > 0) $parts[] = trim($convertChunk($thousands)) . ' Thousand';
        if ($remaining > 0) $parts[] = trim($convertChunk($remaining));

        $str = 'Rupees ' . trim(implode(' ', array_filter($parts)));
    }

    if ($paise > 0) {
        $str .= ' and ' . trim($convertChunk($paise)) . ' Paise';
    }
    return $str . ' Only';
}

/**
 * Returns official RBI & GST statutory details for security service invoicing.
 */
function rbi_bank_details(): array
{
    $companyName = setting('company_name', 'Jatta Security Agency');
    $gstin = setting('company_gstin') ?: '29AAACJ1234A1Z5';
    // Derive PAN from GSTIN if 15 chars, else default
    $pan = (strlen($gstin) === 15) ? substr($gstin, 2, 10) : 'AAACJ1234A';

    return [
        'company_name'    => $companyName,
        'tagline'         => 'Government Licensed Security & Protection Agency',
        'address'         => setting('company_address') ?: "Plot No. 42, Commercial Hub, Airport Road\nHubballi, Karnataka - 580030",
        'phone'           => setting('company_phone') ?: '+91 83109 79654',
        'email'           => setting('company_email') ?: 'accounts@jattamkommerce.com',
        'gstin'           => $gstin,
        'pan'             => $pan,
        'state'           => 'Karnataka',
        'state_code'      => '29',
        'sac_code'        => '998525', // Security and Guard Services
        'rcm_applicable'  => 'No',
        'bank_name'       => setting('bank_name', 'State Bank of India'),
        'account_name'    => setting('bank_account_name', $companyName),
        'account_no'      => setting('bank_account_no', '38491028475'),
        'account_type'    => setting('bank_account_type', 'Current Account'),
        'ifsc_code'       => setting('bank_ifsc', 'SBIN0000845'),
        'branch'          => setting('bank_branch', 'Hubballi Main Branch, Karnataka'),
        'upi_id'          => setting('company_upi', '8310979654@ybl'),
    ];
}

/**
 * Builds dynamic UPI intent payment URL for scan-and-pay
 */
function upi_payment_url(string $upiId, string $payeeName, float $amount, string $invoiceNo): string
{
    $params = [
        'pa' => $upiId,
        'pn' => $payeeName,
        'am' => number_format($amount, 2, '.', ''),
        'cu' => 'INR',
        'tn' => 'Inv ' . $invoiceNo,
    ];
    return 'upi://pay?' . http_build_query($params);
}
