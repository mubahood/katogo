<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerTicket;
use App\Models\CustomerTicketRecord;
use App\Models\MovieRequest;
use App\Models\SupportAuditLog;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class MovieRequestController extends BaseController
{
    use ApiResponser;

    private const VALID_PLATFORM_TYPES = ['lugaflix', 'luga', 'ugflix', 'muno', 'muno_app'];

    private function isSupportOrAdmin(User $user): bool
    {
        return DB::table('admin_role_users')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->where('admin_role_users.user_id', $user->id)
            ->whereIn('admin_roles.slug', ['support_team', 'administrator'])
            ->exists();
    }

    private function resolveActorRole(User $user): string
    {
        $isAdmin = DB::table('admin_role_users')
            ->join('admin_roles', 'admin_roles.id', '=', 'admin_role_users.role_id')
            ->where('admin_role_users.user_id', $user->id)
            ->where('admin_roles.slug', 'administrator')
            ->exists();

        if ($isAdmin) {
            return 'administrator';
        }

        return $this->isSupportOrAdmin($user) ? 'support_team' : 'normal_user';
    }

    private function logAudit(?User $actor, string $eventType, ?MovieRequest $movieRequest, string $description, array $meta = []): void
    {
        try {
            SupportAuditLog::create([
                'actor_id' => $actor?->id,
                'actor_role' => $actor ? $this->resolveActorRole($actor) : 'system',
                'event_type' => $eventType,
                'entity_type' => 'movie_request',
                'entity_id' => $movieRequest?->id,
                'description' => $description,
                'meta' => $meta,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $th) {
            // Never block customer flow on audit failures.
        }
    }

    public function create(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);
        if (!$u) {
            return $this->error('User not found.', 404);
        }

        $requestedMovies = $this->normalizeRequestedMovies($r->input('requested_movies', []), (string) $r->input('query', ''));
        if (count($requestedMovies) < 1) {
            return $this->error('Provide at least one movie title to request.');
        }

        $source = strtolower(trim((string) $r->input('source', 'search')));
        if (!in_array($source, MovieRequest::$validSources, true)) {
            $source = 'search';
        }

        $platformType = $this->normalizePlatformType((string) ($r->input('platform_type') ?: $u->app_type ?: 'lugaflix'));
        $appType = strtolower(trim((string) ($r->input('app_type') ?: $u->app_type ?: 'lugaflix')));
        $query = trim((string) $r->input('query', ''));
        $userMessage = trim((string) $r->input('message', ''));

        $movieRequest = null;
        $ticket = null;

        DB::beginTransaction();
        try {
            $ticket = CustomerTicket::where('user_id', $u->id)
                ->where('ticket_type', 'movie_request')
                ->whereNotIn('status', ['closed'])
                ->latest('id')
                ->first();

            if (!$ticket) {
                $ticket = CustomerTicket::create([
                    'user_id' => $u->id,
                    'status' => 'open',
                    'ticket_type' => 'movie_request',
                    'resolution_state' => 'unresolved',
                    'subject' => 'Movie request: ' . $requestedMovies[0],
                    'account_origin' => $u->account_origin ?? 'manual',
                    'app_type' => $appType,
                    'platform_type' => $platformType,
                    'platform' => $u->platform ?? 'android',
                    'has_unread_support' => true,
                    'is_movie_request' => true,
                ]);
            }

            $payload = [
                'request_source' => $source,
                'searched_query' => $query,
                'requested_movies' => $requestedMovies,
                'message' => $userMessage,
                'requested_at' => now()->toDateTimeString(),
            ];

            $ticket->is_movie_request = true;
            $ticket->movie_request_payload = $payload;
            $ticket->last_reply_at = now();
            $ticket->reply_count = ((int) $ticket->reply_count) + 1;
            $ticket->customer_has_responded = true;
            $ticket->has_unread_support = true;
            $ticket->has_unread_user = false;
            if (in_array($ticket->status, ['pending', 'resolved'], true)) {
                $ticket->status = 'in_progress';
            }
            $ticket->save();

            $recordMessage = $this->buildTicketRecordMessage($query, $requestedMovies, $userMessage);

            CustomerTicketRecord::create([
                'customer_ticket_id' => $ticket->id,
                'sender_type' => 'user',
                'sender_id' => $u->id,
                'message' => $recordMessage,
                'action_type' => 'message_from_customer',
                'action_description' => 'Customer submitted movie request from app search.',
                'is_internal_note' => false,
                'show_to_customer' => true,
                'is_read_by_user' => true,
                'customer_seen' => true,
                'customer_seen_at' => now(),
                'is_read_by_support' => false,
            ]);

            $movieRequest = MovieRequest::create([
                'user_id' => $u->id,
                'customer_ticket_id' => $ticket->id,
                'status' => 'submitted',
                'request_source' => $source,
                'platform_type' => $platformType,
                'app_type' => $appType,
                'searched_query' => $query !== '' ? $query : null,
                'requested_movies' => $requestedMovies,
                'user_message' => $userMessage !== '' ? $userMessage : null,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error('Failed to submit movie request: ' . $th->getMessage());
        }

        $this->logAudit(
            $u,
            'movie_request_created',
            $movieRequest,
            'Movie request submitted and linked to support ticket.',
            [
                'ticket_id' => $ticket?->id,
                'request_source' => $source,
                'requested_movies_count' => count($requestedMovies),
                'platform_type' => $platformType,
            ]
        );

        return $this->success([
            'movie_request' => $movieRequest,
            'ticket' => $ticket,
        ], 'Movie request submitted successfully. Support team will follow up shortly.');
    }

    public function index(Request $r)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);
        if (!$u) {
            return $this->error('User not found.', 404);
        }

        $query = MovieRequest::query()
            ->with(['user:id,name,email,phone_number,account_origin,account_state', 'ticket:id,status,ticket_type,resolution_state,assigned_to,agent_has_contacted_customer,has_unread_user,reply_count,last_reply_at', 'handledBy:id,name,email'])
            ->orderByDesc('created_at');

        if (!$this->isSupportOrAdmin($u)) {
            $query->where('user_id', $u->id);
        }

        if ($r->filled('status')) {
            $query->where('status', $r->input('status'));
        }

        if ($r->filled('platform_type')) {
            $query->where('platform_type', $this->normalizePlatformType((string) $r->input('platform_type')));
        }

        if ($r->filled('search')) {
            $search = '%' . trim((string) $r->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('searched_query', 'like', $search)
                    ->orWhere('user_message', 'like', $search)
                    ->orWhereRaw('JSON_SEARCH(requested_movies, "one", ?) IS NOT NULL', [str_replace('%', '', $search)]);
            });
        }

        $perPage = min((int) $r->input('per_page', 20), 100);
        $rows = $query->paginate($perPage);

        $items = collect($rows->items());
        $ticketIds = $items->pluck('customer_ticket_id')->filter()->unique()->values()->all();

        $latestSupportRecords = [];
        if (!empty($ticketIds)) {
            $records = CustomerTicketRecord::query()
                ->select(['customer_ticket_id', 'message', 'created_at'])
                ->whereIn('customer_ticket_id', $ticketIds)
                ->where('sender_type', 'support_team')
                ->where('is_internal_note', false)
                ->orderByDesc('id')
                ->get();

            foreach ($records as $record) {
                $ticketId = (int) $record->customer_ticket_id;
                if (!isset($latestSupportRecords[$ticketId])) {
                    $latestSupportRecords[$ticketId] = $record;
                }
            }
        }

        $mappedItems = $items->map(function (MovieRequest $row) use ($latestSupportRecords) {
            $payload = $row->toArray();

            $ticketId = (int) ($row->customer_ticket_id ?? 0);
            $latestSupport = $latestSupportRecords[$ticketId] ?? null;

            $directReply = trim((string) ($row->support_reply ?? ''));
            $hasSupportReply = $directReply !== '' || $latestSupport !== null;

            $preview = $directReply;
            if ($preview === '' && $latestSupport) {
                $preview = trim((string) $latestSupport->message);
            }
            if (mb_strlen($preview) > 220) {
                $preview = mb_substr($preview, 0, 220) . '...';
            }

            $payload['has_support_reply'] = $hasSupportReply;
            $payload['support_reply_preview'] = $preview;
            $payload['support_reply_at_effective'] = $row->support_reply_at
                ? (string) $row->support_reply_at
                : ($latestSupport?->created_at ? (string) $latestSupport->created_at : null);

            return $payload;
        })->values()->all();

        return $this->success([
            'items' => $mappedItems,
            'total' => $rows->total(),
            'per_page' => $rows->perPage(),
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
        ], 'Movie requests loaded.');
    }

    public function show(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);
        if (!$u) {
            return $this->error('User not found.', 404);
        }

        $row = MovieRequest::with([
            'user:id,name,email,phone_number,account_origin,account_state',
            'ticket:id,status,ticket_type,resolution_state,assigned_to,last_reply_at,reply_count',
            'handledBy:id,name,email',
        ])->find($id);

        if (!$row) {
            return $this->error('Movie request not found.', 404);
        }

        if (!$this->isSupportOrAdmin($u) && $row->user_id !== $u->id) {
            return $this->error('Access denied.', 403);
        }

        return $this->success($row, 'Movie request loaded.');
    }

    public function updateStatus(Request $r, int $id)
    {
        $u = Utils::get_user($r);
        if (!$u || $u->id < 1) {
            return $this->error('Not authenticated.', 401);
        }

        $u = User::find($u->id);
        if (!$u) {
            return $this->error('User not found.', 404);
        }

        if (!$this->isSupportOrAdmin($u)) {
            return $this->error('Access denied. Support team role required.', 403);
        }

        $row = MovieRequest::with('ticket')->find($id);
        if (!$row) {
            return $this->error('Movie request not found.', 404);
        }

        $newStatus = strtolower(trim((string) $r->input('status', '')));
        if (!in_array($newStatus, MovieRequest::$validStatuses, true)) {
            return $this->error('Invalid status. Valid: ' . implode(', ', MovieRequest::$validStatuses));
        }

        $supportReply = trim((string) $r->input('support_reply', ''));

        DB::beginTransaction();
        try {
            $oldStatus = $row->status;
            $row->status = $newStatus;
            $row->handled_by = $u->id;
            if ($supportReply !== '') {
                $row->support_reply = $supportReply;
                $row->support_reply_at = now();
            }
            $row->save();

            if ($row->ticket) {
                $this->syncTicketFromMovieRequest($row->ticket, $newStatus, $supportReply, $u);
                $row->ticket->save();
            }

            DB::commit();

            $this->logAudit(
                $u,
                'movie_request_status_updated',
                $row,
                "Movie request status changed from {$oldStatus} to {$newStatus}.",
                [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'ticket_id' => $row->customer_ticket_id,
                ]
            );

            return $this->success($row->fresh(['ticket', 'user', 'handledBy']), 'Movie request updated successfully.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error('Failed to update movie request: ' . $th->getMessage());
        }
    }

    private function syncTicketFromMovieRequest(CustomerTicket $ticket, string $movieRequestStatus, string $supportReply, User $supportUser): void
    {
        $ticket->is_movie_request = true;

        if (in_array($movieRequestStatus, ['submitted', 'reviewing', 'in_progress'], true)) {
            $ticket->status = 'in_progress';
            $ticket->resolution_state = 'unresolved';
        } elseif ($movieRequestStatus === 'fulfilled') {
            $ticket->status = 'resolved';
            $ticket->resolution_state = 'resolved';
        } elseif (in_array($movieRequestStatus, ['rejected', 'cancelled'], true)) {
            $ticket->status = 'closed';
            $ticket->resolution_state = 'cancelled';
        }

        $ticket->agent_has_contacted_customer = true;
        $ticket->has_unread_user = $supportReply !== '';
        $ticket->has_unread_support = false;
        $ticket->last_reply_at = now();
        $ticket->reply_count = ((int) $ticket->reply_count) + 1;

        $statusMessage = 'Movie request status updated to: ' . ucwords(str_replace('_', ' ', $movieRequestStatus));
        $message = $supportReply !== '' ? $supportReply : $statusMessage;

        CustomerTicketRecord::create([
            'customer_ticket_id' => $ticket->id,
            'sender_type' => 'support_team',
            'sender_id' => $supportUser->id,
            'message' => $message,
            'action_type' => $supportReply !== '' ? 'needs_user_action' : 'status_change',
            'action_description' => $statusMessage,
            'is_internal_note' => false,
            'is_read_by_user' => false,
            'is_read_by_support' => true,
        ]);
    }

    private function normalizeRequestedMovies(mixed $input, string $query): array
    {
        $titles = [];

        if (is_array($input)) {
            $titles = $input;
        } elseif (is_string($input) && trim($input) !== '') {
            $titles = preg_split('/[,\n\r]+/', $input) ?: [];
        }

        $clean = [];
        foreach ($titles as $title) {
            $t = trim((string) $title);
            if ($t === '') {
                continue;
            }
            if (mb_strlen($t) > 120) {
                $t = mb_substr($t, 0, 120);
            }
            $clean[] = $t;
        }

        $clean = array_values(array_unique($clean));

        if (count($clean) < 1 && trim($query) !== '') {
            $clean[] = trim($query);
        }

        if (count($clean) > 20) {
            $clean = array_slice($clean, 0, 20);
        }

        return $clean;
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

    private function buildTicketRecordMessage(string $query, array $requestedMovies, string $message): string
    {
        $lines = ['Movie request submitted from app search.'];

        if ($query !== '') {
            $lines[] = 'Search query: ' . $query;
        }

        $lines[] = 'Requested movies:';
        foreach ($requestedMovies as $title) {
            $lines[] = '- ' . $title;
        }

        if ($message !== '') {
            $lines[] = 'Customer message: ' . $message;
        }

        return implode("\n", $lines);
    }
}
