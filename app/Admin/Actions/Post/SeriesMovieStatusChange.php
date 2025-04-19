<?php

namespace App\Admin\Actions\Post;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request; 

class SeriesMovieStatusChange extends BatchAction
{
    public $name = 'Change Status';
    public $icon = 'fa fa-check';


    public function handle(Collection $collection, Request $r)
    {
        $i = 0;
        foreach ($collection as $model) {
            $model->is_active = $r->get('is_active');
            $model->save();
            $i++;
        }
        return $this->response()->success("Updated $i status to " . $r->get('is_active') . " successfully.")->refresh();
    }

    public function form()
    {
        $this->select('is_active', __('Status'))
            ->options(['Yes' => 'Active', 'No' => 'Not active']);
    }
}
