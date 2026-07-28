<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    try {
        Supabase::insert(TBL_CUSTOMERS, [
            'name'  => trim($_POST['name']),
            'phone' => trim($_POST['phone']),
        ], $token);
        $notice = 'Customer added successfully.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Delete Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    try {
        $delId = trim($_POST['customer_id']);
        Supabase::delete(TBL_CUSTOMERS, ['id' => 'eq.' . $delId], $token);
        $notice = 'Customer deleted successfully.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $dbCustomers = Supabase::select(TBL_CUSTOMERS, ['select' => '*', 'order' => 'created_at.desc'], $token);
} catch (Exception $e) {
    $dbCustomers = [];
    $error = $error ?: $e->getMessage();
}

try {
    $vehicles = Supabase::select(TBL_VEHICLES, [], $token);
} catch (Exception $e) {
    $vehicles = [];
}

// Build a merged list of unique customers grouped by phone number
$customersMap = [];
foreach ($dbCustomers as $c) {
    if (!empty($c['phone'])) {
        $customersMap[trim($c['phone'])] = [
            'id'         => $c['id'],
            'name'       => $c['name'],
            'phone'      => $c['phone'],
            'created_at' => $c['created_at'] ?? null,
        ];
    }
}

foreach ($vehicles as $v) {
    if (!empty($v['customer_name']) && !empty($v['customer_phone'])) {
        $phone = trim($v['customer_phone']);
        if (!isset($customersMap[$phone])) {
            $customersMap[$phone] = [
                'id'         => 'v-' . $v['id'],
                'name'       => $v['customer_name'],
                'phone'      => $v['customer_phone'],
                'created_at' => $v['created_at'] ?? null,
            ];
        }
    }
}

// Convert map to simple list
$customers = array_values($customersMap);

// Sort by created_at desc
usort($customers, function($a, $b) {
    $t1 = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $t2 = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $t2 - $t1;
});

$pageTitle = 'Customers & Vehicles';
$pageSubtitle = 'Manage customer profiles and vehicle history';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-card">
  <div class="table-toolbar">
    <input type="text" id="searchCustomers" placeholder="🔍 Search by name, phone...">
    <button class="btn btn-primary" onclick="openModal('addCustomerModal')">+ Add Customer</button>
  </div>
  <table class="data-table" id="customersTable">
    <thead>
      <tr><th>Name</th><th>Phone</th><th>Registered</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if (empty($customers)): ?>
        <tr><td colspan="4" class="empty-state">No customers found. Add your first customer above.</td></tr>
      <?php else: foreach ($customers as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
          <td><?= htmlspecialchars(isset($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '-') ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="customer_view.php?id=<?= urlencode($c['id'] ?? '') ?>" class="btn btn-outline btn-sm">View</a>
            <?php if (!str_starts_with($c['id'], 'v-')): ?>
            <form method="POST" action="customers.php" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['name'])) ?>? This cannot be undone.');" style="display:inline;">
              <input type="hidden" name="action" value="delete_customer">
              <input type="hidden" name="customer_id" value="<?= htmlspecialchars($c['id']) ?>">
              <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#b91c1c;border:none;cursor:pointer;">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Customer Modal -->
<div class="modal-overlay" id="addCustomerModal">
  <div class="modal-box">
    <h3>Add New Customer</h3>
    <form method="POST" action="customers.php">
      <input type="hidden" name="action" value="add_customer">
      <div class="form-group"><label>Full Name</label><input type="text" name="name" required placeholder="e.g. Ahmad Razak"></div>
      <div class="form-group"><label>Phone Number</label><input type="text" name="phone" required placeholder="e.g. 012-3456789"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('addCustomerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Customer</button>
      </div>
    </form>
  </div>
</div>

<script>filterTable('searchCustomers', 'customersTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
