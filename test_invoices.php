<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Supabase.php';

try {
    // Select everything from the invoices table
    $invoices = Supabase::select(TBL_INVOICES, []);
    echo "Invoices table count: " . count($invoices) . "\n";
    print_r($invoices);

    // Select everything from the service_orders table to see what data is there
    $orders = Supabase::select(TBL_SERVICE_ORDERS, []);
    echo "Service orders table count: " . count($orders) . "\n";
    print_r($orders);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
