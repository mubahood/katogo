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
        $config = SystemConfig::instance();
        $form   = $this->form();
        // Explicitly set the action so Encore Admin doesn't try to derive it
        // from request()->getUri() (which produces a wrong URL when the edit
        // form is embedded directly in the index page).
        $form->setAction(admin_url('system-configs/' . $config->id));

        return $content
            ->title('System Configuration')
            ->description('Global settings for the LugaFlix platform')
            ->body($form->edit($config->id));
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

        $form->divider('iOS App Store Review');

        $form->radio('ios_review_mode', 'iOS Review Mode')
            ->options(['0' => 'Off', '1' => 'On'])
            ->help('When On, iOS users see a simplified review version of the app. Android is unaffected.');

        $form->text('ios_review_message', 'Review Home Message')
            ->default('Welcome to LugaFlix')
            ->help('Shown on the simplified home screen during iOS review.');

        $movies = MovieModel::where('status', 'Active')
            ->orderBy('title')
            ->limit(200)
            ->pluck('title', 'id')
            ->toArray();

        $form->multipleSelect('ios_review_movie_ids', 'Review Movies')
            ->options($movies)
            ->help('Movies shown during iOS review. Leave empty to auto-select top 10 free movies.');

        $form->divider('Maintenance');

        $form->radio('maintenance_mode', 'Maintenance Mode')
            ->options(['0' => 'Off', '1' => 'On'])
            ->help('When On, the app shows a maintenance message to all users.');

        $form->textarea('maintenance_message', 'Maintenance Message')
            ->rows(2)
            ->placeholder('We are performing scheduled maintenance. Back shortly.');

        $form->divider('Hetzner Storage Maintenance (Video URL Fallback)');

        $form->radio('storage_maintenance_enabled', 'Hetzner Under Maintenance?')
            ->options(['0' => 'No', '1' => 'Yes'])
            ->help('When Yes, Hetzner-hosted movies are automatically served from their original '
                 . 'CDN URLs (transfer record source, then old_video_url) so playback keeps working. '
                 . 'Turns itself off automatically after the end date below.');

        $form->text('storage_maintenance_host', 'Affected Storage Host')
            ->default('nx100800.your-storageshare.de')
            ->help('Movie URLs containing this hostname are substituted with their fallback URL.');

        $form->datetime('storage_maintenance_ends_at', 'Maintenance Ends At (UTC)')
            ->help('When this time passes, the fallback deactivates automatically — no need to '
                 . 'come back and switch it off. Leave empty to keep it on until manually disabled.');

        $form->divider('Video URL Serving Priority (Bunny CDN)');

        $form->select('movie_url_priority', 'URL Priority Order')
            ->options([
                'bunny,main,hetzner' => 'Bunny first → Main → Hetzner   (serve Bunny CDN when a copy exists)',
                'main,bunny,hetzner' => 'Main first → Bunny → Hetzner   (safe default — no behavior change)',
                'hetzner,bunny,main' => 'Hetzner first → Bunny → Main',
                'main,hetzner,bunny' => 'Main first → Hetzner → Bunny   (Bunny as last resort)',
            ])
            ->default('bunny,main,hetzner')
            ->help('Which video URL variant the API serves to the mobile apps. Per movie, the first
                    variant that exists wins. Takes effect within ~1 minute of saving — no deploy needed.
                    Bunny copies are created from Admin → Movie Transfers → Bunny Transfers.');

        $form->divider('Version Requirements');

        $form->number('min_android_version', 'Min Android App Version')
            ->min(1)
            ->help('Android users below this build number will be forced to update.');

        $form->number('min_ios_version', 'Min iOS App Version')
            ->min(1)
            ->help('iOS users below this build number will be forced to update.');

        // Normalise radio string values to booleans before save
        $form->saving(function (Form $form) {
            $form->ios_review_mode             = (bool) $form->ios_review_mode;
            $form->maintenance_mode            = (bool) $form->maintenance_mode;
            $form->storage_maintenance_enabled = (bool) $form->storage_maintenance_enabled;
        });

        $form->saved(function () {
            Cache::forget('system_config');
            Cache::forget('ios_review_movies_default');
            Cache::forget('storage_maint_cfg');
            Cache::forget('storage_fallback_map');
            Cache::forget('movie_url_priority_cfg');
            Cache::forget('bunny_url_map');
            Cache::forget('hetzner_url_map');
        });

        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });

        return $form;
    }
}
