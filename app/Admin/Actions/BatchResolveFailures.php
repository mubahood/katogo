<?php

namespace App\Admin\Actions;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchResolveFailures extends BatchAction
{
    public $name = 'Resolve Selected';

    public function handle(Collection $collection, Request $request)
    {
        foreach ($collection as $model) {
            $model->markAsResolved('Batch resolved by admin');
        }

        return $this->response()->success('Selected failures have been marked as resolved')->refresh();
    }

    public function dialog()
    {
        $this->confirm('Are you sure you want to mark the selected failures as resolved?');
    }
}
