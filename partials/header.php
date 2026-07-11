<?php
/**
 * Usage: set $pageTitle and $pageSubtitle before including this file.
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · WorkshopX Admin</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js"></script>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div>
      <h1><?= htmlspecialchars($pageTitle) ?></h1>
      <?php if ($pageSubtitle): ?><div class="subtitle"><?= htmlspecialchars($pageSubtitle) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="content">
