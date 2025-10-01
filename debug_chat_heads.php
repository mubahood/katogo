<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ChatHead;
use App\Models\ChatMessage;

echo "=== Debug Chat Heads Logic ===\n\n";

$user_id = 100; // Alex Trevor
$u = User::find($user_id);

if (!$u) {
    die("User $user_id not found!\n");
}

echo "User found: {$u->name} (ID: {$u->id})\n\n";

// Get chat heads
echo "Querying chat heads...\n";
$chat_heads = ChatHead::where(function($query) use ($u) {
        $query->where('product_owner_id', $u->id)
              ->orWhere('customer_id', $u->id);
    })
    ->orderBy('updated_at', 'desc')
    ->get();

echo "Found " . $chat_heads->count() . " chat heads\n\n";

foreach ($chat_heads as $head) {
    echo "--- Chat Head ID: {$head->id} ---\n";
    echo "Type: {$head->type}\n";
    echo "Product Owner ID: {$head->product_owner_id}\n";
    echo "Customer ID: {$head->customer_id}\n";
    
    $their_id = null;
    $is_customer = ($u->id == $head->customer_id);
    
    if ($is_customer) {
        $their_id = $head->product_owner_id;
        echo "I am customer, other is product owner (ID: $their_id)\n";
    } else {
        $their_id = $head->customer_id;
        echo "I am product owner, other is customer (ID: $their_id)\n";
    }
    
    echo "Looking for user $their_id...\n";
    $them = User::find($their_id);
    
    if ($them == null) {
        echo "❌ User $their_id NOT FOUND - SKIPPING THIS CHAT\n\n";
        continue;
    }
    
    echo "✅ User found: {$them->name}\n";
    
    // Check for messages
    $lastMesg = ChatMessage::where('chat_head_id', $head->id)
                          ->orderBy('created_at', 'desc')
                          ->first();
    
    if ($lastMesg) {
        echo "Last message: " . substr($lastMesg->body, 0, 50) . "...\n";
    } else {
        echo "No messages yet\n";
    }
    
    echo "\n";
}

echo "\n=== End Debug ===\n";
