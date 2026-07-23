<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        Supabase::update(TBL_SERVICE_ORDERS, ['status' => $_POST['status']], ['id' => 'eq.' . $_POST['order_id']], $token);
        $notice = 'Order status updated.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $orders = Supabase::select(TBL_SERVICE_ORDERS, [
        'select' => '*,vehicles(plate_no,make,model,customers(name))',
        'order' => 'created_at.desc',
    ], $token);

    $mechanicsMap = [];
    try {
        $profilesList = Supabase::select(TBL_PROFILES, [], $token);
        foreach ($profilesList as $p) {
            $mechanicsMap[$p['id']] = $p['full_name'];
        }
    } catch (Exception $ex) {
        // Fallback silently if profiles load fails
    }
} catch (Exception $e) {
    $orders = [];
    $error = $error ?: $e->getMessage();
}

$statuses = ['Pending', 'In Progress', 'Completed', 'Paid', 'Cancelled'];

$pageTitle = 'Service Orders';
$pageSubtitle = 'Monitor and manage all service orders';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-card">
  <div class="table-toolbar">
    <input type="text" id="searchOrders" placeholder="🔍 Search orders...">
  </div>
  <table class="data-table" id="ordersTable">
    <thead>
      <tr><th>Order ID</th><th>Vehicle</th><th>Assigned Mechanic</th><th>Created</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
        <tr><td colspan="5" class="empty-state">No service orders found.</td></tr>
      <?php else: foreach ($orders as $o):
        $vehicle = $o['vehicles'] ?? null;
        $customerName = $vehicle['customers']['name'] ?? (!empty($vehicle['customer_name']) ? $vehicle['customer_name'] : null);
        $vehicleLabel = $vehicle ? trim(($vehicle['plate_no'] ?? '') . ' — ' . ($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) : '-';
      ?>
        <tr>
          <td>#<?= htmlspecialchars(substr($o['id'] ?? '', 0, 8)) ?></td>
          <td><?= htmlspecialchars($vehicleLabel) ?><?= $customerName ? ' (' . htmlspecialchars($customerName) . ')' : '' ?></td>
          <td><?= htmlspecialchars($mechanicsMap[$o['mechanic_id'] ?? ''] ?? (!empty($o['mechanic_id']) ? substr($o['mechanic_id'], 0, 8) : '-')) ?></td>
          <td><?= htmlspecialchars(isset($o['created_at']) ? date('d M Y', strtotime($o['created_at'])) : '-') ?></td>
          <td>
            <form method="POST" action="service_orders.php" style="display:flex; gap:6px;">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_id" value="<?= htmlspecialchars($o['id'] ?? '') ?>">
              <select name="status" style="padding:6px;border-radius:6px;border:1px solid #e1e8e8;">
                <?php foreach ($statuses as $s): ?>
                  <option value="<?= $s ?>" <?= strtolower($o['status'] ?? '') === strtolower($s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Update</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>filterTable('searchOrders', 'ordersTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
