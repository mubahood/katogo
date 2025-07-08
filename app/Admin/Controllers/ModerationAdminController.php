<?php

namespace App\Admin\Controllers;

use App\Models\ContentReport;
use App\Models\UserBlock;
use App\Models\ContentModerationLog;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

class ModerationAdminController extends AdminController
{
    /**
     * Title for current resource.
     */
    protected $title = 'Content Moderation';

    /**
     * Moderation dashboard
     */
    public function index(Content $content)
    {
        return $content
            ->title('Content Moderation Dashboard')
            ->description('Overview of all moderation activities')
            ->body($this->dashboard());
    }

    /**
     * Content Reports Grid
     */
    public function reports(Content $content)
    {
        // Get filter parameters
        $status = request('status');
        $contentType = request('content_type');
        $reason = request('reason');

        // Build query
        $query = ContentReport::with(['reporter', 'target_user']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate stats
        $stats = [
            'total_reports' => ContentReport::count(),
            'pending_reports' => ContentReport::where('status', 'pending')->count(),
            'resolved_reports' => ContentReport::where('status', 'resolved')->count(),
            'urgent_reports' => ContentReport::where('priority', 'urgent')->count(),
        ];

        return $content
            ->title('Content Reports')
            ->description('Manage content reports from users')
            ->view('admin.moderation.reports', compact('reports', 'stats'));
    }

    /**
     * User Blocks Grid
     */
    public function blocks(Content $content)
    {
        // Get filter parameters
        $status = request('status');
        $search = request('search');
        $dateRange = request('date_range');

        // Build query
        $query = UserBlock::with(['blocker', 'blockedUser']);

        if ($status) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } else {
                $query->where('is_active', false);
            }
        }

        if ($search) {
            $query->whereHas('blocker', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('blockedUser', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($dateRange) {
            switch ($dateRange) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
            }
        }

        $blocks = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate stats
        $stats = [
            'total_blocks' => UserBlock::count(),
            'active_blocks' => UserBlock::where('is_active', true)->count(),
            'today_blocks' => UserBlock::whereDate('created_at', today())->count(),
            'unique_blockers' => UserBlock::distinct('blocker_id')->count(),
        ];

        return $content
            ->title('User Blocks')
            ->description('View and manage user blocks')
            ->view('admin.moderation.blocks', compact('blocks', 'stats'));
    }

    /**
     * Moderation Logs Grid
     */
    public function logs(Content $content)
    {
        // Get filter parameters
        $action = request('action');
        $actorType = request('actor_type');
        $contentType = request('content_type');
        $dateRange = request('date_range');
        $search = request('search');

        // Build query
        $query = ContentModerationLog::with(['actor', 'target_user']);

        if ($action) {
            $query->where('action', $action);
        }

        if ($actorType) {
            $query->where('actor_type', $actorType);
        }

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        if ($search) {
            $query->whereHas('actor', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('content_id', 'like', "%{$search}%");
        }

        if ($dateRange) {
            switch ($dateRange) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
            }
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate stats
        $stats = [
            'total_logs' => ContentModerationLog::count(),
            'today_logs' => ContentModerationLog::whereDate('created_at', today())->count(),
            'admin_actions' => ContentModerationLog::where('actor_type', 'admin')->count(),
            'system_actions' => ContentModerationLog::where('actor_type', 'system')->count(),
        ];

        return $content
            ->title('Moderation Logs')
            ->description('View all moderation actions taken')
            ->view('admin.moderation.logs', compact('logs', 'stats'));
    }

    /**
     * Moderation Statistics
     */
    public function statistics(Content $content)
    {
        $period = request('period', 7);
        
        // Basic stats
        $stats = [
            'total_reports' => ContentReport::count(),
            'pending_reports' => ContentReport::where('status', 'pending')->count(),
            'total_blocks' => UserBlock::count(),
            'resolved_reports' => ContentReport::where('status', 'resolved')->count(),
            'total_logs' => ContentModerationLog::count(),
        ];

        // Calculate resolution rate
        $totalReports = $stats['total_reports'];
        $resolvedReports = $stats['resolved_reports'];
        $stats['resolution_rate'] = $totalReports > 0 ? ($resolvedReports / $totalReports) * 100 : 0;

        // Chart data preparation
        $chart_data = [
            'reports_timeline' => $this->getReportsTimeline($period),
            'status_distribution' => $this->getStatusDistribution(),
            'content_types' => $this->getContentTypesData(),
            'reasons' => $this->getReasonsData(),
        ];

        // Performance metrics
        $metrics = [
            'avg_response_time' => $this->getAverageResponseTime(),
            'resolution_rate' => $stats['resolution_rate'],
            'active_moderators' => User::whereHas('roles', function($q) {
                $q->where('slug', 'admin');
            })->count(),
            'backlog_size' => ContentReport::where('status', 'pending')->count(),
        ];

        // Top reporters
        $top_reporters = User::withCount(['content_reports'])
            ->having('content_reports_count', '>', 0)
            ->orderBy('content_reports_count', 'desc')
            ->limit(10)
            ->get();

        // Most reported content
        $most_reported = ContentReport::select('content_type', 'content_id')
            ->selectRaw('COUNT(*) as reports_count')
            ->groupBy('content_type', 'content_id')
            ->orderBy('reports_count', 'desc')
            ->limit(10)
            ->get();

        return $content
            ->title('Moderation Statistics')
            ->description('Comprehensive analytics and trends for content moderation')
            ->view('admin.moderation.statistics', compact(
                'stats', 'chart_data', 'metrics', 'top_reporters', 'most_reported'
            ));
    }

    /**
     * Dashboard view
     */
    protected function dashboard()
    {
        $stats = [
            'pending_reports' => ContentReport::where('status', 'pending')->count(),
            'total_reports' => ContentReport::count(),
            'active_blocks' => UserBlock::active()->count(),
            'total_blocks' => UserBlock::count(),
            'reports_today' => ContentReport::whereDate('created_at', today())->count(),
            'blocks_today' => UserBlock::whereDate('created_at', today())->count(),
        ];

        $recentReports = ContentReport::with(['reporter', 'reportedUser'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentActions = ContentModerationLog::with(['user', 'moderator'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.moderation.dashboard', compact('stats', 'recentReports', 'recentActions'));
    }

    /**
     * Reports grid configuration
     */
    protected function reportsGrid()
    {
        $grid = new Grid(new ContentReport());

        $grid->column('id', 'ID')->sortable();
        $grid->column('reporter.name', 'Reporter');
        $grid->column('reported_content_type', 'Content Type')->using([
            'movie' => 'Movie',
            'user' => 'User',
            'comment' => 'Comment',
            'chat_message' => 'Chat Message',
            'other' => 'Other',
        ])->label([
            'movie' => 'primary',
            'user' => 'warning',
            'comment' => 'info',
            'chat_message' => 'success',
            'other' => 'default',
        ]);
        
        $grid->column('report_type', 'Report Type')->using([
            'copyright_infringement' => 'Copyright',
            'request_delete_movie' => 'Delete Request',
            'inappropriate_content' => 'Inappropriate',
            'adult_content' => 'Adult Content',
            'violence' => 'Violence',
            'hate_speech' => 'Hate Speech',
            'harassment' => 'Harassment',
            'spam' => 'Spam',
            'false_information' => 'False Info',
            'privacy_violation' => 'Privacy',
            'illegal_content' => 'Illegal',
            'other' => 'Other',
        ])->label([
            'copyright_infringement' => 'danger',
            'request_delete_movie' => 'warning',
            'inappropriate_content' => 'warning',
            'adult_content' => 'danger',
            'violence' => 'danger',
            'hate_speech' => 'danger',
            'harassment' => 'warning',
            'spam' => 'info',
            'false_information' => 'warning',
            'privacy_violation' => 'warning',
            'illegal_content' => 'danger',
            'other' => 'default',
        ]);

        $grid->column('status', 'Status')->using([
            'pending' => 'Pending Review',
            'under_review' => 'Under Review',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
        ])->label([
            'pending' => 'warning',
            'under_review' => 'info',
            'resolved' => 'success',
            'dismissed' => 'default',
        ]);

        $grid->column('admin_action', 'Admin Action')->using([
            'content_removed' => 'Content Removed',
            'user_warned' => 'User Warned',
            'user_suspended' => 'User Suspended',
            'user_banned' => 'User Banned',
            'content_approved' => 'Content Approved',
            'no_action' => 'No Action',
            'under_investigation' => 'Under Investigation',
            'escalated' => 'Escalated',
        ])->label([
            'content_removed' => 'danger',
            'user_warned' => 'warning',
            'user_suspended' => 'danger',
            'user_banned' => 'danger',
            'content_approved' => 'success',
            'no_action' => 'info',
            'under_investigation' => 'warning',
            'escalated' => 'danger',
        ]);

        $grid->column('created_at', 'Reported At')->display(function ($date) {
            return $date ? $date->format('Y-m-d H:i') : '';
        })->sortable();

        $grid->column('action_taken_at', 'Action Taken At')->display(function ($date) {
            return $date ? $date->format('Y-m-d H:i') : 'Pending';
        });

        // Filters
        $grid->filter(function($filter) {
            $filter->disableIdFilter();
            
            $filter->equal('status', 'Status')->select([
                'pending' => 'Pending',
                'under_review' => 'Under Review', 
                'resolved' => 'Resolved',
                'dismissed' => 'Dismissed',
            ]);

            $filter->equal('report_type', 'Report Type')->select([
                'copyright_infringement' => 'Copyright Infringement',
                'request_delete_movie' => 'Request to Delete Movie',
                'inappropriate_content' => 'Inappropriate Content',
                'adult_content' => 'Adult Content',
                'violence' => 'Violence',
                'hate_speech' => 'Hate Speech',
                'harassment' => 'Harassment',
                'spam' => 'Spam',
                'false_information' => 'False Information',
                'privacy_violation' => 'Privacy Violation',
                'illegal_content' => 'Illegal Content',
                'other' => 'Other',
            ]);

            $filter->equal('reported_content_type', 'Content Type')->select([
                'movie' => 'Movie',
                'user' => 'User',
                'comment' => 'Comment',
                'chat_message' => 'Chat Message',
                'other' => 'Other',
            ]);

            $filter->between('created_at', 'Reported Date')->date();
        });

        // Bulk actions
        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

        // Row actions
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            // Edit action allows moderators to review and take action on reports
        });

        $grid->model()->orderBy('created_at', 'desc');

        return $grid;
    }

    /**
     * User blocks grid configuration
     */
    protected function blocksGrid()
    {
        $grid = new Grid(new UserBlock());

        $grid->column('id', 'ID')->sortable();
        $grid->column('blocker.name', 'Blocker');
        $grid->column('blockedUser.name', 'Blocked User');
        $grid->column('reason', 'Reason');
        $grid->column('status', 'Status')->using([
            'active' => 'Active',
            'expired' => 'Expired',
            'removed' => 'Removed',
        ])->label([
            'active' => 'danger',
            'expired' => 'warning',
            'removed' => 'success',
        ]);
        $grid->column('created_at', 'Blocked At')->display(function ($date) {
            return $date ? $date->format('Y-m-d H:i') : '';
        })->sortable();
        $grid->column('expires_at', 'Expires At')->display(function ($date) {
            return $date ? $date->format('Y-m-d H:i') : 'Permanent';
        });

        $grid->filter(function($filter) {
            $filter->disableIdFilter();
            $filter->equal('status', 'Status')->select([
                'active' => 'Active',
                'expired' => 'Expired',
                'removed' => 'Removed',
            ]);
            $filter->between('created_at', 'Block Date')->date();
        });

        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            $actions->disableDelete();
            $actions->disableEdit();
        });

        $grid->model()->orderBy('created_at', 'desc');

        return $grid;
    }

    /**
     * Moderation logs grid configuration
     */
    protected function logsGrid()
    {
        $grid = new Grid(new ContentModerationLog());

        $grid->column('id', 'ID')->sortable();
        $grid->column('user.name', 'User');
        $grid->column('moderator.name', 'Moderator');
        $grid->column('action_type', 'Action')->label([
            'content_reported' => 'info',
            'user_blocked' => 'warning',
            'user_unblocked' => 'success',
            'content_removed' => 'danger',
            'user_warned' => 'warning',
            'user_suspended' => 'danger',
            'user_banned' => 'danger',
            'legal_consent_updated' => 'info',
        ]);
        $grid->column('content_type', 'Content Type');
        $grid->column('severity', 'Severity')->using([
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ])->label([
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
        ]);
        $grid->column('source', 'Source');
        $grid->column('created_at', 'Date')->display(function ($date) {
            return $date ? $date->format('Y-m-d H:i') : '';
        })->sortable();

        $grid->filter(function($filter) {
            $filter->disableIdFilter();
            $filter->equal('action_type', 'Action Type');
            $filter->equal('severity', 'Severity')->select([
                'low' => 'Low',
                'medium' => 'Medium', 
                'high' => 'High',
                'critical' => 'Critical',
            ]);
            $filter->between('created_at', 'Date')->date();
        });

        $grid->disableCreateButton();
        $grid->disableActions();

        $grid->model()->orderBy('created_at', 'desc');

        return $grid;
    }

    /**
     * Make a grid.
     */
    protected function grid()
    {
        return $this->reportsGrid();
    }

    /**
     * Make a show layout.
     */
    protected function detail($id)
    {
        $show = new Show(ContentReport::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('reporter.name', 'Reporter');
        $show->field('reportedUser.name', 'Reported User');
        $show->field('reported_content_type', 'Content Type');
        $show->field('reported_content_id', 'Content ID');
        $show->field('report_type', 'Report Type');
        $show->field('description', 'Description');
        $show->field('status', 'Status');
        $show->field('admin_action', 'Admin Action');
        $show->field('moderator_notes', 'Moderator Notes');
        $show->field('moderator.name', 'Moderator');
        $show->field('created_at', 'Created At');
        $show->field('action_taken_at', 'Action Taken At');

        return $show;
    }

    /**
     * Make a form.
     */
    protected function form()
    {
        $form = new Form(new ContentReport());

        $form->display('id', 'ID');
        $form->display('reporter.name', 'Reporter');
        $form->display('reported_content_type', 'Content Type');
        $form->display('report_type', 'Report Type');
        $form->display('description', 'Description');
        
        $form->select('status', 'Status')->options([
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
        ])->required();

        $form->select('admin_action', 'Admin Action')->options([
            'content_removed' => 'Content Removed',
            'user_warned' => 'User Warned',
            'user_suspended' => 'User Suspended',
            'user_banned' => 'User Banned',
            'content_approved' => 'Content Approved',
            'no_action' => 'No Action Required',
            'under_investigation' => 'Under Investigation',
            'escalated' => 'Escalated',
        ]);

        $form->textarea('moderator_notes', 'Moderator Notes');

        $form->saving(function (Form $form) {
            $form->moderator_id = auth()->id();
            $form->action_taken_at = now();
        });

        return $form;
    }

    /**
     * Get reports timeline data
     */
    private function getReportsTimeline($days)
    {
        $reports = ContentReport::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');
            $data[] = $reports->where('date', $date)->first()->count ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get status distribution data
     */
    private function getStatusDistribution()
    {
        $distribution = ContentReport::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return [
            'labels' => $distribution->pluck('status')->map(function($status) {
                return ucfirst($status);
            })->toArray(),
            'data' => $distribution->pluck('count')->toArray()
        ];
    }

    /**
     * Get content types data
     */
    private function getContentTypesData()
    {
        $types = ContentReport::selectRaw('content_type, COUNT(*) as count')
            ->groupBy('content_type')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'labels' => $types->pluck('content_type')->map(function($type) {
                return ucfirst($type);
            })->toArray(),
            'data' => $types->pluck('count')->toArray()
        ];
    }

    /**
     * Get reasons data
     */
    private function getReasonsData()
    {
        $reasons = ContentReport::selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $reasons->pluck('reason')->map(function($reason) {
                return ucwords(str_replace('_', ' ', $reason));
            })->toArray(),
            'data' => $reasons->pluck('count')->toArray()
        ];
    }

    /**
     * Calculate average response time
     */
    private function getAverageResponseTime()
    {
        $avgTime = ContentReport::whereNotNull('updated_at')
            ->where('status', '!=', 'pending')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours')
            ->first();

        return $avgTime ? round($avgTime->avg_hours, 1) : 0;
    }

    /**
     * Get detailed report data (AJAX)
     */
    public function getReport($id)
    {
        $report = ContentReport::with(['reporter', 'target_user'])->findOrFail($id);
        return response()->json($report);
    }

    /**
     * Get detailed block data (AJAX)
     */
    public function getBlock($id)
    {
        $block = UserBlock::with(['blocker', 'blockedUser'])->findOrFail($id);
        return response()->json($block);
    }

    /**
     * Get detailed log data (AJAX)
     */
    public function getLog($id)
    {
        $log = ContentModerationLog::with(['actor', 'target_user'])->findOrFail($id);
        return response()->json($log);
    }

    /**
     * Update report status
     */
    public function updateReportStatus(Request $request, $id)
    {
        $report = ContentReport::findOrFail($id);
        $oldStatus = $report->status;
        $report->status = $request->status;
        $report->updated_at = now();
        $report->save();

        // Log the action
        ContentModerationLog::create([
            'action' => 'report_updated',
            'actor_id' => auth()->id(),
            'actor_type' => 'admin',
            'content_type' => 'report',
            'content_id' => $report->id,
            'details' => ['old_status' => $oldStatus, 'new_status' => $request->status],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Unblock user
     */
    public function unblockUser(Request $request, $id)
    {
        $block = UserBlock::findOrFail($id);
        $block->is_active = false;
        $block->unblocked_at = now();
        $block->save();

        // Log the action
        ContentModerationLog::create([
            'action' => 'user_unblocked',
            'actor_id' => auth()->id(),
            'actor_type' => 'admin',
            'content_type' => 'user',
            'content_id' => $block->blocked_user_id,
            'target_user_id' => $block->blocked_user_id,
            'details' => ['block_id' => $block->id, 'reason' => 'admin_action'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete block record
     */
    public function deleteBlock(Request $request, $id)
    {
        $block = UserBlock::findOrFail($id);
        
        // Log the action before deletion
        ContentModerationLog::create([
            'action' => 'block_deleted',
            'actor_id' => auth()->id(),
            'actor_type' => 'admin',
            'content_type' => 'user',
            'content_id' => $block->blocked_user_id,
            'target_user_id' => $block->blocked_user_id,
            'details' => ['block_id' => $block->id, 'blocker_id' => $block->blocker_id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $block->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Export statistics
     */
    public function exportStatistics(Request $request)
    {
        $period = $request->get('period', 7);
        
        // Prepare export data
        $data = [
            'reports' => ContentReport::with(['reporter'])->get(),
            'blocks' => UserBlock::with(['blocker', 'blockedUser'])->get(),
            'logs' => ContentModerationLog::with(['actor'])->get(),
        ];

        // Return CSV download
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="moderation_stats_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Reports section
            fputcsv($file, ['CONTENT REPORTS']);
            fputcsv($file, ['ID', 'Reporter', 'Content Type', 'Content ID', 'Reason', 'Status', 'Created']);
            foreach ($data['reports'] as $report) {
                fputcsv($file, [
                    $report->id,
                    $report->reporter->name ?? 'Unknown',
                    $report->content_type,
                    $report->content_id,
                    $report->reason,
                    $report->status,
                    $report->created_at->format('Y-m-d H:i:s')
                ]);
            }
            
            fputcsv($file, []);
            
            // Blocks section
            fputcsv($file, ['USER BLOCKS']);
            fputcsv($file, ['ID', 'Blocker', 'Blocked User', 'Reason', 'Active', 'Created']);
            foreach ($data['blocks'] as $block) {
                fputcsv($file, [
                    $block->id,
                    $block->blocker->name ?? 'Unknown',
                    $block->blockedUser->name ?? 'Unknown',
                    $block->reason ?? '',
                    $block->is_active ? 'Yes' : 'No',
                    $block->created_at->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
