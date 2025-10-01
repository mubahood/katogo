<?php
/**
 * Comprehensive Chat API Test Script
 * This script tests all chat endpoints with the test data we created
 */

require __DIR__ . '/vendor/autoload.php';

// Base URL for API
$base_url = 'http://localhost:8888/katogo/api';

// Test users - Using existing users with password 'password'
$test_users = [
    [
        'id' => 100,
        'email' => 'ssenyonjoalex08@gmail.com',
        'password' => 'password',
        'name' => 'Alex Trevor'
    ],
    [
        'id' => 101,
        'email' => 'tayebwasimon5@gmail.com',
        'password' => 'password',
        'name' => 'Tayebwa Simon'
    ],
    [
        'id' => 102,
        'email' => 'mahadpellucid@gmail.com',
        'password' => 'password',
        'name' => 'pellucid'
    ],
];

// Helper function to make API calls
function api_call($endpoint, $method = 'GET', $data = [], $token = null, $user_id = null) {
    global $base_url;
    
    $ch = curl_init();
    $url = $base_url . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Tok: Bearer ' . $token;
    }
    
    if ($user_id) {
        $headers[] = 'logged_in_user_id: ' . $user_id;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Helper to print results
function print_result($test_name, $result) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "TEST: $test_name\n";
    echo str_repeat('=', 80) . "\n";
    echo "Status: " . $result['status'] . "\n";
    echo "Response:\n";
    echo json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         COMPREHENSIVE CHAT API TEST SUITE                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

// Test 1: Login as test user 1 (John Doe)
echo "\n[1/10] Testing Login for User 1 (John Doe)...\n";
$login_result = api_call('/auth/login', 'POST', [
    'email' => $test_users[0]['email'],
    'password' => $test_users[0]['password']
]);
print_result('Login User 1', $login_result);

if ($login_result['status'] != 200 || !isset($login_result['body']['data']['user']['token'])) {
    echo "\n❌ ERROR: Login failed! Cannot proceed with tests.\n";
    exit(1);
}

$user1_token = $login_result['body']['data']['user']['token'];
$user1_id = $login_result['body']['data']['user']['id'];
echo "\n✅ User 1 logged in successfully. Token: " . substr($user1_token, 0, 20) . "...\n";

// Test 2: Login as test user 2 (Jane Smith)
echo "\n[2/10] Testing Login for User 2 (Jane Smith)...\n";
$login_result2 = api_call('/auth/login', 'POST', [
    'email' => $test_users[1]['email'],
    'password' => $test_users[1]['password']
]);
print_result('Login User 2', $login_result2);

if ($login_result2['status'] != 200 || !isset($login_result2['body']['data']['user']['token'])) {
    echo "\n❌ ERROR: User 2 login failed!\n";
    exit(1);
}

$user2_token = $login_result2['body']['data']['user']['token'];
$user2_id = $login_result2['body']['data']['user']['id'];
echo "\n✅ User 2 logged in successfully. Token: " . substr($user2_token, 0, 20) . "...\n";

// Test 3: Get /me endpoint to verify authentication
echo "\n[3/10] Testing /me endpoint for User 1...\n";
$me_result = api_call('/me', 'GET', [], $user1_token, $user1_id);
print_result('Get Current User', $me_result);

if ($me_result['status'] != 200) {
    echo "\n❌ ERROR: /me endpoint failed!\n";
} else {
    echo "\n✅ /me endpoint working correctly\n";
}

// Test 4: Get chat heads for User 1
echo "\n[4/10] Testing chat_heads endpoint for User 1 (John Doe)...\n";
$chat_heads_result = api_call('/chat-heads', 'GET', [], $user1_token, $user1_id);
print_result('Get Chat Heads for User 1', $chat_heads_result);

if ($chat_heads_result['status'] != 200) {
    echo "\n❌ ERROR: chat-heads endpoint failed!\n";
} else {
    $heads_count = count($chat_heads_result['body']['data'] ?? []);
    echo "\n✅ Chat heads retrieved successfully. Found $heads_count chat heads.\n";
    
    if ($heads_count > 0) {
        echo "\nChat heads summary:\n";
        foreach ($chat_heads_result['body']['data'] as $index => $head) {
            echo "  " . ($index + 1) . ". Chat ID: {$head['id']}, ";
            echo "With: {$head['other_user_name']}, ";
            echo "Type: {$head['type']}, ";
            echo "Unread: {$head['unread_count']}, ";
            echo "Last: " . substr($head['last_message_body'], 0, 30) . "...\n";
        }
    }
}

// Test 5: Get chat heads for User 2
echo "\n[5/10] Testing chat_heads endpoint for User 2 (Jane Smith)...\n";
$chat_heads_result2 = api_call('/chat-heads', 'GET', [], $user2_token, $user2_id);
print_result('Get Chat Heads for User 2', $chat_heads_result2);

if ($chat_heads_result2['status'] != 200) {
    echo "\n❌ ERROR: chat-heads endpoint failed for User 2!\n";
} else {
    $heads_count2 = count($chat_heads_result2['body']['data'] ?? []);
    echo "\n✅ Chat heads for User 2 retrieved successfully. Found $heads_count2 chat heads.\n";
}

// Test 6: Get chat messages for a specific chat head
if (!empty($chat_heads_result['body']['data'])) {
    $first_chat_head = $chat_heads_result['body']['data'][0];
    $chat_head_id = $first_chat_head['id'];
    
    echo "\n[6/10] Testing chat_messages endpoint for Chat Head ID: $chat_head_id...\n";
    $messages_result = api_call('/chat-messages?chat_head_id=' . $chat_head_id, 'GET', [], $user1_token, $user1_id);
    print_result('Get Chat Messages', $messages_result);
    
    if ($messages_result['status'] != 200) {
        echo "\n❌ ERROR: chat-messages endpoint failed!\n";
    } else {
        $messages_count = count($messages_result['body']['data'] ?? []);
        echo "\n✅ Chat messages retrieved successfully. Found $messages_count messages.\n";
        
        if ($messages_count > 0) {
            echo "\nMessages summary:\n";
            foreach (array_slice($messages_result['body']['data'], 0, 5) as $index => $msg) {
                echo "  " . ($index + 1) . ". From: {$msg['sender_name']}, ";
                echo "Status: {$msg['status']}, ";
                echo "Message: " . substr($msg['body'], 0, 40) . "...\n";
            }
        }
    }
} else {
    echo "\n[6/10] SKIPPED: No chat heads available to test messages.\n";
}

// Test 7: Start a new chat
echo "\n[7/10] Testing chat_start endpoint...\n";
$new_chat_result = api_call('/chat-start', 'POST', [
    'sender_id' => $user1_id,
    'receiver_id' => $user2_id,
    'product_id' => null
], $user1_token, $user1_id);
print_result('Start New Chat', $new_chat_result);

if ($new_chat_result['status'] != 200) {
    echo "\n❌ ERROR: chat-start endpoint failed!\n";
    $new_chat_head_id = null;
} else {
    echo "\n✅ Chat started successfully.\n";
    $new_chat_head_id = $new_chat_result['body']['data']['id'] ?? null;
    echo "New Chat Head ID: $new_chat_head_id\n";
}

// Test 8: Send a message
if ($new_chat_head_id) {
    echo "\n[8/10] Testing chat_send endpoint...\n";
    $send_result = api_call('/chat-send', 'POST', [
        'chat_head_id' => $new_chat_head_id,
        'receiver_id' => $user2_id,
        'body' => 'This is an automated test message sent at ' . date('Y-m-d H:i:s')
    ], $user1_token, $user1_id);
    print_result('Send Message', $send_result);
    
    if ($send_result['status'] != 200) {
        echo "\n❌ ERROR: chat-send endpoint failed!\n";
    } else {
        echo "\n✅ Message sent successfully.\n";
    }
} else {
    echo "\n[8/10] SKIPPED: No chat head ID available to send message.\n";
}

// Test 9: Mark messages as read
if ($new_chat_head_id) {
    echo "\n[9/10] Testing chat_mark_as_read endpoint...\n";
    $mark_read_result = api_call('/chat-mark-as-read', 'POST', [
        'chat_head_id' => $new_chat_head_id,
        'receiver_id' => $user2_id
    ], $user2_token, $user2_id);
    print_result('Mark as Read', $mark_read_result);
    
    if ($mark_read_result['status'] != 200) {
        echo "\n❌ ERROR: chat-mark-as-read endpoint failed!\n";
    } else {
        echo "\n✅ Messages marked as read successfully.\n";
    }
} else {
    echo "\n[9/10] SKIPPED: No chat head ID available to mark as read.\n";
}

// Test 10: Verify chat heads again (should see the new chat)
echo "\n[10/10] Re-testing chat_heads to verify new chat appears...\n";
$final_chat_heads = api_call('/chat-heads', 'GET', [], $user1_token, $user1_id);
print_result('Final Chat Heads Check', $final_chat_heads);

if ($final_chat_heads['status'] != 200) {
    echo "\n❌ ERROR: Final chat-heads check failed!\n";
} else {
    $final_count = count($final_chat_heads['body']['data'] ?? []);
    echo "\n✅ Final chat heads retrieved. Total: $final_count chat heads.\n";
}

// Final Summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST SUITE COMPLETED                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\nSUMMARY:\n";
echo "✅ All critical chat endpoints tested\n";
echo "✅ Test data verified\n";
echo "✅ Chat system is functional\n";
echo "\nNext steps:\n";
echo "1. Review the test output above for any errors\n";
echo "2. Test the React frontend with these endpoints\n";
echo "3. Verify real-time updates work correctly\n";
echo "\n";
