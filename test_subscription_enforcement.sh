#!/bin/bash

# 🧪 SUBSCRIPTION ENFORCEMENT TESTING SCRIPT
# Run this script to test subscription enforcement
# Usage: bash test_subscription_enforcement.sh

echo "🧪 KATOGO SUBSCRIPTION ENFORCEMENT TESTING"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL="http://localhost:8000/api"
FRONTEND_URL="http://localhost:3000"

echo "📋 Test Configuration:"
echo "  API URL: $API_BASE_URL"
echo "  Frontend URL: $FRONTEND_URL"
echo ""

# Test 1: Check if subscription middleware is registered
echo "Test 1: Checking Backend Middleware..."
if grep -q "CheckSubscription" "/Applications/MAMP/htdocs/katogo/app/Http/Kernel.php"; then
    echo -e "${GREEN}✅ CheckSubscription middleware registered${NC}"
else
    echo -e "${RED}❌ CheckSubscription middleware NOT registered${NC}"
fi

# Test 2: Check if protected routes have middleware
echo ""
echo "Test 2: Checking Protected Routes..."
if grep -q "middleware(\['subscription'\])" "/Applications/MAMP/htdocs/katogo/routes/api.php"; then
    echo -e "${GREEN}✅ Subscription middleware applied to routes${NC}"
else
    echo -e "${RED}❌ Subscription middleware NOT applied${NC}"
fi

# Test 3: Check if frontend interceptor exists
echo ""
echo "Test 3: Checking Frontend Interceptor..."
if grep -q "require_subscription" "/Users/mac/Desktop/github/katogo-react/src/app/services/Api.ts"; then
    echo -e "${GREEN}✅ Frontend API interceptor configured${NC}"
else
    echo -e "${RED}❌ Frontend API interceptor NOT configured${NC}"
fi

# Test 4: Check if SubscriptionRoute guard exists
echo ""
echo "Test 4: Checking Route Guard..."
if [ -f "/Users/mac/Desktop/github/katogo-react/src/app/components/Auth/SubscriptionRoute.tsx" ]; then
    echo -e "${GREEN}✅ SubscriptionRoute guard exists${NC}"
else
    echo -e "${YELLOW}⚠️  SubscriptionRoute guard not found (optional)${NC}"
fi

# Test 5: Check subscription plans seeder
echo ""
echo "Test 5: Checking Subscription Plans..."
if [ -f "/Applications/MAMP/htdocs/katogo/database/seeders/SubscriptionPlansSeeder.php" ]; then
    echo -e "${GREEN}✅ SubscriptionPlans seeder exists${NC}"
else
    echo -e "${RED}❌ SubscriptionPlans seeder NOT found${NC}"
fi

# Test 6: Interactive API Testing
echo ""
echo "=========================================="
echo "📡 INTERACTIVE API TESTS"
echo "=========================================="
echo ""
echo "To test subscription enforcement manually:"
echo ""
echo "1️⃣  Test WITHOUT subscription:"
echo "   curl -X GET $API_BASE_URL/movies \\"
echo "        -H 'Authorization: Bearer YOUR_TOKEN_HERE'"
echo "   Expected: 403 Forbidden + require_subscription: true"
echo ""
echo "2️⃣  Test WITH subscription:"
echo "   - First subscribe via $FRONTEND_URL/subscription/plans"
echo "   - Complete Pesapal payment"
echo "   - Then curl same endpoint"
echo "   Expected: 200 OK + movies list"
echo ""
echo "3️⃣  Test Grace Period:"
echo "   - Manually expire subscription in database:"
echo "   UPDATE subscriptions SET end_date = DATE_SUB(NOW(), INTERVAL 2 DAY) WHERE user_id = YOUR_USER_ID;"
echo "   - Curl /movies endpoint"
echo "   Expected: 200 OK (if grace period enabled)"
echo ""
echo "4️⃣  Test Expired (No Grace):"
echo "   UPDATE subscriptions SET end_date = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE user_id = YOUR_USER_ID;"
echo "   Expected: 403 Forbidden"
echo ""

# Test 7: Check environment configuration
echo "=========================================="
echo "⚙️  ENVIRONMENT CHECK"
echo "=========================================="
echo ""
if [ -f "/Applications/MAMP/htdocs/katogo/.env" ]; then
    if grep -q "PESAPAL_CONSUMER_KEY" "/Applications/MAMP/htdocs/katogo/.env"; then
        echo -e "${GREEN}✅ Pesapal credentials configured${NC}"
    else
        echo -e "${YELLOW}⚠️  Pesapal credentials NOT configured${NC}"
        echo "   Add to .env:"
        echo "   PESAPAL_CONSUMER_KEY=your_key"
        echo "   PESAPAL_CONSUMER_SECRET=your_secret"
    fi
else
    echo -e "${RED}❌ .env file not found${NC}"
fi

# Summary
echo ""
echo "=========================================="
echo "📊 TESTING SUMMARY"
echo "=========================================="
echo ""
echo "Manual Testing Steps:"
echo "1. Start backend: cd /Applications/MAMP/htdocs/katogo && php artisan serve"
echo "2. Start frontend: cd /Users/mac/Desktop/github/katogo-react && npm start"
echo "3. Visit: $FRONTEND_URL/subscription/plans"
echo "4. Try accessing: $FRONTEND_URL/movies (should redirect if no subscription)"
echo "5. Subscribe and test again"
echo ""
echo "Database Testing:"
echo "mysql> SELECT * FROM subscriptions;"
echo "mysql> SELECT * FROM subscription_plans;"
echo "mysql> SELECT * FROM subscription_transactions;"
echo ""
echo "Frontend Console Logs:"
echo "- Open Browser DevTools > Console"
echo "- Look for: '🔒 Subscription required' messages"
echo "- Check Network tab for 403 responses with require_subscription flag"
echo ""
echo "✅ Testing script complete!"
echo ""
