#!/bin/bash
# Test Game API Endpoints

BASE_URL="http://localhost:8888/katogo/public/api"

echo "=== Game API Integration Tests ==="
echo ""

# Login to get token
echo "1. Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@gmail.com","password":"test1234"}')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "   ✗ Login failed: $LOGIN_RESPONSE"
    exit 1
fi

echo "   ✓ Got token: ${TOKEN:0:30}..."
echo ""

# Test online users
echo "2. Testing GET /game/online-users..."
RESPONSE=$(curl -s "$BASE_URL/game/online-users" \
  -H "Authorization: Bearer $TOKEN")
CODE=$(echo $RESPONSE | grep -o '"code":[0-9]*' | cut -d':' -f2)
echo "   Response code: $CODE"
if [ "$CODE" == "1" ]; then
    echo "   ✓ Success"
else
    echo "   Response: $RESPONSE"
fi
echo ""

# Test get invitations
echo "3. Testing GET /game/invitations..."
RESPONSE=$(curl -s "$BASE_URL/game/invitations" \
  -H "Authorization: Bearer $TOKEN")
CODE=$(echo $RESPONSE | grep -o '"code":[0-9]*' | cut -d':' -f2)
echo "   Response code: $CODE"
if [ "$CODE" == "1" ]; then
    echo "   ✓ Success"
else
    echo "   Response: $RESPONSE"
fi
echo ""

# Test send invitation (to user 2)
echo "4. Testing POST /game/invite (to user 2)..."
RESPONSE=$(curl -s -X POST "$BASE_URL/game/invite" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"receiver_id": 2, "message": "Lets play!"}')
CODE=$(echo $RESPONSE | grep -o '"code":[0-9]*' | cut -d':' -f2)
echo "   Response code: $CODE"
INVITE_ID=$(echo $RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
if [ "$CODE" == "1" ]; then
    echo "   ✓ Success - Invitation ID: $INVITE_ID"
else
    echo "   Response: $RESPONSE"
fi
echo ""

# Test invitation status
if [ ! -z "$INVITE_ID" ]; then
    echo "5. Testing GET /game/invite/$INVITE_ID/status..."
    RESPONSE=$(curl -s "$BASE_URL/game/invite/$INVITE_ID/status" \
      -H "Authorization: Bearer $TOKEN")
    CODE=$(echo $RESPONSE | grep -o '"code":[0-9]*' | cut -d':' -f2)
    echo "   Response code: $CODE"
    if [ "$CODE" == "1" ]; then
        STATUS=$(echo $RESPONSE | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        REMAINING=$(echo $RESPONSE | grep -o '"remaining_seconds":[0-9]*' | cut -d':' -f2)
        echo "   ✓ Status: $STATUS, Remaining: ${REMAINING}s"
    else
        echo "   Response: $RESPONSE"
    fi
    echo ""
    
    # Cancel the invitation
    echo "6. Testing POST /game/invite/$INVITE_ID/cancel..."
    RESPONSE=$(curl -s -X POST "$BASE_URL/game/invite/$INVITE_ID/cancel" \
      -H "Authorization: Bearer $TOKEN")
    CODE=$(echo $RESPONSE | grep -o '"code":[0-9]*' | cut -d':' -f2)
    if [ "$CODE" == "1" ]; then
        echo "   ✓ Invitation cancelled"
    else
        echo "   Response: $RESPONSE"
    fi
fi

echo ""
echo "=== Tests Complete ==="
