<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';

// Handle Add Customer + Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    try {
        // Step 1: Insert customer
        $newCustomer = Supabase::insert(TBL_CUSTOMERS, [
            'name'  => trim($_POST['name']),
            'phone' => trim($_POST['phone']),
        ], $token);

        // Step 2: Insert vehicle linked to the new customer
        $customerId = $newCustomer[0]['id'] ?? null;
        if ($customerId && !empty(trim($_POST['plate_no']))) {
            $dropdownLabel = trim($_POST['plate_no']) . ' - ' . trim($_POST['make']) . ' ' . trim($_POST['model']);
            Supabase::insert(TBL_VEHICLES, [
                'customer_id'    => $customerId,
                'plate_no'       => strtoupper(trim($_POST['plate_no'])),
                'make'           => trim($_POST['make']),
                'model'          => trim($_POST['model']),
                'year'           => (int) $_POST['year'],
                'colour'         => trim($_POST['colour']),
                'mileage'        => (int) $_POST['mileage'],
                'dropdown_label' => $dropdownLabel,
            ], $token);
        }

        $notice = 'Customer and vehicle registered successfully.';
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

$customers = array_values($customersMap);
usort($customers, function($a, $b) {
    $t1 = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $t2 = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $t2 - $t1;
});

$pageTitle    = 'Customers & Vehicles';
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

<!-- Add Customer + Vehicle Modal -->
<div class="modal-overlay" id="addCustomerModal">
  <div class="modal-box" style="max-width:560px;">
    <h3>Register New Customer &amp; Vehicle</h3>
    <form method="POST" action="customers.php">
      <input type="hidden" name="action" value="add_customer">

      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">Customer Information</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group" style="margin:0;">
          <label>Full Name <span style="color:red">*</span></label>
          <input type="text" name="name" required placeholder="e.g. Ahmad Razak">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Phone Number <span style="color:red">*</span></label>
          <input type="text" name="phone" required placeholder="e.g. 012-3456789">
        </div>
      </div>

      <hr style="margin:20px 0;border-color:#eee;">
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">Vehicle Information <span style="font-size:11px;">(optional — leave Plate No blank to skip)</span></p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group" style="margin:0;">
          <label>Plate No</label>
          <input type="text" name="plate_no" placeholder="e.g. WXY 1234" style="text-transform:uppercase;">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Make (Brand)</label>
          <input type="text" name="make" placeholder="e.g. Perodua">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Model</label>
          <input type="text" name="model" placeholder="e.g. Myvi">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Year</label>
          <input type="number" name="year" placeholder="e.g. 2020" min="1990" max="2030">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Colour</label>
          <input type="text" name="colour" placeholder="e.g. Silver">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Current Mileage (km)</label>
          <input type="number" name="mileage" placeholder="e.g. 45000" min="0">
        </div>
      </div>

      <div class="modal-actions" style="margin-top:24px;">
        <button type="button" class="btn btn-outline" onclick="closeModal('addCustomerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Customer &amp; Vehicle</button>
      </div>
    </form>
  </div>
</div>

<script>filterTable('searchCustomers', 'customersTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
