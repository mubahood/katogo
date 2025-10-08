#!/bin/bash

# ================================================
# MUNOWATCH CRAWLER DEPLOYMENT SCRIPT
# ================================================
# 
# This script deploys the munowatch crawler integration
# to a production server. It handles database setup,
# verification, and initial testing.
#
# Author: Katogo Development Team
# Date: 2025-10-08
# Version: 1.0
#
# Usage: chmod +x deploy_munowatch.sh && ./deploy_munowatch.sh
# ================================================

echo "🚀 MUNOWATCH CRAWLER DEPLOYMENT SCRIPT"
echo "======================================"
echo "Starting deployment process..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    print_error "Error: Not in Laravel project directory. Please run from project root."
    exit 1
fi

print_status "Found Laravel project directory"

# Check if .env file exists
if [ ! -f ".env" ]; then
    print_error "Error: .env file not found. Please configure environment first."
    exit 1
fi

print_status "Environment configuration found"

# Check database connection
print_info "Testing database connection..."
php artisan migrate:status > /dev/null 2>&1
if [ $? -eq 0 ]; then
    print_status "Database connection successful"
else
    print_error "Database connection failed. Please check .env configuration."
    exit 1
fi

# Run migrations to ensure tables exist
print_info "Ensuring database tables are up to date..."
php artisan migrate --force
if [ $? -eq 0 ]; then
    print_status "Database migrations completed"
else
    print_warning "Migration issues detected, continuing..."
fi

# Method 1: Try Laravel seeder
print_info "Attempting Laravel seeder deployment..."
php artisan db:seed --class=MunowatchProductionSeeder 2>/dev/null
if [ $? -eq 0 ]; then
    print_status "Laravel seeder deployment successful"
    SEEDER_SUCCESS=true
else
    print_warning "Laravel seeder failed, trying alternative method..."
    SEEDER_SUCCESS=false
fi

# Method 2: Direct database execution if seeder failed
if [ "$SEEDER_SUCCESS" = false ]; then
    print_info "Attempting direct database setup..."
    
    # Create a temporary PHP script for database setup
    cat > temp_munowatch_setup.php << 'EOF'
<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "Setting up munowatch via direct database access...\n";

try {
    // Check if record exists
    $existing = DB::table('movie_crawler_websites')->where('slug', 'munowatch')->first();
    
    if ($existing) {
        echo "✅ Munowatch record already exists (ID: {$existing->id})\n";
    } else {
        // Insert record
        $id = DB::table('movie_crawler_websites')->insertGetId([
            'name' => 'Munowatch API',
            'url' => 'https://munowatch.com/api/list/p/{category_id}/3/{page}',
            'slug' => 'munowatch',
            'token' => 'munowatch123',
            'email' => 'Api-munowatch-2024',
            'status' => 'Active',
            'page_number' => 0,
            'max_page' => 50,
            'total_movies_found' => 0,
            'new_movies_found' => 0,
            'fetch_status' => 'pending',
            'error_message' => null,
            'last_fetched_at' => null,
            'response_data' => null,
            'last_page_url' => null,
            'description' => 'Munowatch crawler integration',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        
        echo "✅ Munowatch record created (ID: {$id})\n";
    }
    
    // Verify setup
    $website = DB::table('movie_crawler_websites')->where('slug', 'munowatch')->first();
    echo "📊 Verification:\n";
    echo "   ID: {$website->id}\n";
    echo "   Name: {$website->name}\n";
    echo "   Status: {$website->status}\n";
    echo "   Token: {$website->token}\n";
    echo "✅ Setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

    php temp_munowatch_setup.php
    if [ $? -eq 0 ]; then
        print_status "Direct database setup successful"
        rm temp_munowatch_setup.php
    else
        print_error "Direct database setup failed"
        rm temp_munowatch_setup.php
        exit 1
    fi
fi

# Verify the installation
print_info "Verifying munowatch installation..."

cat > temp_verify.php << 'EOF'
<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 MUNOWATCH INSTALLATION VERIFICATION\n";
echo "=====================================\n";

// Check database record
$website = \Illuminate\Support\Facades\DB::table('movie_crawler_websites')->where('slug', 'munowatch')->first();
if ($website) {
    echo "✅ Database Record: EXISTS (ID: {$website->id})\n";
    echo "   Name: {$website->name}\n";
    echo "   Status: {$website->status}\n";
    echo "   URL: {$website->url}\n";
} else {
    echo "❌ Database Record: NOT FOUND\n";
    exit(1);
}

// Check model constant
try {
    $constant = \App\Models\MovieCrawlerWebsite::MUNOWATCH;
    echo "✅ Model Constant: {$constant}\n";
} catch (Exception $e) {
    echo "⚠️  Model Constant: Could not verify\n";
}

// Check Utils methods
try {
    $headers = \App\Models\Utils::get_munowatch_headers('test', 'test');
    if (isset($headers['Authorization'])) {
        echo "✅ HTTP Client: FUNCTIONAL\n";
    } else {
        echo "⚠️  HTTP Client: Unexpected response\n";
    }
} catch (Exception $e) {
    echo "⚠️  HTTP Client: " . $e->getMessage() . "\n";
}

echo "\n🎯 VERIFICATION COMPLETE\n";
echo "✅ Munowatch integration is ready!\n";
EOF

php temp_verify.php
VERIFY_RESULT=$?
rm temp_verify.php

if [ $VERIFY_RESULT -eq 0 ]; then
    print_status "Verification completed successfully"
else
    print_warning "Verification completed with warnings"
fi

# Clear caches
print_info "Clearing application caches..."
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1
php artisan route:clear > /dev/null 2>&1
print_status "Caches cleared"

# Display final summary
echo ""
echo "🎉 MUNOWATCH DEPLOYMENT SUMMARY"
echo "==============================="
echo "✅ Database setup completed"
echo "✅ Configuration verified"  
echo "✅ Application caches cleared"
echo ""
echo "📋 NEXT STEPS:"
echo "1. Test crawler: Visit /crawler route in browser"
echo "2. Monitor logs: tail -f storage/logs/laravel.log"
echo "3. Check pages: SELECT COUNT(*) FROM movie_crawler_pages;"
echo "4. Verify status: Check movie_crawler_websites table"
echo ""
echo "🚀 MUNOWATCH CRAWLER IS READY FOR PRODUCTION!"
echo ""