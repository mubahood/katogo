<?php

namespace App\Admin\Controllers;

use App\Models\MovieModel;
use App\Models\SystemConfig;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\Cache;

class SystemConfigController extends AdminController
{
    protected $title = 'System Configuration';

    /**
     * Show a single-row edit form directly (skip the grid listing).
     */
    public function index(Content $content): Content
    {
        return $content
            ->title('System Configuration')
            ->description('Global settings for the LugaFlix platform')
            ->body($this->form()->edit(SystemConfig::instance()->id));
    }

    protected function grid(): Grid
    {
        $grid = new Grid(new SystemConfig());
        $grid->disableCreateButton();
        $grid->disableExport();
        $grid->disableBatchActions();
        $grid->disablePagination();

        $grid->column('id');
        $grid->column('ios_review_mode', 'iOS Review Mode')->bool(['1' => true, '0' => false]);
        $grid->column('maintenance_mode', 'Maintenance Mode')->bool(['1' => true, '0' => false]);
        $grid->column('updated_at', 'Last Updated');

        return $grid;
    }

    protected function form(): Form
    {
        $form = new Form(new SystemConfig());

        $form->tab('iOS App Store Review', function (Form $form) {
            $form->switch('ios_review_mode', 'iOS Review Mode')
                ->help('When ON, iOS users see a simplified review version of the app. Android is unaffected.');

            $form->text('ios_review_message', 'Review Home Message')
                ->default('Welcome to LugaFlix')
                ->help('Shown on the simplified home screen during iOS review.');

            // Build a multi-select of available movies
            $movies = MovieModel::where('status', 'Active')
                ->orderBy('title')
                ->limit(200)
                ->pluck('title', 'id')
                ->toArray();

            $form->multipleSelect('ios_review_movie_ids', 'Review Movies')
                ->options($movies)
                ->help('Movies shown during iOS review. Leave empty to auto-select top 10 free movies.');
        });

        $form->tab('Maintenance', function (Form $form) {
            $form->switch('maintenance_mode', 'Maintenance Mode')
                ->help('When ON, app shows maintenance message to all users.');
            $form->textarea('maintenance_message', 'Maintenance Message')
                ->rows(2)
                ->placeholder('We are performing scheduled maintenance. Back shortly.');
        });

        $form->tab('Version Requirements', function (Form $form) {
            $form->number('min_android_version', 'Min Android App Version')
                ->min(1)
                ->help('Android users below this build number will be forced to update.');
            $form->number('min_ios_version', 'Min iOS App Version')
                ->min(1)
                ->help('iOS users below this build number will be forced to update.');
        });

        $form->saved(function () {
            Cache::forget('system_config');
            Cache::forget('ios_review_movies_default');
        });

        // Disable delete — this is a singleton record
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });

        return $form;
    }
}
