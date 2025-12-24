<?php

namespace App\Admin\Actions;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchIgnoreFailures extends BatchAction
{
    public $name = 'Ignore Selected';

    public function handle(Collection $collection, Request $request)
    {
        foreach ($collection as $model) {
            $model->status = 'ignored';
            $model->admin_notes = 'Batch ignored by admin';
            $model->save();
        }

        return $this->response()->success('Selected failures have been marked as ignored')->refresh();
    }

    public function dialog()
    {
        $this->confirm('Are you sure you want to ignore the selected failures?');
    }
}
