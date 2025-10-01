-- =====================================================
-- CHAT SYSTEM TEST DATA SETUP
-- This script creates comprehensive test data for the chat system
-- =====================================================

-- First, let's clean up orphaned chat data (chat heads with non-existent users)
DELETE FROM chat_messages WHERE chat_head_id IN (
    SELECT id FROM chat_heads WHERE 
    product_owner_id NOT IN (SELECT id FROM admin_users) 
    OR customer_id NOT IN (SELECT id FROM admin_users)
);

DELETE FROM chat_heads WHERE 
    product_owner_id NOT IN (SELECT id FROM admin_users) 
    OR customer_id NOT IN (SELECT id FROM admin_users);

-- Create test users if they don't exist
INSERT IGNORE INTO admin_users (id, username, password, name, email, avatar, created_at, updated_at, company_id, status)
VALUES 
(100, 'testuser1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'testuser1@test.com', 'no_image.png', NOW(), NOW(), 1, 'Active'),
(101, 'testuser2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'testuser2@test.com', 'no_image.png', NOW(), NOW(), 1, 'Active'),
(102, 'testuser3@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob Johnson', 'testuser3@test.com', 'no_image.png', NOW(), NOW(), 1, 'Active'),
(103, 'testuser4@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice Williams', 'testuser4@test.com', 'no_image.png', NOW(), NOW(), 1, 'Active'),
(104, 'testuser5@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Charlie Brown', 'testuser5@test.com', 'no_image.png', NOW(), NOW(), 1, 'Active');

-- Assign test users to role 2 (regular users)
INSERT IGNORE INTO admin_role_users (user_id, role_id, created_at, updated_at)
VALUES 
(100, 2, NOW(), NOW()),
(101, 2, NOW(), NOW()),
(102, 2, NOW(), NOW()),
(103, 2, NOW(), NOW()),
(104, 2, NOW(), NOW());

-- Create test products for product-based chats
INSERT IGNORE INTO stock_items (id, company_id, created_by_id, stock_category_id, stock_sub_category_id, financial_period_id, name, description, image, original_quantity, selling_price, created_at, updated_at)
VALUES 
(1000, 1, 100, 1, 1, 1, 'Test Product 1 - Laptop', 'High-performance laptop for testing', 'no_image.png', 10, 150000, NOW(), NOW()),
(1001, 1, 101, 1, 1, 1, 'Test Product 2 - Phone', 'Smartphone for chat testing', 'no_image.png', 5, 80000, NOW(), NOW()),
(1002, 1, 102, 1, 1, 1, 'Test Product 3 - Tablet', 'Tablet device for testing', 'no_image.png', 8, 95000, NOW(), NOW());

-- Delete existing test chat data if any
DELETE FROM chat_messages WHERE chat_head_id IN (
    SELECT id FROM chat_heads WHERE id BETWEEN 1000 AND 1020
);
DELETE FROM chat_heads WHERE id BETWEEN 1000 AND 1020;

-- Create comprehensive test chat heads
-- Scenario 1: User 1 (John) selling product to User 2 (Jane)
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1000, 1000, 'Test Product 1 - Laptop', 'no_image.png', 100, 'John Doe', 'no_image.png', 101, 'Jane Smith', 'no_image.png', 'Is this laptop still available?', NOW(), 'sent', 'product', NOW(), NOW());

-- Scenario 2: User 2 (Jane) selling product to User 3 (Bob)
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1001, 1001, 'Test Product 2 - Phone', 'no_image.png', 101, 'Jane Smith', 'no_image.png', 102, 'Bob Johnson', 'no_image.png', 'Can you deliver this phone today?', NOW(), 'sent', 'product', NOW(), NOW());

-- Scenario 3: User 3 (Bob) selling product to User 4 (Alice)
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1002, 1002, 'Test Product 3 - Tablet', 'no_image.png', 102, 'Bob Johnson', 'no_image.png', 103, 'Alice Williams', 'no_image.png', 'What is the battery life?', NOW(), 'sent', 'product', NOW(), NOW());

-- Scenario 4: Dating/Social chat between User 1 and User 4
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1003, NULL, NULL, NULL, 100, 'John Doe', 'no_image.png', 103, 'Alice Williams', 'no_image.png', 'Hey, how are you doing?', NOW(), 'sent', 'dating', NOW(), NOW());

-- Scenario 5: Dating/Social chat between User 2 and User 5
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1004, NULL, NULL, NULL, 101, 'Jane Smith', 'no_image.png', 104, 'Charlie Brown', 'no_image.png', 'Nice to meet you!', NOW(), 'sent', 'dating', NOW(), NOW());

-- Scenario 6: User 1 has chat with admin user
INSERT INTO chat_heads (id, product_id, product_name, product_photo, product_owner_id, product_owner_name, product_owner_photo, customer_id, customer_name, customer_photo, last_message_body, last_message_time, last_message_status, type, created_at, updated_at)
VALUES 
(1005, NULL, NULL, NULL, 100, 'John Doe', 'no_image.png', 1, 'Muhindo Mubaraka', 'images/WhatsApp Image 2025-06-10 at 14.45.15_784f948e.jpg', 'Hi admin, I need help!', NOW(), 'sent', 'dating', NOW(), NOW());

-- Create comprehensive test chat messages
-- For chat 1000 (John selling laptop to Jane)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1000, 101, 100, 'Jane Smith', 'no_image.png', 'John Doe', 'no_image.png', 'Hi, I saw your laptop listing', 'text', 'read', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1000, 100, 101, 'John Doe', 'no_image.png', 'Jane Smith', 'no_image.png', 'Hello! Yes, it is available', 'text', 'read', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1000, 101, 100, 'Jane Smith', 'no_image.png', 'John Doe', 'no_image.png', 'What is your best price?', 'text', 'read', DATE_SUB(NOW(), INTERVAL 50 MINUTE), DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
(1000, 100, 101, 'John Doe', 'no_image.png', 'Jane Smith', 'no_image.png', 'I can do 145,000 UGX for you', 'text', 'read', DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(1000, 101, 100, 'Jane Smith', 'no_image.png', 'John Doe', 'no_image.png', 'Is this laptop still available?', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 5 MINUTE), DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- For chat 1001 (Jane selling phone to Bob)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1001, 102, 101, 'Bob Johnson', 'no_image.png', 'Jane Smith', 'no_image.png', 'Hello, is the phone brand new?', 'text', 'read', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1001, 101, 102, 'Jane Smith', 'no_image.png', 'Bob Johnson', 'no_image.png', 'Yes, it is sealed in the box', 'text', 'read', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1001, 102, 101, 'Bob Johnson', 'no_image.png', 'Jane Smith', 'no_image.png', 'Can you deliver this phone today?', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE));

-- For chat 1002 (Bob selling tablet to Alice)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1002, 103, 102, 'Alice Williams', 'no_image.png', 'Bob Johnson', 'no_image.png', 'Hi, interested in your tablet', 'text', 'read', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1002, 102, 103, 'Bob Johnson', 'no_image.png', 'Alice Williams', 'no_image.png', 'Great! It has a 10-hour battery life', 'text', 'read', DATE_SUB(NOW(), INTERVAL 55 MINUTE), DATE_SUB(NOW(), INTERVAL 55 MINUTE)),
(1002, 103, 102, 'Alice Williams', 'no_image.png', 'Bob Johnson', 'no_image.png', 'What is the battery life?', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 2 MINUTE), DATE_SUB(NOW(), INTERVAL 2 MINUTE));

-- For chat 1003 (John and Alice - dating/social)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1003, 100, 103, 'John Doe', 'no_image.png', 'Alice Williams', 'no_image.png', 'Hi Alice!', 'text', 'read', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1003, 103, 100, 'Alice Williams', 'no_image.png', 'John Doe', 'no_image.png', 'Hello John! How are you?', 'text', 'read', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1003, 100, 103, 'John Doe', 'no_image.png', 'Alice Williams', 'no_image.png', 'I am doing great, thanks for asking', 'text', 'read', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1003, 103, 100, 'Alice Williams', 'no_image.png', 'John Doe', 'no_image.png', 'That is good to hear!', 'text', 'read', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1003, 100, 103, 'John Doe', 'no_image.png', 'Alice Williams', 'no_image.png', 'Hey, how are you doing?', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 15 MINUTE), DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- For chat 1004 (Jane and Charlie - dating/social)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1004, 101, 104, 'Jane Smith', 'no_image.png', 'Charlie Brown', 'no_image.png', 'Hi Charlie!', 'text', 'read', DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1004, 104, 101, 'Charlie Brown', 'no_image.png', 'Jane Smith', 'no_image.png', 'Hey Jane, nice to meet you!', 'text', 'read', DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1004, 101, 104, 'Jane Smith', 'no_image.png', 'Charlie Brown', 'no_image.png', 'Nice to meet you!', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE));

-- For chat 1005 (John and Admin)
INSERT INTO chat_messages (chat_head_id, sender_id, receiver_id, sender_name, sender_photo, receiver_name, receiver_photo, body, type, status, created_at, updated_at)
VALUES 
(1005, 100, 1, 'John Doe', 'no_image.png', 'Muhindo Mubaraka', 'images/WhatsApp Image 2025-06-10 at 14.45.15_784f948e.jpg', 'Hi admin, I have a question', 'text', 'read', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1005, 1, 100, 'Muhindo Mubaraka', 'images/WhatsApp Image 2025-06-10 at 14.45.15_784f948e.jpg', 'John Doe', 'no_image.png', 'Hello John! How can I help you?', 'text', 'read', DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1005, 100, 1, 'John Doe', 'no_image.png', 'Muhindo Mubaraka', 'images/WhatsApp Image 2025-06-10 at 14.45.15_784f948e.jpg', 'Hi admin, I need help!', 'text', 'sent', DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Verification Queries
SELECT '=== TEST DATA SUMMARY ===' as info;
SELECT 'Test Users Created:' as info, COUNT(*) as count FROM admin_users WHERE id BETWEEN 100 AND 104;
SELECT 'Test Products Created:' as info, COUNT(*) as count FROM stock_items WHERE id BETWEEN 1000 AND 1002;
SELECT 'Test Chat Heads Created:' as info, COUNT(*) as count FROM chat_heads WHERE id BETWEEN 1000 AND 1005;
SELECT 'Test Chat Messages Created:' as info, COUNT(*) as count FROM chat_messages WHERE chat_head_id BETWEEN 1000 AND 1005;
SELECT '=== SAMPLE CHAT HEADS ===' as info;
SELECT id, product_owner_id, product_owner_name, customer_id, customer_name, last_message_body, type FROM chat_heads WHERE id BETWEEN 1000 AND 1005;
