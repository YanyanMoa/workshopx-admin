<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

$error = '';
$order = null;
$partsMap = [];

if (empty($_GET['id'])) {
    die("Invalid Invoice ID.");
}

try {
    // Select the service order with vehicle & customer info (bypass auth check for this read-only UUID link)
    $results = Supabase::select(TBL_SERVICE_ORDERS, [
        'select' => '*,vehicles(plate_no,make,model,customers(name,phone))',
        'id' => 'eq.' . $_GET['id']
    ], SUPABASE_SERVICE_KEY); // Use service key to read this specific invoice securely via its UUID

    if (empty($results)) {
        throw new Exception('Invoice not found.');
    }
    $order = $results[0];

    // Fetch all spare parts to map IDs to prices & names
    $partsList = Supabase::select(TBL_SPARE_PARTS, [], SUPABASE_SERVICE_KEY);
    foreach ($partsList as $p) {
        $partsMap[$p['id']] = $p;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if ($error) {
    die("Error loading invoice: " . htmlspecialchars($error));
}

$vehicle = $order['vehicles'] ?? null;
$customer = $vehicle['customers'] ?? null;
$custName = $customer['name'] ?? (!empty($vehicle['customer_name']) ? $vehicle['customer_name'] : 'Walk-in Customer');
$custPhone = $customer['phone'] ?? (!empty($vehicle['customer_phone']) ? $vehicle['customer_phone'] : '-');

$carDetails = $vehicle ? trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : 'Unknown Vehicle';
$plateNo = $vehicle['plate_no'] ?? '-';

$labourCost = (float)($order['labour_cost'] ?? 0);
$partsCost = 0;
$partsItems = [];

if (!empty($order['parts_used']) && is_array($order['parts_used'])) {
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice INV-<?= htmlspecialchars(substr($order['id'], 0, 8)) ?></title>
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      margin: 0;
      padding: 20px;
      background: #f8fafc;
      color: #1e293b;
    }
    .receipt-container {
      background: #ffffff;
      max-width: 600px;
      margin: 20px auto;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
      border: 1px solid #e2e8f0;
    }
    .receipt-header {
      text-align: center;
      border-bottom: 2px dashed #cbd5e1;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }
    .receipt-header h2 {
      margin: 0;
      color: #0f766e;
      font-size: 24px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .receipt-header p {
      margin: 5px 0 0 0;
      font-size: 11px;
      color: #64748b;
    }
    .receipt-meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      font-size: 12px;
      margin-bottom: 20px;
      color: #334155;
    }
    .meta-block strong {
      display: block;
      color: #64748b;
      text-transform: uppercase;
      font-size: 9px;
      margin-bottom: 4px;
    }
    .items-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .items-table th {
      border-bottom: 1px solid #cbd5e1;
      padding: 8px 0;
      text-align: left;
      color: #64748b;
      text-transform: uppercase;
      font-size: 9px;
    }
    .items-table td {
      padding: 10px 0;
      border-bottom: 1px dashed #e2e8f0;
      vertical-align: top;
    }
    .summary-section {
      display: flex;
      justify-content: flex-end;
      font-size: 13px;
      margin-top: 15px;
    }
    .summary-table {
      width: 200px;
    }
    .summary-table td {
      padding: 4px 0;
    }
    .summary-table td:last-child {
      text-align: right;
      font-weight: 600;
    }
    .grand-total {
      border-top: 2px solid #0f766e;
      font-size: 16px;
      color: #0f766e;
      font-weight: 800;
    }
    .grand-total td {
      padding-top: 10px !important;
    }
    .footer {
      text-align: center;
      margin-top: 30px;
      font-size: 11px;
      color: #94a3b8;
      border-top: 1px solid #e2e8f0;
      padding-top: 15px;
    }
    .print-btn-container {
      text-align: center;
      margin-top: 20px;
    }
    .print-btn {
      background: #0f766e;
      color: white;
      border: none;
      padding: 10px 24px;
      font-size: 14px;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(15,118,110,0.2);
    }
    .print-btn:hover {
      background: #0d9488;
    }
    @media print {
      body {
        background: white;
        padding: 0;
      }
      .receipt-container {
        border: none;
        box-shadow: none;
        margin: 0;
        padding: 0;
        max-width: 100%;
      }
      .print-btn-container {
        display: none;
      }
    }
  </style>
</head>
<body>

  <div class="receipt-container">
    <div class="receipt-header">
      <h2>WorkshopX</h2>
      <p>Empowering Workshops, Driving Efficiency</p>
      <p style="font-size: 9px; margin-top: 4px; color: #94a3b8;">INV-<?= htmlspecialchars(substr($order['id'], 0, 8)) ?> | Date: <?= date('d M Y', strtotime($order['created_at'])) ?></p>
    </div>

    <div class="receipt-meta">
      <div class="meta-block">
        <strong>Customer Info</strong>
        Name: <?= htmlspecialchars($custName) ?><br>
        Phone: <?= htmlspecialchars($custPhone) ?>
      </div>
      <div class="meta-block" style="text-align: right;">
        <strong>Vehicle Details</strong>
        Model: <?= htmlspecialchars($carDetails) ?><br>
        Plate: <?= htmlspecialchars($plateNo) ?>
      </div>
    </div>

    <table class="items-table">
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align: center; width: 50px;">Qty</th>
          <th style="text-align: right; width: 100px;">Price</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong>Professional Labour Charges</strong><br>
            <span style="font-size:11px; color:#64748b; font-style:italic;">"<?= htmlspecialchars($order['description'] ?: 'Car service & inspection') ?>"</span>
          </td>
          <td style="text-align: center;">1</td>
          <td style="text-align: right;">RM <?= number_format($labourCost, 2) ?></td>
        </tr>
        <?php foreach ($partsItems as $item): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($item['name']) ?></strong><br>
              <span style="font-size:10px; color:#64748b;">SKU: <?= htmlspecialchars($item['sku']) ?></span>
            </td>
            <td style="text-align: center;"><?= $item['qty'] ?></td>
            <td style="text-align: right;">RM <?= number_format($item['total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="summary-section">
      <table class="summary-table">
        <tr>
          <td>Subtotal</td>
          <td>RM <?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr>
          <td>Tax (SST 6%)</td>
          <td>RM <?= number_format($tax, 2) ?></td>
        </tr>
        <tr class="grand-total">
          <td>Grand Total</td>
          <td>RM <?= number_format($grandTotal, 2) ?></td>
        </tr>
      </table>
    </div>

    <div class="footer">
      Thank you for choosing WorkshopX!<br>
      Please drive safely.
    </div>
  </div>

  <div class="print-btn-container">
    <button class="print-btn" onclick="window.print()">🖨️ Print Invoice Receipt</button>
  </div>

  <script>
    // Automatically open browser print dialog on load
    window.onload = function() {
      window.print();
    }
  </script>
</body>
</html>
