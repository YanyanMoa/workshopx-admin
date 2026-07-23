<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    try {
        Supabase::update(TBL_SERVICE_ORDERS, ['status' => 'Paid'], ['id' => 'eq.' . $_POST['invoice_id']], $token);
        $notice = 'Invoice marked as paid.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    // Select all service orders that are Completed or Paid
    $orders = Supabase::select(TBL_SERVICE_ORDERS, [
        'select' => '*,vehicles(plate_no,make,model,customers(name))',
        'status' => 'in.(Completed,completed,Paid,paid)',
        'order' => 'created_at.desc'
    ], $token);

    // Fetch all spare parts to map IDs to prices
    $partsList = Supabase::select(TBL_SPARE_PARTS, [], $token);
    $partsMap = [];
    foreach ($partsList as $p) {
        $partsMap[$p['id']] = $p;
    }

    // Map service orders to invoices
    $invoices = [];
    $totalRevenue = 0;

    foreach ($orders as $o) {
        $labourCost = (float)($o['labour_cost'] ?? 0);
        $partsCost = 0;
        if (!empty($o['parts_used']) && is_array($o['parts_used'])) {
            foreach ($o['parts_used'] as $partId) {
                if (isset($partsMap[$partId])) {
                    $partsCost += (float)$partsMap[$partId]['price'];
                }
            }
        }
        $totalAmount = $labourCost + $partsCost;
        $isPaid = strtolower($o['status'] ?? '') === 'paid';

        if ($isPaid) {
            $totalRevenue += $totalAmount;
        }

        $vehicle = $o['vehicles'] ?? null;
        $carDetails = $vehicle ? trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : 'Unknown';
        $plateNo = $vehicle['plate_no'] ?? '-';
        $customerName = $vehicle['customers']['name'] ?? (!empty($vehicle['customer_name']) ? $vehicle['customer_name'] : 'Walk-in');

        $invoices[] = [
            'id' => $o['id'],
            'order_id' => $o['id'],
            'vehicle_label' => $carDetails . ' [' . $plateNo . ']',
            'customer_name' => $customerName,
            'total_amount' => $totalAmount,
            'status' => $o['status'], // 'Completed' or 'Paid'
            'issued_at' => $o['created_at']
        ];
    }

} catch (Exception $e) {
    $invoices = [];
    $totalRevenue = 0;
    $error = $error ?: $e->getMessage();
}

$pageTitle = 'Invoices & Payments';
$pageSubtitle = 'Scope, manage, and export invoice and payment information';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); margin-bottom:20px;">
  <div class="summary-card">
    <div class="label">Total Revenue (Paid)</div>
    <div class="value">RM <?= number_format($totalRevenue, 2) ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Total Invoices</div>
    <div class="value"><?= count($invoices) ?></div>
  </div>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <input type="text" id="searchInvoices" placeholder="🔍 Search invoices...">
  </div>
  <table class="data-table" id="invoicesTable">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Vehicle Details</th>
        <th>Customer Name</th>
        <th>Amount (RM)</th>
        <th>Payment Status</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($invoices)): ?>
        <tr><td colspan="7" class="empty-state">No invoices found.</td></tr>
      <?php else: foreach ($invoices as $inv):
        $paid = strtolower($inv['status'] ?? '') === 'paid';
      ?>
        <tr>
          <td>#<?= htmlspecialchars(substr($inv['id'] ?? '', 0, 8)) ?></td>
          <td><?= htmlspecialchars($inv['vehicle_label'] ?? '-') ?></td>
          <td><?= htmlspecialchars($inv['customer_name'] ?? '-') ?></td>
          <td>RM <?= number_format((float)($inv['total_amount'] ?? 0), 2) ?></td>
          <td><span class="badge badge-<?= $paid ? 'paid' : 'unpaid' ?>"><?= $paid ? 'Paid' : 'Unpaid' ?></span></td>
          <td><?= htmlspecialchars(isset($inv['issued_at']) ? date('d M Y', strtotime($inv['issued_at'])) : '-') ?></td>
          <td>
            <div style="display:flex; gap:6px; align-items:center;">
              <a href="invoice_view.php?id=<?= urlencode($inv['id']) ?>" class="btn btn-outline btn-sm">View / Print</a>
              <?php if (!$paid): ?>
                <form method="POST" action="invoices.php" style="margin:0;">
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($inv['id'] ?? '') ?>">
                  <button type="submit" class="btn btn-primary btn-sm">Mark Paid</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>filterTable('searchInvoices', 'invoicesTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
