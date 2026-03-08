<?php

namespace App\Admin\Actions\Post;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchActivateStations extends BatchAction
{
    public $name = 'Activate Selected';
    public $icon = 'fa fa-check-circle';

    public function handle(Collection $collection, Request $request)
    {
        $count = $collection->count();
        foreach ($collection as $station) {
            $station->status = 'Active';
            $station->save();
        }

        return $this->response()->success("{$count} station(s) activated successfully.")->refresh();
    }

    public function dialog()
    {
        $this->confirm('Are you sure you want to activate all selected stations?');
    }
}
