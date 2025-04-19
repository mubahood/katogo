<?php

namespace App\Admin\Controllers;

use App\Models\SeriesMovie;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SeriesMovieController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Series Movies';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SeriesMovie());

        //add batch action of SeriesMovieStatusChange
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\Post\SeriesMovieStatusChange());
        });


        $grid->quickSearch('title')->placeholder('Search by title');
        //add some filters
        $grid->filter(function ($filter) {
            // Remove the default id filter
            $filter->disableIdFilter();
            // Add a custom filter for title
            $filter->like('title', 'Title');
            // Add a custom filter for category
            $filter->equal('Category', 'Category')->select(Utils::$CATEGORIES);
        });

        $grid->column('thumbnail', __('Thumbnail'))->image('', 50, 50)->sortable();
        $grid->column('id', __('Id'))->sortable()->hide();
        $grid->column('title', __('Title'))->sortable();
        $grid->column('Category', __('Category'))->sortable();
        $grid->column('description', __('Description'))->hide();
        $grid->column('total_seasons', __('Total seasons'))->hide();
        $grid->column('total_episodes', __('Total episodes'))->sortable()
            ->display(function ($total_episodes) {
                $real_total_episodes = $this->episodes()->count();
                if ($real_total_episodes != $total_episodes || $real_total_episodes < 3) {
                    $this->total_episodes = $real_total_episodes;
                    $this->save();
                }
                //url to filter http://localhost/katogo/movies?title=&type=&category_id=4&created_at%5Bstart%5D=&created_at%5Bend%5D=
                $url = url('movies?category_id=' . $this->id);
                //open new tab
                return '<a href="' . $url . '" target="_blank">' . $total_episodes . '</a>';
            });
        $grid->column('total_views', __('Total views'));
        $grid->column('is_active', __('Is ACTIVE'))
            ->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
                'Failed' => 'Failed',
            ])
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed']);
        //sour
        $grid->column('external_url', __('External URL'))
            ->filter('like')
            ->sortable()
            ->display(function ($external_url) {
                if (strlen($external_url) <= 45) {
                    return $external_url;
                }
                //openn new tab
                return '<a href="' . $external_url . '" target="_blank">' . $external_url . '</a>';
            });
        //fix-serries-movies
        $grid->column('fix', __('Created at'))
            ->display(function ($created_at) {
                $ur = url('fix-serries-movies?id=' . $this->id);
                //open new tab
                return '<a href="' . $ur . '" target="_blank">Fix</a>';
            });

        //increase  $grid per page options to 500 and 1k
        $grid->perPages([10, 20, 50, 100, 200, 500, 1000]);

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
        $show = new Show(SeriesMovie::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('title', __('Title'));
        $show->field('Category', __('Category'));
        $show->field('description', __('Description'));
        $show->field('thumbnail', __('Thumbnail'));
        $show->field('total_seasons', __('Total seasons'));
        $show->field('total_episodes', __('Total episodes'));
        $show->field('total_views', __('Total views'));
        $show->field('total_rating', __('Total rating'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SeriesMovie());

        $form->text('title', __('Title'))->creationRules('required|unique:series_movies')->updateRules('required|unique:series_movies,title,{{id}}');

        $form->image('thumbnail', __('Thumbnail'));

        $form->radio('is_active', __('is ACTIVE'))
            ->options(['Yes' => 'Yes', 'No' => 'No', 'Failed' => 'Failed'])
            ->default('Yes');

        /* 

          $form->select('Category', __('Category'))
            ->options(
                Utils::$CATEGORIES
            );
            
                 $form->quill('description', __('Description'));
        $form->decimal('total_seasons', __('Total seasons'));
        $form->decimal('total_episodes', __('Total episodes'));
        $form->decimal('total_views', __('Total views'));
        $form->decimal('total_rating', __('Total rating'));
            */

        return $form;
    }
}
