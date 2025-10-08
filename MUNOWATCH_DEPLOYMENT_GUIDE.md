# Munowatch Crawler - Database Setup Guide

This guide provides multiple methods to set up the munowatch crawler integration on your online database.

## 🎯 Quick Start (Recommended)

### Method 1: Laravel Seeder (Preferred)
```bash
# Navigate to your Laravel project directory
cd /path/to/your/katogo/project

# Run the production seeder
php artisan db:seed --class=MunowatchProductionSeeder
```

### Method 2: Automated Deployment Script
```bash
# Make script executable
chmod +x deploy_munowatch.sh

# Run deployment
./deploy_munowatch.sh
```

### Method 3: Direct SQL Execution
```bash
# Execute the SQL file directly in your MySQL database
mysql -u your_username -p your_database < database/sql/munowatch_setup.sql
```

## 📋 What Gets Created

The seeder creates a new record in the `movie_crawler_websites` table with:

```sql
INSERT INTO movie_crawler_websites (
    name: 'Munowatch API'
    slug: 'munowatch'
    url: 'https://munowatch.com/api/list/p/{category_id}/3/{page}'
    token: 'munowatch123'
    email: 'Api-munowatch-2024'
    status: 'Active'
    page_number: 0
    max_page: 50
    fetch_status: 'pending'
    description: 'Munowatch movie crawler integration'
)
```

## 🔍 Verification

After running any setup method, verify the installation:

```bash
# Check if record exists
php artisan tinker --execute="
\$w = App\Models\MovieCrawlerWebsite::where('slug', 'munowatch')->first();
echo 'Found: ' . (\$w ? \$w->name . ' (ID: ' . \$w->id . ')' : 'NOT FOUND');
"

# Test functionality
php artisan tinker --execute="
\$headers = App\Models\Utils::get_munowatch_headers('test', 'test');
echo 'HTTP Client: ' . (isset(\$headers['Authorization']) ? 'WORKING' : 'FAILED');
"
```

## 🚀 Available Files

1. **MunowatchCrawlerSeeder.php** - Comprehensive seeder with validation
2. **MunowatchProductionSeeder.php** - Lightweight production seeder  
3. **munowatch_setup.sql** - Direct SQL execution file
4. **deploy_munowatch.sh** - Automated deployment script
5. **DatabaseSeeder.php** - Updated to include munowatch seeder

## 📊 Crawler Configuration

The munowatch crawler will rotate through these endpoints:

- **Category 1 (Movies):** `https://munowatch.com/api/list/p/1/3/{page}`
- **Category 2 (Series):** `https://munowatch.com/api/list/p/2/3/{page}`  
- **Category 3 (Korean):** `https://munowatch.com/api/list/p/3/3/{page}`
- **Category 4 (Animation):** `https://munowatch.com/api/list/p/4/3/{page}`

Each category will be crawled for up to 20 pages before rotating to the next.

## 🔧 Troubleshooting

### Issue: "Table doesn't exist"
```bash
# Run migrations first
php artisan migrate
```

### Issue: "Column 'last_tested_at' not found"
```sql
-- Add missing column
ALTER TABLE movie_crawler_websites 
ADD COLUMN last_tested_at TIMESTAMP NULL;
```

### Issue: "Class not found"
```bash
# Clear and rebuild autoloader
composer dump-autoload
php artisan config:clear
```

## 🎯 Next Steps

1. **Test Crawler:** Visit `/crawler` route in your browser
2. **Monitor Logs:** `tail -f storage/logs/laravel.log`
3. **Check Database:** Monitor `movie_crawler_pages` table for new records
4. **Verify Status:** Check `fetch_status` in `movie_crawler_websites` table

## 📈 Success Indicators

✅ Website record created with ID  
✅ Status shows as 'Active'  
✅ HTTP client methods functional  
✅ No errors in Laravel logs  
✅ Pages being created in database  

## 🆘 Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database connection: `php artisan migrate:status`
3. Test model access: `php artisan tinker`
4. Review seeder output for error messages

The munowatch crawler integration is now ready for production use! 🚀