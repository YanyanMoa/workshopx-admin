<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_part') {
            Supabase::insert(TBL_INVENTORY, [
                'name'  => trim($_POST['part_name']),
                'stock' => (int) $_POST['quantity'],
                'price' => (float) $_POST['unit_price'],
            ], $token);
            $notice = 'Part added to inventory.';
        } elseif ($_POST['action'] === 'adjust_qty') {
            Supabase::update(TBL_INVENTORY, ['stock' => (int) $_POST['quantity']], ['id' => 'eq.' . $_POST['part_id']], $token);
            $notice = 'Stock quantity updated.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $parts = Supabase::select(TBL_INVENTORY, ['select' => '*', 'order' => 'name.asc'], $token);
} catch (Exception $e) {
    $parts = [];
    $error = $error ?: $e->getMessage();
}

$pageTitle = 'Inventory Management';
$pageSubtitle = 'Track and manage spare parts stock';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-card">
  <div class="table-toolbar">
    <input type="text" id="searchParts" placeholder="🔍 Search parts by name or code...">
    <button class="btn btn-primary" onclick="openModal('addPartModal')">+ Add Part</button>
  </div>
  <table class="data-table" id="partsTable">
    <thead><tr><th>Part Name</th><th>Part Code</th><th>Quantity</th><th>Unit Price (RM)</th><th>Stock Level</th><th>Adjust</th></tr></thead>
    <tbody>
      <?php if (empty($parts)): ?>
        <tr><td colspan="6" class="empty-state">No inventory items found.</td></tr>
      <?php else: foreach ($parts as $p):
        $qty = (int)($p['stock'] ?? 0);
        $threshold = 5;
        $low = $qty < $threshold;
      ?>
        <tr>
          <td><?= htmlspecialchars($p['name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($p['sku'] ?? '-') ?></td>
          <td><?= $qty ?></td>
          <td>RM <?= number_format((float)($p['price'] ?? 0), 2) ?></td>
          <td><span class="badge badge-<?= $low ? 'low' : 'ok' ?>"><?= $low ? 'Low Stock' : 'In Stock' ?></span></td>
          <td>
            <form method="POST" action="inventory.php" style="display:flex; gap:6px;">
              <input type="hidden" name="action" value="adjust_qty">
              <input type="hidden" name="part_id" value="<?= htmlspecialchars($p['id'] ?? '') ?>">
              <input type="number" name="quantity" value="<?= $qty ?>" style="width:70px;padding:6px;border-radius:6px;border:1px solid #e1e8e8;">
              <button type="submit" class="btn btn-outline btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="addPartModal">
  <div class="modal-box">
    <h3>Add Spare Part</h3>
    <form method="POST" action="inventory.php">
      <input type="hidden" name="action" value="add_part">
      <div class="form-group"><label>Part Name</label><input type="text" name="part_name" required></div>
      <div class="form-row">
        <div class="form-group"><label>Part Code</label><input type="text" name="part_code"></div>
        <div class="form-group"><label>Quantity</label><input type="number" name="quantity" value="0" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Unit Price (RM)</label><input type="number" step="0.01" name="unit_price" value="0.00" required></div>
        <div class="form-group"><label>Low Stock Alert Threshold</label><input type="number" name="low_stock_alert" value="5" required></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('addPartModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Part</button>
      </div>
    </form>
  </div>
</div>

<script>filterTable('searchParts', 'partsTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
