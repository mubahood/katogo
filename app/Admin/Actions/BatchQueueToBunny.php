<?php

namespace App\Admin\Actions;

use App\Jobs\TransferMovieToBunny;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchQueueToBunny extends BatchAction
{
    public $name = 'Send to Bunny';

    public function handle(Collection $collection, Request $request)
    {
        $queued  = 0;
        $skipped = 0;

        foreach ($collection as $t) {
            // Only rows whose Hetzner leg is complete and that aren't already
            // on Bunny or actively uploading.
            if ($t->status !== 'done' || in_array($t->bunny_status, ['done', 'uploading', 'pending'], true)) {
                $skipped++;
                continue;
            }
            $t->bunny_status = 'pending';
            $t->bunny_error  = null;
            $t->save();
            TransferMovieToBunny::dispatch($t->id)->onQueue('default');
            $queued++;
        }

        $msg = "Queued {$queued} movie(s) to Bunny.";
        if ($skipped) $msg .= " Skipped {$skipped} (already on Bunny / in progress / Hetzner leg incomplete).";

        return $this->response()->success($msg)->refresh();
    }

    public function dialog()
    {
        $this->question('Send to Bunny?', 'Selected movies will be queued for upload to Bunny Storage. Uploads run in the background via the queue worker.');
    }
}
