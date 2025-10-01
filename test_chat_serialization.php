<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ChatHead;

$u = User::find(100);
$head = ChatHead::find(1000);

echo "Chat Head ID: {$head->id}\n";
echo "Type: {$head->type}\n";
echo "Product Owner ID: {$head->product_owner_id}\n";
echo "Customer ID: {$head->customer_id}\n\n";

echo "Checking appends...\n";
try {
    $count1 = $head->customer_unread_messages_count;
    echo "customer_unread_messages_count: $count1\n";
} catch (\Exception $e) {
    echo "ERROR getting customer_unread_messages_count: " . $e->getMessage() . "\n";
}

try {
    $count2 = $head->product_owner_unread_messages_count;
    echo "product_owner_unread_messages_count: $count2\n";
} catch (\Exception $e) {
    echo "ERROR getting product_owner_unread_messages_count: " . $e->getMessage() . "\n";
}

echo "\nTrying to convert to array...\n";
try {
    $arr = $head->toArray();
    echo "Success! Array keys: " . implode(', ', array_keys($arr)) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

