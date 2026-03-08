<?php

namespace App\Admin\Actions\Post;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BatchDeactivateStations extends BatchAction
{
    public $name = 'Deactivate Selected';
    public $icon = 'fa fa-times-circle';

    public function handle(Collection $collection, Request $request)
    {
        $count = $collection->count();
        foreach ($collection as $station) {
            $station->status = 'Inactive';
            $station->save();
        }

        return $this->response()->success("{$count} station(s) deactivated successfully.")->refresh();
    }

    public function dialog()
    {
        $this->confirm('Are you sure you want to deactivate all selected stations?');
    }
}
