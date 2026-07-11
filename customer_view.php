<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$id = $_GET['id'] ?? '';
$error = '';
$customer = null;
$vehicles = [];

try {
    if (strpos($id, 'v-') === 0) {
        // This is a customer stored in the vehicles table!
        $vehicleId = substr($id, 2);
        $vRows = Supabase::select(TBL_VEHICLES, ['id' => 'eq.' . $vehicleId, 'select' => '*'], $token);
        $v = $vRows[0] ?? null;
        if ($v) {
            $customer = [
                'id' => $id,
                'name' => $v['customer_name'] ?? 'Walk-in Customer',
                'phone' => $v['customer_phone'] ?? '-',
                'email' => '-',
                'address' => '-'
            ];
            // Find all vehicles owned by this phone number
            $vehicles = Supabase::select(TBL_VEHICLES, ['customer_phone' => 'eq.' . $v['customer_phone'], 'select' => '*'], $token);
        }
    } else {
        // Standard customer record
        $rows = Supabase::select(TBL_CUSTOMERS, ['id' => 'eq.' . $id, 'select' => '*'], $token);
        $customer = $rows[0] ?? null;
        if ($customer) {
            $vehicles = Supabase::select(TBL_VEHICLES, ['customer_id' => 'eq.' . $id, 'select' => '*'], $token);
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = $customer['name'] ?? 'Customer';
$pageSubtitle = 'Customer profile and vehicle history';
include __DIR__ . '/partials/header.php';
?>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$customer): ?>
  <div class="empty-state card">Customer not found.</div>
<?php else: ?>
  <div class="card" style="margin-bottom:24px;">
    <p><strong>Phone:</strong> <?= htmlspecialchars($customer['phone'] ?? '-') ?></p>
  </div>

  <div class="table-card">
    <div class="table-toolbar"><strong>Vehicles</strong></div>
    <table class="data-table">
      <thead><tr><th>Plate Number</th><th>Make / Model</th><th>Year</th><th>Colour</th><th>Mileage</th></tr></thead>
      <tbody>
        <?php if (empty($vehicles)): ?>
          <tr><td colspan="5" class="empty-state">No vehicles registered for this customer.</td></tr>
        <?php else: foreach ($vehicles as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v['plate_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?></td>
            <td><?= htmlspecialchars($v['year'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['colour'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['mileage'] ?? '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<p style="margin-top:20px;"><a href="customers.php" class="btn btn-outline btn-sm">← Back to Customers</a></p>

<?php include __DIR__ . '/partials/footer.php'; ?>
