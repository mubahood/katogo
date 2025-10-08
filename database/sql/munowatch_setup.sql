-- ================================================
-- MUNOWATCH CRAWLER INTEGRATION - DATABASE SETUP
-- ================================================
-- 
-- This SQL script sets up the munowatch crawler integration
-- for the Katogo movie platform. Execute this script on your
-- production database to enable munowatch movie crawling.
--
-- Author: Katogo Development Team
-- Date: 2025-10-08
-- Version: 1.0
--
-- Prerequisites:
-- - movie_crawler_websites table must exist
-- - movie_crawler_pages table must exist
-- 
-- Usage:
-- 1. Connect to your MySQL database
-- 2. Execute this script
-- 3. Verify the record was created
-- 4. Test crawler functionality
--
-- ================================================

-- Check if munowatch record already exists
SELECT 
    'Checking for existing munowatch record...' as status,
    COUNT(*) as existing_records
FROM movie_crawler_websites 
WHERE slug = 'munowatch';

-- Insert munowatch crawler configuration
-- (Will fail gracefully if record already exists due to unique constraint)
INSERT IGNORE INTO movie_crawler_websites (
    name,
    url,
    slug,
    token,
    email,
    status,
    page_number,
    max_page,
    total_movies_found,
    new_movies_found,
    fetch_status,
    error_message,
    last_fetched_at,
    response_data,
    last_page_url,
    description,
    created_at,
    updated_at
) VALUES (
    'Munowatch API',
    'https://munowatch.com/api/list/p/{category_id}/3/{page}',
    'munowatch',
    'munowatch123',
    'Api-munowatch-2024',
    'Active',
    0,
    50,
    0,
    0,
    'pending',
    NULL,
    NULL,
    NULL,
    NULL,
    'Munowatch movie and series crawler integration for translated content',
    NOW(),
    NOW()
);

-- Verify the insertion
SELECT 
    'Munowatch setup verification:' as status,
    id,
    name,
    slug,
    status,
    token,
    email,
    url,
    created_at
FROM movie_crawler_websites 
WHERE slug = 'munowatch';

-- Display setup summary
SELECT 
    '✅ MUNOWATCH CRAWLER SETUP COMPLETED!' as message,
    CONCAT('Record ID: ', id) as record_info,
    CONCAT('Status: ', status) as status_info,
    CONCAT('Created: ', created_at) as created_info
FROM movie_crawler_websites 
WHERE slug = 'munowatch';

-- ================================================
-- OPTIONAL: Add missing column if needed
-- ================================================
-- Uncomment the following line if you encounter 
-- "Column 'last_tested_at' not found" errors:
--
-- ALTER TABLE movie_crawler_websites 
-- ADD COLUMN last_tested_at TIMESTAMP NULL 
-- AFTER last_fetched_at;

-- ================================================
-- VERIFICATION QUERIES
-- ================================================
-- Use these queries to verify the setup:

-- 1. Check website record
-- SELECT * FROM movie_crawler_websites WHERE slug = 'munowatch';

-- 2. Check created pages (after running crawler)
-- SELECT COUNT(*) as total_pages 
-- FROM movie_crawler_pages 
-- WHERE movie_crawler_website_id = (
--     SELECT id FROM movie_crawler_websites WHERE slug = 'munowatch'
-- );

-- 3. Check latest crawler activity
-- SELECT 
--     slug,
--     fetch_status,
--     last_fetched_at,
--     new_movies_found,
--     error_message
-- FROM movie_crawler_websites 
-- WHERE slug = 'munowatch';

-- ================================================
-- CRAWLER ENDPOINT CONFIGURATION
-- ================================================
-- The munowatch crawler will rotate through these endpoints:
-- 
-- Category 1 (Movies):    https://munowatch.com/api/list/p/1/3/{page}
-- Category 2 (Series):    https://munowatch.com/api/list/p/2/3/{page}
-- Category 3 (Korean):    https://munowatch.com/api/list/p/3/3/{page}
-- Category 4 (Animation): https://munowatch.com/api/list/p/4/3/{page}
--
-- Each category will be crawled for up to 20 pages before
-- rotating to the next category automatically.
-- ================================================

-- ================================================
-- NEXT STEPS AFTER RUNNING THIS SCRIPT:
-- ================================================
-- 1. Verify the record exists: Check the verification query results above
-- 2. Test HTTP client: Ensure Utils class methods are working
-- 3. Test crawler: Run via /crawler route or artisan command
-- 4. Monitor logs: Check Laravel logs for any errors
-- 5. Verify pages: Check movie_crawler_pages table for created records
-- 6. Set up monitoring: Track fetch_status and error_message fields
-- ================================================