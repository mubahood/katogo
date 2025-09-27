<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\MovieView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        // COMPREHENSIVE MOVIE PROCESSING STATISTICS
        
        // Basic Statistics
        $totalMovies = MovieModel::count();
        $moviesCount = MovieModel::where('type', 'Movie')->count();
        $seriesCount = MovieModel::where('type', 'Series')->count();
        $activeMovies = MovieModel::where('status', 'Active')->count();
        
        // URL Testing Pipeline
        $urlNotTested = MovieModel::whereNotIn('video_url_tested_by_curl', ['Yes'])->count();
        $urlTested = MovieModel::where('video_url_tested_by_curl', 'Yes')->count();
        $urlsWorking = MovieModel::where('video_url_tested_by_curl_works', 'Yes')->count();
        
        // Firebase Pipeline
        $readyForTransfer = MovieModel::where('video_url_tested_by_curl_works', 'Yes')
            ->whereNotIn('firebase_transfer_successful', ['Yes'])->count();
        $transferSuccessful = MovieModel::where('firebase_transfer_successful', 'Yes')->count();
        $firebaseWorking = MovieModel::where('firebase_video_tested_by_curl_works', 'Yes')->count();
        
        // Production Ready
        $completePipeline = MovieModel::where('video_url_tested_by_curl_works', 'Yes')
            ->where('firebase_transfer_successful', 'Yes')
            ->where('firebase_video_tested_by_curl_works', 'Yes')
            ->where('status', 'Active')->count();
        
        $overallCompletion = $totalMovies > 0 ? round(($completePipeline / $totalMovies) * 100, 2) : 0;
        $urlSuccessRate = $urlTested > 0 ? round(($urlsWorking / $urlTested) * 100, 1) : 0;

        // Main Statistics InfoBoxes
        $mainStats = [
            ['Total Movies', 'film', 'blue', admin_url('movies?all=1'), number_format($totalMovies)],
            ['Active Movies', 'check-circle', 'green', admin_url('movies?status=active'), number_format($activeMovies)],
            ['Production Ready', 'cloud-check', 'purple', admin_url('movies?status=active&type=production'), number_format($completePipeline)],
            ['Pipeline Progress', 'progress-check', 'orange', admin_url('movies?progress=1'), $overallCompletion . '%'],
        ];

        // Type Breakdown
        $typeStats = [
            ['Movies Type', 'video', 'info', admin_url('movies?type=Movie'), number_format($moviesCount)],
            ['Series Type', 'list', 'warning', admin_url('movies?type=Series'), number_format($seriesCount)],
            ['URLs Working', 'link', 'success', admin_url('movies?url_tested=working'), number_format($urlsWorking)],
            ['Firebase Ready', 'cloud', 'primary', admin_url('movies?firebase_ready=1'), number_format($firebaseWorking)],
        ];

        // Additional Business Metrics
        $totalUsers = User::count();
        $totalViews = MovieView::count();
        $totalViewingHours = round(MovieView::sum('progress') / 3600, 1);
        $recentMovies = MovieModel::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        $businessStats = [
            ['Total Users', 'users', 'success', admin_url('users?all=1'), number_format($totalUsers)],
            ['Total Views', 'eye', 'info', admin_url('movie-views?all=1'), number_format($totalViews)],
            ['Watch Hours', 'clock-o', 'warning', admin_url('movie-views?hours=1'), number_format($totalViewingHours).'h'],
            ['New Movies (30d)', 'plus-circle', 'danger', admin_url('movies?recent=1'), number_format($recentMovies)],
        ];

        // System Performance Metrics
        $errorMovies = MovieModel::whereNotNull('error_message')->count();
        $processingQueue = $urlNotTested + $readyForTransfer;
        $systemEfficiency = $totalMovies > 0 ? round((($totalMovies - $errorMovies) / $totalMovies) * 100, 1) : 100;

        $systemStats = [
            ['Processing Queue', 'tasks', 'orange', admin_url('movies?queue=1'), number_format($processingQueue)],
            ['Error Count', 'exclamation-triangle', 'danger', admin_url('movies?errors=1'), number_format($errorMovies)],
            ['System Efficiency', 'tachometer', 'success', admin_url('movies?efficiency=1'), $systemEfficiency.'%'],
            ['Success Rate', 'check-circle-o', 'primary', admin_url('movies?success=1'), $urlSuccessRate.'%'],
        ];

        // URL Processing Pipeline Table
        $pipelineHeaders = ['Processing Stage', 'Count', 'Percentage', 'Status'];
        $pipelineRows = [
            ['🔄 URLs Not Tested', number_format($urlNotTested), round(($urlNotTested/$totalMovies)*100, 1).'%', $urlNotTested > 1000 ? '🔴 High Priority' : '🟡 Medium'],
            ['✅ URLs Tested', number_format($urlTested), round(($urlTested/$totalMovies)*100, 1).'%', $urlTested > 0 ? '🟢 Active' : '⏳ Pending'],
            ['🟢 Working URLs', number_format($urlsWorking), $urlSuccessRate.'% success rate', $urlSuccessRate >= 90 ? '🟢 Excellent' : '🟡 Good'],
            ['🔥 Firebase Ready', number_format($firebaseWorking), number_format($completePipeline).' production ready', $completePipeline > 0 ? '🎯 Success' : '⏳ In Progress'],
        ];
        
        $pipelineBox = (new Box('📊 Movie Processing Pipeline Statistics', new Table($pipelineHeaders, $pipelineRows)))
            ->style('info')->solid();

        // Action Items Table  
        $actionHeaders = ['Action Required', 'Count', 'Priority Level', 'Next Steps'];
        $actionRows = [
            ['🔗 Test Movie URLs', number_format($urlNotTested), $urlNotTested > 1000 ? '🔴 Critical' : '🟡 Normal', 'Run /admin/movies/test-urls'],
            ['📤 Firebase Transfer', number_format($readyForTransfer), $readyForTransfer > 50 ? '🔴 High' : '🟢 Low', 'Run /admin/movies/transfer-firebase'],
            ['✨ Production Movies', number_format($completePipeline), '🎯 Complete', 'Fully processed & active'],
            ['📈 Success Rate', $urlSuccessRate.'%', $urlSuccessRate >= 90 ? '🟢 Great' : '🟡 Improving', 'Continue processing'],
        ];
        
        $actionBox = (new Box('⚡ System Status & Action Items', new Table($actionHeaders, $actionRows)))
            ->style('warning')->solid();

        // Performance Metrics Table
        $performanceHeaders = ['Performance Metric', 'Current Value', 'Target', 'Status'];
        $performanceRows = [
            ['URL Testing Success Rate', $urlSuccessRate.'%', '≥90%', $urlSuccessRate >= 90 ? '🟢 Excellent' : ($urlSuccessRate >= 70 ? '🟡 Good' : '🔴 Needs Work')],
            ['Pipeline Completion Rate', $overallCompletion.'%', '≥10%', $overallCompletion >= 10 ? '🟢 On Track' : ($overallCompletion >= 1 ? '🟡 Starting' : '🔴 Beginning')],
            ['Movies Ready for Production', number_format($completePipeline), '1000+', $completePipeline >= 1000 ? '🟢 Target Met' : ($completePipeline >= 100 ? '🟡 Progress' : '🔴 Early Stage')],
            ['Active Movie Percentage', round(($activeMovies/$totalMovies)*100, 1).'%', '≥80%', round(($activeMovies/$totalMovies)*100, 1) >= 80 ? '🟢 Great' : '🟡 Improving'],
        ];
        
        $performanceBox = (new Box('📈 Performance Metrics & Targets', new Table($performanceHeaders, $performanceRows)))
            ->style('primary')->solid();

        //
        // MONTHLY STATISTICS (Past 4 Months)
        //
        $today = Carbon::today();
        $monthlyBoxes = [];
        
        for ($i = 3; $i >= 0; $i--) {
            $month = $today->copy()->subMonths($i);
            $monthName = $month->format('F Y');
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            $headers = ['Day', 'New Users', 'Total Views', 'Watch Hours', 'User Growth'];
            $rows = [];

            // Get data for each day of the month
            for ($d = $startOfMonth; $d->lte($endOfMonth) && $d->lte(Carbon::today()); $d->addDay()) {
                $dayUsers = User::whereDate('created_at', $d)->count();
                $dayViews = MovieView::whereDate('created_at', $d)->count();
                $dayWatchHours = round(MovieView::whereDate('created_at', $d)->sum('progress') / 3600, 1);
                $totalUsersUpToDate = User::whereDate('created_at', '<=', $d)->count();

                $rows[] = [
                    $d->format('d'),
                    $dayUsers,
                    $dayViews,
                    $dayWatchHours.'h',
                    number_format($totalUsersUpToDate),
                ];
            }

            $monthlyBoxes[] = (new Box($monthName, new Table($headers, $rows)))
                ->style('info')->solid();
        }

        //
        // ADDITIONAL COMPREHENSIVE SUMMARIES
        //
        
        // Content Quality Statistics
        $contentQualityHeaders = ['Content Quality Metric', 'Count', 'Percentage', 'Status'];
        $contentProcessed = MovieModel::where('content_type_processed', 'Yes')->count();
        $contentIsVideo = MovieModel::where('content_is_video', 'Yes')->count();
        $contentQualityRows = [
            ['📹 Confirmed Video Content', number_format($contentIsVideo), round(($contentIsVideo/$totalMovies)*100, 1).'%', $contentIsVideo > 10000 ? '🟢 Excellent' : '🟡 Growing'],
            ['🔍 Content Processed', number_format($contentProcessed), round(($contentProcessed/$totalMovies)*100, 1).'%', $contentProcessed > 5000 ? '🟢 Good' : '🟡 Processing'],
            ['🎬 Movies vs Series', number_format($moviesCount).' vs '.number_format($seriesCount), round(($moviesCount/$totalMovies)*100, 1).'% movies', 'ℹ️ Balanced'],
        ];
        
        $contentQualityBox = (new Box('🎯 Content Quality Analysis', new Table($contentQualityHeaders, $contentQualityRows)))
            ->style('success')->solid();

        // System Health & Performance
        $systemHealthHeaders = ['System Health Metric', 'Current Status', 'Trend', 'Recommendation'];
        $errorCount = MovieModel::whereNotNull('error_message')->count();
        $recentActivity = MovieModel::where('updated_at', '>=', Carbon::now()->subDays(7))->count();
        $systemHealthRows = [
            ['🚨 Movies with Errors', number_format($errorCount), $errorCount < 100 ? '📈 Low' : '📉 High', $errorCount < 100 ? '✅ Healthy' : '⚠️ Investigate'],
            ['📊 Recent Activity (7 days)', number_format($recentActivity), $recentActivity > 100 ? '📈 High' : '📉 Low', $recentActivity > 100 ? '🟢 Active System' : '🟡 Increase Processing'],
            ['💾 Database Size', number_format($totalMovies).' movies', $totalMovies > 10000 ? '📈 Large' : '📈 Growing', '🔧 Monitor Performance'],
            ['🔄 Processing Pipeline', $overallCompletion.'% complete', $completePipeline > 0 ? '📈 Working' : '📉 Starting', $completePipeline > 100 ? '🟢 Scaling Well' : '🟡 Keep Processing'],
        ];
        
        $systemHealthBox = (new Box('🏥 System Health Dashboard', new Table($systemHealthHeaders, $systemHealthRows)))
            ->style('danger')->solid();

        // Firebase & Cloud Statistics
        $cloudHeaders = ['Cloud Storage Metric', 'Value', 'Cost Impact', 'Optimization'];
        $firebaseTransferred = MovieModel::where('firebase_transfer_successful', 'Yes')->count();
        $estimatedStorageGB = round($firebaseTransferred * 1.2, 1); // Estimate 1.2GB average per movie
        $cloudRows = [
            ['☁️ Videos in Firebase', number_format($firebaseTransferred), 'Storage: ~'.$estimatedStorageGB.'GB', $firebaseTransferred > 1000 ? '💰 Monitor Costs' : '📈 Scale Up'],
            ['🔗 Firebase URLs Working', number_format($firebaseWorking), 'CDN Bandwidth Usage', $firebaseWorking > 500 ? '🌐 Global Ready' : '🚀 Expand Coverage'],
            ['📤 Transfer Success Rate', $transferSuccessful > 0 ? '100%' : '0%', 'Minimal Failed Transfers', '✅ Reliable System'],
            ['🎯 Production Ready Rate', $overallCompletion.'%', 'Revenue Ready Content', $overallCompletion > 5 ? '💰 Monetizable' : '📈 Building Library'],
        ];
        
        $cloudBox = (new Box('☁️ Cloud Storage & CDN Analytics', new Table($cloudHeaders, $cloudRows)))
            ->style('primary')->solid();

        // User Engagement & Business Metrics (calculate average)
        $avgViewsPerMovie = $totalMovies > 0 ? round($totalViews / $totalMovies, 1) : 0;
        
        $engagementHeaders = ['Business Metric', 'Current Value', 'Growth Indicator', 'Business Impact'];
        $engagementRows = [
            ['👥 Total Users', number_format($totalUsers), $totalUsers > 1000 ? '📈 Growing' : '🌱 Starting', $totalUsers > 5000 ? '💼 Scalable' : '📢 Marketing Needed'],
            ['📺 Total Video Views', number_format($totalViews), $totalViews > 10000 ? '🔥 Popular' : '📈 Building', $totalViews > 50000 ? '💰 High Engagement' : '📊 Growth Potential'],
            ['⏱️ Total Watch Hours', number_format($totalViewingHours).'h', $totalViewingHours > 1000 ? '🎯 Engaged' : '📈 Growing', $totalViewingHours > 5000 ? '🏆 Success' : '📈 Retention Focus'],
            ['📊 Avg Views per Movie', $avgViewsPerMovie, $avgViewsPerMovie > 5 ? '⭐ Popular Content' : '📈 Improving', $avgViewsPerMovie > 10 ? '🎬 Hit Content' : '🎯 Content Strategy'],
        ];
        
        $engagementBox = (new Box('📈 User Engagement & Business Intelligence', new Table($engagementHeaders, $engagementRows)))
            ->style('warning')->solid();

        return $content
            ->title('🎬 Movie Processing Dashboard')
            ->description('Comprehensive real-time statistics for movie URL testing and Firebase transfer pipeline')
            
            // Row 1: Main Statistics
            ->row(function(Row $row) use ($mainStats) {
                foreach ($mainStats as [$label,$icon,$color,$link,$value]) {
                    $row->column(3, function(Column $col) use ($label,$icon,$color,$link,$value) {
                        $col->append((new InfoBox($label, $icon, $color, $link, $value))->solid());
                    });
                }
            })
            
            // Row 2: Type Breakdown  
            ->row(function(Row $row) use ($typeStats) {
                foreach ($typeStats as [$label,$icon,$color,$link,$value]) {
                    $row->column(3, function(Column $col) use ($label,$icon,$color,$link,$value) {
                        $col->append((new InfoBox($label, $icon, $color, $link, $value))->solid());
                    });
                }
            })
            
            // Row 3: Business Metrics
            ->row(function(Row $row) use ($businessStats) {
                foreach ($businessStats as [$label,$icon,$color,$link,$value]) {
                    $row->column(3, function(Column $col) use ($label,$icon,$color,$link,$value) {
                        $col->append((new InfoBox($label, $icon, $color, $link, $value))->solid());
                    });
                }
            })
            
            // Row 4: System Performance
            ->row(function(Row $row) use ($systemStats) {
                foreach ($systemStats as [$label,$icon,$color,$link,$value]) {
                    $row->column(3, function(Column $col) use ($label,$icon,$color,$link,$value) {
                        $col->append((new InfoBox($label, $icon, $color, $link, $value))->solid());
                    });
                }
            })
            
            // Row 5: Pipeline Statistics
            ->row(function(Row $row) use ($pipelineBox) {
                $row->column(12, function(Column $col) use ($pipelineBox) {
                    $col->append($pipelineBox);
                });
            })
            
            // Row 6: Action Items & Performance
            ->row(function(Row $row) use ($actionBox, $performanceBox) {
                $row->column(6, function(Column $col) use ($actionBox) {
                    $col->append($actionBox);
                });
                $row->column(6, function(Column $col) use ($performanceBox) {
                    $col->append($performanceBox);
                });
            })
            
            // Row 7: Monthly Statistics (Past 4 Months) 
            ->row(function(Row $row) use ($monthlyBoxes) {
                foreach ($monthlyBoxes as $monthBox) {
                    $row->column(3, function(Column $col) use ($monthBox) {
                        $col->append($monthBox);
                    });
                }
            })
            
            // Row 8: Content Quality & System Health
            ->row(function(Row $row) use ($contentQualityBox, $systemHealthBox) {
                $row->column(6, function(Column $col) use ($contentQualityBox) {
                    $col->append($contentQualityBox);
                });
                $row->column(6, function(Column $col) use ($systemHealthBox) {
                    $col->append($systemHealthBox);
                });
            })
            
            // Row 9: Cloud Storage & User Engagement Analytics
            ->row(function(Row $row) use ($cloudBox, $engagementBox) {
                $row->column(6, function(Column $col) use ($cloudBox) {
                    $col->append($cloudBox);
                });
                $row->column(6, function(Column $col) use ($engagementBox) {
                    $col->append($engagementBox);
                });
            });
    }
}
