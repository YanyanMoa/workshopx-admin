<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$error = '';
$order = null;
$partsMap = [];

if (empty($_GET['id'])) {
    header('Location: invoices.php');
    exit;
}

try {
    // Select the service order with vehicle & customer info
    $results = Supabase::select(TBL_SERVICE_ORDERS, [
        'select' => '*,vehicles(plate_no,make,model,customers(name,phone,email))',
        'id' => 'eq.' . $_GET['id']
    ], $token);

    if (empty($results)) {
        throw new Exception('Invoice not found.');
    }
    $order = $results[0];

    // Fetch all spare parts to map IDs to prices & names
    $partsList = Supabase::select(TBL_SPARE_PARTS, [], $token);
    foreach ($partsList as $p) {
        $partsMap[$p['id']] = $p;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Invoice Details';
include __DIR__ . '/partials/header.php';
?>

<style>
.invoice-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  padding: 40px;
  max-width: 800px;
  margin: 0 auto 30px auto;
  color: #1a2b2b;
}

.invoice-header {
  display: flex;
  justify-content: space-between;
  border-bottom: 2px solid #f4f7f7;
  padding-bottom: 30px;
  margin-bottom: 30px;
}

.brand-section h2 {
  margin: 0;
  font-size: 26px;
  color: var(--teal-dark);
  font-weight: 800;
  text-transform: uppercase;
}

.brand-section span {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.company-details {
  text-align: right;
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.5;
}

.invoice-meta-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 40px;
  font-size: 13px;
}

.meta-col h4 {
  margin: 0 0 8px 0;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.meta-col p {
  margin: 0;
  line-height: 1.5;
}

.invoice-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 30px;
}

.invoice-table th {
  background: #f4f7f7;
  padding: 12px 15px;
  text-align: left;
  font-size: 11px;
  text-transform: uppercase;
  color: var(--text-muted);
  font-weight: 700;
}

.invoice-table td {
  padding: 15px;
  border-bottom: 1px solid #f4f7f7;
  font-size: 14px;
}

.invoice-summary {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

.summary-table {
  width: 250px;
  font-size: 14px;
}

.summary-table tr td {
  padding: 8px 0;
}

.summary-table tr td:last-child {
  text-align: right;
  font-weight: 600;
}

.summary-table .grand-total {
  border-top: 2px solid var(--teal-dark);
  font-size: 18px;
  color: var(--teal-dark);
  font-weight: 800 !important;
  padding-top: 12px;
}

.invoice-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 800px;
  margin: 0 auto;
  padding: 0 10px;
}

.badge-invoice {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-invoice-paid {
  background: #e2f7ec;
  color: #1f7a4d;
}

.badge-invoice-unpaid {
  background: #fff0f0;
  color: #c92a2a;
}

@media print {
  body {
    background: #fff !important;
  }
  .sidebar, .topbar, .invoice-actions-bar {
    display: none !important;
  }
  .app-shell, .main, .content {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    background: #fff !important;
  }
  .invoice-card {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    max-width: 100% !important;
    margin: 0 !important;
  }
}
</style>

<?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
  <div class="invoice-actions-bar">
    <a href="invoices.php" class="btn btn-outline">&larr; Back to Invoices</a>
  </div>
<?php else: 
  $vehicle = $order['vehicles'] ?? null;
  $customer = $vehicle['customers'] ?? null;
  $custName = $customer['name'] ?? (!empty($vehicle['customer_name']) ? $vehicle['customer_name'] : 'Walk-in Customer');
  $custPhone = $customer['phone'] ?? (!empty($vehicle['customer_phone']) ? $vehicle['customer_phone'] : '-');
  $custEmail = $customer['email'] ?? '-';
  
  $carDetails = $vehicle ? trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : 'Unknown Vehicle';
  $plateNo = $vehicle['plate_no'] ?? '-';
  
  $labourCost = (float)($order['labour_cost'] ?? 0);
  $partsCost = 0;
  $partsItems = [];
  
  if (!empty($order['parts_used']) && is_array($order['parts_used'])) {
      // Group same parts together to calculate quantities
      $partsCounts = array_count_values($order['parts_used']);
      foreach ($partsCounts as $partId => $qty) {
          if (isset($partsMap[$partId])) {
              $part = $partsMap[$partId];
              $itemTotal = (float)$part['price'] * $qty;
              $partsCost += $itemTotal;
              $partsItems[] = [
                  'name' => $part['name'],
                  'sku' => $part['sku'] ?? '-',
                  'qty' => $qty,
                  'price' => (float)$part['price'],
                  'total' => $itemTotal
              ];
          }
      }
  }

  $subtotal = $labourCost + $partsCost;
  $tax = $subtotal * 0.06; // 6% SST Service Tax
  $grandTotal = $subtotal + $tax;
  
  $isPaid = strtolower($order['status'] ?? '') === 'paid';
?>

  <div class="invoice-card">
    <div class="invoice-header">
      <div class="brand-section">
        <h2>WorkshopX</h2>
        <span>PLMM Certified Service Portal</span>
      </div>
      <div class="company-details">
        <strong>WorkshopX HQ Sdn. Bhd.</strong><br>
        12, Jalan Petronas, Seksyen 15,<br>
        40000 Shah Alam, Selangor<br>
        support@workshopx.com | +603-5512 8899
      </div>
    </div>

    <div class="invoice-meta-grid">
      <div class="meta-col">
        <h4>Invoice To</h4>
        <p><strong><?= htmlspecialchars($custName) ?></strong></p>
        <p>Phone: <?= htmlspecialchars($custPhone) ?></p>
        <p>Email: <?= htmlspecialchars($custEmail) ?></p>
      </div>
      <div class="meta-col">
        <h4>Vehicle Info</h4>
        <p>Model: <strong><?= htmlspecialchars($carDetails) ?></strong></p>
        <p>License Plate: <strong><?= htmlspecialchars($plateNo) ?></strong></p>
      </div>
      <div class="meta-col">
        <h4>Invoice Details</h4>
        <p>Invoice #: <strong>INV-<?= htmlspecialchars(substr($order['id'], 0, 8)) ?></strong></p>
        <p>Date: <?= htmlspecialchars(date('d F Y', strtotime($order['created_at']))) ?></p>
        <p style="margin-top:8px;">
          <span class="badge-invoice badge-invoice-<?= $isPaid ? 'paid' : 'unpaid' ?>">
            <?= $isPaid ? 'Paid' : 'Awaiting Payment' ?>
          </span>
        </p>
      </div>
    </div>

    <table class="invoice-table">
      <thead>
        <tr>
          <th>Item & Description</th>
          <th style="text-align: center; width: 80px;">Qty</th>
          <th style="text-align: right; width: 120px;">Unit Price</th>
          <th style="text-align: right; width: 120px;">Total (RM)</th>
        </tr>
      </thead>
      <tbody>
        <!-- Labour Charges -->
        <tr>
          <td>
            <strong>Professional Labour Charges</strong>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
              <?= htmlspecialchars($order['description'] ?: 'Mechanical inspection & repair service') ?>
            </div>
          </td>
          <td style="text-align: center;">1</td>
          <td style="text-align: right;">RM <?= number_format($labourCost, 2) ?></td>
          <td style="text-align: right;">RM <?= number_format($labourCost, 2) ?></td>
        </tr>

        <!-- Parts Used -->
        <?php foreach ($partsItems as $item): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($item['name']) ?></strong>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">SKU: <?= htmlspecialchars($item['sku']) ?></div>
            </td>
            <td style="text-align: center;"><?= $item['qty'] ?></td>
            <td style="text-align: right;">RM <?= number_format($item['price'], 2) ?></td>
            <td style="text-align: right;">RM <?= number_format($item['total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="invoice-summary">
      <table class="summary-table">
        <tr>
          <td>Subtotal</td>
          <td>RM <?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr>
          <td>Service Tax (SST 6%)</td>
          <td>RM <?= number_format($tax, 2) ?></td>
        </tr>
        <tr class="grand-total">
          <td>Grand Total</td>
          <td>RM <?= number_format($grandTotal, 2) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="invoice-actions-bar">
    <a href="invoices.php" class="btn btn-outline">&larr; Back to Invoices</a>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print Invoice Receipt</button>
  </div>

<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
