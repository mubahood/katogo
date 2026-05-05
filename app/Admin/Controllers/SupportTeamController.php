<?php

namespace App\Admin\Controllers;

use App\Models\CustomerTicket;
use App\Models\CustomerTicketRecord;
use App\Models\MovieModel;
use App\Models\SupportAuditLog;
use App\Models\User;
use Encore\Admin\Admin;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SupportTeamController
 *
 * Provides two sections in the admin panel:
 *
 *  1. /admin/support-team         — Assign / remove support_team role from users
 *  2. /admin/support-tickets      — View and manage all customer support tickets
 *  3. /admin/support-team/toggle  — AJAX endpoint: toggle support_team role
 *  4. /admin/support-tickets/{id} — Ticket detail + reply
 */
class SupportTeamController extends AdminController
{
    protected $title = 'Support Team';

    private const APP_TYPE_OPTIONS = [
        'lugaflix' => 'Lugaflix',
        'ugflix' => 'Ugflix',
        'muno_app' => 'Muno App',
    ];

    private const PLATFORM_TYPE_OPTIONS = [
        'lugaflix' => 'Lugaflix',
        'luga' => 'Luga',
        'ugflix' => 'Ugflix',
        'muno' => 'Muno',
        'muno_app' => 'Muno App',
    ];

    private const DEVICE_PLATFORM_OPTIONS = [
        'android' => 'Android',
        'ios' => 'iOS',
        'web' => 'Web',
    ];

    private const ACCOUNT_ORIGIN_OPTIONS = [
        'auto_device' => 'Auto Device',
        'manual' => 'Manual',
        'google' => 'Google',
    ];

    private const SWITCH_STATES = [
        'on' => ['value' => 1, 'text' => 'Yes', 'color' => 'success'],
        'off' => ['value' => 0, 'text' => 'No', 'color' => 'default'],
    ];

    private function isAdministrator(int $userId): bool
    {
        return DB::table('admin_role_users')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->where('admin_role_users.user_id', $userId)
            ->where('admin_roles.slug', 'administrator')
            ->exists();
    }

    private function logAudit(?int $actorId, string $eventType, string $entityType, ?int $entityId, string $description, array $meta = []): void
    {
        try {
            SupportAuditLog::create([
                'actor_id' => $actorId,
                'actor_role' => $actorId ? 'administrator' : 'system',
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'meta' => $meta,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $th) {
            // Ignore audit persistence failures in admin runtime.
        }
    }

    // ── 1. Support Team Role Management ──────────────────────────────

    public function index(Content $content)
    {
        return $content
            ->title('Support Team')
            ->description('Assign or remove the Support Team role from user accounts.')
            ->body($this->roleStats())
            ->body($this->welcomeQueueTable())
            ->body($this->usersGrid());
    }

    private function roleStats(): string
    {
        $supportRoleId = DB::table('admin_roles')->where('slug', 'support_team')->value('id');
        $supportCount  = $supportRoleId
            ? DB::table('admin_role_users')->where('role_id', $supportRoleId)->count()
            : 0;

        $openTickets     = CustomerTicket::where('status', 'open')->count();
        $pendingTickets  = CustomerTicket::where('status', 'pending')->count();
        $totalTickets    = CustomerTicket::count();
        $unassigned      = CustomerTicket::whereNull('assigned_to')
            ->whereNotIn('status', ['closed'])->count();
        $welcomeQueueCount = $this->welcomeQueueQuery()->count();

        return view('admin.support_team_stats', compact(
            'supportCount', 'openTickets', 'pendingTickets', 'totalTickets', 'unassigned', 'welcomeQueueCount'
        ))->render();
    }

    private function welcomeQueueQuery()
    {
        return CustomerTicket::query()
            ->with(['user'])
            ->where('ticket_type', 'account_opening')
            ->where('subject', 'Welcome message')
            ->where('account_origin', 'auto_device')
            ->where('agent_has_contacted_customer', false)
            ->whereNotIn('status', ['closed']);
    }

    private function normalizeUgPhoneToWhatsApp(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
            $digits = '256' . substr($digits, 1);
        }
        if (strpos($digits, '256') !== 0 && strlen($digits) === 9) {
            $digits = '256' . $digits;
        }
        return $digits;
    }

    private function getWhatsAppTemplatesByTicketType(): array
    {
        return [
            'auto_account_issue' => [
                "Hello{hello_name}, this is {agent_name} from {app_name}. We noticed an account setup issue and we are here to help you sort it out quickly.",
                "Hi{hello_name}, thanks for starting with {app_name}. If your account setup got stuck, reply here and we will guide you step by step.",
                "Hello{hello_name}, welcome to {app_name}. I can see an auto-account issue on your side; share what you are seeing and I will help fix it now.",
                "Hi{hello_name}, you are not alone, we can resolve this account issue together. Reply with the error text or screenshot and {agent_name} will assist.",
                "Hello{hello_name}, {agent_name} from {app_name} here. We are checking your account activation flow and can help you complete it in a few minutes.",
            ],
            'account_opening' => [
                "Hello{hello_name}, welcome to {app_name}. I am {agent_name} and I will help you complete your account opening smoothly.",
                "Hi{hello_name}, great to have you on {app_name}. If anything is unclear while setting up, just reply and we will sort it out.",
                "Hello{hello_name}, your account opening request is on our desk. Share any blocker and we will guide you right away.",
                "Hi{hello_name}, we are excited to have you. I can help you finish setup, verify details, and start watching without delays.",
                "Hello{hello_name}, this is {agent_name} from {app_name}. Need help with login, profile, or subscription during setup? Reply here anytime.",
            ],
            'payment_fail' => [
                "Hello{hello_name}, we noticed your recent payment did not complete. Please share the error message so {agent_name} can help immediately.",
                "Hi{hello_name}, your payment seems pending or failed. Send the number used plus screenshot if possible and we will resolve it quickly.",
                "Hello{hello_name}, thanks for trying to subscribe on {app_name}. We can help you complete payment safely, just reply with what happened.",
                "Hi{hello_name}, no worries, failed payments happen. I will walk you through a quick fix and get your access restored.",
                "Hello{hello_name}, this is {agent_name}. We are already checking your payment attempt and can help confirm the correct next step.",
            ],
            'payment_thanks' => [
                "Hello{hello_name}, thank you for your payment on {app_name}. We appreciate your support and we are here if you need anything.",
                "Hi{hello_name}, payment received successfully. Enjoy your content, and message us anytime if you need quick assistance.",
                "Hello{hello_name}, your payment is confirmed. Thank you for trusting {app_name}; we are happy to support your viewing journey.",
                "Hi{hello_name}, thanks again for subscribing. If you need help with playback or account settings, {agent_name} is ready to help.",
                "Hello{hello_name}, welcome and thank you. Your support helps us keep great content coming, and our team is always one message away.",
            ],
            'subscription_issue' => [
                "Hello{hello_name}, we found a subscription issue on your {app_name} account. Reply and we will restore access as fast as possible.",
                "Hi{hello_name}, your subscription status needs attention. Share what you see on screen and {agent_name} will fix it with you.",
                "Hello{hello_name}, thanks for your patience. We are reviewing your subscription and can guide you to a quick resolution.",
                "Hi{hello_name}, if your premium access is not active, reply here now. We will verify your plan and update you immediately.",
                "Hello{hello_name}, this is {agent_name} from {app_name}. We are on your subscription case and will help you get back to full access.",
            ],
            'technical_issue' => [
                "Hello{hello_name}, we are following up on your technical issue in {app_name}. Please share details and we will troubleshoot together.",
                "Hi{hello_name}, thanks for reporting this. Send the error text or screenshot so {agent_name} can diagnose quickly.",
                "Hello{hello_name}, we can help fix playback, login, or app crashes fast. Tell us what device and what happened before the issue.",
                "Hi{hello_name}, our support team is ready. Reply with steps to reproduce and we will provide the best fix for your case.",
                "Hello{hello_name}, thank you for your patience. We are here to resolve this technical issue and keep your experience smooth.",
            ],
            'billing_issue' => [
                "Hello{hello_name}, we are here to help with your billing concern on {app_name}. Reply with details and we will sort it out promptly.",
                "Hi{hello_name}, if you noticed a billing mismatch, share the amount, date, and number used so we can verify quickly.",
                "Hello{hello_name}, this is {agent_name}. We can help clarify charges, renewals, or transaction history, just reply here.",
                "Hi{hello_name}, we understand billing issues can be stressful. We will review your case carefully and update you fast.",
                "Hello{hello_name}, thanks for reaching out. We are checking your billing record and will guide you on the next best action.",
            ],
            'content_issue' => [
                "Hello{hello_name}, thanks for reporting a content issue. Please share the title and what exactly is wrong so we can fix it quickly.",
                "Hi{hello_name}, we appreciate the report. Send the movie or episode name plus a short description, and {agent_name} will follow up.",
                "Hello{hello_name}, if audio, subtitle, or video quality is off, reply with details and we will escalate to the content team.",
                "Hi{hello_name}, your feedback helps us improve. Please tell us where the issue appears and we will investigate immediately.",
                "Hello{hello_name}, thank you for flagging this content problem on {app_name}. We will review and update you once handled.",
            ],
            'general' => [
                "Hello{hello_name}, this is {agent_name} from {app_name}. We received your {ticket_type} request and are ready to help.",
                "Hi{hello_name}, thanks for contacting {app_name} support. Share any extra details and we will assist as quickly as possible.",
                "Hello{hello_name}, we are here for you. Reply with your concern and we will guide you until it is fully resolved.",
                "Hi{hello_name}, thanks for reaching out. We have opened your support case and will keep you updated on progress.",
                "Hello{hello_name}, support is available right here. Send your details and {agent_name} will take care of your request.",
            ],
        ];
    }

    private function statusOptions(): array
    {
        return array_combine(
            CustomerTicket::$validStatuses,
            array_map(fn($v) => ucwords(str_replace('_', ' ', $v)), CustomerTicket::$validStatuses)
        );
    }

    private function ticketTypeOptions(): array
    {
        return array_combine(
            CustomerTicket::$validTicketTypes,
            array_map(fn($v) => ucwords(str_replace('_', ' ', $v)), CustomerTicket::$validTicketTypes)
        );
    }

    private function resolutionOptions(): array
    {
        return array_combine(
            CustomerTicket::$validResolutionStates,
            array_map(fn($v) => ucwords(str_replace('_', ' ', $v)), CustomerTicket::$validResolutionStates)
        );
    }

    private function supportAgentOptions(): array
    {
        $roleIds = DB::table('admin_roles')
            ->whereIn('slug', ['support_team', 'administrator'])
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return [];
        }

        $agentIds = DB::table('admin_role_users')
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($agentIds->isEmpty()) {
            return [];
        }

        return User::whereIn('id', $agentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn(User $user) => [
                $user->id => trim($user->name . ($user->email ? ' (' . $user->email . ')' : '')),
            ])
            ->toArray();
    }

    private function adminUserSearchUrl(): string
    {
        $prefix = trim((string) config('admin.route.prefix', 'admin'), '/');
        return '/' . ($prefix !== '' ? $prefix . '/' : '') . 'api/users';
    }

    private function welcomeQueueTable(): string
    {
        $tickets = $this->welcomeQueueQuery()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $rows = $tickets->map(function (CustomerTicket $ticket) {
            $phone = (string) ($ticket->user->phone_number ?? '');
            $waNumber = $this->normalizeUgPhoneToWhatsApp($phone);
            $waUrl = $waNumber !== '' ? 'https://wa.me/' . $waNumber : '';

            return [
                '#' . $ticket->id,
                e((string) ($ticket->user->name ?? 'Unknown user')),
                e((string) ($ticket->user->email ?? '—')),
                e($phone !== '' ? $phone : '—'),
                e((string) ($ticket->app_type ?? 'lugaflix')),
                e((string) ($ticket->created_at ?? '—')),
                $waUrl !== ''
                    ? '<a class="btn btn-xs btn-success" target="_blank" href="' . e($waUrl) . '"><i class="fa fa-whatsapp"></i> WhatsApp Welcome</a>'
                    : '<span class="label label-default">No valid phone</span>',
                '<a class="btn btn-xs btn-primary" href="' . e(admin_url('support-tickets/' . $ticket->id)) . '"><i class="fa fa-ticket"></i> Open Ticket</a>',
            ];
        })->toArray();

        $table = new Table(
            ['Ticket', 'User', 'Email', 'Phone', 'App', 'Created', 'WhatsApp', 'Action'],
            $rows
        );

        return '<div class="box box-success"><div class="box-header with-border"><h3 class="box-title">Welcome Message Queue</h3><div class="box-tools"><span class="label label-success">' . count($rows) . ' pending</span></div></div><div class="box-body">' . $table->render() . '</div></div>';
    }

    private function usersGrid(): Grid
    {
        $grid = new Grid(new User());

        $grid->model()->orderByDesc('id');

        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->disableRowSelector();
        $grid->disableBatchActions();

        $grid->column('id', 'ID')->sortable();
        $grid->column('name', 'Name');
        $grid->column('email', 'Email');
        $grid->column('app_type', 'App');
        $grid->column('account_origin', 'Origin');
        $grid->column('account_state', 'State');
        $grid->column('created_at', 'Joined')->display(fn($v) => $v ? date('d M Y', strtotime($v)) : '—');

        $grid->column('support_role', 'Support Role')->display(function () {
            /** @var object{id:int} $this */
            $supportRoleId = DB::table('admin_roles')->where('slug', 'support_team')->value('id');
            if (!$supportRoleId) {
                return '<span class="label label-default">Role missing</span>';
            }
            $hasRole = DB::table('admin_role_users')
                ->where('user_id', $this->id)
                ->where('role_id', $supportRoleId)
                ->exists();

            $btn = $hasRole
                ? "<a href='javascript:void(0)' class='btn btn-sm btn-danger support-role-toggle' data-id='{$this->id}' data-action='remove'>Remove Role</a>"
                : "<a href='javascript:void(0)' class='btn btn-sm btn-success support-role-toggle' data-id='{$this->id}' data-action='assign'>Assign Role</a>";

            return $btn;
        });

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('name', 'Name');
            $filter->like('email', 'Email');
            $filter->equal('app_type', 'App Type')->select([
                'lugaflix' => 'Lugaflix',
                'ugflix'   => 'UgFlix',
                'muno_app' => 'Muno App',
            ]);
        });

        // Inject the toggle JS
        $grid->footer(function () {
            $csrfToken   = csrf_token();
            $toggleUrl   = rtrim(request()->getBaseUrl(), '/') . '/' . trim(config('admin.route.prefix', 'admin'), '/') . '/support-team/toggle';
            return <<<HTML
<script>
$(document).on('click', '.support-role-toggle', function () {
    const btn    = $(this);
    const userId = btn.data('id');
    const action = btn.data('action');
    btn.prop('disabled', true).text('...');
    $.ajax({
        url:  '{$toggleUrl}',
        type: 'POST',
        data: { _token: '{$csrfToken}', user_id: userId, action: action },
        success: function (res) {
            if (res.success) {
                toastr.success(res.message);
                setTimeout(() => location.reload(), 800);
            } else {
                toastr.error(res.message || 'Action failed.');
                btn.prop('disabled', false);
            }
        },
        error: function () {
            toastr.error('Request failed. Please try again.');
            btn.prop('disabled', false);
        }
    });
});
</script>
HTML;
        });

        return $grid;
    }

    // ── AJAX: Toggle support_team role ────────────────────────────────

    public function toggleRole(Request $request)
    {
        $adminUser = auth('admin')->user();
        if (!$adminUser || !$this->isAdministrator((int) $adminUser->id)) {
            return response()->json(['success' => false, 'message' => 'Only administrator role can change support team assignments.'], 403);
        }

        $userId = (int) $request->input('user_id');
        $action = $request->input('action'); // 'assign' | 'remove'

        if (!$userId || !in_array($action, ['assign', 'remove'])) {
            return response()->json(['success' => false, 'message' => 'Invalid request.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.']);
        }

        $supportRoleId = DB::table('admin_roles')->where('slug', 'support_team')->value('id');
        if (!$supportRoleId) {
            return response()->json(['success' => false, 'message' => 'support_team role not found. Run migrations first.']);
        }

        if ($action === 'assign') {
            $alreadyHas = DB::table('admin_role_users')
                ->where('user_id', $userId)
                ->where('role_id', $supportRoleId)
                ->exists();
            if (!$alreadyHas) {
                DB::table('admin_role_users')->insert([
                    'user_id'    => $userId,
                    'role_id'    => $supportRoleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->logAudit(
                (int) $adminUser->id,
                'support_role_assigned',
                'admin_user',
                (int) $userId,
                "Support Team role assigned to {$user->name}.",
                ['target_user_email' => $user->email]
            );
            return response()->json(['success' => true, 'message' => "{$user->name} assigned the Support Team role."]);
        }

        // remove
        DB::table('admin_role_users')
            ->where('user_id', $userId)
            ->where('role_id', $supportRoleId)
            ->delete();

        $this->logAudit(
            (int) $adminUser->id,
            'support_role_removed',
            'admin_user',
            (int) $userId,
            "Support Team role removed from {$user->name}.",
            ['target_user_email' => $user->email]
        );

        return response()->json(['success' => true, 'message' => "Support Team role removed from {$user->name}."]);
    }

    // ── 2. Support Tickets Grid ───────────────────────────────────────

    public function tickets(Content $content, ?string $segment = null)
    {
        $segment = $this->normalizeTicketSegment($segment);
        Admin::script($this->buildTicketInteractionScript());

        return $content
            ->title('Support Tickets')
            ->description($this->ticketSegmentDescription($segment))
            ->body($this->supportTicketSummaryCards($segment))
            ->body($this->ticketsGrid($segment))
            ->body($this->supportTicketInteractionAssets($segment));
    }

    private function normalizeTicketSegment(?string $segment): string
    {
        $value = strtolower(trim((string) $segment));
        $allowed = ['all', 'pending', 'contacted', 'contacted-customer-replied'];
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    private function ticketSegmentDescription(string $segment): string
    {
        return match ($segment) {
            'pending' => 'Tickets awaiting first support contact',
            'contacted' => 'Tickets where support contacted customer, awaiting customer reply',
            'contacted-customer-replied' => 'Tickets where support contacted customer and customer replied',
            default => 'All customer support tickets',
        };
    }

    private function supportTicketCounts(): array
    {
        $base = CustomerTicket::query();

        $pending = (clone $base)
            ->where('agent_has_contacted_customer', false)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        $contacted = (clone $base)
            ->where('agent_has_contacted_customer', true)
            ->where('customer_has_responded', false)
            ->whereNotIn('status', ['closed'])
            ->count();

        $contactedReplied = (clone $base)
            ->where('agent_has_contacted_customer', true)
            ->where('customer_has_responded', true)
            ->count();

        return [
            'all' => (clone $base)->count(),
            'pending' => $pending,
            'contacted' => $contacted,
            'contacted-customer-replied' => $contactedReplied,
        ];
    }

    private function supportTicketSummaryCards(string $activeSegment): string
    {
        $counts = $this->supportTicketCounts();

        $cards = [
            ['key' => 'pending', 'label' => 'Pending', 'icon' => 'fa-hourglass-half', 'url' => admin_url('support-tickets/pending')],
            ['key' => 'contacted', 'label' => 'Contacted', 'icon' => 'fa-phone', 'url' => admin_url('support-tickets/contacted')],
            ['key' => 'contacted-customer-replied', 'label' => 'Contacted + Replied', 'icon' => 'fa-comments', 'url' => admin_url('support-tickets/contacted-customer-replied')],
            ['key' => 'all', 'label' => 'All Tickets', 'icon' => 'fa-list', 'url' => admin_url('support-tickets/all')],
        ];

        $html = '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:8px">';
        foreach ($cards as $card) {
            $isActive = $activeSegment === $card['key'];
            $count = (int) ($counts[$card['key']] ?? 0);
            $html .= '<a href="' . e($card['url']) . '" style="display:block;background:' . ($isActive ? '#3c8dbc' : '#fff') . ';color:' . ($isActive ? '#fff' : '#2f3e4e') . ';border:1px solid ' . ($isActive ? '#2f7ba9' : '#d7dce1') . ';padding:8px 10px;text-decoration:none">'
                . '<div style="font-size:11px;opacity:' . ($isActive ? '0.92' : '0.75') . ';text-transform:uppercase"><i class="fa ' . e($card['icon']) . '"></i> ' . e($card['label']) . '</div>'
                . '<div style="font-size:20px;font-weight:800;line-height:1.2;margin-top:2px">' . $count . '</div>'
                . '</a>';
        }
        $html .= '</div>';
        $html .= '<style>@media(max-width:992px){.content .box-body>div[style*="grid-template-columns:repeat(4"]{grid-template-columns:repeat(2,minmax(0,1fr))!important}}@media(max-width:640px){.content .box-body>div[style*="grid-template-columns:repeat(4"]{grid-template-columns:1fr!important}}</style>';

        return $html;
    }

    private function ticketsGrid(string $segment = 'all'): Grid
    {
        $grid = new Grid(new CustomerTicket());

        $statusOptions = $this->statusOptions();

        $ticketTypeOptions = $this->ticketTypeOptions();

        $resolutionOptions = $this->resolutionOptions();

        $boolStates = self::SWITCH_STATES;

        $grid->model()
            ->with(['user', 'assignedAgent'])
            ->when($segment === 'pending', function ($query) {
                $query->where('agent_has_contacted_customer', false)
                    ->whereNotIn('status', ['resolved', 'closed']);
            })
            ->when($segment === 'contacted', function ($query) {
                $query->where('agent_has_contacted_customer', true)
                    ->where('customer_has_responded', false)
                    ->whereNotIn('status', ['closed']);
            })
            ->when($segment === 'contacted-customer-replied', function ($query) {
                $query->where('agent_has_contacted_customer', true)
                    ->where('customer_has_responded', true);
            })
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at');

        $grid->disableExport();

        $grid->column('id', 'ID')->sortable();

        $grid->column('user.name', 'User')->display(function () {
            /** @var object{user:?object,account_origin:?string} $this */
            if (!$this->user) return '—';
            $badge = '';
            if ($this->account_origin === 'auto_device') {
                $badge = ' <span class="label label-warning">Auto</span>';
            }
            return e((string) $this->user->name) . $badge;
        });

        $grid->column('user.email', 'Email')->hide();

        $grid->column('user.phone_number', 'Phone')->display(function () {
            /** @var object{user:?object} $this */
            $phone = trim((string) ($this->user->phone_number ?? ''));
            if ($phone === '') {
                return '<span class="text-muted">—</span>';
            }
            return e($phone);
        });

        $grid->column('platform_type', 'Platform')->display(fn($v) => $v ? strtoupper((string) $v) : '—')->sortable();

        $grid->column('ticket_type', 'Type')
            ->editable('select', $ticketTypeOptions)
            ->sortable();

        $grid->column('status', 'Status')
            ->editable('select', $statusOptions)
            ->sortable();

        $grid->column('resolution_state', 'Resolve')
            ->editable('select', $resolutionOptions)
            ->sortable();

        $grid->column('customer_has_responded', 'Customer Replied')
            ->switch($boolStates)
            ->sortable();

        $grid->column('agent_has_contacted_customer', 'Agent Contacted')
            ->switch($boolStates)
            ->sortable();

        $grid->column('rating_of_satisfaction', 'Rating')
            ->editable('select', [
                1 => '1',
                2 => '2',
                3 => '3',
                4 => '4',
                5 => '5',
            ])
            ->sortable()
            ->hide();

        $grid->column('subject', 'Subject')->display(fn($v) => e((string) ($v ?: '(none)')));

        $grid->column('assignedAgent.name', 'Assigned To')->display(function () {
            /** @var object{assignedAgent:?object} $this */
            return $this->assignedAgent ? $this->assignedAgent->name : '<span class="text-muted">Unassigned</span>';
        })->hide();

        $grid->column('reply_count', 'Replies')->sortable()->hide();

        $grid->column('has_unread_support', 'Unread')->display(fn($v) =>
            $v ? '<span class="label label-danger">New</span>' : '—'
        )->sortable()->hide();

        $grid->column('last_reply_at', 'Last Reply')->display(fn($v) =>
            $v ? date('d M Y H:i', strtotime($v)) : '—'
        )->sortable();

        $grid->column('created_at', 'Created')->display(fn($v) =>
            $v ? date('d M Y', strtotime($v)) : '—'
        )->sortable();

        $grid->column('wa_engage', 'Engage')->display(function () {
            /** @var object{user:?object,ticket_type:?string,id:int} $this */
            $phone = trim((string) ($this->user->phone_number ?? ''));
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
                $digits = '256' . substr($digits, 1);
            }
            if (strpos($digits, '256') !== 0 && strlen($digits) === 9) {
                $digits = '256' . $digits;
            }

            $hasValidPhone = $digits !== '';

            $ticketType = (string) ($this->ticket_type ?? 'general');
            $userName = (string) ($this->user->name ?? 'there');
            $appName = (string) ($this->platform_type ?: ($this->app_type ?: 'Katogo'));
            if ($appName === 'lugaflix' || $appName === 'luga') {
                $appName = 'Lugaflix';
            } elseif ($appName === 'muno_app' || $appName === 'muno') {
                $appName = 'Muno App';
            } elseif ($appName === 'ugflix') {
                $appName = 'Ugflix';
            } else {
                $appName = ucwords(str_replace('_', ' ', $appName));
            }

            $isMovieRequest = ((string) ($this->ticket_type ?? '') === 'movie_request' || (bool) ($this->is_movie_request ?? false));
            $moviePayload = is_array($this->movie_request_payload ?? null) ? $this->movie_request_payload : [];
            $hasMovieAdminResponse = is_array($moviePayload['admin_response'] ?? null);
            $preselectedMovies = [];
            if ($hasMovieAdminResponse && is_array($moviePayload['admin_response']['movie_titles'] ?? null)) {
                $preselectedMovies = array_values(array_filter(array_map(
                    static fn($v) => trim((string) $v),
                    $moviePayload['admin_response']['movie_titles']
                )));
            }
            $preselectedMoviesJson = e(json_encode($preselectedMovies, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
            $hasSupportResponse = (bool) ($this->agent_has_contacted_customer ?? false)
                || ((int) ($this->reply_count ?? 0) > 0)
                || $hasMovieAdminResponse;

            $respondLabel = $hasSupportResponse
                ? ($isMovieRequest ? 'Responded (Movie)' : 'Responded')
                : ($isMovieRequest ? 'Respond (Movie)' : 'Respond');
            $respondClass = $hasSupportResponse ? 'btn-success' : 'btn-primary';
            $respondIcon = $hasSupportResponse ? 'fa-check' : 'fa-reply';

            $engageBtn = $hasValidPhone
                ? '<button type="button" class="btn btn-xs btn-success js-wa-engage stc-action-btn" data-ticket="' . $this->id . '" data-type="' . e($ticketType) . '" data-name="' . e($userName) . '" data-app="' . e($appName) . '" data-phone="' . e($digits) . '"><i class="fa fa-whatsapp"></i> Engage</button>'
                : '<span class="label label-default stc-action-label">No phone</span>';

            return '<div class="stc-action-stack">'
                . $engageBtn
                . '<button type="button" class="btn btn-xs ' . $respondClass . ' js-ticket-respond stc-action-btn" data-ticket="' . $this->id . '" data-type="' . e($ticketType) . '" data-name="' . e($userName) . '" data-app="' . e($appName) . '" data-phone="' . e($digits) . '" data-movie-request="' . ($isMovieRequest ? '1' : '0') . '" data-selected-movies="' . $preselectedMoviesJson . '"><i class="fa ' . $respondIcon . '"></i> ' . e($respondLabel) . '</button>'
                . '<button type="button" class="btn btn-xs btn-info js-ticket-details stc-action-btn" data-ticket="' . $this->id . '"><i class="fa fa-list-alt"></i> Details</button>'
                . '</div>';
        });

        // Action: view ticket
        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
            $id = $actions->getKey();
            $url = admin_url("support-tickets/{$id}");
            $actions->prepend("<a href='{$url}' class='btn btn-sm btn-primary'>View</a> ");
        });

        $grid->quickSearch(function ($model, $query) {
            $q = trim((string) $query);
            if ($q === '') {
                return;
            }

            $model->where(function ($w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('user_id', (int) $q);
                }

                $w->orWhere('subject', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone_number', 'like', "%{$q}%");
                    })
                    ->orWhereHas('records', function ($r) use ($q) {
                        $r->where('message', 'like', "%{$q}%");
                    });
            });
        })->placeholder('Quick search: name, email, phone, subject, message, ticket #');

        $grid->filter(function (Grid\Filter $filter) use ($statusOptions, $ticketTypeOptions, $resolutionOptions) {
            $filter->disableIdFilter();
            $filter->equal('id', 'Ticket #');
            $filter->equal('user_id', 'User ID');
            $filter->where(function ($query) {
                /** @var object{input:mixed} $this */
                $value = trim((string) $this->input);
                if ($value === '') {
                    return;
                }
                $query->whereHas('user', function ($userQuery) use ($value) {
                    $userQuery->where('phone_number', 'like', "%{$value}%");
                });
            }, 'Phone');
            $filter->like('subject', 'Subject');
            $filter->equal('status', 'Status')->select($statusOptions);
            $filter->equal('ticket_type', 'Type')->select($ticketTypeOptions);
            $filter->equal('resolution_state', 'Resolve')->select($resolutionOptions);
            $filter->equal('platform_type', 'Platform')->select(self::PLATFORM_TYPE_OPTIONS);
            $filter->equal('account_origin', 'Origin')->select(self::ACCOUNT_ORIGIN_OPTIONS);
            $filter->equal('agent_has_contacted_customer', 'Agent Contacted')->radio([
                '' => 'All',
                '1' => 'Yes',
                '0' => 'No',
            ]);
            $filter->equal('customer_has_responded', 'Customer Replied')->radio([
                '' => 'All',
                '1' => 'Yes',
                '0' => 'No',
            ]);
            $filter->equal('rating_of_satisfaction', 'Rating')->select([
                '' => 'All',
                1 => '1',
                2 => '2',
                3 => '3',
                4 => '4',
                5 => '5',
            ]);
            $filter->equal('has_unread_support', 'Unread')->radio([
                ''  => 'All',
                '1' => 'Unread only',
                '0' => 'Read only',
            ]);
            $filter->between('created_at', 'Created')->datetime();
            $filter->between('last_reply_at', 'Last Reply')->datetime();
        });

        return $grid;
    }

    private function supportTicketInteractionAssets(string $segment = 'all'): string
    {
        $counts = $this->supportTicketCounts();
        // Build a single JSON payload and embed it in a data attribute.
        // Avoid <script type="application/json"> because jquery-pjax may still evaluate script tags during partial reload.
        $dataIsland = json_encode([
            'templates'      => $this->getWhatsAppTemplatesByTicketType(),
            'agentName'      => (string) (auth('admin')->user()->name ?? 'Support Team'),
            'respondBaseUrl' => (string) admin_url('support-tickets'),
            'movieSearchUrl' => (string) admin_url('support-tickets/ajax-movie-search'),
            'ticketDetailsUrlTemplate' => (string) admin_url('support-tickets/__ID__/ajax-details'),
            'ticketUrlTemplate' => (string) admin_url('support-tickets/__ID__'),
            'menuCounts' => $counts,
            'activeSegment' => $segment,
            'csrfToken'      => (string) csrf_token(),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $dataIslandB64 = base64_encode($dataIsland ?: '{}');

        return <<<HTML
<div id="stc-island" data-config-b64="{$dataIslandB64}" style="display:none"></div>
<style>
    #ticketRespondModal .modal-content,
    #ticketRespondModal .btn,
    #ticketRespondModal .form-control,
    #ticketRespondModal .input-group-addon,
    #waEngageModal .modal-content {
        border-radius: 0;
    }

    #ticketRespondModal .modal-header {
        background: #3c8dbc;
        color: #fff;
        border-bottom: 0;
        padding: 8px 12px;
    }

    #ticketRespondModal .modal-title,
    #waEngageModal .modal-title {
        font-size: 18px;
        font-weight: 700;
    }

    #ticketRespondModal .modal-body,
    #waEngageModal .modal-body {
        padding: 10px 12px;
        background: #f7f7f7;
    }

    #ticketRespondModal .modal-footer,
    #waEngageModal .modal-footer {
        padding: 8px 12px;
        border-top: 1px solid #e1e7ed;
        background: #f7f7f7;
    }

    .trm-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
    }

    .trm-meta {
        background: #fff;
        border: 1px solid #d7dce1;
        padding: 6px 8px;
    }

    .trm-k { display: block; font-size: 10px; text-transform: uppercase; color: #607182; margin-bottom: 2px; }
    .trm-v { font-size: 13px; font-weight: 700; color: #2f3e4e; word-break: break-word; }
    .trm-section { margin-top: 6px; }
    .trm-label { font-weight: 700; color: #2f3e4e; margin-bottom: 3px; display: inline-block; font-size: 12px; }
    #trmMessage,#trmMovieSearch,#trmMovieList { border: 1px solid #cfd6dd; box-shadow: none; padding: 6px 8px; font-size: 12px; }
    .trm-help { margin-top: 3px; font-size: 11px; color: #627688; }
    .trm-movie-only { border-left: 4px solid #f39c12; background: #fff7e8; padding: 6px 8px; font-size: 12px; color: #6a4a07; margin-bottom: 6px; }
    .trm-results { border: 1px solid #d7dce1; border-top: 0; background: #fff; max-height: 180px; overflow-y: auto; display: none; }
    .trm-result-item { padding: 6px 8px; border-top: 1px solid #eef2f5; cursor: pointer; font-size: 12px; color: #324a5f; }
    .trm-result-item:hover { background: #edf4fb; }
    .trm-selected { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 4px; min-height: 26px; }
    .trm-chip { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #3c8dbc; color: #2a6d97; background: #f4f9fd; padding: 3px 6px; font-size: 11px; line-height: 1.1; }
    .trm-chip button { border: 0; background: transparent; padding: 0; color: #2a6d97; font-weight: 700; }

    .stc-action-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        max-width: 188px;
    }
    .stc-action-btn {
        padding: 2px 6px !important;
        line-height: 1.2;
        white-space: nowrap;
    }
    .stc-action-label {
        margin: 0;
        font-size: 10px;
    }
    #waTemplateButtons .js-wa-template-btn {
        margin: 0 4px 4px 0;
        min-width: 0;
        padding: 3px 7px;
        font-size: 10px;
    }

    #ticketDetailsModal .modal-content { border-radius: 0; }
    #ticketDetailsModal .modal-header {
        background: #2f3a4b;
        color: #fff;
        border-bottom: 0;
        padding: 8px 12px;
    }
    #ticketDetailsModal .modal-body {
        background: #f5f7fa;
        padding: 10px 12px;
        max-height: 72vh;
        overflow-y: auto;
    }
    .tdm-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
        margin-bottom: 8px;
    }
    .tdm-box {
        background: #fff;
        border: 1px solid #d7dce1;
        padding: 6px 8px;
    }
    .tdm-k { display: block; font-size: 10px; text-transform: uppercase; color: #607182; margin-bottom: 2px; }
    .tdm-v { font-size: 12px; font-weight: 700; color: #2f3e4e; word-break: break-word; }
    .tdm-thread {
        background: #fff;
        border: 1px solid #d7dce1;
        padding: 8px;
    }
    .tdm-item {
        border: 1px solid #e1e7ed;
        background: #fcfdff;
        padding: 8px;
        margin-bottom: 6px;
    }
    .tdm-item:last-child { margin-bottom: 0; }
    .tdm-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
        gap: 8px;
    }
    .tdm-name { font-weight: 700; color: #33485e; font-size: 12px; }
    .tdm-time { color: #74889b; font-size: 11px; white-space: nowrap; }
    .tdm-msg { font-size: 12px; color: #2f3e4e; white-space: pre-wrap; }
    .tdm-meta { margin-top: 5px; font-size: 11px; color: #6d8194; }
    @media (max-width: 991px) { .tdm-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .trm-grid { grid-template-columns: 1fr; } }
    @media (max-width: 767px) { .tdm-grid { grid-template-columns: 1fr; } }
</style>

<div class="modal fade" id="ticketDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-list-alt"></i> Ticket Details</h4>
            </div>
            <div class="modal-body">
                <div id="tdmLoading" style="display:none;color:#6d8194;font-size:12px"><i class="fa fa-spinner fa-spin"></i> Loading ticket details...</div>
                <div id="tdmError" class="alert alert-danger" style="display:none;margin-bottom:8px"></div>
                <div id="tdmContent" style="display:none">
                    <div class="tdm-grid" id="tdmSummary"></div>
                    <div class="tdm-thread" id="tdmThread"></div>
                </div>
            </div>
            <div class="modal-footer" style="padding:8px 12px;background:#f5f7fa">
                <a href="#" class="btn btn-default" id="tdmOpenFull" target="_blank"><i class="fa fa-external-link"></i> Open Full Ticket</a>
                <button type="button" class="btn btn-success" id="tdmEngage"><i class="fa fa-whatsapp"></i> Engage WhatsApp</button>
                <button type="button" class="btn btn-primary" id="tdmRespond"><i class="fa fa-reply"></i> Respond</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="waEngageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-whatsapp"></i> Engage by WhatsApp</h4>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:8px">
                    <label style="font-size:12px">Quick Templates</label>
                    <div id="waTemplateButtons" style="display:flex;flex-wrap:wrap;gap:4px"></div>
                    <div style="font-size:11px;color:#6f7f8f;margin-top:4px">Tap any template below to replace the message.</div>
                </div>
                <div class="form-group" style="margin-bottom:8px">
                    <label style="font-size:12px">Message</label>
                    <textarea id="waMessage" class="form-control" rows="5" placeholder="Edit before sending..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="waOpenBtn"><i class="fa fa-external-link"></i> Open WhatsApp</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ticketRespondModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-reply"></i> Quick Ticket Response</h4>
            </div>
            <div class="modal-body">
                <div class="trm-grid">
                    <div class="trm-meta"><span class="trm-k">Ticket</span><span class="trm-v" id="trmTicketId">-</span></div>
                    <div class="trm-meta"><span class="trm-k">Type</span><span class="trm-v" id="trmTicketType">-</span></div>
                    <div class="trm-meta"><span class="trm-k">Customer</span><span class="trm-v" id="trmUser">-</span></div>
                    <div class="trm-meta"><span class="trm-k">Phone</span><span class="trm-v" id="trmPhone">-</span></div>
                </div>
                <div id="trmMovieHint" class="trm-section trm-movie-only" style="display:none">
                    Movie request flow detected. Add suggested movie titles below and they will be attached to the ticket response.
                </div>
                <div class="trm-section">
                    <label class="trm-label" for="trmMessage">Reply Message <span style="color:#d9534f">*</span></label>
                    <textarea id="trmMessage" class="form-control" rows="5" placeholder="Type a clear customer-facing message..."></textarea>
                </div>
                <div class="trm-section" id="trmMovieListWrap">
                    <label class="trm-label" for="trmMovieSearch">Suggested Movies (optional)</label>
                    <input id="trmMovieSearch" class="form-control" type="text" placeholder="Search movie titles from database, then tap to add">
                    <div id="trmMovieResults" class="trm-results"></div>
                    <div id="trmSelectedMovies" class="trm-selected"></div>
                    <textarea id="trmMovieList" class="form-control" style="display:none"></textarea>
                    <div class="trm-help">Only movie titles are searched. Press Enter to add typed text if not listed.</div>
                </div>
                <div class="checkbox" style="margin-top:8px">
                    <label><input type="checkbox" id="trmInternalNote"> Save as internal note only (not a customer-facing response)</label>
                </div>
                <div class="checkbox" style="margin-top:2px">
                    <label><input type="checkbox" id="trmShowToCustomer" checked> Show this record to customer</label>
                </div>
                <div class="checkbox" style="margin-top:2px">
                    <label><input type="checkbox" id="trmCustomerSeen"> Mark as customer seen now</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="trmSubmitBtn"><i class="fa fa-paper-plane"></i> Send Response</button>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function buildTicketInteractionScript(): string
    {
        // All JS runs after DOM is ready (Admin::script wraps in $(document).ready automatically).
        // Data is read from the #stc-island data island rendered in the HTML body.
        return <<<'JSCODE'
(function ($) {
    var _stcData = (function () {
        try {
            var el = document.getElementById('stc-island');
            if (!el) return {};
            var b64 = String(el.getAttribute('data-config-b64') || '').trim();
            if (!b64) return {};
            var json = window.atob ? window.atob(b64) : '';
            return json ? JSON.parse(json) : {};
        } catch (e) { return {}; }
    }());
    var templatesByType   = _stcData.templates      || {};
    var activeAgentName   = _stcData.agentName      || 'Support Team';
    var ajaxRespondBase   = _stcData.respondBaseUrl  || '';
    var movieSearchUrl    = _stcData.movieSearchUrl  || '';
    var detailsUrlTpl     = _stcData.ticketDetailsUrlTemplate || '';
    var ticketUrlTpl      = _stcData.ticketUrlTemplate || '';
    var menuCounts        = _stcData.menuCounts || {};
    var csrfToken         = _stcData.csrfToken       || '';
    var activePhone       = '';
    var activeRespondId   = null;
    var activeRespondBtn  = null;
    var activeDetailsId   = null;
    var selectedMovies    = [];
    var movieSearchTimer  = null;

    function renderWaTemplateButtons(type, ctx) {
        var holder = $('#waTemplateButtons');
        holder.empty();
        var list = templatesByType[type] || templatesByType['general'] || [];
        if (!Array.isArray(list) || !list.length) {
            return;
        }

        for (var i = 0; i < list.length; i++) {
            (function (idx, rawTpl) {
                var tpl = personalize(String(rawTpl || ''), ctx).trim();
                if (!tpl) {
                    return;
                }

                var label = 'Template ' + (idx + 1);
                var btn = $('<button/>', {
                    type: 'button',
                    'class': 'btn btn-default btn-xs js-wa-template-btn',
                    text: label,
                    'data-template': tpl,
                    title: tpl
                }).css({
                    borderRadius: '0',
                    border: '1px solid #cfd6dd',
                    background: '#fff',
                    color: '#2f3e4e',
                    padding: '3px 7px',
                    fontSize: '10px'
                });

                holder.append(btn);
            }(i, list[i]));
        }
    }

    function applyMenuBadge(uri, count, color) {
        var hrefNeedle = '/' + String(uri || '').replace(/^\//, '');
        var anchor = $('.sidebar-menu a').filter(function () {
            var href = String($(this).attr('href') || '');
            return href.indexOf(hrefNeedle) !== -1;
        }).first();
        if (!anchor.length) return;
        anchor.find('.support-menu-badge').remove();
        var badge = $('<small/>', {
            'class': 'label pull-right support-menu-badge',
            text: String(parseInt(count || 0, 10) || 0)
        }).css({ backgroundColor: color || '#3c8dbc', marginLeft: '6px' });
        anchor.append(badge);
    }

    function renderSupportMenuBadges() {
        applyMenuBadge('support-tickets/pending', menuCounts.pending || 0, '#f39c12');
        applyMenuBadge('support-tickets/contacted', menuCounts.contacted || 0, '#00a65a');
        applyMenuBadge('support-tickets/contacted-customer-replied', menuCounts['contacted-customer-replied'] || 0, '#3c8dbc');
        applyMenuBadge('support-tickets/all', menuCounts.all || 0, '#6c757d');
    }

    renderSupportMenuBadges();

    function normalizeTitle(v) { return String(v || '').trim().replace(/\s+/g, ' '); }
    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
        });
    }
    function formatDate(v) {
        var s = String(v || '').trim();
        if (!s) return '—';
        var d = new Date(s.replace(' ', 'T'));
        if (String(d) === 'Invalid Date') return s;
        return d.toLocaleString();
    }
    function detailUrl(id) { return String(detailsUrlTpl || '').replace('__ID__', encodeURIComponent(String(id || ''))); }
    function ticketUrl(id) { return String(ticketUrlTpl || '').replace('__ID__', encodeURIComponent(String(id || ''))); }
    function typeLabel(t) {
        return String(t || 'general').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }
    function syncPayload() { $('#trmMovieList').val(selectedMovies.join('\n')); }

    function renderTicketDetails(data) {
        var ticket = data && data.ticket ? data.ticket : {};
        var summary = [
            ['Ticket', '#' + (ticket.id || '—')],
            ['Status', typeLabel(ticket.status || '—')],
            ['Type', typeLabel(ticket.ticket_type || '—')],
            ['Resolution', typeLabel(ticket.resolution_state || '—')],
            ['Subject', ticket.subject || '—'],
            ['Replies', String(ticket.reply_count || 0)],
            ['Rating', ticket.rating_of_satisfaction ? String(ticket.rating_of_satisfaction) + '/5' : '—'],
            ['Unread (Support)', ticket.has_unread_support ? 'Yes' : 'No'],
            ['Unread (User)', ticket.has_unread_user ? 'Yes' : 'No'],
            ['Customer', ticket.user_name || '—'],
            ['Customer Email', ticket.user_email || '—'],
            ['Customer Phone', ticket.user_phone || '—'],
            ['Assigned To', ticket.assigned_to_name || 'Unassigned'],
            ['Platform', ticket.platform_type || ticket.platform || '—'],
            ['Origin', ticket.account_origin || '—'],
            ['Created', formatDate(ticket.created_at)],
            ['Last Reply', formatDate(ticket.last_reply_at)],
            ['Updated', formatDate(ticket.updated_at)]
        ];

        var summaryHtml = '';
        for (var i = 0; i < summary.length; i++) {
            summaryHtml += '<div class="tdm-box"><span class="tdm-k">' + escapeHtml(summary[i][0]) + '</span><span class="tdm-v">' + escapeHtml(summary[i][1]) + '</span></div>';
        }
        $('#tdmSummary').html(summaryHtml);

        var records = Array.isArray(data.records) ? data.records : [];
        if (records.length === 0) {
            $('#tdmThread').html('<div class="text-muted" style="font-size:12px">No ticket records yet.</div>');
            return;
        }

        var items = '';
        for (var j = 0; j < records.length; j++) {
            var r = records[j] || {};
            var flags = [];
            if (r.is_internal_note) flags.push('Internal');
            if (r.show_to_customer) flags.push('Visible to customer');
            if (r.customer_seen) flags.push('Seen by customer');
            if (r.action_type && r.action_type !== 'none') flags.push('Action: ' + typeLabel(r.action_type));

            items += '<div class="tdm-item">'
                + '<div class="tdm-top"><span class="tdm-name">' + escapeHtml(r.sender_name || r.sender_type || 'System') + '</span><span class="tdm-time">' + escapeHtml(formatDate(r.created_at)) + '</span></div>'
                + '<div class="tdm-msg">' + escapeHtml(r.message || '') + '</div>'
                + (r.action_description ? '<div class="tdm-meta"><strong>Action Notes:</strong> ' + escapeHtml(r.action_description) + '</div>' : '')
                + (flags.length ? '<div class="tdm-meta">' + escapeHtml(flags.join(' | ')) + '</div>' : '')
                + '</div>';
        }
        $('#tdmThread').html(items);
    }

    function openTicketDetails(ticketId) {
        activeDetailsId = String(ticketId || '').trim();
        if (!activeDetailsId) {
            toastr.error('Ticket id missing.');
            return;
        }
        $('#tdmError').hide().text('');
        $('#tdmContent').hide();
        $('#tdmLoading').show();
        $('#tdmOpenFull').attr('href', ticketUrl(activeDetailsId));
        $('#ticketDetailsModal').modal('show');

        $.ajax({
            url: detailUrl(activeDetailsId),
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res && res.success && res.data) {
                    renderTicketDetails(res.data);
                    $('#tdmLoading').hide();
                    $('#tdmContent').show();
                    return;
                }
                $('#tdmLoading').hide();
                $('#tdmError').text((res && res.message) ? res.message : 'Failed to load details.').show();
            },
            error: function (xhr) {
                $('#tdmLoading').hide();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load details.';
                $('#tdmError').text(msg).show();
            }
        });
    }

    function addMovieTitle(raw) {
        var title = normalizeTitle(raw);
        if (!title) return;
        var dup = false;
        for (var i = 0; i < selectedMovies.length; i++) {
            if (selectedMovies[i].toLowerCase() === title.toLowerCase()) { dup = true; break; }
        }
        if (!dup) { selectedMovies.push(title); renderChips(); }
    }

    function renderChips() {
        var holder = $('#trmSelectedMovies');
        holder.empty();
        for (var i = 0; i < selectedMovies.length; i++) {
            (function (idx, title) {
                var chip = $('<span/>', { 'class': 'trm-chip' });
                chip.append($('<span/>', { text: title }));
                var btn = $('<button/>', { type: 'button', text: 'x', title: 'Remove' });
                btn.on('click', function () { selectedMovies.splice(idx, 1); renderChips(); });
                chip.append(btn);
                holder.append(chip);
            }(i, selectedMovies[i]));
        }
        syncPayload();
    }

    function renderResults(items) {
        var holder = $('#trmMovieResults');
        holder.empty();
        if (!Array.isArray(items) || items.length === 0) {
            holder.append($('<div/>', { 'class': 'trm-result-item', text: 'No matching titles found.', css: { cursor: 'default', color: '#8a99a8' } }));
            holder.show();
            return;
        }
        for (var i = 0; i < items.length; i++) {
            (function (item) {
                var title = normalizeTitle(item.title || '');
                if (!title) return;
                var parts = [item.type || '', item.year || '', item.vj ? ('VJ ' + item.vj) : ''];
                var meta = [];
                for (var j = 0; j < parts.length; j++) { if (parts[j]) meta.push(parts[j]); }
                var metaStr = meta.join(' \u2022 ');
                var row = $('<div/>', { 'class': 'trm-result-item' });
                var titleEl = $('<strong/>').text(title);
                row.append(titleEl);
                if (metaStr) { row.append($('<br/>')).append($('<small/>').text(metaStr)); }
                row.on('click', function () {
                    addMovieTitle(title);
                    $('#trmMovieSearch').val('');
                    holder.hide().empty();
                });
                holder.append(row);
            }(items[i]));
        }
        holder.show();
    }

    function cleanName(n) {
        var v = String(n || '').trim();
        return (!v || /\bguest\b/i.test(v)) ? '' : v;
    }

    function personalize(msg, ctx) {
        return String(msg || '')
            .replace(/\{name\}/g, ctx.userName)
            .replace(/\{hello_name\}/g, ctx.helloName)
            .replace(/\{agent_name\}/g, ctx.agentName)
            .replace(/\{app_name\}/g, ctx.appName)
            .replace(/\{ticket_type\}/g, ctx.ticketTypeLabel);
    }

    function getTemplate(type, ctx) {
        var list = templatesByType[type] || templatesByType['general'] || [];
        var base = (list[0] && String(list[0]).trim())
            || 'Hello{hello_name}, this is {agent_name} from {app_name}. We are following up on your {ticket_type} request.';
        return personalize(base, ctx);
    }

    $(document).off('click.stcWaEngage', '.js-wa-engage').on('click.stcWaEngage', '.js-wa-engage', function () {
        var type    = String($(this).data('type') || 'general');
        var name    = cleanName(String($(this).data('name') || ''));
        activePhone = String($(this).data('phone') || '');
        var appName = String($(this).data('app') || 'Katogo');
        var ctx = {
            userName:       name,
            helloName:      name ? ' ' + name : '',
            agentName:      String(activeAgentName || 'Support Team').trim() || 'Support Team',
            appName:        appName,
            ticketTypeLabel: typeLabel(type)
        };

        renderWaTemplateButtons(type, ctx);

        // Default to first template, but allow instant switch with buttons.
        $('#waMessage').val(getTemplate(type, ctx));
        $('#waEngageModal').modal('show');
    });

    $(document).off('click.stcWaTemplatePick', '.js-wa-template-btn').on('click.stcWaTemplatePick', '.js-wa-template-btn', function () {
        var msg = String($(this).data('template') || '').trim();
        if (!msg) {
            return;
        }
        $('#waTemplateButtons .js-wa-template-btn').removeClass('btn-primary').addClass('btn-default').css({ background: '#fff', color: '#2f3e4e' });
        $(this).removeClass('btn-default').addClass('btn-primary').css({ background: '#3c8dbc', color: '#fff' });
        $('#waMessage').val(msg);
    });

    $(document).off('click.stcWaOpen', '#waOpenBtn').on('click.stcWaOpen', '#waOpenBtn', function () {
        var msg = String($('#waMessage').val() || '').trim();
        if (!activePhone) { toastr.error('This user has no valid WhatsApp number.'); return; }
        window.open('https://wa.me/' + activePhone + '?text=' + encodeURIComponent(msg), '_blank');
    });

    $(document).off('click.stcRespondOpen', '.js-ticket-respond').on('click.stcRespondOpen', '.js-ticket-respond', function () {
        var ticketId      = String($(this).data('ticket') || '');
        var ticketType    = String($(this).data('type') || 'general');
        var userName      = String($(this).data('name') || 'Customer');
        var phone         = String($(this).data('phone') || 'N/A');
        var isMovieReq    = String($(this).data('movie-request') || '0') === '1' || ticketType === 'movie_request';
        var selectedRaw   = String($(this).attr('data-selected-movies') || '[]');
        var preselected   = [];

        try {
            var parsed = JSON.parse(selectedRaw);
            if (Array.isArray(parsed)) {
                preselected = parsed.map(function (x) { return normalizeTitle(x); }).filter(function (x) { return !!x; });
            }
        } catch (e) { preselected = []; }

        activeRespondBtn = $(this);
        activeRespondId = ticketId;
        $('#trmTicketId').text('#' + ticketId);
        $('#trmTicketType').text(typeLabel(ticketType));
        $('#trmUser').text(userName || 'Customer');
        $('#trmPhone').text(phone || 'N/A');
        $('#trmMovieHint').toggle(isMovieReq);
        $('#trmMovieListWrap').toggle(isMovieReq || ticketType === 'content_issue');
        $('#trmMessage').val(isMovieReq
            ? 'Hello ' + (userName || 'there') + ', thank you for your movie request. We reviewed your request and below are the suggested titles/updates. We will keep you posted on availability.'
            : 'Hello ' + (userName || 'there') + ', thank you for contacting support. We are on your request and will guide you until it is resolved.');

        selectedMovies = preselected;
        $('#trmMovieSearch').val('');
        $('#trmMovieResults').hide().empty();
        renderChips();
        $('#trmInternalNote').prop('checked', false);
        $('#trmShowToCustomer').prop('checked', true);
        $('#trmCustomerSeen').prop('checked', false);
        $('#ticketRespondModal').modal('show');
    });

    $(document).off('click.stcDetailsOpen', '.js-ticket-details').on('click.stcDetailsOpen', '.js-ticket-details', function () {
        openTicketDetails($(this).data('ticket'));
    });

    $(document).off('click.stcDetailsRespond', '#tdmRespond').on('click.stcDetailsRespond', '#tdmRespond', function () {
        if (!activeDetailsId) { return; }
        $('#ticketDetailsModal').modal('hide');
        var btn = $('.js-ticket-respond[data-ticket="' + activeDetailsId + '"]').first();
        if (btn.length) {
            btn.trigger('click');
        } else {
            window.location.href = ticketUrl(activeDetailsId);
        }
    });

    $(document).off('click.stcDetailsEngage', '#tdmEngage').on('click.stcDetailsEngage', '#tdmEngage', function () {
        if (!activeDetailsId) { return; }
        $('#ticketDetailsModal').modal('hide');
        var btn = $('.js-wa-engage[data-ticket="' + activeDetailsId + '"]').first();
        if (btn.length) {
            btn.trigger('click');
        } else {
            toastr.warning('No valid phone was detected for this ticket.');
        }
    });

    $(document).off('change.stcInternalToggle', '#trmInternalNote').on('change.stcInternalToggle', '#trmInternalNote', function () {
        if ($(this).is(':checked')) {
            $('#trmShowToCustomer').prop('checked', false);
            $('#trmCustomerSeen').prop('checked', false);
        } else {
            $('#trmShowToCustomer').prop('checked', true);
        }
    });

    $(document).off('change.stcShowToggle', '#trmShowToCustomer').on('change.stcShowToggle', '#trmShowToCustomer', function () {
        if (!$(this).is(':checked')) {
            $('#trmCustomerSeen').prop('checked', false);
        }
    });

    $(document).off('keydown.stcMovieSearch', '#trmMovieSearch').on('keydown.stcMovieSearch', '#trmMovieSearch', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var typed = normalizeTitle($(this).val());
            if (typed) { addMovieTitle(typed); $(this).val(''); $('#trmMovieResults').hide().empty(); }
        }
    });

    $(document).off('input.stcMovieSearch', '#trmMovieSearch').on('input.stcMovieSearch', '#trmMovieSearch', function () {
        var q = normalizeTitle($(this).val());
        if (movieSearchTimer) clearTimeout(movieSearchTimer);
        if (q.length < 2) { $('#trmMovieResults').hide().empty(); return; }
        $('#trmMovieResults').show().html('<div class="trm-result-item" style="cursor:default;color:#8a99a8">Searching titles...</div>');
        movieSearchTimer = setTimeout(function () {
            $.ajax({
                url: movieSearchUrl, type: 'GET', dataType: 'json', data: { q: q },
                success: function (res) { renderResults(res && Array.isArray(res.items) ? res.items : []); },
                error:   function () { $('#trmMovieResults').show().html('<div class="trm-result-item" style="cursor:default;color:#8a99a8">Search failed. Try again.</div>'); }
            });
        }, 250);
    });

    $(document).off('click.stcOutside').on('click.stcOutside', function (e) {
        if (!$(e.target).closest('#trmMovieSearch, #trmMovieResults').length) {
            $('#trmMovieResults').hide();
        }
    });

    $(document).off('click.stcSubmit', '#trmSubmitBtn').on('click.stcSubmit', '#trmSubmitBtn', function () {
        if (!activeRespondId) { toastr.error('Ticket context is missing.'); return; }
        var message   = String($('#trmMessage').val() || '').trim();
        var movieList = String($('#trmMovieList').val() || '').trim();
        var internal  = $('#trmInternalNote').is(':checked') ? 1 : 0;
        var showToCustomer = $('#trmShowToCustomer').is(':checked') ? 1 : 0;
        var customerSeen = $('#trmCustomerSeen').is(':checked') ? 1 : 0;
        if (message.length < 3) { toastr.warning('Please provide a meaningful response message.'); return; }
        var btn = $(this), orig = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
        $.ajax({
            url:      String(ajaxRespondBase).replace(/\/$/, '') + '/' + activeRespondId + '/ajax-respond',
            type:     'POST',
            dataType: 'json',
            data:     {
                _token: csrfToken,
                message: message,
                movie_titles: movieList,
                is_internal_note: internal,
                show_to_customer: showToCustomer,
                customer_seen: customerSeen
            },
            success: function (res) {
                if (res && res.success) {
                    toastr.success(res.message || 'Response sent.');
                    $('#ticketRespondModal').modal('hide');
                    activeRespondId = null;

                    // Keep response flow fully AJAX without pjax reload (pjax script eval can throw syntax errors).
                    if (activeRespondBtn && activeRespondBtn.length) {
                        activeRespondBtn.removeClass('btn-primary').addClass('btn-success').html('<i class="fa fa-check"></i> Responded');
                        activeRespondBtn.attr('data-selected-movies', JSON.stringify(selectedMovies));
                        var row = activeRespondBtn.closest('tr');
                        row.find('td').each(function () {
                            var cell = $(this);
                            if (cell.text().indexOf('Unread') !== -1 && cell.find('.label-danger').length) {
                                cell.html('—');
                            }
                        });
                    }
                } else {
                    toastr.error((res && res.message) ? res.message : 'Failed to send response.');
                }
            },
            error: function (xhr) {
                toastr.error((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Request failed. Please try again.');
            },
            complete: function () { btn.prop('disabled', false).html(orig); }
        });
    });
}(jQuery));
JSCODE;
    }

    protected function form()
    {
        $form = new Form(new CustomerTicket());
        $statusOptions = $this->statusOptions();
        $ticketTypeOptions = $this->ticketTypeOptions();
        $resolutionOptions = $this->resolutionOptions();
        $agentOptions = $this->supportAgentOptions();
        $userSearchUrl = $this->adminUserSearchUrl();
        $actionTypeOptions = array_combine(
            CustomerTicketRecord::$validActionTypes,
            array_map(fn($v) => ucwords(str_replace('_', ' ', $v)), CustomerTicketRecord::$validActionTypes)
        );

        $form->ignore([
            'movie_request_payload_json',
            'initial_record_message',
            'initial_record_sender_type',
            'initial_record_action_type',
            'initial_record_action_description',
            'initial_record_internal_note',
            'initial_record_show_to_customer',
            'initial_record_customer_seen',
        ]);

        $form->tab('Ticket', function (Form $form) use ($statusOptions, $ticketTypeOptions, $resolutionOptions, $agentOptions, $userSearchUrl) {
            $form->select('user_id', 'User')
                ->options(function ($id) {
                    $user = User::find($id);
                    if (!$user) {
                        return [];
                    }

                    $label = $user->name ?: ('User #' . $user->id);
                    if ($user->email) {
                        $label .= ' (' . $user->email . ')';
                    }

                    return [$user->id => $label];
                })
                ->ajax($userSearchUrl)
                ->rules('required')
                ->help('Search by name, email, or ID.');

            $form->text('subject', 'Subject')->rules('nullable|max:255');
            $form->select('status', 'Status')->options($statusOptions)->default('open')->rules('required');
            $form->select('ticket_type', 'Type')->options($ticketTypeOptions)->default('general')->rules('required');
            $form->select('resolution_state', 'Resolve')->options($resolutionOptions)->default('unresolved')->rules('required');
            $form->select('assigned_to', 'Assigned To')->options($agentOptions)
                ->help('Support-team or administrator account responsible for the ticket.');
        });

        $form->tab('Context', function (Form $form) {
            $form->select('app_type', 'App')->options(self::APP_TYPE_OPTIONS);
            $form->select('platform_type', 'Platform Type')->options(self::PLATFORM_TYPE_OPTIONS);
            $form->select('platform', 'Platform')->options(self::DEVICE_PLATFORM_OPTIONS);
            $form->select('account_origin', 'Account Origin')->options(self::ACCOUNT_ORIGIN_OPTIONS)
                ->help('Use Auto Device for auto-created accounts.');
        });

        $form->tab('Tracking', function (Form $form) {
            $form->switch('agent_has_contacted_customer', 'Agent Contacted')->states(self::SWITCH_STATES)->default(0);
            $form->switch('customer_has_responded', 'Customer Replied')->states(self::SWITCH_STATES)->default(0);
            $form->switch('has_unread_user', 'Unread For User')->states(self::SWITCH_STATES)->default(0);
            $form->switch('has_unread_support', 'Unread For Support')->states(self::SWITCH_STATES)->default(0);
            $form->switch('is_movie_request', 'Is Movie Request')->states(self::SWITCH_STATES)->default(0);
            $form->number('rating_of_satisfaction', 'Rating')->min(1)->max(5)
                ->help('Optional satisfaction rating from 1 to 5.');
            $form->number('reply_count', 'Replies')->min(0)->default(0);
            $form->datetime('last_reply_at', 'Last Reply');
        });

        $form->tab('Movie Request', function (Form $form) {
            $payload = [];
            try {
                $payload = is_array($form->model()->movie_request_payload ?? null)
                    ? $form->model()->movie_request_payload
                    : [];
            } catch (\Throwable $th) {
                $payload = [];
            }

            $form->textarea('movie_request_payload_json', 'Movie Request Payload (JSON)')
                ->default(!empty($payload) ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                ->rows(10)
                ->help('Optional: provide structured movie request payload as valid JSON. Leave blank to keep current value.');
        });

        $form->tab('Initial Record', function (Form $form) use ($actionTypeOptions) {
            $form->textarea('initial_record_message', 'Initial Conversation Record')
                ->rows(6)
                ->help('Optional: if provided on create, this will create the first ticket record automatically.');

            $form->select('initial_record_sender_type', 'Initial Sender Type')->options([
                'support_team' => 'Support Team',
                'user' => 'User',
                'system' => 'System',
                'auto' => 'Auto',
            ])->default('support_team');

            $form->select('initial_record_action_type', 'Initial Action Type')
                ->options($actionTypeOptions)
                ->default('none');

            $form->text('initial_record_action_description', 'Initial Action Description')
                ->help('Optional short context, e.g. "Ticket opened by admin for payment follow-up".');

            $form->switch('initial_record_internal_note', 'Initial Internal Note')
                ->states(self::SWITCH_STATES)
                ->default(0);
            $form->switch('initial_record_show_to_customer', 'Initial Show To Customer')
                ->states(self::SWITCH_STATES)
                ->default(1);
            $form->switch('initial_record_customer_seen', 'Initial Mark Customer Seen')
                ->states(self::SWITCH_STATES)
                ->default(0);
        });

        $form->tab('Audit', function (Form $form) {
            $form->display('id', 'ID');
            $form->display('created_at', 'Created');
            $form->display('updated_at', 'Updated');
        });

        $form->saving(function (Form $form) {
            if ($form->rating_of_satisfaction !== null && $form->rating_of_satisfaction !== '') {
                $rating = (int) $form->rating_of_satisfaction;
                if ($rating < 1 || $rating > 5) {
                    throw new \InvalidArgumentException('Rating must be between 1 and 5.');
                }
                $form->rating_of_satisfaction = $rating;
            } else {
                $form->rating_of_satisfaction = null;
            }

            if (empty($form->resolution_state)) {
                $form->resolution_state = 'unresolved';
            }

            if ($form->status === 'resolved' || $form->status === 'closed') {
                if (empty($form->resolution_state) || $form->resolution_state === 'unresolved') {
                    $form->resolution_state = 'resolved';
                }
            }

            if (empty($form->reply_count)) {
                $form->reply_count = 0;
            }

            if (request()->has('movie_request_payload_json')) {
                $rawPayload = trim((string) request()->input('movie_request_payload_json', ''));
                if ($rawPayload === '') {
                    $form->movie_request_payload = null;
                } else {
                    $decodedPayload = json_decode($rawPayload, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedPayload)) {
                        throw new \InvalidArgumentException('Movie Request Payload JSON is invalid.');
                    }
                    $form->movie_request_payload = $decodedPayload;
                }
            }
        });

        $form->saved(function (Form $form) {
            /** @var CustomerTicket $ticket */
            $ticket = $form->model();

            if ($form->isCreating()) {
                $initialMessage = trim((string) request()->input('initial_record_message', ''));
                if ($initialMessage !== '') {
                    $senderType = (string) request()->input('initial_record_sender_type', 'support_team');
                    if (!in_array($senderType, ['support_team', 'user', 'system', 'auto'], true)) {
                        $senderType = 'support_team';
                    }

                    $actionType = (string) request()->input('initial_record_action_type', 'none');
                    if (!in_array($actionType, CustomerTicketRecord::$validActionTypes, true)) {
                        $actionType = 'none';
                    }

                    $isInternal = (bool) request()->boolean('initial_record_internal_note', false);
                    $showToCustomer = (bool) request()->boolean('initial_record_show_to_customer', !$isInternal);
                    $customerSeen = (bool) request()->boolean('initial_record_customer_seen', false);

                    $senderId = null;
                    if ($senderType === 'support_team') {
                        $senderId = auth('admin')->id();
                    } elseif ($senderType === 'user') {
                        $senderId = (int) ($ticket->user_id ?? 0);
                    }

                    CustomerTicketRecord::create([
                        'customer_ticket_id' => (int) $ticket->id,
                        'sender_type' => $senderType,
                        'sender_id' => $senderId,
                        'message' => $initialMessage,
                        'action_type' => $actionType,
                        'action_description' => (string) request()->input('initial_record_action_description', ''),
                        'is_internal_note' => $isInternal,
                        'show_to_customer' => $showToCustomer,
                        'is_read_by_user' => $customerSeen,
                        'customer_seen' => $customerSeen,
                        'customer_seen_at' => $customerSeen ? now() : null,
                        'is_read_by_support' => true,
                    ]);

                    $ticket->reply_count = ((int) $ticket->reply_count) + 1;
                    $ticket->last_reply_at = now();
                    if (!$isInternal && $showToCustomer && !$customerSeen && in_array($senderType, ['support_team', 'system', 'auto'], true)) {
                        $ticket->has_unread_user = true;
                    }
                    if ($senderType === 'user') {
                        $ticket->has_unread_support = true;
                        $ticket->customer_has_responded = true;
                    }
                    if ($senderType === 'support_team') {
                        $ticket->agent_has_contacted_customer = true;
                    }
                    $ticket->save();
                }
            }

            $this->logAudit(
                auth('admin')->id(),
                $form->isCreating() ? 'admin_ticket_created' : 'admin_ticket_updated',
                'customer_ticket',
                (int) $ticket->id,
                $form->isCreating() ? 'Support ticket created from admin form.' : 'Support ticket updated from admin form.',
                [
                    'status' => $ticket->status,
                    'ticket_type' => $ticket->ticket_type,
                    'resolution_state' => $ticket->resolution_state,
                    'assigned_to' => $ticket->assigned_to,
                ]
            );
        });

        return $form;
    }

    // ── 3. Ticket Detail ──────────────────────────────────────────────

    public function ticketDetail(int $id, Content $content)
    {
        $ticket  = CustomerTicket::with(['user', 'assignedAgent'])->findOrFail($id);
        $records = CustomerTicketRecord::where('customer_ticket_id', $id)
            ->orderBy('created_at')
            ->get();

        $records->each(function (CustomerTicketRecord $record) use ($ticket) {
            $record->setAttribute('message_body', $this->adminStripSuggestedMoviesSection((string) $record->message));
            $record->setAttribute('movie_suggestions', $this->adminBuildMovieSuggestions(
                $this->adminExtractMovieSuggestionTitles($record, $ticket)
            ));
        });

        // Fetch support team members for assign dropdown
        $supportRoleId = DB::table('admin_roles')->where('slug', 'support_team')->value('id');
        $adminRoleId   = DB::table('admin_roles')->where('slug', 'administrator')->value('id');
        $agentIds      = collect();
        if ($supportRoleId) {
            $agentIds = $agentIds->merge(
                DB::table('admin_role_users')->where('role_id', $supportRoleId)->pluck('user_id')
            );
        }
        if ($adminRoleId) {
            $agentIds = $agentIds->merge(
                DB::table('admin_role_users')->where('role_id', $adminRoleId)->pluck('user_id')
            );
        }
        $agents = User::whereIn('id', $agentIds->unique())->select('id', 'name')->get();

        // Mark unread
        CustomerTicketRecord::where('customer_ticket_id', $id)
            ->where('is_read_by_support', false)
            ->update(['is_read_by_support' => true]);
        $ticket->has_unread_support = false;
        $ticket->save();

        $validStatuses = CustomerTicket::$validStatuses;
        $validTicketTypes = CustomerTicket::$validTicketTypes;
        $validResolutionStates = CustomerTicket::$validResolutionStates;
        $validActionTypes = CustomerTicketRecord::$validActionTypes;

        return $content
            ->title("Ticket #{$id}")
            ->description("Support conversation with {$ticket->user?->name}")
            ->body(
                view('admin.support_ticket_detail', compact(
                    'ticket', 'records', 'agents', 'validStatuses', 'validTicketTypes', 'validResolutionStates', 'validActionTypes'
                ))
            );
    }

    private function adminExtractMovieSuggestionTitles(CustomerTicketRecord $record, CustomerTicket $ticket): array
    {
        $titles = $this->adminParseMovieTitlesFromActionDescription((string) $record->action_description);

        if (empty($titles)) {
            $titles = $this->adminParseMovieTitlesFromMessage((string) $record->message);
        }

        if (
            empty($titles)
            && $ticket->ticket_type === 'movie_request'
            && $record->sender_type === 'support_team'
        ) {
            $payload = is_array($ticket->movie_request_payload) ? $ticket->movie_request_payload : [];
            $adminResponse = is_array($payload['admin_response'] ?? null) ? $payload['admin_response'] : [];
            $candidateTitles = $adminResponse['movie_titles'] ?? null;
            $candidateMessage = $this->adminStripSuggestedMoviesSection((string) ($adminResponse['message'] ?? ''));
            $recordMessage = $this->adminStripSuggestedMoviesSection((string) $record->message);

            if (
                is_array($candidateTitles)
                && !empty($candidateTitles)
                && $candidateMessage !== ''
                && Str::lower(trim($candidateMessage)) === Str::lower(trim($recordMessage))
            ) {
                $titles = $candidateTitles;
            }
        }

        return $this->adminNormalizeMovieTitles($titles);
    }

    private function adminParseMovieTitlesFromActionDescription(string $actionDescription): array
    {
        $actionDescription = trim($actionDescription);
        if ($actionDescription === '') {
            return [];
        }

        if (stripos($actionDescription, 'Suggested movies:') === 0) {
            $actionDescription = trim(substr($actionDescription, strlen('Suggested movies:')));
        }

        return preg_split('/\s*,\s*/', $actionDescription) ?: [];
    }

    private function adminParseMovieTitlesFromMessage(string $message): array
    {
        $markerPos = stripos($message, 'Suggested movies:');
        if ($markerPos === false) {
            return [];
        }

        $listSection = trim(substr($message, $markerPos + strlen('Suggested movies:')));
        $lines = preg_split('/\r\n|\r|\n/', $listSection) ?: [];

        return array_map(
            static fn(string $line) => trim(ltrim($line, "- \t")),
            array_filter($lines, static fn(string $line) => trim($line) !== '')
        );
    }

    private function adminNormalizeMovieTitles(array $titles): array
    {
        $normalized = [];
        foreach ($titles as $title) {
            $cleanTitle = preg_replace('/\s+/', ' ', trim((string) $title)) ?? '';
            if ($cleanTitle === '') {
                continue;
            }
            if (!in_array($cleanTitle, $normalized, true)) {
                $normalized[] = $cleanTitle;
            }
        }

        return $normalized;
    }

    private function adminStripSuggestedMoviesSection(string $message): string
    {
        $markerPos = stripos($message, 'Suggested movies:');
        if ($markerPos === false) {
            return trim($message);
        }

        return trim(substr($message, 0, $markerPos));
    }

    private function adminBuildMovieSuggestions(array $titles): array
    {
        if (empty($titles)) {
            return [];
        }

        $lookup = [];
        foreach ($titles as $title) {
            $lookup[Str::lower(trim($title))] = $title;
        }

        $matchedMovies = MovieModel::query()
            ->select(['id', 'title', 'thumbnail_url', 'image_url', 'year', 'vj', 'type', 'status'])
            ->whereIn(DB::raw('LOWER(TRIM(title))'), array_keys($lookup))
            ->get();

        $movieMap = [];
        foreach ($matchedMovies as $movie) {
            $movieMap[Str::lower(trim((string) $movie->getRawOriginal('title')))] = $movie;
        }

        $items = [];
        foreach ($titles as $title) {
            $normalizedTitle = Str::lower(trim($title));
            $movie = $movieMap[$normalizedTitle] ?? null;

            $items[] = [
                'id' => $movie ? (int) $movie->id : null,
                'title' => $movie ? (string) $movie->title : $title,
                'thumbnail_url' => $movie ? (string) ($movie->thumbnail_url ?? '') : '',
                'image_url' => $movie ? (string) ($movie->image_url ?? '') : '',
                'year' => $movie ? (string) ($movie->year ?? '') : '',
                'vj' => $movie ? (string) ($movie->vj ?? '') : '',
                'type' => $movie ? (string) ($movie->type ?? '') : '',
                'status' => $movie ? (string) ($movie->status ?? '') : '',
                'is_available' => $movie !== null,
            ];
        }

        return $items;
    }

    // ── 4. Reply from admin ───────────────────────────────────────────

    public function replyTicket(Request $request, int $id)
    {
        $ticket = CustomerTicket::findOrFail($id);

        $message    = trim((string) $request->input('message', ''));
        $isInternal = (bool) $request->input('is_internal_note', false);
        $showToCustomer = (bool) $request->boolean('show_to_customer', !$isInternal);
        $customerSeen = (bool) $request->boolean('customer_seen', false);
        $newStatus  = $request->input('status');
        $assignTo   = $request->input('assigned_to');
        $newType    = $request->input('ticket_type');
        $newResolution = $request->input('resolution_state');
        $rating = $request->input('rating_of_satisfaction');
        $actionType = (string) $request->input('action_type', 'none');

        if (empty($message)) {
            return redirect()->back()->with('error', 'Message cannot be empty.');
        }

        if (!in_array($actionType, CustomerTicketRecord::$validActionTypes, true)) {
            return redirect()->back()->with('error', 'Invalid action type selected.');
        }

        $agent = auth('admin')->user();
        $senderId = $agent ? $agent->id : null;

        CustomerTicketRecord::create([
            'customer_ticket_id' => $ticket->id,
            'sender_type'        => 'support_team',
            'sender_id'          => $senderId,
            'message'            => $message,
            'action_type'        => $actionType,
            'action_description' => $request->input('action_description'),
            'is_internal_note'   => $isInternal,
            'show_to_customer'   => $showToCustomer,
            'is_read_by_user'    => $customerSeen,
            'customer_seen'      => $customerSeen,
            'customer_seen_at'   => $customerSeen ? now() : null,
            'is_read_by_support' => true,
        ]);

        $ticket->last_reply_at = now();
        $ticket->reply_count   = ($ticket->reply_count ?? 0) + 1;

        if (!$isInternal && $showToCustomer && !$customerSeen) {
            $ticket->has_unread_user    = true;
            $ticket->has_unread_support = false;
        }

        if ($newStatus && in_array($newStatus, CustomerTicket::$validStatuses, true)) {
            $ticket->status = $newStatus;
        } elseif ($ticket->status === 'open') {
            $ticket->status = 'pending';
        }

        if ($newType && in_array($newType, CustomerTicket::$validTicketTypes, true)) {
            $ticket->ticket_type = $newType;
        }

        if ($newResolution && in_array($newResolution, CustomerTicket::$validResolutionStates, true)) {
            $ticket->resolution_state = $newResolution;
        } elseif ($newStatus === 'resolved') {
            $ticket->resolution_state = 'resolved';
        }

        if ($rating !== null && $rating !== '') {
            $ratingValue = (int) $rating;
            if ($ratingValue < 1 || $ratingValue > 5) {
                return redirect()->back()->with('error', 'Rating must be between 1 and 5.');
            }
            $ticket->rating_of_satisfaction = $ratingValue;
        }

        $ticket->agent_has_contacted_customer = true;

        if ($assignTo && (int) $assignTo > 0) {
            $ticket->assigned_to = (int) $assignTo;
        }

        $ticket->save();

        $this->logAudit(
            $senderId,
            'admin_ticket_reply',
            'customer_ticket',
            (int) $ticket->id,
            'Admin/support reply posted on ticket.',
            [
                'action_type' => $actionType,
                'status' => $ticket->status,
                'resolution_state' => $ticket->resolution_state,
                'assigned_to' => $ticket->assigned_to,
                'show_to_customer' => $showToCustomer,
                'customer_seen' => $customerSeen,
            ]
        );

        return redirect()->back()->with('success', 'Reply sent successfully.');
    }

    // ── 5. AJAX quick response from ticket list ──────────────────────

    public function ajaxMovieSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $rows = MovieModel::query()
            ->select(['id', 'title', 'year', 'vj', 'type'])
            ->where('title', 'like', '%' . $q . '%')
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$q . '%'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $items = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'title' => (string) ($row->title ?? ''),
                'year' => (string) ($row->year ?? ''),
                'vj' => (string) ($row->vj ?? ''),
                'type' => (string) ($row->type ?? ''),
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function ajaxTicketDetails(Request $request, int $id)
    {
        $ticket = CustomerTicket::query()
            ->with([
                'user:id,name,email,phone_number,account_state,account_origin,last_online_at,created_at',
                'assignedAgent:id,name,email,phone_number',
                'records' => function ($query) {
                    $query->with('sender:id,name,email,phone_number')
                        ->orderByDesc('created_at')
                        ->limit(80);
                },
            ])
            ->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        $records = $ticket->records->map(function (CustomerTicketRecord $record) use ($ticket) {
            $movieSuggestions = $this->adminBuildMovieSuggestions(
                $this->adminExtractMovieSuggestionTitles($record, $ticket)
            );

            return [
                'id' => (int) $record->id,
                'sender_type' => (string) ($record->sender_type ?? ''),
                'sender_name' => (string) ($record->sender->name ?? ucfirst((string) $record->sender_type)),
                'sender_email' => (string) ($record->sender->email ?? ''),
                'message' => (string) ($record->message ?? ''),
                'message_body' => $this->adminStripSuggestedMoviesSection((string) $record->message),
                'action_type' => (string) ($record->action_type ?? 'none'),
                'action_description' => (string) ($record->action_description ?? ''),
                'is_internal_note' => (bool) $record->is_internal_note,
                'show_to_customer' => (bool) $record->show_to_customer,
                'customer_seen' => (bool) $record->customer_seen,
                'is_read_by_support' => (bool) $record->is_read_by_support,
                'movie_suggestions' => $movieSuggestions,
                'created_at' => optional($record->created_at)->toDateTimeString(),
                'updated_at' => optional($record->updated_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => [
                    'id' => (int) $ticket->id,
                    'status' => (string) ($ticket->status ?? ''),
                    'ticket_type' => (string) ($ticket->ticket_type ?? ''),
                    'resolution_state' => (string) ($ticket->resolution_state ?? ''),
                    'subject' => (string) ($ticket->subject ?? ''),
                    'app_type' => (string) ($ticket->app_type ?? ''),
                    'platform_type' => (string) ($ticket->platform_type ?? ''),
                    'platform' => (string) ($ticket->platform ?? ''),
                    'account_origin' => (string) ($ticket->account_origin ?? ''),
                    'assigned_to' => (int) ($ticket->assigned_to ?? 0),
                    'assigned_to_name' => (string) ($ticket->assignedAgent->name ?? ''),
                    'agent_has_contacted_customer' => (bool) $ticket->agent_has_contacted_customer,
                    'customer_has_responded' => (bool) $ticket->customer_has_responded,
                    'has_unread_support' => (bool) $ticket->has_unread_support,
                    'has_unread_user' => (bool) $ticket->has_unread_user,
                    'reply_count' => (int) ($ticket->reply_count ?? 0),
                    'rating_of_satisfaction' => $ticket->rating_of_satisfaction,
                    'last_reply_at' => optional($ticket->last_reply_at)->toDateTimeString(),
                    'created_at' => optional($ticket->created_at)->toDateTimeString(),
                    'updated_at' => optional($ticket->updated_at)->toDateTimeString(),
                    'user_id' => (int) ($ticket->user_id ?? 0),
                    'user_name' => (string) ($ticket->user->name ?? ''),
                    'user_email' => (string) ($ticket->user->email ?? ''),
                    'user_phone' => (string) ($ticket->user->phone_number ?? ''),
                    'user_account_state' => (string) ($ticket->user->account_state ?? ''),
                    'movie_request_payload' => is_array($ticket->movie_request_payload) ? $ticket->movie_request_payload : [],
                ],
                'records' => $records,
            ],
        ]);
    }

    public function ajaxRespondTicket(Request $request, int $id)
    {
        $ticket = CustomerTicket::with('user')->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $message = trim((string) $request->input('message', ''));
        if ($message === '' || mb_strlen($message) < 3) {
            return response()->json(['success' => false, 'message' => 'Message must be at least 3 characters.'], 422);
        }

        $isInternal = (bool) $request->boolean('is_internal_note', false);
        $showToCustomer = (bool) $request->boolean('show_to_customer', !$isInternal);
        $customerSeen = (bool) $request->boolean('customer_seen', false);
        $status = (string) $request->input('status', '');
        $resolution = (string) $request->input('resolution_state', '');
        $isMovieRequest = ((string) $ticket->ticket_type === 'movie_request') || (bool) $ticket->is_movie_request;
        $defaultActionType = $isMovieRequest ? 'needs_user_action' : 'none';
        $actionType = (string) $request->input('action_type', $defaultActionType);

        if (!in_array($actionType, CustomerTicketRecord::$validActionTypes, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid action type.'], 422);
        }

        $rawMovieTitles = (string) $request->input('movie_titles', '');
        $movieTitles = collect(preg_split('/[\n,;|]+/', $rawMovieTitles) ?: [])
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->take(20)
            ->values()
            ->all();

        if ($isMovieRequest && empty($movieTitles) && str_contains(strtolower($message), 'movie')) {
            // Not hard-required, but guide the agent to keep movie-request responses actionable.
            // We still allow saving if they intentionally send only text.
        }

        $agent = auth('admin')->user();
        $senderId = $agent ? (int) $agent->id : null;

        $actionDescription = null;
        if (!empty($movieTitles)) {
            $actionDescription = 'Suggested movies: ' . implode(', ', $movieTitles);
        }

        if (!$isInternal && !empty($movieTitles)) {
            $message .= "\n\nSuggested movies:\n- " . implode("\n- ", $movieTitles);
        }

        DB::beginTransaction();
        try {
            CustomerTicketRecord::create([
                'customer_ticket_id' => $ticket->id,
                'sender_type' => 'support_team',
                'sender_id' => $senderId,
                'message' => $message,
                'action_type' => $actionType,
                'action_description' => $actionDescription,
                'is_internal_note' => $isInternal,
                'show_to_customer' => $showToCustomer,
                'is_read_by_user' => $customerSeen,
                'customer_seen' => $customerSeen,
                'customer_seen_at' => $customerSeen ? now() : null,
                'is_read_by_support' => true,
            ]);

            $ticket->last_reply_at = now();
            $ticket->reply_count = ((int) $ticket->reply_count) + 1;
            $ticket->agent_has_contacted_customer = true;

            if (!$isInternal && $showToCustomer && !$customerSeen) {
                $ticket->has_unread_user = true;
                $ticket->has_unread_support = false;
                $ticket->customer_has_responded = false;
            }

            if ($status !== '' && in_array($status, CustomerTicket::$validStatuses, true)) {
                $ticket->status = $status;
            } elseif ($ticket->status === 'open') {
                $ticket->status = 'pending';
            }

            if ($resolution !== '' && in_array($resolution, CustomerTicket::$validResolutionStates, true)) {
                $ticket->resolution_state = $resolution;
            } elseif (in_array($ticket->status, ['resolved', 'closed'], true) && $ticket->resolution_state === 'unresolved') {
                $ticket->resolution_state = 'resolved';
            }

            if ($isMovieRequest) {
                $payload = is_array($ticket->movie_request_payload) ? $ticket->movie_request_payload : [];
                $payload['admin_response'] = [
                    'message' => $message,
                    'movie_titles' => $movieTitles,
                    'responded_at' => now()->toDateTimeString(),
                    'responded_by' => $senderId,
                    'is_internal_note' => $isInternal,
                    'show_to_customer' => $showToCustomer,
                    'customer_seen' => $customerSeen,
                ];
                $ticket->movie_request_payload = $payload;
                $ticket->is_movie_request = true;
            }

            $ticket->save();

            $this->logAudit(
                $senderId,
                'admin_ticket_ajax_reply',
                'customer_ticket',
                (int) $ticket->id,
                'Quick AJAX response posted from support ticket list.',
                [
                    'action_type' => $actionType,
                    'status' => $ticket->status,
                    'resolution_state' => $ticket->resolution_state,
                    'movie_titles_count' => count($movieTitles),
                    'is_internal_note' => $isInternal,
                    'show_to_customer' => $showToCustomer,
                    'customer_seen' => $customerSeen,
                ]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send response. ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket response sent successfully.',
            'ticket_id' => (int) $ticket->id,
            'reply_count' => (int) $ticket->reply_count,
        ]);
    }

    public function updateRecordVisibility(Request $request, int $id)
    {
        $record = CustomerTicketRecord::findOrFail($id);

        $showToCustomer = (bool) $request->boolean('show_to_customer', false);
        $customerSeen = (bool) $request->boolean('customer_seen', false);

        $record->show_to_customer = $showToCustomer;
        $record->customer_seen = $customerSeen;
        $record->customer_seen_at = $customerSeen ? ($record->customer_seen_at ?: now()) : null;
        $record->is_read_by_user = $customerSeen;
        $record->save();

        $ticket = CustomerTicket::find($record->customer_ticket_id);
        if ($ticket) {
            $ticket->has_unread_user = CustomerTicketRecord::where('customer_ticket_id', $ticket->id)
                ->where('is_internal_note', false)
                ->where('show_to_customer', true)
                ->where('customer_seen', false)
                ->whereIn('sender_type', ['support_team', 'system'])
                ->exists();
            $ticket->save();
        }

        $this->logAudit(
            optional(auth('admin')->user())->id,
            'admin_ticket_record_visibility_updated',
            'customer_ticket_record',
            (int) $record->id,
            'Admin updated ticket record customer visibility/seen state.',
            [
                'ticket_id' => (int) $record->customer_ticket_id,
                'show_to_customer' => $showToCustomer,
                'customer_seen' => $customerSeen,
            ]
        );

        return redirect()->back()->with('success', 'Record visibility updated.');
    }
}
