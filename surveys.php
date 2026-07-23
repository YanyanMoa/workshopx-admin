<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$notice = '';
$error = '';
$tableMissing = false;

// Handle Survey form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_survey') {
            Supabase::insert(TBL_SURVEYS, [
                'equipment_name' => trim($_POST['equipment_name']),
                'condition'      => trim($_POST['condition']),
                'surveyed_by'    => trim($_POST['surveyed_by']),
            ], $token);
            $notice = 'Equipment survey recorded successfully.';
        } elseif ($_POST['action'] === 'delete_survey') {
            Supabase::delete(TBL_SURVEYS, [
                'id' => 'eq.' . $_POST['survey_id']
            ], $token);
            $notice = 'Equipment survey deleted successfully.';
        } elseif ($_POST['action'] === 'update_condition') {
            Supabase::update(TBL_SURVEYS, [
                'condition' => $_POST['condition']
            ], ['id' => 'eq.' . $_POST['survey_id']], $token);
            $notice = 'Equipment condition updated successfully.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $surveys = Supabase::select(TBL_SURVEYS, ['select' => '*', 'order' => 'created_at.desc'], $token);
} catch (Exception $e) {
    $surveys = [];
    $error = $error ?: $e->getMessage();
    $tableMissing = true;
}

$pageTitle = 'Equipment Surveys';
$pageSubtitle = 'Conduct surveys and reports on workshop equipment';
include __DIR__ . '/partials/header.php';
?>

<?php if ($notice): ?><div class="error-msg" style="background:#e2f7ec;color:#1f7a4d;"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error && !$tableMissing): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($tableMissing): ?>
  <div class="card" style="background:#fff8e6; border-left:4px solid #f2a93c; margin-bottom:20px;">
    <strong>This module isn't connected to a database table yet.</strong>
    <p style="margin:8px 0 0; color:var(--text-muted); font-size:14px;">
      Your Supabase project doesn't currently have an <code>equipment_surveys</code> table.
      This page will work as soon as you create one (e.g. with columns like
      <code>equipment_name</code>, <code>condition</code>, <code>surveyed_by</code>, <code>created_at</code>) —
      no other code changes needed. If you'd rather skip this feature for now, just remove its link
      from <code>partials/sidebar.php</code>.
    </p>
  </div>
<?php endif; ?>

<div class="table-card">
  <div class="table-toolbar">
    <input type="text" id="searchSurveys" placeholder="🔍 Search surveys...">
    <?php if (!$tableMissing): ?>
      <button class="btn btn-primary" onclick="openModal('addSurveyModal')">+ Conduct Survey</button>
    <?php endif; ?>
  </div>
  <table class="data-table" id="surveysTable">
    <thead>
      <tr>
        <th>Equipment</th>
        <th>Condition</th>
        <th>Surveyed By</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($surveys)): ?>
        <tr><td colspan="5" class="empty-state">No equipment surveys recorded yet.</td></tr>
      <?php else: foreach ($surveys as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['equipment_name'] ?? '-') ?></td>
          <td>
            <span class="badge-status" style="padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; text-transform:uppercase; 
              <?php
                $cond = strtolower($s['condition'] ?? '');
                if ($cond === 'excellent' || $cond === 'good') {
                    echo 'background:#e2f7ec; color:#1f7a4d;';
                } elseif ($cond === 'needs repair' || $cond === 'fair') {
                    echo 'background:#fff3cd; color:#856404;';
                } else {
                    echo 'background:#f8d7da; color:#721c24;';
                }
              ?>">
              <?= htmlspecialchars($s['condition'] ?? '-') ?>
            </span>
          </td>
          <td><?= htmlspecialchars($s['surveyed_by'] ?? '-') ?></td>
          <td><?= htmlspecialchars(isset($s['created_at']) ? date('d M Y', strtotime($s['created_at'])) : '-') ?></td>
          <td>
            <div style="display:flex; gap:10px; align-items:center;">
              <!-- Edit/Update Form -->
              <form method="POST" action="surveys.php" style="display:flex; gap:4px; margin:0;">
                <input type="hidden" name="action" value="update_condition">
                <input type="hidden" name="survey_id" value="<?= htmlspecialchars($s['id'] ?? '') ?>">
                <select name="condition" style="padding:4px 6px; border-radius:6px; border:1px solid #e1e8e8; font-size:12px; background:white;">
                  <?php
                    $conditions = ['Excellent', 'Good', 'Fair', 'Needs Repair', 'Out of Service'];
                    foreach ($conditions as $cOpt):
                  ?>
                    <option value="<?= $cOpt ?>" <?= ($s['condition'] ?? '') === $cOpt ? 'selected' : '' ?>><?= htmlspecialchars($cOpt) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;">Save</button>
              </form>

              <!-- Delete Form -->
              <form method="POST" action="surveys.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this survey?');">
                <input type="hidden" name="action" value="delete_survey">
                <input type="hidden" name="survey_id" value="<?= htmlspecialchars($s['id'] ?? '') ?>">
                <button type="submit" style="padding:4px 10px; font-size:11px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:6px; cursor:pointer; font-weight:600;">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Survey Modal -->
<div class="modal-overlay" id="addSurveyModal">
  <div class="modal-box">
    <h3>Conduct New Equipment Survey</h3>
    <form method="POST" action="surveys.php">
      <input type="hidden" name="action" value="add_survey">
      
      <div class="form-group">
        <label>Equipment Name</label>
        <input type="text" name="equipment_name" placeholder="e.g. Two-Post Car Lift, Air Compressor" required>
      </div>
      
      <div class="form-group">
        <label>Condition</label>
        <select name="condition" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-main); color:var(--text-main);">
          <option value="Excellent">Excellent</option>
          <option value="Good">Good</option>
          <option value="Fair">Fair</option>
          <option value="Needs Repair">Needs Repair</option>
          <option value="Out of Service">Out of Service</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Surveyed By</label>
        <input type="text" name="surveyed_by" value="<?= htmlspecialchars($_SESSION['sb_user']['name'] ?? '') ?>" required>
      </div>
      
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('addSurveyModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Survey</button>
      </div>
    </form>
  </div>
</div>

<script>filterTable('searchSurveys', 'surveysTable');</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
