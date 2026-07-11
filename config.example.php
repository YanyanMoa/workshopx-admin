<?php
/**
 * WorkshopX Admin - Configuration (Example Template)
 * -------------------------------
 * Copy this file to 'config.php' and fill in your actual Supabase credentials.
 */

// ---- SUPABASE CONNECTION (REQUIRED) ----
define('SUPABASE_URL', 'YOUR_SUPABASE_PROJECT_URL');
define('SUPABASE_ANON_KEY', 'YOUR_SUPABASE_ANON_KEY');
define('SUPABASE_SERVICE_KEY', 'YOUR_SUPABASE_SERVICE_ROLE_KEY');

// ---- TABLE NAMES ----
define('TBL_PROFILES',        'profiles');
define('TBL_CUSTOMERS',       'customers');
define('TBL_VEHICLES',        'vehicles');
define('TBL_SERVICE_ORDERS',  'service_orders');
define('TBL_SERVICE_ORDER_ITEMS', 'service_order_items');
define('TBL_REPAIR_PROGRESS', 'repair_progress');
define('TBL_INVENTORY',       'spare_parts');
define('TBL_SPARE_PARTS',     'spare_parts');
define('TBL_INVOICES',        'invoices');
define('TBL_SURVEYS',         'equipment_surveys');

// ---- APP SETTINGS ----
define('APP_NAME', 'WorkshopX Admin');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

// ---- SESSION ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// ---- ERROR DISPLAY (turn off in production) ----
ini_set('display_errors', 0);
error_reporting(0);
