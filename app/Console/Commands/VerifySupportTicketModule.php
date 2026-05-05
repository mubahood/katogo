<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\SupportTicketController;
use App\Models\SupportAuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifySupportTicketModule extends Command
{
    protected $signature = 'support:verify-module';

    protected $description = 'Runs dummy support-ticket workflow checks with transaction rollback';

    public function handle(): int
    {
        $requiredColumns = [
            'customer_tickets' => [
                'ticket_type',
                'resolution_state',
                'platform_type',
                'rating_of_satisfaction',
                'agent_has_contacted_customer',
                'customer_has_responded',
            ],
            'support_audit_logs' => [
                'event_type',
                'entity_type',
                'description',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $this->error("Missing required table: {$table}");
                return self::FAILURE;
            }
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $this->error("Missing required column: {$table}.{$column}");
                    return self::FAILURE;
                }
            }
        }

        DB::beginTransaction();

        try {
            $supportRoleId = (int) DB::table('admin_roles')->where('slug', 'support_team')->value('id');
            if ($supportRoleId < 1) {
                DB::table('admin_roles')->insert([
                    'name' => 'Support Team',
                    'slug' => 'support_team',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $supportRoleId = (int) DB::table('admin_roles')->where('slug', 'support_team')->value('id');
            }

            $user = User::create([
                'name' => 'Dummy Support User',
                'email' => 'dummy_support_user_' . uniqid() . '@example.test',
                'password' => bcrypt('password123'),
            ]);

            $support = User::create([
                'name' => 'Dummy Support Agent',
                'email' => 'dummy_support_agent_' . uniqid() . '@example.test',
                'password' => bcrypt('password123'),
            ]);

            DB::table('admin_role_users')->insert([
                'user_id' => $support->id,
                'role_id' => $supportRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $controller = app(SupportTicketController::class);

            $createRequest = Request::create('/api/support/tickets', 'POST', [
                'logged_in_user_id' => $user->id,
                'subject' => 'Dummy ticket for verification',
                'message' => 'Initial dummy message from user',
                'ticket_type' => 'payment_fail',
                'platform_type' => 'luga',
                'platform' => 'android',
            ]);

            $createResponse = $controller->createTicket($createRequest);
            $createBody = json_decode($createResponse->getContent(), true);

            if (($createBody['code'] ?? 0) !== 1 || empty($createBody['data']['id'])) {
                throw new \RuntimeException('Ticket creation check failed.');
            }

            $ticketId = (int) $createBody['data']['id'];

            $replyRequest = Request::create('/api/support/tickets/' . $ticketId . '/records', 'POST', [
                'logged_in_user_id' => $support->id,
                'message' => 'Dummy support follow-up reply',
                'action_type' => 'agent_has_contacted_customer',
                'resolution_state' => 'unresolved',
            ]);
            $replyResponse = $controller->addReply($replyRequest, $ticketId);
            $replyBody = json_decode($replyResponse->getContent(), true);
            if (($replyBody['code'] ?? 0) !== 1) {
                throw new \RuntimeException('Support reply check failed.');
            }

            $statusRequest = Request::create('/api/support/tickets/' . $ticketId . '/status', 'PATCH', [
                'logged_in_user_id' => $support->id,
                'status' => 'resolved',
                'resolution_state' => 'resolved',
                'note' => 'Dummy resolved state check',
            ]);
            $statusResponse = $controller->updateStatus($statusRequest, $ticketId);
            $statusBody = json_decode($statusResponse->getContent(), true);
            if (($statusBody['code'] ?? 0) !== 1) {
                throw new \RuntimeException('Status update check failed.');
            }

            $ticket = DB::table('customer_tickets')->where('id', $ticketId)->first();
            if (!$ticket) {
                throw new \RuntimeException('Ticket missing after workflow.');
            }
            if ($ticket->ticket_type !== 'payment_fail') {
                throw new \RuntimeException('ticket_type did not persist as expected.');
            }
            if ($ticket->platform_type !== 'lugaflix') {
                throw new \RuntimeException('platform_type normalization failed (expected lugaflix).');
            }
            if ($ticket->resolution_state !== 'resolved') {
                throw new \RuntimeException('resolution_state did not update to resolved.');
            }
            if ((int) $ticket->agent_has_contacted_customer !== 1) {
                throw new \RuntimeException('agent_has_contacted_customer flag was not set.');
            }

            $auditCount = SupportAuditLog::where('entity_type', 'customer_ticket')
                ->where('entity_id', $ticketId)
                ->count();
            if ($auditCount < 3) {
                throw new \RuntimeException('Expected at least 3 support audit log records.');
            }

            // Verify auto-welcome ticket creation when first valid phone is set
            $autoUser = User::create([
                'name' => 'Dummy Auto User',
                'email' => 'dummy_auto_user_' . uniqid() . '@example.test',
                'password' => bcrypt('password123'),
            ]);
            $autoUser->account_origin = 'auto_device';
            $autoUser->account_state = 'auto_created';
            $autoUser->phone_number = $autoUser->email; // placeholder used by auto-account flow
            $autoUser->save();

            $autoUser->phone_number = '256701234567';
            $autoUser->save();

            $welcomeTicket = DB::table('customer_tickets')
                ->where('user_id', $autoUser->id)
                ->where('ticket_type', 'account_opening')
                ->where('subject', 'Welcome message')
                ->first();

            if (!$welcomeTicket) {
                throw new \RuntimeException('Auto welcome ticket was not created after first valid phone set.');
            }

            if ((int) $welcomeTicket->agent_has_contacted_customer !== 0) {
                throw new \RuntimeException('Welcome ticket should start with agent_has_contacted_customer=false.');
            }

            $welcomeRecord = DB::table('customer_ticket_records')
                ->where('customer_ticket_id', $welcomeTicket->id)
                ->where('action_type', 'needs_support_action')
                ->first();

            if (!$welcomeRecord) {
                throw new \RuntimeException('Welcome ticket support-action record missing.');
            }

            $welcomeAuditCount = SupportAuditLog::where('event_type', 'welcome_ticket_auto_created')
                ->where('entity_type', 'customer_ticket')
                ->where('entity_id', $welcomeTicket->id)
                ->count();

            if ($welcomeAuditCount < 1) {
                throw new \RuntimeException('Expected welcome_ticket_auto_created audit log entry.');
            }

            $this->info('Support module dummy verification passed.');
            $this->line('Ticket ID checked: ' . $ticketId);
            $this->line('Audit records found: ' . $auditCount);
            $this->line('Welcome ticket ID checked: ' . $welcomeTicket->id);
            $this->line('All dummy data rolled back at end of command.');

            DB::rollBack();
            return self::SUCCESS;
        } catch (\Throwable $th) {
            DB::rollBack();
            $this->error('Support module verification failed: ' . $th->getMessage());
            return self::FAILURE;
        }
    }
}
