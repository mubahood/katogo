#!/bin/bash

# Google Drive Video Transfer System - Quick Test Script
# This script helps you test the video transfer system

echo "🎬 Google Drive Video Transfer System - Test Script"
echo "=================================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: artisan file not found. Please run this script from the Laravel root directory.${NC}"
    exit 1
fi

echo "✅ Laravel directory confirmed"
echo ""

# Test 1: Check if migration exists
echo "Test 1: Checking migration file..."
if [ -f "database/migrations/2025_10_19_000001_create_video_transfers_table.php" ]; then
    echo -e "${GREEN}✅ Migration file found${NC}"
else
    echo -e "${RED}❌ Migration file not found${NC}"
fi
echo ""

# Test 2: Check if model exists
echo "Test 2: Checking VideoTransfer model..."
if [ -f "app/Models/VideoTransfer.php" ]; then
    echo -e "${GREEN}✅ VideoTransfer model found${NC}"
else
    echo -e "${RED}❌ VideoTransfer model not found${NC}"
fi
echo ""

# Test 3: Check if controller exists
echo "Test 3: Checking VideoTransferController..."
if [ -f "app/Admin/Controllers/VideoTransferController.php" ]; then
    echo -e "${GREEN}✅ VideoTransferController found${NC}"
else
    echo -e "${RED}❌ VideoTransferController not found${NC}"
fi
echo ""

# Test 4: Check .env configuration
echo "Test 4: Checking .env configuration..."
if [ -f ".env" ]; then
    if grep -q "GOOGLE_DRIVE_CLIENT_ID" .env; then
        echo -e "${GREEN}✅ Google Drive credentials configured in .env${NC}"
    else
        echo -e "${YELLOW}⚠️  Google Drive credentials not found in .env${NC}"
        echo "   Please add the following to your .env file:"
        echo "   GOOGLE_DRIVE_CLIENT_ID=your-client-id"
        echo "   GOOGLE_DRIVE_CLIENT_SECRET=your-secret"
        echo "   GOOGLE_DRIVE_REFRESH_TOKEN=your-token"
    fi
else
    echo -e "${RED}❌ .env file not found${NC}"
fi
echo ""

# Test 5: Check if migration has been run
echo "Test 5: Checking if migration has been run..."
php artisan migrate:status | grep -q "video_transfers"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Migration has been run${NC}"
else
    echo -e "${YELLOW}⚠️  Migration not run yet${NC}"
    echo "   Run: php artisan migrate"
fi
echo ""

# Test 6: Check routes
echo "Test 6: Checking routes..."
if grep -q "video-transfers" app/Admin/routes.php; then
    echo -e "${GREEN}✅ Routes configured${NC}"
else
    echo -e "${RED}❌ Routes not configured${NC}"
fi
echo ""

echo "=================================================="
echo "📊 Test Summary"
echo "=================================================="
echo ""

# Prompt for running migration
read -p "Would you like to run the migration now? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Running migration..."
    php artisan migrate
    echo ""
fi

# Prompt for clearing cache
read -p "Would you like to clear the cache? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Clearing cache..."
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    echo -e "${GREEN}✅ Cache cleared${NC}"
    echo ""
fi

echo "=================================================="
echo "🎉 Setup Complete!"
echo "=================================================="
echo ""
echo "Next steps:"
echo "1. Configure Google Drive API credentials in .env"
echo "2. Visit: http://your-domain.com/admin/video-transfers"
echo "3. Create your first video transfer"
echo ""
echo "📚 Documentation:"
echo "- Full Guide: GOOGLE_DRIVE_VIDEO_TRANSFER_GUIDE.md"
echo "- Quick Start: VIDEO_TRANSFER_QUICK_START.md"
echo "- API Integration: VIDEO_TRANSFER_API_INTEGRATION.md"
echo ""
echo "Happy transferring! 🚀"
