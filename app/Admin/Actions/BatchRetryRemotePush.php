<?php

namespace App\Admin\Actions;

use App\Jobs\PushUrlChangeToOrigin;
use App\Models\MovieVideoURLChange;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchRetryRemotePush extends BatchAction
{
    public $name = 'Retry Remote Push';

    public function handle(Collection $collection, Request $request)
    {
        $queued = 0;
        foreach ($collection as $change) {
            if (in_array($change->remote_status, ['pending', 'failed'])) {
                $change->update([
                    'remote_status' => MovieVideoURLChange::REMOTE_PENDING,
                    'attempts'      => 0,
                ]);
                dispatch(new PushUrlChangeToOrigin($change->id))->onQueue('url-sync');
                $queued++;
            }
        }

        return $this->response()->success("Queued {$queued} remote push(es).")->refresh();
    }

    public function dialog()
    {
        $this->question('Retry remote push?', 'This will re-queue the selected URL changes to be pushed to the origin server.');
    }
}
