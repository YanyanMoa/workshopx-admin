<?php
/**
 * Targeted script to set up perfect demo data for capstone presentation.
 * This distributes the orders realistically across multiple months and statuses
 * so the Live Queue is filled with different statuses (Pending, In Progress, Awaiting Parts, Completed, Paid, Cancelled).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

$token = null;

// Fetch all service orders
$orders = Supabase::select('service_orders', ['select' => 'id', 'order' => 'created_at.asc'], $token);

if (empty($orders)) {
    echo "<p>No orders found.</p>";
    exit;
}

// 16 orders total. Let's create a perfect status distribution:
// July 2026 (Live Queue demo month)
// - 3 Paid (Revenue)
// - 2 Completed
// - 2 In Progress
// - 2 Pending
// - 1 Awaiting Parts
// - 1 Cancelled
//
// June 2026
// - 1 Paid (Revenue)
// - 1 Awaiting Parts
// - 1 Completed
//
// May 2026
// - 1 Paid (Revenue)
//
// April 2026
// - 1 Paid (Revenue)

$demoPlan = [
    // July 2026 (Current active queue demo)
    ['date' => '2026-07-28T09:30:00Z', 'status' => 'Pending'],
    ['date' => '2026-07-28T10:15:00Z', 'status' => 'In Progress'],
    ['date' => '2026-07-27T14:45:00Z', 'status' => 'Awaiting Parts'],
    ['date' => '2026-07-27T11:00:00Z', 'status' => 'Pending'],
    ['date' => '2026-07-26T16:20:00Z', 'status' => 'In Progress'],
    ['date' => '2026-07-26T09:00:00Z', 'status' => 'Completed'],
    ['date' => '2026-07-25T13:30:00Z', 'status' => 'Completed'],
    ['date' => '2026-07-24T15:10:00Z', 'status' => 'Cancelled'],
    ['date' => '2026-07-22T10:00:00Z', 'status' => 'Paid'],
    ['date' => '2026-07-18T14:00:00Z', 'status' => 'Paid'],
    ['date' => '2026-07-15T09:30:00Z', 'status' => 'Paid'],

    // June 2026
    ['date' => '2026-06-20T11:30:00Z', 'status' => 'Paid'],
    ['date' => '2026-06-12T14:20:00Z', 'status' => 'Completed'],
    ['date' => '2026-06-05T10:10:00Z', 'status' => 'Awaiting Parts'],

    // May 2026
    ['date' => '2026-05-15T15:00:00Z', 'status' => 'Paid'],

    // April 2026
    ['date' => '2026-04-10T10:20:00Z', 'status' => 'Paid'],
];

$total = count($orders);
$updated = 0;
$errors  = 0;
$log     = [];

foreach ($orders as $i => $o) {
    // Pick from plan, or loop back if more orders than plan
    $plan = $demoPlan[$i % count($demoPlan)];

    try {
        Supabase::update('service_orders', [
            'created_at' => $plan['date'],
            'status' => $plan['status']
        ], ['id' => 'eq.' . $o['id']], $token);
        
        $log[] = "✅ Order #{$o['id']} → {$plan['date']} ({$plan['status']})";
        $updated++;
    } catch (Exception $e) {
        $log[] = "❌ Order #{$o['id']} → FAILED: " . $e->getMessage();
        $errors++;
    }
}
?>
<!DOCTYPE html>
<html><head><title>Setup Demo Data</title>
<style>body{font-family:sans-serif;padding:30px;background:#f4f7f7;}
.box{background:#fff;border-radius:12px;padding:24px;max-width:700px;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
h2{color:#1a6b6b;} .ok{color:#16a34a;} .err{color:#b91c1c;}
pre{background:#f8fafc;padding:16px;border-radius:8px;font-size:13px;max-height:400px;overflow:auto;}
.btn{display:inline-block;margin-top:16px;padding:10px 20px;background:#1a6b6b;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
</style></head><body>
<div class="box">
  <h2>🎯 Demo Data Setup Complete</h2>
  <p>Processed <strong><?= $total ?></strong> orders — 
     <span class="ok"><?= $updated ?> updated</span>, 
     <span class="err"><?= $errors ?> failed</span>.</p>
  <pre><?= implode("\n", $log) ?></pre>
  <a href="reports.php" class="btn">→ Go to Reports</a>
</div>
</body></html>
