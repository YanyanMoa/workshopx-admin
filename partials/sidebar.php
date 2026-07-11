<?php
// Determine current page for active-link highlighting
$__current = basename($_SERVER['PHP_SELF']);
function nav_active($file, $current) {
    return $file === $current ? 'active' : '';
}
$__user = current_user();
$__role = current_role();
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">Workshop<span>X</span> Admin</div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="<?= nav_active('dashboard.php', $__current) ?>">📊 Dashboard</a>
    <a href="customers.php" class="<?= nav_active('customers.php', $__current) ?>">👤 Customers &amp; Vehicles</a>
    <a href="service_orders.php" class="<?= nav_active('service_orders.php', $__current) ?>">🛠️ Service Orders</a>
    <a href="inventory.php" class="<?= nav_active('inventory.php', $__current) ?>">📦 Inventory</a>
    <a href="invoices.php" class="<?= nav_active('invoices.php', $__current) ?>">🧾 Invoices &amp; Payments</a>
    <a href="surveys.php" class="<?= nav_active('surveys.php', $__current) ?>">📋 Equipment Surveys</a>
    <?php if ($__role === 'admin'): ?>
    <a href="users.php" class="<?= nav_active('users.php', $__current) ?>">🧑‍🤝‍🧑 User &amp; Role Management</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-name"><?= htmlspecialchars($__user['email'] ?? 'User') ?></div>
    <span class="role-badge"><?= htmlspecialchars(ucfirst($__role)) ?></span>
    <a href="logout.php" class="logout">Log out →</a>
  </div>
</aside>
