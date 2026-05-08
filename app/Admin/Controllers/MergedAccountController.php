<?php

namespace App\Admin\Controllers;

use App\Models\MergedAccount;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergedAccountController extends AdminController
{
    protected $title = 'Merged Accounts';

    protected function grid()
    {
        $grid = new Grid(new MergedAccount());

        $grid->model()->orderByDesc('id');

        $grid->column('id', 'ID')->sortable();
        $grid->column('source_user_id', 'Old User')->display(function ($value) {
            if (!$value) {
                return '—';
            }

            $url = admin_url('users/' . $value . '/edit');
            return "<a href=\"{$url}\">#{$value}</a>";
        });
        $grid->column('target_user_id', 'New User')->display(function ($value) {
            if (!$value) {
                return '—';
            }

            $url = admin_url('users/' . $value . '/edit');
            return "<a href=\"{$url}\">#{$value}</a>";
        });

        $grid->column('match_type', 'Match Type')->label([
            'phone' => 'primary',
            'email' => 'info',
            'phone_or_email' => 'success',
        ], 'default');

        $grid->column('merge_reason', 'Reason')->limit(40);
        $grid->column('status', 'Status')->label([
            'completed' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
        ], 'default');
        $grid->column('merged_at', 'Merged At')->sortable();
        $grid->column('last_synced_at', 'Last Sync')->sortable();

        $grid->quickSearch('id', 'source_user_id', 'target_user_id', 'source_email', 'target_email', 'source_phone_number', 'target_phone_number');

        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->equal('status', 'Status')->select([
                'completed' => 'Completed',
                'failed' => 'Failed',
                'pending' => 'Pending',
            ]);
            $filter->equal('match_type', 'Match Type')->select([
                'phone_or_email' => 'Phone or Email',
                'phone' => 'Phone',
                'email' => 'Email',
            ]);
            $filter->equal('source_user_id', 'Old User ID');
            $filter->equal('target_user_id', 'New User ID');
            $filter->between('merged_at', 'Merged At')->datetime();
        });

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $id = $actions->getKey();
            $syncUrl = admin_url('merged-accounts/' . $id . '/sync-access');
            $actions->append("<a href=\"{$syncUrl}\" class=\"btn btn-xs btn-primary\" style=\"margin-left:5px\"><i class=\"fa fa-refresh\"></i> Sync Access</a>");
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(MergedAccount::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('source_user_id', 'Old User ID');
        $show->field('target_user_id', 'New User ID');
        $show->field('source_email', 'Old Email');
        $show->field('source_phone_number', 'Old Phone');
        $show->field('target_email', 'New Email');
        $show->field('target_phone_number', 'New Phone');
        $show->field('match_type', 'Match Type');
        $show->field('merge_reason', 'Reason');
        $show->field('status', 'Status');
        $show->field('sync_mode', 'Sync Mode');
        $show->field('source_permissions', 'Old Permissions')->json();
        $show->field('target_permissions', 'New Permissions')->json();
        $show->field('source_snapshot', 'Old Snapshot')->json();
        $show->field('target_snapshot', 'New Snapshot')->json();
        $show->field('request_ip', 'Request IP');
        $show->field('request_user_agent', 'User Agent');
        $show->field('notes', 'Notes');
        $show->field('merged_at', 'Merged At');
        $show->field('last_synced_at', 'Last Synced At');
        $show->field('created_at', 'Created At');
        $show->field('updated_at', 'Updated At');

        return $show;
    }

    protected function form()
    {
        $form = new Form(new MergedAccount());

        $form->number('source_user_id', 'Old User ID')->required()->min(1);
        $form->number('target_user_id', 'New User ID')->required()->min(1);

        $form->text('source_email', 'Old Email')->readonly();
        $form->text('source_phone_number', 'Old Phone')->readonly();
        $form->text('target_email', 'New Email')->readonly();
        $form->text('target_phone_number', 'New Phone')->readonly();

        $form->select('match_type', 'Match Type')->options([
            'phone_or_email' => 'Phone or Email',
            'phone' => 'Phone',
            'email' => 'Email',
        ])->default('phone_or_email');

        $form->text('merge_reason', 'Reason')->default('manual_admin_merge');
        $form->select('status', 'Status')->options([
            'completed' => 'Completed',
            'pending' => 'Pending',
            'failed' => 'Failed',
        ])->default('completed');

        $form->text('sync_mode', 'Sync Mode')->default('bidirectional_permissions');
        $form->textarea('notes', 'Notes');

        $form->display('source_permissions', 'Old Permissions JSON')->with(function ($value) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
        $form->display('target_permissions', 'New Permissions JSON')->with(function ($value) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
        $form->display('source_snapshot', 'Old Snapshot JSON')->with(function ($value) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
        $form->display('target_snapshot', 'New Snapshot JSON')->with(function ($value) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });

        $form->datetime('merged_at', 'Merged At')->default(date('Y-m-d H:i:s'));
        $form->datetime('last_synced_at', 'Last Synced At')->readonly();

        $form->saving(function (Form $form) {
            if ((int) $form->source_user_id === (int) $form->target_user_id) {
                throw new \RuntimeException('Old and new account cannot be the same user.');
            }

            $source = User::find((int) $form->source_user_id);
            $target = User::find((int) $form->target_user_id);

            if (!$source || !$target) {
                throw new \RuntimeException('Both old and new users must exist.');
            }

            $form->source_email = (string) ($source->email ?? '');
            $form->source_phone_number = (string) ($source->phone_number ?? '');
            $form->target_email = (string) ($target->email ?? '');
            $form->target_phone_number = (string) ($target->phone_number ?? '');
            $form->source_snapshot = $this->snapshotUser($source);
            $form->target_snapshot = $this->snapshotUser($target);
            $form->source_permissions = $this->permissionSnapshot((int) $source->id);
            $form->target_permissions = $this->permissionSnapshot((int) $target->id);

            if (empty($form->merged_at)) {
                $form->merged_at = now();
            }

            if (admin_user()) {
                $form->created_by = (int) admin_user()->id;
            }
        });

        $form->saved(function (Form $form) {
            Log::info('MERGED_ACCOUNT_ADMIN_SAVED', [
                'merged_account_id' => $form->model()->id,
                'source_user_id' => $form->model()->source_user_id,
                'target_user_id' => $form->model()->target_user_id,
                'admin_user_id' => admin_user()->id ?? null,
            ]);
        });

        return $form;
    }

    public function syncAccess($id)
    {
        $record = MergedAccount::findOrFail($id);
        $source = User::find($record->source_user_id);
        $target = User::find($record->target_user_id);

        if (!$source || !$target) {
            admin_toastr('Cannot sync access: source or target user no longer exists.', 'error');
            return redirect()->back();
        }

        DB::transaction(function () use ($record, $source, $target) {
            $this->syncBidirectionalPermissions((int) $source->id, (int) $target->id);

            $record->source_permissions = $this->permissionSnapshot((int) $source->id);
            $record->target_permissions = $this->permissionSnapshot((int) $target->id);
            $record->source_snapshot = $this->snapshotUser($source->fresh() ?: $source);
            $record->target_snapshot = $this->snapshotUser($target->fresh() ?: $target);
            $record->last_synced_at = now();
            $record->status = 'completed';
            $record->save();
        });

        Log::info('MERGED_ACCOUNT_ACCESS_SYNCED', [
            'merged_account_id' => $record->id,
            'source_user_id' => $record->source_user_id,
            'target_user_id' => $record->target_user_id,
            'admin_user_id' => admin_user()->id ?? null,
        ]);

        admin_toastr('Merged account access synced successfully.', 'success');
        return redirect()->back();
    }

    private function syncBidirectionalPermissions(int $sourceUserId, int $targetUserId): void
    {
        $roleIds = collect();
        if (DB::getSchemaBuilder()->hasTable('admin_role_users')) {
            $roleIds = DB::table('admin_role_users')
                ->whereIn('user_id', [$sourceUserId, $targetUserId])
                ->pluck('role_id')
                ->unique()
                ->values();

            foreach ([$sourceUserId, $targetUserId] as $userId) {
                foreach ($roleIds as $roleId) {
                    $exists = DB::table('admin_role_users')
                        ->where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->exists();

                    if (!$exists) {
                        DB::table('admin_role_users')->insert([
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        if (DB::getSchemaBuilder()->hasTable('admin_user_permissions')) {
            $permissionIds = DB::table('admin_user_permissions')
                ->whereIn('user_id', [$sourceUserId, $targetUserId])
                ->pluck('permission_id')
                ->unique()
                ->values();

            foreach ([$sourceUserId, $targetUserId] as $userId) {
                foreach ($permissionIds as $permissionId) {
                    $exists = DB::table('admin_user_permissions')
                        ->where('user_id', $userId)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if (!$exists) {
                        DB::table('admin_user_permissions')->insert([
                            'user_id' => $userId,
                            'permission_id' => $permissionId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    private function permissionSnapshot(int $userId): array
    {
        $snapshot = [
            'role_ids' => [],
            'permission_ids' => [],
        ];

        if (DB::getSchemaBuilder()->hasTable('admin_role_users')) {
            $snapshot['role_ids'] = DB::table('admin_role_users')
                ->where('user_id', $userId)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();
        }

        if (DB::getSchemaBuilder()->hasTable('admin_user_permissions')) {
            $snapshot['permission_ids'] = DB::table('admin_user_permissions')
                ->where('user_id', $userId)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();
        }

        return $snapshot;
    }

    private function snapshotUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone_number' => (string) ($user->phone_number ?? ''),
            'account_state' => (string) ($user->account_state ?? ''),
            'app_type' => (string) ($user->app_type ?? ''),
            'platform' => (string) ($user->platform ?? ''),
            'created_at' => (string) ($user->created_at ?? ''),
            'updated_at' => (string) ($user->updated_at ?? ''),
        ];
    }
}
