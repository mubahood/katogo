<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class TestMunowatchIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:munowatch {--cleanup : Clean up test data after running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive tests for munowatch integration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔥 MUNOWATCH INTEGRATION TEST COMMAND');
        $this->info('====================================');
        
        try {
            // Include the test class
            require_once base_path('tests/MunowatchIntegrationTest.php');
            
            $this->info('Starting comprehensive test suite...');
            $this->newLine();
            
            // Create and run test instance
            $testSuite = new \Tests\MunowatchIntegrationTest();
            
            // Capture output
            ob_start();
            $testSuite->runAllTests();
            $output = ob_get_clean();
            
            // Display the output
            $this->line($output);
            
            $this->info('🎉 ALL TESTS COMPLETED SUCCESSFULLY!');
            $this->info('The munowatch integration is fully functional and ready.');
            
            // Optional cleanup
            if ($this->option('cleanup')) {
                $this->info('🧹 Cleaning up test data...');
                $this->cleanupTestData();
                $this->info('✓ Test data cleanup completed.');
            }
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('💥 TEST EXECUTION FAILED!');
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')');
            
            if ($this->getOutput()->isVerbose()) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanupTestData()
    {
        try {
            \App\Models\MovieCrawlerPage::where('title', 'LIKE', 'Test %')->delete();
            $this->info('✓ Removed test MovieCrawlerPage records');
        } catch (Exception $e) {
            $this->warn('⚠ Could not clean up test data: ' . $e->getMessage());
        }
    }
}