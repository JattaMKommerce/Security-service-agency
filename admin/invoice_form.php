<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/billing.php';
$admin = require_admin();

$id = get_int('id');
$inv = $id ? q_one('SELECT * FROM invoices WHERE id = ?', [$id]) : null;
if ($id && !$inv) {
    flash('danger', 'Invoice not found.');
    redirect('admin/invoices.php');
}
if ($inv && $inv['status'] !== 'draft') {
    flash('warning', 'Only draft invoices can be edited.');
    redirect('admin/invoice_view.php?id=' . $id);
}

$errors = [];
if (is_post()) {
    require_csrf();
    $clientId = $inv ? (int)$inv['client_id'] : (int)post_int('client_id', 0);
    $h = [
        'period_from' => post_str('period_from', 10),
        'period_to'   => post_str('period_to', 10),
        'issued_on'   => post_str('issued_on', 10),
        'due_on'      => post_str('due_on', 10),
        'tax_percent' => max(0, min(100, (float)(post_float('tax_percent') ?? 0))),
        'notes'       => post_str('notes', 2000),
    ];
    $discount = (float)(post_float('discount') ?? 0);

    $items = [];
    $raw = $_POST['items'] ?? [];
    foreach ((array)($raw['description'] ?? []) as $i => $desc) {
        $desc = trim((string)$desc);
        $qty = (float)($raw['quantity'][$i] ?? 0);
        $rate = (float)($raw['rate'][$i] ?? 0);
        if ($desc === '' && $qty == 0 && $rate == 0) continue;
        if ($desc === '') { $errors[] = 'Every line needs a description.'; break; }
        if ($qty <= 0 || $rate < 0) { $errors[] = 'Quantity must be more than 0 and rate cannot be negative.'; break; }
        $asgId = (int)($raw['assignment_id'][$i] ?? 0);
        $items[] = [
            'assignment_id' => $asgId > 0 ? $asgId : null,
            'description' => mb_substr($desc, 0, 255),
            'quantity' => round($qty, 2),
            'unit' => mb_substr(trim((string)($raw['unit'][$i] ?? 'day')) ?: 'day', 0, 20),
            'rate' => round($rate, 2),
        ];
    }

    if (!q_val("SELECT id FROM clients WHERE id = ?", [$clientId])) $errors[] = 'Choose a client.';
    if (!valid_date($h['issued_on'])) $errors[] = 'Invoice date is required.';
    if ($h['due_on'] && !valid_date($h['due_on'])) $errors[] = 'Due date is not valid.';
    if ($h['period_from'] && !valid_date($h['period_from'])) $errors[] = 'Billing period start is not valid.';
    if ($h['period_to'] && !valid_date($h['period_to'])) $errors[] = 'Billing period end is not valid.';
    if (!$items && !$errors) $errors[] = 'Add at least one line item.';
    // Only allow linking items to this client's deployments.
    foreach ($items as &$it) {
        if ($it['assignment_id'] && !q_val('SELECT id FROM assignments WHERE id = ? AND client_id = ?', [$it['assignment_id'], $clientId])) {
            $it['assignment_id'] = null;
        }
    }
    unset($it);

    if (!$errors) {
        $t = invoice_totals($items, $h['tax_percent'], $discount);
        $newId = db_tx(function () use ($inv, $id, $clientId, $h, $t, $items, $admin) {
            $row = $h + $t;
            if ($inv) {
                $cols = array_keys($row);
                q('UPDATE invoices SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($row), $id]);
                q('DELETE FROM invoice_items WHERE invoice_id = ?', [$id]);
                $invId = $id;
            } else {
                $row += ['invoice_no' => next_invoice_no(), 'client_id' => $clientId, 'status' => 'draft', 'created_by' => $admin['id']];
                q('INSERT INTO invoices (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', array_values($row));
                $invId = (int)db()->lastInsertId();
            }
            foreach ($items as $n => $it) {
                q('INSERT INTO invoice_items (invoice_id, assignment_id, description, quantity, unit, rate, amount, sort_order) VALUES (?,?,?,?,?,?,?,?)',
                  [$invId, $it['assignment_id'], $it['description'], $it['quantity'], $it['unit'], $it['rate'], round($it['quantity'] * $it['rate'], 2), $n]);
            }
            return $invId;
        });
        log_activity($inv ? 'invoice.update' : 'invoice.create', 'Invoice #' . $newId . ' total ' . $t['total']);
        flash('success', $inv ? 'Invoice updated.' : 'Invoice created as draft.');
        redirect('admin/invoice_view.php?id=' . $newId);
    }
    $form = $h + ['client_id' => $clientId, 'discount' => $discount];
    $formItems = $items;
} elseif ($inv) {
    $form = $inv;
    $formItems = q_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
} else {
    $form = [
        'client_id' => get_int('client_id'),
        'period_from' => date('Y-m-01', strtotime('first day of last month')),
        'period_to' => date('Y-m-t', strtotime('last day of last month')),
        'issued_on' => date('Y-m-d'),
        'due_on' => date('Y-m-d', strtotime('+' . (int)setting('invoice_due_days', '15') . ' days')),
        'tax_percent' => setting('tax_percent', '18'),
        'discount' => 0,
        'notes' => setting('invoice_terms'),
    ];
    $formItems = [];
}
$f = fn($k) => e($form[$k] ?? '');
$clients = q_all("SELECT id, name, client_code FROM clients ORDER BY name");

$pageTitle = $inv ? 'Edit Invoice ' . $inv['invoice_no'] : 'New Invoice';
$activeNav = 'invoices';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="mb-3"><a href="<?= url($inv ? 'admin/invoice_view.php?id=' . $id : 'admin/invoices.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" id="invForm">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label required">Client</label>
                    <select name="client_id" id="invClient" class="form-select" <?= $inv ? 'disabled' : 'required' ?>>
                        <option value="">Select client…</option>
                        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$form['client_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['client_code']) ?>)</option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-2"><label class="form-label">Period from</label><input type="date" name="period_from" id="pFrom" class="form-control" value="<?= $f('period_from') ?>"></div>
                <div class="col-md-2"><label class="form-label">Period to</label><input type="date" name="period_to" id="pTo" class="form-control" value="<?= $f('period_to') ?>"></div>
                <div class="col-md-2"><label class="form-label required">Invoice date</label><input type="date" name="issued_on" class="form-control" value="<?= $f('issued_on') ?>" required></div>
                <div class="col-md-2"><label class="form-label">Due date</label><input type="date" name="due_on" class="form-control" value="<?= $f('due_on') ?>"></div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mt-3 p-3 rounded" style="background:var(--brand-50)">
                <i class="bi bi-magic text-primary"></i>
                <span class="small fw-semibold">Fill lines automatically from this client's deployments in the period.</span>
                <select id="basis" class="form-select form-select-sm" style="width:auto">
                    <option value="scheduled">Per-day guards: bill every deployed day</option>
                    <option value="attended">Per-day guards: bill only days attended</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" id="autoFill">Auto-fill lines</button>
            </div>
        </div>
    </div>

    <div class="card table-card mb-3">
        <div class="table-responsive">
            <table class="table" id="itemsTable">
                <thead><tr><th style="width:50%">Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody id="itemsBody"></tbody>
            </table>
        </div>
        <div class="card-footer"><button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus"></i> Add line</button></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7"><div class="card"><div class="card-body">
            <label class="form-label">Notes / payment terms (printed on invoice)</label>
            <textarea name="notes" class="form-control" rows="4"><?= $f('notes') ?></textarea>
        </div></div></div>
        <div class="col-lg-5"><div class="card"><div class="card-body">
            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong id="tSub">0.00</strong></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><span>Discount</span><input type="number" step="0.01" min="0" name="discount" id="disc" class="form-control form-control-sm text-end" style="width:140px" value="<?= $f('discount') ?>"></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><span><?= e(setting('tax_label', 'GST')) ?> %</span><input type="number" step="0.01" min="0" max="100" name="tax_percent" id="taxp" class="form-control form-control-sm text-end" style="width:140px" value="<?= $f('tax_percent') ?>"></div>
            <div class="d-flex justify-content-between mb-2"><span><?= e(setting('tax_label', 'GST')) ?> amount</span><strong id="tTax">0.00</strong></div>
            <hr>
            <div class="d-flex justify-content-between fs-5"><span class="fw-bold">Total</span><strong id="tTotal">0.00</strong></div>
            <button class="btn btn-primary w-100 mt-3"><i class="bi bi-save"></i> <?= $inv ? 'Save invoice' : 'Create invoice' ?></button>
        </div></div></div>
    </div>
</form>
<?php
$inlineScript = '<script>
(function () {
    const body = document.getElementById("itemsBody");
    const cur = APP.currency;
    const fmt = n => cur + (Math.round(n * 100) / 100).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function row(it = {}) {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td><input type="hidden" name="items[assignment_id][]" value="${it.assignment_id || ""}">
              <input name="items[description][]" class="form-control form-control-sm" value="${APP.esc(it.description || "")}" placeholder="Service description"></td>
            <td><input type="number" step="0.01" min="0" name="items[quantity][]" class="form-control form-control-sm q" value="${it.quantity ?? 1}" style="width:90px"></td>
            <td><input name="items[unit][]" class="form-control form-control-sm" value="${APP.esc(it.unit || "day")}" style="width:80px"></td>
            <td><input type="number" step="0.01" min="0" name="items[rate][]" class="form-control form-control-sm r" value="${it.rate ?? 0}" style="width:120px"></td>
            <td class="text-end fw-semibold amt">0.00</td>
            <td><button type="button" class="btn btn-sm btn-light del" title="Remove"><i class="bi bi-x-lg"></i></button></td>`;
        body.appendChild(tr);
    }
    function recalc() {
        let sub = 0;
        body.querySelectorAll("tr").forEach(tr => {
            const a = (parseFloat(tr.querySelector(".q").value) || 0) * (parseFloat(tr.querySelector(".r").value) || 0);
            tr.querySelector(".amt").textContent = fmt(a); sub += Math.round(a * 100) / 100;
        });
        const disc = Math.min(sub, Math.max(0, parseFloat(document.getElementById("disc").value) || 0));
        const tax = Math.round((sub - disc) * (parseFloat(document.getElementById("taxp").value) || 0)) / 100;
        document.getElementById("tSub").textContent = fmt(sub);
        document.getElementById("tTax").textContent = fmt(tax);
        document.getElementById("tTotal").textContent = fmt(sub - disc + tax);
    }
    body.addEventListener("input", recalc);
    body.addEventListener("click", e => { if (e.target.closest(".del")) { e.target.closest("tr").remove(); recalc(); } });
    ["disc", "taxp"].forEach(id => document.getElementById(id).addEventListener("input", recalc));
    document.getElementById("addLine").addEventListener("click", () => { row(); recalc(); });
    document.getElementById("autoFill").addEventListener("click", async () => {
        const c = document.getElementById("invClient").value, f = document.getElementById("pFrom").value, t = document.getElementById("pTo").value;
        if (!c || !f || !t) return alert("Choose the client and billing period first.");
        try {
            const d = await APP.api("api/admin/invoice_autofill.php?client_id=" + c + "&from=" + f + "&to=" + t + "&basis=" + document.getElementById("basis").value);
            if (!d.lines.length) return alert("No billable deployments or attendance found for this client in that period.");
            if (body.children.length && !confirm("Replace the current lines with " + d.lines.length + " generated line(s)?")) return;
            body.innerHTML = ""; d.lines.forEach(row); recalc();
        } catch (e) { alert(e.message); }
    });
    const initial = ' . json_encode(array_values($formItems)) . ';
    initial.length ? initial.forEach(row) : row();
    recalc();
})();
</script>';
require APP_ROOT . '/includes/layout/footer.php';
