<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

function pass(string $msg): void { echo "  [PASS] $msg\n"; }
function fail(string $msg): void { echo "  [FAIL] $msg\n"; }

// Check subscriptions table has flutterwave columns
$cols = collect(DB::select("SHOW COLUMNS FROM subscriptions"))->pluck('Field')->toArray();
$flwFields = ['flutterwave_reference', 'flutterwave_transaction_id', 'flutterwave_response', 'payment_gateway'];
foreach ($flwFields as $field) {
    if (in_array($field, $cols)) {
        pass("subscriptions.$field exists");
    } else {
        fail("subscriptions.$field MISSING");
    }
}

// Check admin_users has preferred_payment_gateway
$adminCols = collect(DB::select("SHOW COLUMNS FROM admin_users"))->pluck('Field')->toArray();
if (in_array('preferred_payment_gateway', $adminCols)) {
    pass("admin_users.preferred_payment_gateway exists");
} else {
    fail("admin_users.preferred_payment_gateway MISSING");
}

echo "\nDB schema check done.\n";
