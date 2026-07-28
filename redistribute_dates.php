<?php
/**
 * One-time script: Redistribute service order dates across multiple months
 * Access once via browser, then delete or restrict access.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

$token = null; // uses service role key automatically

// Fetch all service orders
$orders = Supabase::select('service_orders', ['select' => 'id,created_at', 'order' => 'created_at.asc'], $token);

if (empty($orders)) {
    echo "<p>No orders found.</p>";
    exit;
}

// Define spread: assign each order a target month
// We'll cycle through these months
$monthSlots = [
    ['year'=>2025,'month'=>10,'days'=>[3,8,12,15,20,25]],
    ['year'=>2025,'month'=>11,'days'=>[2,7,10,14,18,22,28]],
    ['year'=>2025,'month'=>12,'days'=>[4,9,13,17,21,26]],
    ['year'=>2026,'month'=>1,'days'=>[5,10,14,18,22,27]],
    ['year'=>2026,'month'=>2,'days'=>[3,8,11,15,20,24]],
    ['year'=>2026,'month'=>3,'days'=>[4,9,13,17,22,28]],
    ['year'=>2026,'month'=>4,'days'=>[2,7,11,15,19,24]],
    ['year'=>2026,'month'=>5,'days'=>[5,9,14,18,23,27]],
    ['year'=>2026,'month'=>6,'days'=>[3,8,12,17,21,26]],
    ['year'=>2026,'month'=>7,'days'=>[5,10,14,18,23,27]],
];

// Flatten all date slots
$allDates = [];
foreach ($monthSlots as $slot) {
    foreach ($slot['days'] as $day) {
        $allDates[] = sprintf('%04d-%02d-%02dT%02d:%02d:%02dZ',
            $slot['year'], $slot['month'], $day,
            rand(8,17), rand(0,59), rand(0,59)
        );
    }
}

// Shuffle dates and assign to orders
shuffle($allDates);
$total = count($orders);
$updated = 0;
$errors  = 0;
$log     = [];

foreach ($orders as $i => $o) {
    // Pick a date slot (cycle if more orders than slots)
    $newDate = $allDates[$i % count($allDates)];

    try {
        Supabase::update('service_orders', ['created_at' => $newDate], ['id' => 'eq.' . $o['id']], $token);
        $log[] = "✅ Order #{$o['id']} → $newDate";
        $updated++;
    } catch (Exception $e) {
        $log[] = "❌ Order #{$o['id']} → FAILED: " . $e->getMessage();
        $errors++;
    }
}
?>
<!DOCTYPE html>
<html><head><title>Redistribute Dates</title>
<style>body{font-family:sans-serif;padding:30px;background:#f4f7f7;}
.box{background:#fff;border-radius:12px;padding:24px;max-width:700px;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
h2{color:#1a6b6b;} .ok{color:#16a34a;} .err{color:#b91c1c;}
pre{background:#f8fafc;padding:16px;border-radius:8px;font-size:13px;max-height:400px;overflow:auto;}
.btn{display:inline-block;margin-top:16px;padding:10px 20px;background:#1a6b6b;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
</style></head><body>
<div class="box">
  <h2>📅 Date Redistribution Complete</h2>
  <p>Processed <strong><?= $total ?></strong> orders — 
     <span class="ok"><?= $updated ?> updated</span>, 
     <span class="err"><?= $errors ?> failed</span>.</p>
  <pre><?= implode("\n", $log) ?></pre>
  <a href="reports.php" class="btn">→ Go to Reports</a>
</div>
</body></html>
