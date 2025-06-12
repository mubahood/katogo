<?php

namespace App\Admin\Controllers;

use App\Models\MovieView;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieViewController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movie Views';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        // $lastView = MovieView::orderBy('updated_at', 'desc')->first();
        // dd($lastView);
        $grid = new Grid(new MovieView());
        $grid->disableBatchActions();
        $grid->model()->orderBy('updated_at', 'desc');
        $grid->column('id', __('Id'))->width(40);
        $grid->column('created_at', __('Date'))->sortable()
            ->display(function ($created_at) {
                //disaplay date and time
                return date('d-m-Y H:i:s', strtotime($created_at));
            })->sortable();
        $grid->column('updated_at', __('Updated'))->display(function ($created_at) {
            //disaplay date and time
            return date('d-m-Y H:i:s', strtotime($created_at));
        })->sortable();
        $grid->column('progress', __('Progress'))
            ->display(function ($progress) {
                //convert from seconds to minutes
                if ($progress == null || $progress == 0) {
                    return '0:00';
                }

                $pecentage = ($progress / $this->max_progress) * 100;
                if ($pecentage > 100) {
                    $pecentage = 100;
                }
                $progress = Utils::secondsToMinutes($progress);
                return "<span class='badge bg-success' style='font-size: 14px; padding: 5px;'>" . $progress . " (" . round($pecentage, 2) . "%)</span>";
            })->sortable();
        //max_progress
        $grid->column('max_progress', __('Max progress'))
            ->display(function ($max_progress) {
                //convert from seconds to minutes
                return Utils::secondsToMinutes($max_progress);
            })->sortable();


        $grid->column('movie_model_id', __('Movie'))
            ->display(function ($movie_model_id) {
                $m = \App\Models\MovieModel::find($movie_model_id);
                if ($m) {
                    if (strlen($m->title) <= 45) {
                        return $m->title;
                    }
                    //LIMIT TITLE TO 30 CHARACTERS
                    return substr($m->title, 0, 45) . '...';
                }
                return 'Deleted';
            })->sortable();
        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                $u = \App\Models\User::find($user_id);
                if ($u) {
                    return $u->name;
                }
                return 'Deleted';
            })->sortable();
        $grid->column('ip_address', __('Ip address'))->hide();
        $grid->column('device', __('Device'))->hide();
        $grid->column('platform', __('Platform'))->hide();
        $grid->column('browser', __('Browser'))->hide();
        $grid->column('country', __('Country'))->hide();
        $grid->column('city', __('City'))->hide();
        $grid->column('status', __('Status'))->hide();
        $grid->column('user_reg_date', __('User reg date'))
            ->display(function ($user_id) {
                $u = \App\Models\User::find($this->user_id);
                if ($u) {
                    $reg_date = Carbon::parse($u->created_at);
                    $now = Carbon::now();
                    $diff = $reg_date->diffInDays($now);
                    return date('d-m-Y H:i:s', strtotime($u->created_at)) . ' (' . $diff . ' days ago)';
                }
                return 'Deleted';
            });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(MovieView::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('movie_model_id', __('Movie model id'));
        $show->field('user_id', __('User id'));
        $show->field('ip_address', __('Ip address'));
        $show->field('device', __('Device'));
        $show->field('platform', __('Platform'));
        $show->field('browser', __('Browser'));
        $show->field('country', __('Country'));
        $show->field('city', __('City'));
        $show->field('status', __('Status'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieView());

        $form->number('movie_model_id', __('Movie model id'));
        $form->number('user_id', __('User id'));
        $form->text('ip_address', __('Ip address'));
        $form->text('device', __('Device'));
        $form->text('platform', __('Platform'));
        $form->text('browser', __('Browser'));
        $form->text('country', __('Country'));
        $form->text('city', __('City'));
        $form->text('progress', __('progress'));
        $form->text('status', __('Status'))->default('Active');

        return $form;
    }
}
