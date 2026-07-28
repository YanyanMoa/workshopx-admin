<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Supabase.php';
require_login();

$token = current_token();
$error = '';

// ── Filter inputs ──────────────────────────────────────────────
$viewType = $_GET['view'] ?? 'monthly';          // 'monthly' or 'yearly'
$selYear  = (int)($_GET['year']  ?? date('Y'));
$selMonth = (int)($_GET['month'] ?? date('n'));

if ($viewType === 'yearly') {
    $dateFrom = sprintf('%04d-01-01', $selYear);
    $dateTo   = sprintf('%04d-01-01', $selYear + 1);
    $periodLabel = "Year $selYear";
} else {
    $dateFrom = sprintf('%04d-%02d-01', $selYear, $selMonth);
    $dateTo   = date('Y-m-d', strtotime('+1 month', strtotime($dateFrom)));
    $periodLabel = date('F Y', strtotime($dateFrom));
}

// ── Fetch data ─────────────────────────────────────────────────
try {
    // All service orders in selected period
    $allOrders = Supabase::select(TBL_SERVICE_ORDERS, [
        'select'     => '*,vehicles(plate_no,make,model,customers(name))',
        'created_at' => 'gte.' . $dateFrom . 'T00:00:00',
        'order'      => 'created_at.asc',
    ], $token);

    // Filter to only orders before dateTo
    $orders = array_filter($allOrders, function($o) use ($dateTo) {
        return isset($o['created_at']) && $o['created_at'] < $dateTo . 'T00:00:00';
    });
    $orders = array_values($orders);

    // Spare parts map
    $partsList = Supabase::select(TBL_SPARE_PARTS, [], $token);
    $partsMap = [];
    foreach ($partsList as $p) { $partsMap[$p['id']] = $p; }

    // Profiles map
    $profilesList = Supabase::select(TBL_PROFILES, [], $token);
    $profilesMap = [];
    foreach ($profilesList as $p) { $profilesMap[$p['id']] = $p['full_name'] ?? 'Unknown'; }

    // Inventory
    $inventory = Supabase::select(TBL_SPARE_PARTS, ['select' => '*', 'order' => 'stock.asc'], $token);

} catch (Exception $e) {
    $orders = []; $partsMap = []; $profilesMap = []; $inventory = [];
    $error = $e->getMessage();
}

// ── Compute stats ──────────────────────────────────────────────
$totalRevenue  = 0;
$totalOrders   = count($orders);
$mechanicStats = [];
$statusCounts  = ['Pending'=>0,'In Progress'=>0,'Completed'=>0,'Paid'=>0,'Cancelled'=>0];

// For chart: group revenue by day (monthly) or month (yearly)
$revenueByPeriod = [];

foreach ($orders as $o) {
    $labour    = (float)($o['labour_cost'] ?? 0);
    $partsCost = 0;
    if (!empty($o['parts_used']) && is_array($o['parts_used'])) {
        foreach ($o['parts_used'] as $pid) {
            if (isset($partsMap[$pid])) $partsCost += (float)$partsMap[$pid]['price'];
        }
    }
    $amount  = $labour + $partsCost;
    $isPaid  = strtolower($o['status'] ?? '') === 'paid';
    $statusKey = ucfirst(strtolower($o['status'] ?? 'pending'));
    if (isset($statusCounts[$statusKey])) $statusCounts[$statusKey]++;

    if ($isPaid) {
        $totalRevenue += $amount;
        // Chart grouping
        $dt = $o['created_at'] ?? '';
        $key = $viewType === 'yearly'
            ? date('M', strtotime($dt))           // Jan, Feb ...
            : date('d M', strtotime($dt));         // 01 Jul, 02 Jul ...
        $revenueByPeriod[$key] = ($revenueByPeriod[$key] ?? 0) + $amount;
    }

    // Mechanic stats
    $mid = $o['mechanic_id'] ?? null;
    if ($mid) {
        if (!isset($mechanicStats[$mid])) {
            $mechanicStats[$mid] = ['name' => $profilesMap[$mid] ?? 'Unknown', 'orders' => 0, 'revenue' => 0];
        }
        $mechanicStats[$mid]['orders']++;
        if ($isPaid) $mechanicStats[$mid]['revenue'] += $amount;
    }
}

usort($mechanicStats, fn($a,$b) => $b['orders'] <=> $a['orders']);
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / max(1, array_sum(array_column(
    array_filter($orders, fn($o) => strtolower($o['status']??'') === 'paid'), 'id'
)) ?: $totalOrders) : 0;
$paidCount = $statusCounts['Paid'];
$avgOrderValue = $paidCount > 0 ? $totalRevenue / $paidCount : 0;
$topMechanic = !empty($mechanicStats) ? $mechanicStats[0]['name'] : '-';

// Low stock parts
$lowStockParts = array_filter($inventory, fn($p) => (int)($p['stock'] ?? 0) < 5);

// Chart data
$chartLabels  = json_encode(array_keys($revenueByPeriod));
$chartData    = json_encode(array_values($revenueByPeriod));
$statusLabels = json_encode(array_keys($statusCounts));
$statusData   = json_encode(array_values($statusCounts));

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="workshopx-report-' . $periodLabel . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order ID', 'Customer', 'Vehicle', 'Mechanic', 'Labour Cost', 'Parts Cost', 'Total', 'Status', 'Date']);
    foreach ($orders as $o) {
        $vehicle = $o['vehicles'] ?? null;
        $labour  = (float)($o['labour_cost'] ?? 0);
        $pc = 0;
        if (!empty($o['parts_used']) && is_array($o['parts_used'])) {
            foreach ($o['parts_used'] as $pid) {
                if (isset($partsMap[$pid])) $pc += (float)$partsMap[$pid]['price'];
            }
        }
        fputcsv($out, [
            substr($o['id'],0,8),
            $vehicle['customers']['name'] ?? ($vehicle['customer_name'] ?? 'Walk-in'),
            ($vehicle['make']??'') . ' ' . ($vehicle['model']??'') . ' [' . ($vehicle['plate_no']??'-') . ']',
            $profilesMap[$o['mechanic_id']??''] ?? '-',
            number_format($labour,2),
            number_format($pc,2),
            number_format($labour+$pc,2),
            $o['status'] ?? '-',
            isset($o['created_at']) ? date('d M Y', strtotime($o['created_at'])) : '-',
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle    = 'Reports & Analytics';
$pageSubtitle = 'Generate and view key performance insights';
include __DIR__ . '/partials/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.report-filter-bar {
  background:#fff;
  border:1px solid var(--border);
  border-radius:12px;
  padding:18px 24px;
  display:flex;
  align-items:center;
  gap:14px;
  flex-wrap:wrap;
  margin-bottom:24px;
  box-shadow:var(--shadow);
}
.report-filter-bar label { font-size:13px; font-weight:600; color:var(--text-muted); }
.report-filter-bar select,
.report-filter-bar input[type=number] {
  padding:8px 12px;
  border-radius:8px;
  border:1px solid #cdd8d8;
  font-size:14px;
  background:#f8fafc;
}
.report-filter-bar .filter-group { display:flex; align-items:center; gap:8px; }
.chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
.chart-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:24px; box-shadow:var(--shadow); }
.chart-card h3 { margin:0 0 16px; font-size:15px; color:var(--teal-dark); }
.report-table-card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:24px; box-shadow:var(--shadow); margin-bottom:20px; }
.report-table-card h3 { margin:0 0 16px; font-size:15px; color:var(--teal-dark); }
.report-table-card table { width:100%; border-collapse:collapse; font-size:14px; }
.report-table-card th { background:#f4f7f7; padding:10px 14px; text-align:left; font-size:11px; text-transform:uppercase; color:var(--text-muted); font-weight:700; }
.report-table-card td { padding:12px 14px; border-bottom:1px solid #f0f4f4; }
.report-table-card tr:last-child td { border-bottom:none; }
@media(max-width:700px){ .chart-grid{ grid-template-columns:1fr; } }
</style>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── Filter Bar ── -->
<form method="GET" action="reports.php" class="report-filter-bar">
  <div class="filter-group">
    <label>View By</label>
    <select name="view" onchange="
      var f=this.form;
      // carry current year+month hidden before submit
      ['year','month'].forEach(function(n){
        if(!f.querySelector('[name='+n+']')){
          var h=document.createElement('input');h.type='hidden';h.name=n;
          h.value=n==='year'?document.getElementById('selYear').value:document.getElementById('selMonth').value;
          f.appendChild(h);
        }
      });
      f.submit();
    ">
      <option value="monthly" <?= $viewType==='monthly'?'selected':'' ?>>📅 Monthly</option>
      <option value="yearly"  <?= $viewType==='yearly' ?'selected':'' ?>>📆 Yearly</option>
    </select>
  </div>

  <?php if ($viewType === 'monthly'): ?>
  <div class="filter-group">
    <label>Month</label>
    <select name="month" id="selMonth">
      <?php for ($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $m===$selMonth?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <?php endif; ?>

  <div class="filter-group">
    <label>Year</label>
    <select name="year" id="selYear">
      <?php for ($y=2026;$y>=2025;$y--): ?>
        <option value="<?= $y ?>" <?= $y===$selYear?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">Generate Report</button>
  <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-outline" style="border-color:#16a34a;color:#16a34a;">⬇ Export CSV</a>
  <span style="margin-left:auto;font-size:13px;color:var(--text-muted);">Showing: <strong><?= htmlspecialchars($periodLabel) ?></strong></span>
</form>

<!-- ── Summary Cards ── -->
<div class="summary-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:24px;">
  <div class="summary-card">
    <div class="label">Total Revenue (Paid)</div>
    <div class="value">RM <?= number_format($totalRevenue, 2) ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Total Orders</div>
    <div class="value"><?= $totalOrders ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Avg Order Value</div>
    <div class="value">RM <?= number_format($avgOrderValue, 2) ?></div>
  </div>
  <div class="summary-card">
    <div class="label">Top Mechanic</div>
    <div class="value" style="font-size:18px;"><?= htmlspecialchars($topMechanic) ?></div>
  </div>
</div>

<!-- ── Charts ── -->
<div class="chart-grid">
  <div class="chart-card">
    <h3>💰 Revenue (<?= htmlspecialchars($periodLabel) ?>)</h3>
    <?php if (empty($revenueByPeriod)): ?>
      <p style="color:var(--text-muted);font-size:13px;">No paid orders in this period.</p>
    <?php else: ?>
      <canvas id="revenueChart" height="160"></canvas>
    <?php endif; ?>
  </div>
  <div class="chart-card">
    <h3>📋 Orders by Status</h3>
    <?php if ($totalOrders === 0): ?>
      <p style="color:var(--text-muted);font-size:13px;">No orders in this period.</p>
    <?php else: ?>
      <canvas id="statusChart" height="160"></canvas>
    <?php endif; ?>
  </div>
</div>

<!-- ── Top Mechanics Table ── -->
<div class="report-table-card">
  <h3>🏆 Mechanic Performance</h3>
  <?php if (empty($mechanicStats)): ?>
    <p style="color:var(--text-muted);font-size:13px;">No mechanic data for this period.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>#</th><th>Mechanic Name</th><th>Orders Handled</th><th>Revenue Generated (RM)</th></tr></thead>
    <tbody>
      <?php foreach ($mechanicStats as $i => $m): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
        <td><?= $m['orders'] ?></td>
        <td>RM <?= number_format($m['revenue'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Order Breakdown Table ── -->
<div class="report-table-card">
  <h3>📄 Order Breakdown — <?= htmlspecialchars($periodLabel) ?></h3>
  <?php if (empty($orders)): ?>
    <p style="color:var(--text-muted);font-size:13px;">No orders found for this period.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Order ID</th><th>Customer</th><th>Vehicle</th><th>Mechanic</th><th>Labour</th><th>Parts</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o):
        $vehicle  = $o['vehicles'] ?? null;
        $custName = $vehicle['customers']['name'] ?? ($vehicle['customer_name'] ?? 'Walk-in');
        $vLabel   = $vehicle ? trim(($vehicle['make']??'').(' '.($vehicle['model']??''))) . ' [' . ($vehicle['plate_no']??'-') . ']' : '-';
        $labour   = (float)($o['labour_cost'] ?? 0);
        $pc = 0;
        if (!empty($o['parts_used']) && is_array($o['parts_used'])) {
            foreach ($o['parts_used'] as $pid) {
                if (isset($partsMap[$pid])) $pc += (float)$partsMap[$pid]['price'];
            }
        }
        $status   = $o['status'] ?? '-';
        $isPaid   = strtolower($status) === 'paid';
      ?>
      <tr>
        <td>#<?= htmlspecialchars(substr($o['id'],0,8)) ?></td>
        <td><?= htmlspecialchars($custName) ?></td>
        <td><?= htmlspecialchars($vLabel) ?></td>
        <td><?= htmlspecialchars($profilesMap[$o['mechanic_id']??''] ?? '-') ?></td>
        <td>RM <?= number_format($labour,2) ?></td>
        <td>RM <?= number_format($pc,2) ?></td>
        <td><strong>RM <?= number_format($labour+$pc,2) ?></strong></td>
        <td><span class="badge badge-<?= $isPaid?'paid':'progress' ?>"><?= htmlspecialchars($status) ?></span></td>
        <td><?= isset($o['created_at']) ? date('d M Y', strtotime($o['created_at'])) : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Chart.js Scripts ── -->
<script>
<?php if (!empty($revenueByPeriod)): ?>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= $chartLabels ?>,
    datasets: [{
      label: 'Revenue (RM)',
      data: <?= $chartData ?>,
      backgroundColor: 'rgba(26,107,107,0.75)',
      borderColor: '#1a6b6b',
      borderWidth: 1,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { callback: v => 'RM '+v } }
    }
  }
});
<?php endif; ?>

<?php if ($totalOrders > 0): ?>
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= $statusLabels ?>,
    datasets: [{
      data: <?= $statusData ?>,
      backgroundColor: ['#fbbf24','#60a5fa','#34d399','#1a6b6b','#f87171'],
      borderWidth: 2,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } }
  }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
