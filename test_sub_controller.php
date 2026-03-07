<?php
require '/Applications/MAMP/htdocs/katogo/vendor/autoload.php';
$app = require_once '/Applications/MAMP/htdocs/katogo/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $ctrl = new \App\Admin\Controllers\SubscriptionController();

    // Test 1: buildStatsCards
    $m = new ReflectionMethod($ctrl, 'buildStatsCards');
    $m->setAccessible(true);
    $html = $m->invoke($ctrl);
    echo "1. buildStatsCards: " . strlen($html) . " bytes - ";
    echo (str_contains($html, 'sub-stats') && str_contains($html, 'fa fa-') ? "PASS" : "FAIL") . "\n";

    // Test 2: buildChartsSection
    $m2 = new ReflectionMethod($ctrl, 'buildChartsSection');
    $m2->setAccessible(true);
    $html2 = $m2->invoke($ctrl);
    echo "2. buildChartsSection: " . strlen($html2) . " bytes - ";
    echo (str_contains($html2, 'Chart') && str_contains($html2, 'pjax:end') ? "PASS" : "FAIL") . "\n";

    // Test 3: appPlatformBreakdownBox
    $m3 = new ReflectionMethod($ctrl, 'appPlatformBreakdownBox');
    $m3->setAccessible(true);
    $box = $m3->invoke($ctrl);
    echo "3. appPlatformBreakdownBox: " . (is_object($box) ? "PASS" : "FAIL") . "\n";

    // Test 4: expiringSubscriptionsBox
    $m4 = new ReflectionMethod($ctrl, 'expiringSubscriptionsBox');
    $m4->setAccessible(true);
    $box2 = $m4->invoke($ctrl);
    echo "4. expiringSubscriptionsBox: " . (is_object($box2) ? "PASS" : "FAIL") . "\n";

    // Test 5: grid
    $m5 = new ReflectionMethod($ctrl, 'grid');
    $m5->setAccessible(true);
    $grid = $m5->invoke($ctrl);
    echo "5. grid: " . (is_object($grid) ? "PASS" : "FAIL") . "\n";

    // Test 6: form
    $m6 = new ReflectionMethod($ctrl, 'form');
    $m6->setAccessible(true);
    $form = $m6->invoke($ctrl);
    echo "6. form: " . (is_object($form) ? "PASS" : "FAIL") . "\n";

    // Test 7: detail
    $firstSub = \App\Models\Subscription::first();
    if ($firstSub) {
        $m7 = new ReflectionMethod($ctrl, 'detail');
        $m7->setAccessible(true);
        $show = $m7->invoke($ctrl, $firstSub->id);
        echo "7. detail(#{$firstSub->id}): " . (is_object($show) ? "PASS" : "FAIL") . "\n";
    } else {
        echo "7. detail: SKIP (no subscriptions)\n";
    }

    // Test 8: adminAction method exists
    echo "8. adminAction method: " . (method_exists($ctrl, 'adminAction') ? "PASS" : "FAIL") . "\n";

    echo "\nAll tests completed.\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    $trace = explode("\n", $e->getTraceAsString());
    echo implode("\n", array_slice($trace, 0, 8)) . "\n";
}
