<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_role(['admin']); // only admins can manage users

$token = current_token();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_role') {
    try {
        Supabase::update(TBL_PROFILES, ['role' => $_POST['role']], ['id' => 'eq.' . $_POST['user_id']], $token);
        $notice = 'Role updated successfully.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $users = Supabase::select(TBL_PROFILES, ['select' => '*', 'order' => 'created_at.desc'], $token);
} catch (Exception $e) {
    $users = [];
    $error = $error ?: $e->getMessage();
}

$roles = ['admin', 'staff', 'mechanic'];

$pageTitle = 'User & Role Management';
$pageSubtitle = 'Manage staff accounts and role assignments';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px; background:#fff8e6; border-left:4px solid #f2a93c;">
  <strong>Note:</strong> New staff/mechanic accounts should be created via Supabase Auth
  (Dashboard &gt; Authentication &gt; Add User, or your FlutterFlow sign-up flow).
  This page manages role assignment for existing accounts.
</div>

<div class="table-card">
  <div class="table-toolbar"><input type="text" id="searchUsers" placeholder="🔍 Search users..."></div>
  <table class="data-table" id="usersTable">
    <thead><tr><th>Full Name</th><th>Phone</th><th>Current Role</th><th>Change Role</th></tr></thead>
    <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="4" class="empty-state">No users found.</td></tr>
      <?php else: foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['full_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
          <td><span class="badge badge-progress"><?= htmlspecialchars(ucfirst($u['role'] ?? 'staff')) ?></span></td>
          <td>
            <form method="POST" action="users.php" style="display:flex; gap:6px;">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id'] ?? '') ?>">
              <select name="role" style="padding:6px;border-radius:6px;border:1px solid #e1e8e8;">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r ?>" <?= ($u['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>filterTable('searchUsers', 'usersTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
