<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['sb_access_token'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
