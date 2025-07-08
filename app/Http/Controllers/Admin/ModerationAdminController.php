<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\UserBlock;
use App\Models\ContentModerationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModerationAdminController extends Controller
{
    /**
     * Display moderation dashboard
     */
    public function index()
    {
        $stats = [
            'pending_reports' => ContentReport::where('status', 'pending')->count(),
            'total_reports' => ContentReport::count(),
            'active_blocks' => UserBlock::active()->count(),
            'total_blocks' => UserBlock::count(),
            'recent_actions' => ContentModerationLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        return view('admin.moderation.index', compact('stats'));
    }

    /**
     * Display all content reports
     */
    public function reports(Request $request)
    {
        $query = ContentReport::with(['reporter', 'reportedUser', 'moderator'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by report type
        if ($request->filled('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        // Filter by content type
        if ($request->filled('content_type')) {
            $query->where('reported_content_type', $request->content_type);
        }

        // Search by reporter or content
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('reporter', function($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reported_content_id', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(20);
        
        $reportTypes = ContentReport::select('report_type')
            ->distinct()
            ->pluck('report_type')
            ->toArray();

        return view('admin.moderation.reports', compact('reports', 'reportTypes'));
    }

    /**
     * Show specific report details
     */
    public function showReport($id)
    {
        $report = ContentReport::with(['reporter', 'reportedUser', 'moderator'])
            ->findOrFail($id);

        return view('admin.moderation.report-detail', compact('report'));
    }

    /**
     * Take action on a report
     */
    public function actionReport(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,remove_content,warn_user,suspend_user,ban_user,dismiss',
            'notes' => 'nullable|string|max:1000',
        ]);

        $report = ContentReport::findOrFail($id);
        
        DB::transaction(function() use ($report, $request) {
            // Update report status
            $report->update([
                'status' => $request->action === 'dismiss' ? 'dismissed' : 'resolved',
                'admin_action' => $request->action,
                'moderator_notes' => $request->notes,
                'moderator_id' => auth()->id(),
                'action_taken_at' => now(),
            ]);

            // Log the moderation action
            ContentModerationLog::create([
                'user_id' => $report->reported_user_id,
                'moderator_id' => auth()->id(),
                'action_type' => $request->action,
                'content_type' => $report->reported_content_type,
                'content_id' => $report->reported_content_id,
                'reason' => $request->notes,
                'severity' => $this->getActionSeverity($request->action),
                'source' => 'admin_panel',
            ]);

            // Take additional actions based on the decision
            $this->executeAction($request->action, $report, $request->notes);
        });

        return redirect()
            ->route('admin.moderation.reports')
            ->with('success', 'Action taken successfully on report #' . $report->id);
    }

    /**
     * Display all user blocks
     */
    public function blocks(Request $request)
    {
        $query = UserBlock::with(['blocker', 'blockedUser'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } else {
                $query->where('status', $request->status);
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('blocker', function($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })->orWhereHas('blockedUser', function($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $blocks = $query->paginate(20);

        return view('admin.moderation.blocks', compact('blocks'));
    }

    /**
     * Display moderation logs
     */
    public function logs(Request $request)
    {
        $query = ContentModerationLog::with(['user', 'moderator'])
            ->orderBy('created_at', 'desc');

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by severity
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);
        
        $actionTypes = ContentModerationLog::select('action_type')
            ->distinct()
            ->pluck('action_type')
            ->toArray();

        return view('admin.moderation.logs', compact('logs', 'actionTypes'));
    }

    /**
     * Display moderation statistics
     */
    public function statistics()
    {
        $stats = [
            'reports_by_type' => ContentReport::select('report_type', DB::raw('count(*) as count'))
                ->groupBy('report_type')
                ->get(),
            'reports_by_status' => ContentReport::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
            'reports_by_month' => ContentReport::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('count(*) as count')
            )->groupBy('year', 'month')
             ->orderBy('year', 'desc')
             ->orderBy('month', 'desc')
             ->limit(12)
             ->get(),
            'top_reporters' => User::withCount('contentReports')
                ->having('content_reports_count', '>', 0)
                ->orderBy('content_reports_count', 'desc')
                ->limit(10)
                ->get(),
            'top_reported_users' => User::withCount('reportsAgainst')
                ->having('reports_against_count', '>', 0)
                ->orderBy('reports_against_count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return view('admin.moderation.statistics', compact('stats'));
    }

    /**
     * Bulk actions on reports
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,dismiss,mark_pending',
            'report_ids' => 'required|array',
            'report_ids.*' => 'exists:content_reports,id',
        ]);

        $updatedCount = 0;

        foreach ($request->report_ids as $reportId) {
            $report = ContentReport::find($reportId);
            if ($report) {
                $status = match($request->action) {
                    'approve' => 'resolved',
                    'dismiss' => 'dismissed',
                    'mark_pending' => 'pending',
                };

                $report->update([
                    'status' => $status,
                    'moderator_id' => auth()->id(),
                    'action_taken_at' => now(),
                ]);

                $updatedCount++;
            }
        }

        return redirect()
            ->back()
            ->with('success', "Bulk action applied to {$updatedCount} reports.");
    }

    /**
     * Execute specific moderation action
     */
    private function executeAction($action, $report, $notes)
    {
        switch ($action) {
            case 'warn_user':
                // Send warning notification to user
                // Could implement email or in-app notification
                break;
                
            case 'suspend_user':
                // Temporarily suspend user account
                if ($report->reportedUser) {
                    $report->reportedUser->update([
                        'status' => 'suspended',
                        'suspended_until' => now()->addDays(7),
                    ]);
                }
                break;
                
            case 'ban_user':
                // Permanently ban user account
                if ($report->reportedUser) {
                    $report->reportedUser->update([
                        'status' => 'banned',
                        'banned_at' => now(),
                    ]);
                }
                break;
                
            case 'remove_content':
                // Mark content as removed/hidden
                // Implementation depends on content type
                break;
        }
    }

    /**
     * Get severity level for action type
     */
    private function getActionSeverity($action)
    {
        return match($action) {
            'dismiss', 'approve' => 'low',
            'warn_user', 'remove_content' => 'medium',
            'suspend_user', 'ban_user' => 'high',
            default => 'medium',
        };
    }
}
