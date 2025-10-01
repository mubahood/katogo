<?php
// Simple debug script to test chat functionality
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\ChatHead;
use App\Models\User;
use App\Models\ChatMessage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "=== CHAT DEBUG SCRIPT ===\n\n";

// 1. Check if chat head 1029 exists
echo "1. Checking if chat head 1029 exists...\n";
$chatHead = ChatHead::find(1029);
if ($chatHead) {
    echo "✅ Chat head found!\n";
    echo "   ID: {$chatHead->id}\n";
    echo "   Customer ID: {$chatHead->customer_id}\n";
    echo "   Product Owner ID: {$chatHead->product_owner_id}\n";
    echo "   Created: {$chatHead->created_at}\n";
    echo "   Updated: {$chatHead->updated_at}\n\n";
    
    // 2. Check users exist
    echo "2. Checking users...\n";
    $customer = User::find($chatHead->customer_id);
    $productOwner = User::find($chatHead->product_owner_id);
    
    echo "   Customer (ID {$chatHead->customer_id}): " . ($customer ? "✅ {$customer->name}" : "❌ Not found") . "\n";
    echo "   Product Owner (ID {$chatHead->product_owner_id}): " . ($productOwner ? "✅ {$productOwner->name}" : "❌ Not found") . "\n\n";
    
    // 3. Check messages
    echo "3. Checking messages...\n";
    $messages = ChatMessage::where('chat_head_id', 1029)->get();
    echo "   Messages count: " . $messages->count() . "\n";
    foreach ($messages as $msg) {
        echo "   - Message {$msg->id}: {$msg->body} (from {$msg->sender_id} to {$msg->receiver_id})\n";
    }
    echo "\n";
    
} else {
    echo "❌ Chat head 1029 not found!\n\n";
}

// 4. Check all chat heads
echo "4. Checking all chat heads...\n";
$allChatHeads = ChatHead::orderBy('id', 'desc')->limit(10)->get();
echo "   Total chat heads (last 10): " . $allChatHeads->count() . "\n";
foreach ($allChatHeads as $head) {
    echo "   - Chat {$head->id}: customer={$head->customer_id}, owner={$head->product_owner_id}, created={$head->created_at}\n";
}
echo "\n";

// 5. Check user authentication
echo "5. Testing auth token...\n";
// You can add your actual token here for testing
$testToken = "your_token_here"; // Replace with actual token from browser
echo "   Token: " . (strlen($testToken) > 10 ? "✅ Provided" : "❌ Not provided") . "\n\n";

echo "=== END DEBUG ===\n";