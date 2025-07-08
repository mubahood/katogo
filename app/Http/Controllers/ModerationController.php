<?php

namespace App\Http\Controllers;

use App\Models\ContentReport;
use App\Models\UserBlock;
use App\Models\ContentModerationLog;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ModerationController extends BaseController
{
    use ApiResponser;

    /**
     * Content filtering endpoint for automated moderation
     */
    public function filterContent(Request $request)
    {
        try {
            $content = $request->input('content');
            $contentType = $request->input('content_type', 'text');
            $userId = $request->input('user_id');
            $additionalContext = $request->input('additional_context');

            // Debug logging
            Log::info('Content filtering request', [
                'content' => $content,
                'content_type' => $contentType,
                'user_id' => $userId
            ]);

            // Get user for logging
            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found for content filtering', ['user_id' => $userId]);
                return $this->error('User not found.', 404);
            }

            Log::info('User found, running content filtering', ['user_id' => $userId]);

            // Run content filtering logic
            $filterResult = $this->runContentFiltering($content, $contentType, $additionalContext);

            Log::info('Content filtering result', ['result' => $filterResult]);

            // Log the filtering action
            ContentModerationLog::logAction(
                $contentType,
                null, // No specific content ID for filtering
                $userId,
                ContentModerationLog::ACTION_CONTENT_FILTERED,
                [
                    'automated' => true,
                    'filter_result' => $filterResult,
                    'severity_level' => $this->mapSeverityLevel($filterResult['severity']),
                    'metadata' => [
                        'content_length' => strlen($content),
                        'additional_context' => $additionalContext
                    ]
                ]
            );

            return $this->success($filterResult, 'Content filtering completed');

        } catch (\Exception $e) {
            Log::error('Content filtering error: ' . $e->getMessage());
            return $this->error('Content filtering service temporarily unavailable', 500);
        }
    }

    /**
     * Report content for moderation
     */
    public function reportContent(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $request->validate([
                
            ]);

            // Check if user has already reported this content
            $existingReport = ContentReport::where('reporter_id', $u->id)
                ->where('reported_content_type', $request->reported_content_type)
                ->where('reported_content_id', $request->reported_content_id)
                ->where('status', '!=', ContentReport::STATUS_DISMISSED)
                ->first();

            if ($existingReport) {
                return $this->error('You have already reported this content.', 400);
            }

            // Auto-assign priority based on report type
            $priority = ContentReport::getAutoPriority($request->report_type);

            $report = ContentReport::create([
                'reporter_id' => $u->id,
                'reported_content_type' => $request->reported_content_type,
                'reported_content_id' => $request->reported_content_id,
                'reported_user_id' => $u->id,
                'report_type' => $request->report_type,
                'description' => $request->description,
                'status' => ContentReport::STATUS_PENDING,
                'priority' => $priority,
                'metadata' => [
                    'reported_at' => now(),
                    'reporter_ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent')
                ]
            ]);

            // Log the report action
            ContentModerationLog::logAction(
                $request->reported_content_type,
                $request->reported_content_id,
                $u->id,
                'content_reported',
                [
                    'automated' => false,
                    'severity_level' => $this->mapSeverityLevel($priority),
                    'metadata' => [
                        'report_id' => $report->id,
                        'report_type' => $request->report_type,
                        'reporter_id' => $u->id
                    ]
                ]
            );

            // For critical priority reports, send immediate notification
            if ($priority === ContentReport::PRIORITY_CRITICAL) {
                $this->sendUrgentModerationAlert($report);
            }

            return $this->success($report, 'Content reported successfully. We will review this within 24 hours.');

        } catch (\Exception $e) {
            Log::error('Report content error: ' . $e->getMessage());
            return $this->error('Failed to submit because : ' . $e->getMessage(), 500);
        }
    }

    /**
     * Block a user
     */
    public function blockUser(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $request->validate([
                'blocked_user_id' => 'required|integer|different:blocker_id',
                'reason' => 'nullable|string|max:500',
                'block_type' => 'nullable|string|in:user_initiated,moderator_initiated,automatic'
            ]);

            $blockedUser = User::find($request->blocked_user_id);
            if (!$blockedUser) {
                return $this->error('User to block not found.', 404);
            }

            // Check if already blocked
            $existingBlock = UserBlock::where('blocker_id', $u->id)
                ->where('blocked_user_id', $request->blocked_user_id)
                ->active()
                ->first();

            if ($existingBlock) {
                return $this->error('User is already blocked.', 400);
            }
            

            $block = UserBlock::create([
                'blocker_id' => $u->id,
                'blocked_user_id' => $request->blocked_user_id,
                'reason' => $request->reason,
                'block_type' => $request->block_type ?? UserBlock::TYPE_USER_INITIATED,
                'status' => UserBlock::STATUS_ACTIVE,
                'metadata' => [
                    'blocked_at' => now(),
                    'blocker_ip' => $request->ip()
                ]
            ]);


            // Log the block action
            ContentModerationLog::logAction(
                'user',
                $request->blocked_user_id,
                $request->blocked_user_id,
                'user_blocked',
                [
                    'automated' => false,
                    'severity_level' => ContentModerationLog::SEVERITY_MEDIUM,
                    'metadata' => [
                        'block_id' => $block->id,
                        'blocker_id' => $u->id,
                        'reason' => $request->reason
                    ]
                ]
            );

            return $this->success($block, 'User blocked successfully.');

        } catch (\Exception $e) {
            Log::error('Block user error: ' . $e->getMessage());
            return $this->error('Failed to block user. Please try again.', 500);
        }
    }

    /**
     * Unblock a user
     */
    public function unblockUser(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $request->validate([
                'blocked_user_id' => 'required|integer'
            ]);

            $block = UserBlock::where('blocker_id', $u->id)
                ->where('blocked_user_id', $request->blocked_user_id)
                ->active()
                ->first();

            if (!$block) {
                return $this->error('No active block found for this user.', 404);
            }

            $block->status = UserBlock::STATUS_REMOVED;
            $block->save();

            // Log the unblock action
            ContentModerationLog::logAction(
                'user',
                $request->blocked_user_id,
                $request->blocked_user_id,
                'user_unblocked',
                [
                    'automated' => false,
                    'severity_level' => ContentModerationLog::SEVERITY_LOW,
                    'metadata' => [
                        'block_id' => $block->id,
                        'unblocker_id' => $u->id
                    ]
                ]
            );

            return $this->success(null, 'User unblocked successfully.');

        } catch (\Exception $e) {
            Log::error('Unblock user error: ' . $e->getMessage());
            return $this->error('Failed to unblock user. Please try again.', 500);
        }
    }

    /**
     * Get user's blocked users list
     */
    public function getBlockedUsers(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $blockedUsers = UserBlock::where('blocker_id', $u->id)
                ->active()
                ->with('blockedUser:id,name,email,avatar')
                ->get();

            return $this->success($blockedUsers, 'Blocked users retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Get blocked users error: ' . $e->getMessage());
            return $this->error('Failed to retrieve blocked users.', 500);
        }
    }

    /**
     * Get user's submitted reports
     */
    public function getUserReports(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $reports = ContentReport::where('reporter_id', $u->id)
                ->with(['reportedUser:id,name,email,avatar'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($reports, 'User reports retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Get user reports error: ' . $e->getMessage());
            return $this->error('Failed to retrieve user reports.', 500);
        }
    }

    /**
     * Update user legal consent fields
     */
    public function updateLegalConsent(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null) {
                return $this->error('User not authenticated.', 401);
            }

            $request->validate([
                'terms_of_service_accepted' => 'nullable|string|in:Yes,No',
                'privacy_policy_accepted' => 'nullable|string|in:Yes,No',
                'community_guidelines_accepted' => 'nullable|string|in:Yes,No',
                'marketing_emails_consent' => 'nullable|string|in:Yes,No',
                'data_processing_consent' => 'nullable|string|in:Yes,No',
                'content_moderation_consent' => 'nullable|string|in:Yes,No'
            ]);

            $user = User::find($u->id);
            $updateData = [];

            // Update legal consent fields
            $legalFields = [
                'terms_of_service_accepted',
                'privacy_policy_accepted', 
                'community_guidelines_accepted',
                'marketing_emails_consent',
                'data_processing_consent',
                'content_moderation_consent'
            ];

            foreach ($legalFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                    
                    // Set acceptance date if accepting
                    if ($request->input($field) === 'Yes') {
                        $dateField = str_replace('_accepted', '_accepted_date', $field);
                        $dateField = str_replace('_consent', '_consent_date', $dateField);
                        $updateData[$dateField] = now()->toDateTimeString();
                    }
                }
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                
                // Log the consent update
                ContentModerationLog::logAction(
                    'user',
                    $user->id,
                    $user->id,
                    'legal_consent_updated',
                    [
                        'automated' => false,
                        'severity_level' => ContentModerationLog::SEVERITY_LOW,
                        'metadata' => [
                            'updated_fields' => array_keys($updateData),
                            'ip_address' => $request->ip()
                        ]
                    ]
                );
            }

            $updatedUser = User::find($u->id);
            return $this->success($updatedUser, 'Legal consent updated successfully.');

        } catch (\Exception $e) {
            Log::error('Update legal consent error: ' . $e->getMessage());
            return $this->error('Failed to update legal consent.', 500);
        }
    }

    /**
     * Moderation dashboard - Get pending reports (Admin only)
     */
    public function getModerationDashboard(Request $request)
    {
        try {
            $u = Utils::get_user($request);
            if ($u == null || !$this->isAdmin($u)) {
                return $this->error('Admin access required.', 403);
            }

            $pendingReports = ContentReport::pending()
                ->with(['reporter:id,name,email', 'reportedUser:id,name,email'])
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            $overdueReports = ContentReport::overdue()->count();
            $highPriorityReports = ContentReport::highPriority()->pending()->count();

            $stats = [
                'total_pending' => $pendingReports->count(),
                'overdue_reports' => $overdueReports,
                'high_priority' => $highPriorityReports,
                'average_response_time' => $this->getAverageResponseTime()
            ];

            return $this->success([
                'reports' => $pendingReports,
                'stats' => $stats
            ], 'Moderation dashboard data retrieved successfully.');

        } catch (\Exception $e) {
            Log::error('Moderation dashboard error: ' . $e->getMessage());
            return $this->error('Failed to retrieve moderation dashboard.', 500);
        }
    }

    /**
     * Private helper methods
     */
    private function runContentFiltering($content, $contentType, $additionalContext = null)
    {
        // This mirrors the logic from the Flutter ContentFilterService
        $isViolation = false;
        $violationType = null;
        $severity = 'low';
        $confidence = 0.0;
        $message = 'Content appears safe';
        $suggestedAction = 'allow';
        $needsHumanReview = false;

        $lowerContent = strtolower($content);

        // Check for profanity
        $profanityKeywords = [
            'fuck', 'shit', 'bitch', 'damn', 'ass', 'bastard', 'crap',
            'piss', 'dick', 'cock', 'pussy', 'whore', 'slut', 'faggot',
            'nigger', 'cunt', 'motherfucker', 'asshole', 'bullshit'
        ];

        foreach ($profanityKeywords as $keyword) {
            if (strpos($lowerContent, $keyword) !== false) {
                return [
                    'is_violation' => true,
                    'violation_type' => 'profanity',
                    'severity' => 'medium',
                    'confidence' => 0.9,
                    'message' => 'Content contains inappropriate language',
                    'suggested_action' => 'block',
                    'needs_human_review' => false
                ];
            }
        }

        // Check for hate speech
        $hateSpeechPatterns = [
            'kill yourself', 'you should die', 'hate all', 'genocide',
            'terrorist', 'racial slur', 'go back to', 'inferior race'
        ];

        foreach ($hateSpeechPatterns as $pattern) {
            if (strpos($lowerContent, $pattern) !== false) {
                return [
                    'is_violation' => true,
                    'violation_type' => 'hate_speech',
                    'severity' => 'critical',
                    'confidence' => 0.95,
                    'message' => 'Content contains hate speech',
                    'suggested_action' => 'block',
                    'needs_human_review' => true
                ];
            }
        }

        // Check for sexual content
        $sexualContentKeywords = [
            'nude', 'naked', 'sex', 'porn', 'masturbate', 'orgasm',
            'sexual', 'erotic', 'xxx', 'adult content', 'hookup',
            'one night stand', 'sugar daddy', 'escort'
        ];

        foreach ($sexualContentKeywords as $keyword) {
            if (strpos($lowerContent, $keyword) !== false) {
                return [
                    'is_violation' => true,
                    'violation_type' => 'sexual_content',
                    'severity' => 'high',
                    'confidence' => 0.8,
                    'message' => 'Content contains adult/sexual material',
                    'suggested_action' => 'block',
                    'needs_human_review' => true
                ];
            }
        }

        // Check for violence
        $violenceKeywords = [
            'kill', 'murder', 'assault', 'beat up', 'stab', 'shoot',
            'bomb', 'violence', 'hurt', 'pain', 'torture', 'abuse'
        ];

        foreach ($violenceKeywords as $keyword) {
            if (strpos($lowerContent, $keyword) !== false) {
                return [
                    'is_violation' => true,
                    'violation_type' => 'violence',
                    'severity' => 'high',
                    'confidence' => 0.7,
                    'message' => 'Content contains violent language',
                    'suggested_action' => 'review',
                    'needs_human_review' => true
                ];
            }
        }

        // Check for spam patterns
        if ($this->isSpamContent($lowerContent)) {
            return [
                'is_violation' => true,
                'violation_type' => 'spam',
                'severity' => 'low',
                'confidence' => 0.6,
                'message' => 'Content appears to be spam',
                'suggested_action' => 'review',
                'needs_human_review' => false
            ];
        }

        // Content passed all filtering
        return [
            'is_violation' => false,
            'violation_type' => null,
            'severity' => 'low',
            'confidence' => 0.0,
            'message' => 'Content appears safe',
            'suggested_action' => 'allow',
            'needs_human_review' => false
        ];
    }

    private function isSpamContent($content)
    {
        $words = explode(' ', $content);
        $wordCount = [];
        
        foreach ($words as $word) {
            $wordCount[$word] = ($wordCount[$word] ?? 0) + 1;
        }

        // If any word appears more than 30% of the time, likely spam
        foreach ($wordCount as $word => $count) {
            if ($count > count($words) * 0.3) {
                return true;
            }
        }

        // Check for excessive capital letters
        $capitalCount = strlen(preg_replace('/[^A-Z]/', '', $content));
        if ($capitalCount > strlen($content) * 0.7) {
            return true;
        }

        // Check for excessive punctuation
        $punctuationCount = strlen(preg_replace('/[^!@#$%^&*(),.?":{}|<>]/', '', $content));
        if ($punctuationCount > strlen($content) * 0.3) {
            return true;
        }

        return false;
    }

    private function mapSeverityLevel($severity)
    {
        switch ($severity) {
            case 'critical':
                return ContentModerationLog::SEVERITY_CRITICAL;
            case 'high':
                return ContentModerationLog::SEVERITY_HIGH;
            case 'medium':
                return ContentModerationLog::SEVERITY_MEDIUM;
            default:
                return ContentModerationLog::SEVERITY_LOW;
        }
    }

    private function sendUrgentModerationAlert($report)
    {
        // TODO: Implement urgent alert system (email, Slack, etc.)
        Log::alert('URGENT: Critical priority content report submitted', [
            'report_id' => $report->id,
            'report_type' => $report->report_type,
            'reported_user_id' => $report->reported_user_id
        ]);
    }

    private function isAdmin($user)
    {
        // TODO: Implement proper admin role checking
        // For now, assume user has admin_role field or check roles table
        return $user->admin_role === 'admin' || $user->id === 1; // Adjust based on your admin logic
    }

    private function getAverageResponseTime()
    {
        $resolved = ContentReport::where('status', ContentReport::STATUS_RESOLVED)
            ->whereNotNull('resolved_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours'))
            ->first();

        return $resolved ? round($resolved->avg_hours, 2) : 0;
    }
}
