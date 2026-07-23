<?php
/**
 * WorkshopX Admin - Configuration
 * -------------------------------
 * Fill in the values below with your actual Supabase project details.
 * Find these in your Supabase Dashboard > Project Settings > API
 */

// ---- SUPABASE CONNECTION (REQUIRED) ----
define('SUPABASE_URL', 'https://ihpwrboxokirlbxhfnbx.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlocHdyYm94b2tpcmxieGhmbmJ4Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODA3MjM0MDAsImV4cCI6MjA5NjI5OTQwMH0.pyJ-qTnA3XRo1Xuiz1aZqdPA6aBXydX0jQqlncJKZmE');    // Settings > API > anon public
// Service role key is optional and only needed for admin-level actions that
// bypass RLS (e.g. creating staff accounts). Keep this SECRET, never expose to browser JS.
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlocHdyYm94b2tpcmxieGhmbmJ4Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MDcyMzQwMCwiZXhwIjoyMDk2Mjk5NDAwfQ.WMxvnzvXMr1qxExPhiZUdR-hzAhiIxyltZBSOBY4f7o'); // Settings > API > service_role

// ---- TABLE NAMES ----
// Matched against your actual Supabase schema (Schema Visualizer, Jan 2026).
define('TBL_PROFILES',        'profiles');         // linked to auth.users.id — full_name, role, phone
define('TBL_CUSTOMERS',       'customers');
define('TBL_VEHICLES',        'vehicles');
define('TBL_SERVICE_ORDERS',  'service_orders');
define('TBL_SERVICE_ORDER_ITEMS', 'service_order_items'); // not yet wired into any page
define('TBL_REPAIR_PROGRESS', 'repair_progress');  // not yet wired into any page
define('TBL_INVENTORY',       'spare_parts');
define('TBL_SPARE_PARTS',     'spare_parts');       // maps directly to active spare_parts table
define('TBL_INVOICES',        'invoices');
define('TBL_SURVEYS',         'equipment_surveys'); // NOTE: this table does not exist yet in your schema

// ---- APP SETTINGS ----
define('APP_NAME', 'WorkshopX Admin');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

// ---- SESSION ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// ---- ERROR DISPLAY (turn off in production) ----
ini_set('display_errors', 1);
error_reporting(E_ALL);
