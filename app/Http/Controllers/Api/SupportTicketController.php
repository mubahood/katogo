<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerTicket;
use App\Models\CustomerTicketRecord;
use App\Models\MovieModel;
use App\Models\SupportAuditLog;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketController extends BaseController
{
    use ApiResponser;

    private const VALID_PLATFORM_TYPES = ['lugaflix', 'luga', 'ugflix', 'muno', 'muno_app'];
    private const VALID_DEVICE_PLATFORMS = ['android', 'ios', 'web'];

    // ── Role helpers ──────────────────────────────────────────────────

    /** Returns true when the user has the support_team OR administrator role */
    private function isSupportOrAdmin(User $user): bool
    {
        return DB::table('admin_role_users')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->where('admin_role_users.user_id', $user->id)
            ->whereIn('admin_roles.slug', ['support_team', 'administrator'])
            ->exists();
    }

    private function isAdminOnly(User $user): bool
    {
        return DB::table('admin_role_users')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->where('admin_role_users.user_id', $user->id)
            ->where('admin_roles.slug', 'administrator')
            ->exists();
    }

    private function resolveRoleForAudit(User $user): string
    {
        if ($this->isAdminOnly($user)) {
            return 'administrator';
        }
        if ($this->isSupportOrAdmin($user)) {
            return 'support_team';
        }
        return 'normal_user';
    }

    private function logAudit(User $actor, string $eventType, string $entityType, ?int $entityId, string $description, array $meta = [], ?Request $request = null): void
    {
        try {
            SupportAuditLog::create([
                'actor_id' => $actor->id,
                'actor_role' => $this->resolveRoleForAudit($actor),
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'meta' => $meta,
                'ip_address' => $request?->ip(),
            ]);
        } catch (\Throwable $th) {
            // Never block support flow on audit persistence issues.
        }
    }

    // ── POST support/tickets ──────────────────────────────────────────
    // Create or re-open a ticket for the authenticated user.
    // Each user has ONE active ticket at a time.

    public function createTicket(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $ticketType = $this->normalizeTicketType((string) $r->input('ticket_type', 'general'));
        if (!$ticketType) {
            return $this->error('Invalid ticket_type. Valid: ' . implode(', ', CustomerTicket::$validTicketTypes));
        }

        $platformType = $this->normalizePlatformType(
            (string) ($r->input('platform_type') ?? $r->input('app_type') ?? $u->app_type ?? 'lugaflix')
        );
        $devicePlatform = $this->normalizeDevicePlatform((string) ($r->input('platform') ?? $u->platform ?? 'android'));

        // Prevent duplicate open tickets
        $existing = CustomerTicket::where('user_id', $u->id)
            ->whereNotIn('status', ['closed'])
            ->first();

        if ($existing) {
            // Return the existing ticket instead of creating a duplicate
            return $this->success(
                $this->formatTicket($existing, $u),
                'Your support ticket is already open.'
            );
        }

        $ticket = CustomerTicket::create([
            'user_id'        => $u->id,
            'status'         => 'open',
            'ticket_type'    => $ticketType,
            'resolution_state' => 'unresolved',
            'subject'        => $r->input('subject'),
            'account_origin' => $u->account_origin ?? 'manual',
            'app_type'       => $this->mapPlatformTypeToAppType($platformType),
            'platform_type'  => $platformType,
            'platform'       => $devicePlatform,
            'has_unread_support' => true,
        ]);

        $this->logAudit(
            $u,
            'ticket_created',
            'customer_ticket',
            $ticket->id,
            'Support ticket created.',
            [
                'ticket_type' => $ticket->ticket_type,
                'platform_type' => $ticket->platform_type,
                'account_origin' => $ticket->account_origin,
            ],
            $r
        );

        // Create the opening message if provided
        if ($r->filled('message')) {
            $this->addRecord($ticket, $u->id, 'user', $r->input('message'));
        }

        // System record marking ticket creation
        $this->addRecord($ticket, null, 'system', 'Support ticket opened.', 'none', null, false, null, true, true);

        return $this->success($this->formatTicket($ticket, $u), 'Support ticket created successfully.');
    }

    // ── GET support/tickets ───────────────────────────────────────────
    // For normal users: returns their own ticket.
    // For support/admin: returns all tickets (paginated).

    public function listTickets(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        if ($this->isSupportOrAdmin($u)) {
            // Support/admin can filter and paginate
            $query = CustomerTicket::with(['user', 'assignedAgent'])
                ->orderByDesc('last_reply_at')
                ->orderByDesc('created_at');

            if ($r->filled('status')) {
                $query->where('status', $r->input('status'));
            }
            if ($r->filled('ticket_type')) {
                $query->where('ticket_type', $r->input('ticket_type'));
            }
            if ($r->filled('resolution_state')) {
                $query->where('resolution_state', $r->input('resolution_state'));
            }
            if ($r->filled('platform_type')) {
                $query->where('platform_type', $this->normalizePlatformType((string) $r->input('platform_type')));
            }
            if ($r->filled('assigned_to')) {
                $query->where('assigned_to', $r->input('assigned_to'));
            }
            if ($r->filled('search')) {
                $search = '%' . $r->input('search') . '%';
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            }

            $perPage = min((int) $r->input('per_page', 20), 100);
            $tickets = $query->paginate($perPage);

            return $this->success([
                'tickets'      => $tickets->items(),
                'total'        => $tickets->total(),
                'per_page'     => $tickets->perPage(),
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
            ], 'Tickets loaded.');
        }

        // Normal user — return only their ticket
        $ticket = CustomerTicket::where('user_id', $u->id)
            ->orderByDesc('updated_at')
            ->first();

        if (!$ticket) {
            return $this->success(null, 'No support ticket found.');
        }

        return $this->success($this->formatTicket($ticket, $u), 'Ticket loaded.');
    }

    // ── GET support/tickets/{id} ──────────────────────────────────────
    // Get full ticket detail including all records.

    public function getTicket(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $ticket = CustomerTicket::with(['user', 'assignedAgent'])->find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        // Users can only see their own ticket
        if (!$this->isSupportOrAdmin($u) && $ticket->user_id !== $u->id) {
            return $this->error('Access denied.', 403);
        }

        // Load records — exclude internal notes for normal users
        $recordsQuery = CustomerTicketRecord::where('customer_ticket_id', $id)
            ->orderBy('created_at');

        if (!$this->isSupportOrAdmin($u)) {
            $recordsQuery
                ->where('is_internal_note', false)
                ->where('show_to_customer', true);
        }

        $records = $recordsQuery->get();

        // Mark unread messages as read
        if ($this->isSupportOrAdmin($u)) {
            CustomerTicketRecord::where('customer_ticket_id', $id)
                ->where('is_read_by_support', false)
                ->update(['is_read_by_support' => true]);
            $ticket->has_unread_support = false;
        } else {
            CustomerTicketRecord::where('customer_ticket_id', $id)
                ->where('show_to_customer', true)
                ->where('is_read_by_user', false)
                ->update([
                    'is_read_by_user' => true,
                    'customer_seen' => true,
                    'customer_seen_at' => now(),
                ]);
            $ticket->has_unread_user = false;
        }
        $ticket->save();

        return $this->success([
            'ticket'  => $this->formatTicket($ticket, $u),
            'records' => $records
                ->map(fn(CustomerTicketRecord $record) => $this->formatRecord($record, $ticket))
                ->values()
                ->all(),
        ], 'Ticket loaded.');
    }

    // ── POST support/tickets/{id}/records ─────────────────────────────
    // Add a message to a ticket.

    public function addReply(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        if (!$r->filled('message')) {
            return $this->error('Message is required.');
        }

        $ticket = CustomerTicket::find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        if ($ticket->status === 'closed') {
            return $this->error('This ticket is closed. Please open a new ticket.');
        }

        $isStaff = $this->isSupportOrAdmin($u);

        // Users can only reply to their own ticket
        if (!$isStaff && $ticket->user_id !== $u->id) {
            return $this->error('Access denied.', 403);
        }

        $senderType    = $isStaff ? 'support_team' : 'user';
        $isInternal    = $isStaff && (bool) $r->input('is_internal_note', false);
        $showToCustomer = $isStaff
            ? (bool) $r->boolean('show_to_customer', !$isInternal)
            : true;
        $customerSeen = $isStaff
            ? (bool) $r->boolean('customer_seen', false)
            : true;
        $actionType    = (string) $r->input('action_type', $this->resolveDefaultActionType($isStaff, $isInternal));
        if (!$this->isValidRecordActionType($actionType)) {
            return $this->error('Invalid action_type. Valid: ' . implode(', ', CustomerTicketRecord::$validActionTypes));
        }
        $actionDesc    = $r->input('action_description');
        $attachmentUrl = $r->input('attachment_url');

        $rating = null;
        if ($r->filled('rating_of_satisfaction')) {
            $rating = (int) $r->input('rating_of_satisfaction');
            if ($rating < 1 || $rating > 5) {
                return $this->error('rating_of_satisfaction must be between 1 and 5.');
            }
        }

        $resolutionState = null;
        if ($r->filled('resolution_state')) {
            $resolutionState = $this->normalizeResolutionState((string) $r->input('resolution_state'));
            if (!$resolutionState) {
                return $this->error('Invalid resolution_state. Valid: ' . implode(', ', CustomerTicket::$validResolutionStates));
            }
        }

        $record = $this->addRecord(
            $ticket,
            $u->id,
            $senderType,
            $r->input('message'),
            $actionType,
            $actionDesc,
            $isInternal,
            $attachmentUrl,
            $showToCustomer,
            $customerSeen
        );

        // Update ticket state
        $ticket->last_reply_at = now();
        $ticket->reply_count   = ($ticket->reply_count ?? 0) + 1;

        if ($isStaff) {
            // Support replied — mark as pending (waiting for user), set user unread
            if ($ticket->status === 'open') {
                $ticket->status = 'pending';
            }
            $ticket->agent_has_contacted_customer = true;
            if (!$isInternal) {
                $ticket->has_unread_user    = true;
                $ticket->has_unread_support = false;
            }
        } else {
            // User replied — move to in_progress, set support unread
            if (in_array($ticket->status, ['pending', 'resolved'])) {
                $ticket->status = 'in_progress';
            }
            $ticket->customer_has_responded = true;
            $ticket->has_unread_support = true;
            $ticket->has_unread_user    = false;

            if ($rating) {
                $ticket->rating_of_satisfaction = $rating;
            }
        }

        if ($resolutionState) {
            $ticket->resolution_state = $resolutionState;
        }

        $ticket->save();

        $this->logAudit(
            $u,
            'ticket_reply_added',
            'customer_ticket',
            $ticket->id,
            'Support ticket reply added.',
            [
                'record_id' => $record->id,
                'sender_type' => $senderType,
                'action_type' => $actionType,
                'status' => $ticket->status,
                'resolution_state' => $ticket->resolution_state,
            ],
            $r
        );

        return $this->success($record, 'Reply sent.');
    }

    // ── PATCH support/tickets/{id}/status ────────────────────────────
    // Update ticket status — support/admin only.

    public function updateStatus(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        if (!$this->isSupportOrAdmin($u)) {
            return $this->error('Access denied. Support team role required.', 403);
        }

        $ticket = CustomerTicket::find($id);
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $newStatus = $r->input('status');
        if (!in_array($newStatus, CustomerTicket::$validStatuses)) {
            return $this->error('Invalid status. Valid: ' . implode(', ', CustomerTicket::$validStatuses));
        }

        $oldStatus   = $ticket->status;
        $ticket->status = $newStatus;

        if ($r->filled('resolution_state')) {
            $resolutionState = $this->normalizeResolutionState((string) $r->input('resolution_state'));
            if (!$resolutionState) {
                return $this->error('Invalid resolution_state. Valid: ' . implode(', ', CustomerTicket::$validResolutionStates));
            }
            $ticket->resolution_state = $resolutionState;
        } elseif ($newStatus === 'resolved') {
            $ticket->resolution_state = 'resolved';
        } elseif ($newStatus === 'closed' && !$ticket->resolution_state) {
            $ticket->resolution_state = 'resolved';
        }

        $ticket->save();

        $this->logAudit(
            $u,
            'ticket_status_updated',
            'customer_ticket',
            $ticket->id,
            "Ticket status changed from {$oldStatus} to {$newStatus}.",
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'resolution_state' => $ticket->resolution_state,
            ],
            $r
        );

        // Log status change as system record
        $note = $r->input('note', "Status changed from {$oldStatus} to {$newStatus}.");
        $this->addRecord($ticket, $u->id, 'system', $note, 'status_change');

        return $this->success($ticket, "Ticket status updated to {$newStatus}.");
    }

    // ── POST support/tickets/{id}/assign ─────────────────────────────
    // Assign ticket to a support team member — admin only.

    public function assignTicket(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        if (!$this->isAdminOnly($u)) {
            return $this->error('Access denied. Admin role required.', 403);
        }

        $ticket = CustomerTicket::find($id);
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $agentId = $r->input('agent_id');

        if ($agentId) {
            // Verify the agent has support_team role
            $isAgent = DB::table('admin_role_users')
                ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
                ->where('admin_role_users.user_id', $agentId)
                ->whereIn('admin_roles.slug', ['support_team', 'administrator'])
                ->exists();

            if (!$isAgent) {
                return $this->error('The selected user does not have the support_team role.');
            }

            $agent = User::find($agentId);
            if (!$agent) {
                return $this->error('Agent not found.');
            }

            $ticket->assigned_to = $agentId;
            $ticket->status      = 'in_progress';
            $ticket->save();

            $this->logAudit(
                $u,
                'ticket_assigned',
                'customer_ticket',
                $ticket->id,
                "Ticket assigned to {$agent->name}.",
                [
                    'agent_id' => $agentId,
                    'agent_name' => $agent->name,
                ],
                $r
            );

            $this->addRecord($ticket, $u->id, 'system', "Ticket assigned to {$agent->name}.", 'none');

            return $this->success($ticket, "Ticket assigned to {$agent->name}.");
        }

        // Unassign
        $ticket->assigned_to = null;
        $ticket->save();

        $this->logAudit(
            $u,
            'ticket_unassigned',
            'customer_ticket',
            $ticket->id,
            'Ticket unassigned.',
            [],
            $r
        );

        return $this->success($ticket, 'Ticket unassigned.');
    }

    // ── GET support/team ──────────────────────────────────────────────
    // List all users with the support_team role (for assignment dropdown).

    public function listSupportTeam(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        if (!$this->isSupportOrAdmin($u)) {
            return $this->error('Access denied.', 403);
        }

        $team = User::join('admin_role_users', 'admin_users.id', '=', 'admin_role_users.user_id')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->whereIn('admin_roles.slug', ['support_team', 'administrator'])
            ->select('admin_users.id', 'admin_users.name', 'admin_users.email', 'admin_users.avatar')
            ->distinct()
            ->get();

        return $this->success($team, 'Support team loaded.');
    }

    private function isValidRecordActionType(string $actionType): bool
    {
        return in_array($actionType, CustomerTicketRecord::$validActionTypes, true);
    }

    private function normalizeTicketType(string $ticketType): ?string
    {
        $ticketType = strtolower(trim($ticketType));
        if ($ticketType === '') {
            return 'general';
        }
        return in_array($ticketType, CustomerTicket::$validTicketTypes, true) ? $ticketType : null;
    }

    private function normalizeResolutionState(string $state): ?string
    {
        $state = strtolower(trim($state));
        if ($state === '') {
            return null;
        }
        return in_array($state, CustomerTicket::$validResolutionStates, true) ? $state : null;
    }

    private function normalizePlatformType(string $platformType): string
    {
        $platformType = strtolower(trim($platformType));
        if ($platformType === 'luga') {
            return 'lugaflix';
        }
        if ($platformType === 'muno') {
            return 'muno_app';
        }
        if (!in_array($platformType, self::VALID_PLATFORM_TYPES, true)) {
            return 'lugaflix';
        }
        return $platformType;
    }

    private function mapPlatformTypeToAppType(string $platformType): string
    {
        if ($platformType === 'muno') {
            return 'muno_app';
        }
        if ($platformType === 'luga') {
            return 'lugaflix';
        }
        return $platformType;
    }

    private function normalizeDevicePlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::VALID_DEVICE_PLATFORMS, true)) {
            return 'android';
        }
        return $platform;
    }

    private function resolveDefaultActionType(bool $isStaff, bool $isInternal): string
    {
        if ($isStaff) {
            return $isInternal ? 'needs_support_action' : 'agent_has_contacted_customer';
        }
        return 'message_from_customer';
    }

    // ── Private helper ────────────────────────────────────────────────

    private function addRecord(
        CustomerTicket $ticket,
        ?int $senderId,
        string $senderType,
        string $message,
        string $actionType = 'none',
        ?string $actionDesc = null,
        bool $isInternal = false,
        ?string $attachmentUrl = null,
        bool $showToCustomer = true,
        bool $customerSeen = false
    ): CustomerTicketRecord {
        if ($senderType === 'user') {
            $showToCustomer = true;
            $customerSeen = true;
        }

        $record = CustomerTicketRecord::create([
            'customer_ticket_id' => $ticket->id,
            'sender_type'        => $senderType,
            'sender_id'          => $senderId,
            'message'            => $message,
            'action_type'        => $actionType,
            'action_description' => $actionDesc,
            'is_internal_note'   => $isInternal,
            'show_to_customer'   => $showToCustomer,
            'attachment_url'     => $attachmentUrl,
            'is_read_by_user'    => $senderType !== 'user',
            'customer_seen'      => $customerSeen,
            'customer_seen_at'   => $customerSeen ? now() : null,
            'is_read_by_support' => $senderType === 'support_team',
        ]);

        return $record;
    }

    private function formatRecord(CustomerTicketRecord $record, CustomerTicket $ticket): array
    {
        $movieTitles = $this->extractMovieSuggestionTitles($record, $ticket);
        $messageBody = $this->stripSuggestedMoviesSection((string) $record->message);

        return array_merge($record->toArray(), [
            'message_body' => $messageBody !== '' ? $messageBody : (string) $record->message,
            'has_movie_suggestions' => !empty($movieTitles),
            'movie_suggestions' => $this->buildMovieSuggestions($movieTitles),
        ]);
    }

    private function extractMovieSuggestionTitles(CustomerTicketRecord $record, CustomerTicket $ticket): array
    {
        $titles = $this->parseMovieTitlesFromActionDescription((string) $record->action_description);

        if (empty($titles)) {
            $titles = $this->parseMovieTitlesFromMessage((string) $record->message);
        }

        if (
            empty($titles)
            && $ticket->ticket_type === 'movie_request'
            && $record->sender_type === 'support_team'
        ) {
            $payload = is_array($ticket->movie_request_payload) ? $ticket->movie_request_payload : [];
            $adminResponse = is_array($payload['admin_response'] ?? null) ? $payload['admin_response'] : [];
            $candidateTitles = $adminResponse['movie_titles'] ?? null;
            $candidateMessage = $this->stripSuggestedMoviesSection((string) ($adminResponse['message'] ?? ''));
            $recordMessage = $this->stripSuggestedMoviesSection((string) $record->message);

            if (
                is_array($candidateTitles)
                && !empty($candidateTitles)
                && $candidateMessage !== ''
                && Str::lower(trim($candidateMessage)) === Str::lower(trim($recordMessage))
            ) {
                $titles = $candidateTitles;
            }
        }

        return $this->normalizeMovieTitles($titles);
    }

    private function parseMovieTitlesFromActionDescription(string $actionDescription): array
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

    private function parseMovieTitlesFromMessage(string $message): array
    {
        $markerPos = stripos($message, 'Suggested movies:');
        if ($markerPos === false) {
            return [];
        }

        $listSection = trim(substr($message, $markerPos + strlen('Suggested movies:')));
        if ($listSection === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $listSection) ?: [];

        return array_map(
            static fn(string $line) => trim(ltrim($line, "- \t")),
            array_filter($lines, static fn(string $line) => trim($line) !== '')
        );
    }

    private function normalizeMovieTitles(array $titles): array
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

    private function stripSuggestedMoviesSection(string $message): string
    {
        $markerPos = stripos($message, 'Suggested movies:');
        if ($markerPos === false) {
            return trim($message);
        }

        return trim(substr($message, 0, $markerPos));
    }

    private function buildMovieSuggestions(array $titles): array
    {
        if (empty($titles)) {
            return [];
        }

        $titleLookup = [];
        foreach ($titles as $title) {
            $titleLookup[Str::lower(trim($title))] = $title;
        }

        $matchedMovies = MovieModel::query()
            ->select(['id', 'title', 'thumbnail_url', 'image_url', 'year', 'vj', 'type', 'status'])
            ->whereIn(DB::raw('LOWER(TRIM(title))'), array_keys($titleLookup))
            ->get();

        $movieMap = [];
        foreach ($matchedMovies as $movie) {
            $movieMap[Str::lower(trim((string) $movie->getRawOriginal('title')))] = $movie;
        }

        $items = [];
        foreach ($titles as $title) {
            $normalizedTitle = Str::lower(trim($title));
            $matchedMovie = $movieMap[$normalizedTitle] ?? null;

            $items[] = [
                'id' => $matchedMovie ? (int) $matchedMovie->id : null,
                'title' => $matchedMovie ? (string) $matchedMovie->title : $title,
                'thumbnail_url' => $matchedMovie ? (string) ($matchedMovie->thumbnail_url ?? '') : '',
                'image_url' => $matchedMovie ? (string) ($matchedMovie->image_url ?? '') : '',
                'year' => $matchedMovie ? (string) ($matchedMovie->year ?? '') : '',
                'vj' => $matchedMovie ? (string) ($matchedMovie->vj ?? '') : '',
                'type' => $matchedMovie ? (string) ($matchedMovie->type ?? '') : '',
                'status' => $matchedMovie ? (string) ($matchedMovie->status ?? '') : '',
                'is_available' => $matchedMovie !== null,
            ];
        }

        return $items;
    }

    private function formatTicket(CustomerTicket $ticket, User $viewer): array
    {
        $customerUnseenQuery = CustomerTicketRecord::where('customer_ticket_id', $ticket->id)
            ->where('show_to_customer', true)
            ->where('customer_seen', false)
            ->whereIn('sender_type', ['support_team', 'system']);

        $customerUnseenCount = (int) $customerUnseenQuery->count();
        $latestCustomerUnseen = $customerUnseenCount > 0
            ? $customerUnseenQuery->latest('created_at')->first()
            : null;

        $latestCustomerUnseenPayload = $latestCustomerUnseen
            ? $this->formatRecord($latestCustomerUnseen, $ticket)
            : null;

        return array_merge($ticket->toArray(), [
            'ticket_type_label' => ucwords(str_replace('_', ' ', (string) $ticket->ticket_type)),
            'resolution_state_label' => ucwords(str_replace('_', ' ', (string) $ticket->resolution_state)),
            'customer_unseen_records_count' => $customerUnseenCount,
            'has_customer_unseen_records' => $customerUnseenCount > 0,
            'latest_unseen_customer_record' => $latestCustomerUnseenPayload ? [
                'id' => (int) ($latestCustomerUnseenPayload['id'] ?? 0),
                'message' => (string) ($latestCustomerUnseenPayload['message_body'] ?? $latestCustomerUnseenPayload['message'] ?? ''),
                'raw_message' => (string) ($latestCustomerUnseenPayload['message'] ?? ''),
                'created_at' => (string) ($latestCustomerUnseenPayload['created_at'] ?? ''),
                'sender_type' => (string) ($latestCustomerUnseenPayload['sender_type'] ?? ''),
                'has_movie_suggestions' => (bool) ($latestCustomerUnseenPayload['has_movie_suggestions'] ?? false),
                'movie_suggestions' => $latestCustomerUnseenPayload['movie_suggestions'] ?? [],
            ] : null,
            'user'           => $ticket->user ? [
                'id'     => $ticket->user->id,
                'name'   => $ticket->user->name,
                'email'  => $ticket->user->email,
                'avatar' => $ticket->user->avatar,
                'account_origin' => $ticket->user->account_origin,
                'account_state'  => $ticket->user->account_state,
            ] : null,
            'assigned_agent' => $ticket->assignedAgent ? [
                'id'   => $ticket->assignedAgent->id,
                'name' => $ticket->assignedAgent->name,
            ] : null,
        ]);
    }
}
