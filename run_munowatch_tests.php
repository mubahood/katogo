<?php

/**
 * Munowatch Test Runner
 * 
 * Simple script to execute munowatch integration tests
 * Can be run directly via PHP CLI or through Laravel artisan
 * 
 * Usage: php run_munowatch_tests.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Include the test class
require_once __DIR__ . '/tests/MunowatchIntegrationTest.php';

try {
    echo "🔥 MUNOWATCH INTEGRATION TEST RUNNER\n";
    echo "====================================\n";
    echo "Starting comprehensive test suite...\n\n";
    
    // Create and run test instance
    $testSuite = new Tests\MunowatchIntegrationTest();
    $testSuite->runAllTests();
    
    echo "\n🎉 ALL TESTS COMPLETED SUCCESSFULLY!\n";
    echo "The munowatch integration is fully functional and ready.\n\n";
    
} catch (Exception $e) {
    echo "\n💥 TEST EXECUTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}