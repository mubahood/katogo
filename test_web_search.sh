#!/bin/bash

# Test MovieSearch Logging - Web Search Simulation
# This script simulates a web browser search request

echo "🔍 Testing MovieSearch Logging from Web"
echo "========================================"
echo ""

# Get your auth token (you may need to update this)
# You can get a token from your browser's developer tools or login endpoint
TOKEN="your_auth_token_here"

BASE_URL="http://localhost:8888/katogo/api"

echo "Test 1: Search via /api/movies endpoint"
echo "---------------------------------------"
echo "Making request: GET $BASE_URL/movies?search=action"
echo ""

curl -X GET "$BASE_URL/movies?search=action" \
  -H "Accept: application/json" \
  -H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36" \
  -H "Authorization: Bearer $TOKEN" \
  -v 2>&1 | head -30

echo ""
echo ""
echo "Test 2: Search via /api/index endpoint"
echo "---------------------------------------"
echo "Making request: GET $BASE_URL/index?model=MovieModel&search=comedy"
echo ""

curl -X GET "$BASE_URL/index?model=MovieModel&search=comedy" \
  -H "Accept: application/json" \
  -H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36" \
  -H "Authorization: Bearer $TOKEN" \
  -v 2>&1 | head -30

echo ""
echo ""
echo "==================================="
echo "Now checking the Laravel logs..."
echo "==================================="
echo ""

tail -100 storage/logs/laravel.log | grep -i "SEARCH"

echo ""
echo "==================================="
echo "Checking database records..."
echo "==================================="
echo ""

php artisan tinker --execute="echo 'Total searches: ' . App\Models\MovieSearch::count() . PHP_EOL; App\Models\MovieSearch::latest()->take(5)->get(['id', 'search_term', 'search_count', 'results_count'])->each(function(\$s) { echo '  - ' . \$s->search_term . ' (' . \$s->search_count . ' times, ' . \$s->results_count . ' results)' . PHP_EOL; });"
