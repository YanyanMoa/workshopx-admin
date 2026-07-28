<?php
/**
 * Diagnostic: check current created_at dates in service_orders
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

$orders = Supabase::select('service_orders', ['select' => 'id,created_at,status', 'order' => 'created_at.asc'], null);

echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#1a6b6b;color:#fff;}</style>";
echo "<h2>Service Orders — Current Dates (" . count($orders) . " records)</h2>";
echo "<table><tr><th>ID</th><th>Created At</th><th>Status</th></tr>";
foreach ($orders as $o) {
    echo "<tr><td>" . substr($o['id'],0,8) . "</td><td>" . ($o['created_at'] ?? 'NULL') . "</td><td>" . ($o['status'] ?? '-') . "</td></tr>";
}
echo "</table>";
echo "<br><a href='redistribute_dates.php'>▶ Run Redistribute Script</a> | <a href='reports.php'>→ Reports</a>";
