<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ChatHead;
use App\Models\ChatMessage;

echo "=== Direct API Logic Test ===\n\n";

$user_id = 100;
$u = User::find($user_id);

echo "User: {$u->name} (ID: {$u->id})\n";
echo "Table: " . $u->getTable() . "\n\n";

// Test the exact query from the API
echo "Running query...\n";
$chat_heads = ChatHead::where(function($query) use ($u) {
        $query->where('product_owner_id', $u->id)
              ->orWhere('customer_id', $u->id);
    })
    ->orderBy('updated_at', 'desc')
    ->get();

echo "Query result: " . $chat_heads->count() . " chat heads\n\n";

if ($chat_heads->count() == 0) {
    echo "❌ NO RESULTS! Let's debug...\n\n";
    
    // Check raw SQL
    $query = ChatHead::where(function($query) use ($u) {
            $query->where('product_owner_id', $u->id)
                  ->orWhere('customer_id', $u->id);
        });
    echo "SQL: " . $query->toSql() . "\n";
    echo "Bindings: " . json_encode($query->getBindings()) . "\n\n";
    
    // Check if ChatHead table has data
    $total = ChatHead::count();
    echo "Total chat_heads in table: $total\n";
    
    // Check if any match
    $as_owner = ChatHead::where('product_owner_id', $u->id)->count();
    $as_customer = ChatHead::where('customer_id', $u->id)->count();
    echo "As product_owner: $as_owner\n";
    echo "As customer: $as_customer\n";
}

foreach ($chat_heads as $head) {
    echo "--- Chat {$head->id} ---\n";
    echo "Type: {$head->type}\n";
    echo "Owner: {$head->product_owner_id}, Customer: {$head->customer_id}\n\n";
}

echo "\n=== End ===\n";
