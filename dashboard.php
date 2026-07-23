<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$counts = ['customers'=>0,'vehicles'=>0,'orders_open'=>0,'orders_completed'=>0,'inventory_low'=>0,'invoices_unpaid'=>0];
$fetchError = '';

    // Count unique customers from both customers table and vehicles table
    try {
        $dbCusts = Supabase::select(TBL_CUSTOMERS, ['select' => 'phone'], $token);
    } catch (Exception $e) {
        $dbCusts = [];
    }
    try {
        $vehCusts = Supabase::select(TBL_VEHICLES, ['select' => 'customer_phone'], $token);
    } catch (Exception $e) {
        $vehCusts = [];
    }
    
    $uniquePhones = [];
    foreach ($dbCusts as $c) {
        if (!empty($c['phone'])) {
            $uniquePhones[trim($c['phone'])] = true;
        }
    }
    foreach ($vehCusts as $v) {
        if (!empty($v['customer_phone'])) {
            $uniquePhones[trim($v['customer_phone'])] = true;
        }
    }
    $counts['customers'] = count($uniquePhones);

try {
    $counts['vehicles']         = Supabase::count(TBL_VEHICLES, [], $token);
    $counts['orders_open']      = Supabase::count(TBL_SERVICE_ORDERS, ['status' => 'in.("Pending","In Progress",pending,in_progress)'], $token);
    $counts['orders_completed'] = Supabase::count(TBL_SERVICE_ORDERS, ['status' => 'in.("Completed","Paid",completed,paid)'], $token);

    // spare_parts uses 'stock' column and has no low_stock_alert, so we default to threshold 5
    $inventoryRows = Supabase::select(TBL_INVENTORY, ['select' => 'stock'], $token);
    foreach ($inventoryRows as $row) {
      $threshold = 5;
      if ((int)($row['stock'] ?? 0) < $threshold) {
        $counts['inventory_low']++;
      }
    }

    $counts['invoices_unpaid']  = Supabase::count(TBL_SERVICE_ORDERS, ['status' => 'eq.Completed'], $token);

    // Embed vehicle -> customer via Supabase's FK-based nested select
    $recentOrders = Supabase::select(TBL_SERVICE_ORDERS, [
        'select' => '*,vehicles(plate_no,make,model,customers(name))',
        'order' => 'created_at.desc',
        'limit' => '6',
    ], $token);
} catch (Exception $e) {
    $fetchError = $e->getMessage();
    $recentOrders = [];
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of workshop performance';
include __DIR__ . '/partials/header.php';
?>

<?php if ($fetchError): ?>
  <div class="error-msg" style="margin-bottom:20px;">
    Could not load live data: <?= htmlspecialchars($fetchError) ?><br>
    Check that your Supabase URL / anon key in <code>config.php</code> and table names match your project.
  </div>
<?php endif; ?>

<div class="summary-grid">
  <div class="summary-card">
    <div class="label">Total Customers</div>
    <div class="value"><?= $counts['customers'] ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Registered Vehicles</div>
    <div class="value"><?= $counts['vehicles'] ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Open Service Orders</div>
    <div class="value"><?= $counts['orders_open'] ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Completed Orders</div>
    <div class="value"><?= $counts['orders_completed'] ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Low Stock Items</div>
    <div class="value"><?= $counts['inventory_low'] ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Unpaid Invoices</div>
    <div class="value"><?= $counts['invoices_unpaid'] ?></div>
  </div>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <strong>Recent Service Orders</strong>
    <a href="service_orders.php" class="btn btn-outline btn-sm">View all →</a>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>Order ID</th><th>Vehicle</th><th>Status</th><th>Created</th></tr>
    </thead>
    <tbody>
      <?php if (empty($recentOrders)): ?>
        <tr><td colspan="4" class="empty-state">No service orders yet.</td></tr>
      <?php else: foreach ($recentOrders as $o):
        $vehicle = $o['vehicles'] ?? null;
        $customerName = $vehicle['customers']['name'] ?? (!empty($vehicle['customer_name']) ? $vehicle['customer_name'] : null);
        $vehicleLabel = $vehicle ? trim(($vehicle['plate_no'] ?? '') . ' — ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : '-';
      ?>
        <tr>
          <td>#<?= htmlspecialchars(substr($o['id'] ?? '', 0, 8)) ?></td>
          <td><?= htmlspecialchars($vehicleLabel) ?><?= $customerName ? ' (' . htmlspecialchars($customerName) . ')' : '' ?></td>
          <td><span class="badge badge-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $o['status'] ?? 'pending'))) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $o['status'] ?? 'pending'))) ?></span></td>
          <td><?= htmlspecialchars(date('d M Y, g:i A', strtotime($o['created_at'] ?? 'now'))) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
