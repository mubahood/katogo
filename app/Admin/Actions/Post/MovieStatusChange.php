<?php

namespace App\Admin\Actions\Post;

use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class MovieStatusChange extends BatchAction
{
    public $name = 'Change Movie Status';
    public $icon = 'fa fa-check';


    public function handle(Collection $collection, Request $r)
    {
        $i = 0;
        foreach ($collection as $model) {
            $model->status = $r->get('status');
            $model->save();
            $i++;
        }
        return $this->response()->success("Updated $i status to " . $r->get('status') . " successfully.")->refresh();
    }

    public function form()
    {
        $this->select('status', __('Status'))
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive']);
    }
}
