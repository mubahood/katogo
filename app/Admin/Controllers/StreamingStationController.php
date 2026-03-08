<?php

namespace App\Admin\Controllers;

use App\Models\StreamingStation;
use App\Models\StreamingUrl;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use App\Admin\Actions\Post\BatchActivateStations;
use App\Admin\Actions\Post\BatchDeactivateStations;

class StreamingStationController extends AdminController
{
    protected $title = 'Streaming Stations';

    /**
     * Detect current route slug for filtering (streaming-tv, streaming-radio, streaming-stations)
     */
    private function detectSlug(): ?string
    {
        $segments = request()->segments();
        $slug = end($segments);
        // Strip numeric IDs and action verbs
        if (is_numeric($slug)) {
            $slug = prev($segments);
        }
        if (in_array($slug, ['create', 'edit'])) {
            $slug = prev($segments);
        }
        return $slug;
    }

    public function index(Content $content)
    {
        // Build stats
        $tvCount = StreamingStation::where('type', 'tv')->where('status', 'Active')->count();
        $radioCount = StreamingStation::where('type', 'radio')->where('status', 'Active')->count();
        $totalUrls = StreamingUrl::where('status', 'Active')->count();
        $featuredCount = StreamingStation::where('is_featured', true)->count();

        $statsHtml = <<<HTML
        <div class="row" style="margin-bottom:15px;">
            <div class="col-md-3">
                <div class="small-box bg-aqua">
                    <div class="inner"><h3>{$tvCount}</h3><p>Active TV Channels</p></div>
                    <div class="icon"><i class="fa fa-tv"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-green">
                    <div class="inner"><h3>{$radioCount}</h3><p>Active Radio Stations</p></div>
                    <div class="icon"><i class="fa fa-podcast"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-yellow">
                    <div class="inner"><h3>{$totalUrls}</h3><p>Active Stream URLs</p></div>
                    <div class="icon"><i class="fa fa-link"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-red">
                    <div class="inner"><h3>{$featuredCount}</h3><p>Featured Stations</p></div>
                    <div class="icon"><i class="fa fa-star"></i></div>
                </div>
            </div>
        </div>
        HTML;

        return $content
            ->title($this->title())
            ->description('Manage TV & Radio streaming stations')
            ->body($statsHtml . $this->grid()->render());
    }

    protected function grid()
    {
        $grid = new Grid(new StreamingStation());
        $grid->model()->orderBy('sort_order', 'asc')->orderBy('votes', 'desc');

        // Apply slug-based filtering
        $slug = $this->detectSlug();
        if ($slug === 'streaming-tv') {
            $grid->model()->where('type', 'tv');
        } elseif ($slug === 'streaming-radio') {
            $grid->model()->where('type', 'radio');
        }

        // Quick search
        $grid->quickSearch('name', 'category', 'frequency', 'region');

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();

            $filter->equal('type', 'Type')->select([
                'tv' => 'TV',
                'radio' => 'Radio',
            ]);

            $filter->equal('category', 'Category')->select(
                StreamingStation::distinct()->pluck('category', 'category')->toArray()
            );

            $filter->equal('status', 'Status')->select([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ]);

            $filter->equal('is_featured', 'Featured')->select([
                1 => 'Yes',
                0 => 'No',
            ]);

            $filter->equal('language', 'Language')->select(
                StreamingStation::distinct()->pluck('language', 'language')->toArray()
            );
        });

        // Columns
        $grid->column('id', 'ID')->sortable();

        $grid->column('logo_url', 'Logo')->image('', 40, 40);

        $grid->column('name', 'Name')->sortable();

        $grid->column('type', 'Type')->display(function ($type) {
            $icon = $type === 'tv' ? 'fa-tv' : 'fa-podcast';
            $color = $type === 'tv' ? 'primary' : 'success';
            return "<span class='badge badge-{$color}'><i class='fa {$icon}'></i> " . strtoupper($type) . "</span>";
        })->sortable();

        $grid->column('category', 'Category')->label('info')->sortable();

        $grid->column('frequency', 'Frequency');

        $grid->column('urls_count', 'URLs')->display(function () {
            $count = $this->streamingUrls()->count();
            $active = $this->activeUrls()->count();
            return "<span class='badge badge-success'>{$active}</span> / <span class='badge badge-default'>{$count}</span>";
        });

        $grid->column('votes', 'Votes')->sortable();

        $grid->column('status', 'Status')->display(function ($status) {
            $color = $status === 'Active' ? 'success' : 'danger';
            return "<span class='badge badge-{$color}'>{$status}</span>";
        })->sortable();

        $grid->column('is_featured', 'Featured')->switch()->sortable();

        $grid->column('sort_order', 'Order')->editable()->sortable();

        $grid->column('created_at', 'Created')->display(function ($date) {
            return date('M j, Y', strtotime($date));
        })->sortable();

        // Enable batch operations
        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
            $batch->add(new BatchActivateStations());
            $batch->add(new BatchDeactivateStations());
        });

        // Export
        $grid->export(function ($export) {
            $export->filename('Streaming_Stations_' . date('Y-m-d'));
            $export->except(['actions']);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(StreamingStation::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('name', 'Name');
        $show->field('slug', 'Slug');
        $show->field('type', 'Type');
        $show->field('category', 'Category');
        $show->field('frequency', 'Frequency');
        $show->field('description', 'Description');
        $show->field('logo_url', 'Logo')->image();
        $show->field('country', 'Country');
        $show->field('language', 'Language');
        $show->field('region', 'Region');
        $show->field('website_url', 'Website');
        $show->field('votes', 'Votes');
        $show->field('listeners_count', 'Listeners');
        $show->field('status', 'Status');
        $show->field('is_featured', 'Featured');
        $show->field('sort_order', 'Sort Order');
        $show->field('created_at', 'Created');
        $show->field('updated_at', 'Updated');

        // Show related URLs
        $show->streamingUrls('Stream URLs', function ($urls) {
            $urls->url('URL');
            $urls->label('Label');
            $urls->format('Format');
            $urls->quality('Quality');
            $urls->cdn_provider('CDN Provider');
            $urls->is_default('Default');
            $urls->status('Status');
        });

        return $show;
    }

    protected function form()
    {
        $form = new Form(new StreamingStation());

        $form->tab('Station Info', function ($form) {
            $form->text('name', 'Station Name')->rules('required');
            $form->text('slug', 'Slug')->help('Auto-generated from name if empty');

            $form->radio('type', 'Type')->options([
                'tv' => 'TV Channel',
                'radio' => 'Radio Station',
            ])->default('radio')->rules('required');

            $form->select('category', 'Category')->options([
                'Entertainment' => 'Entertainment',
                'News' => 'News',
                'Religious' => 'Religious',
                'Regional' => 'Regional',
                'Music' => 'Music',
                'Sports' => 'Sports',
                'Education' => 'Education',
                'General' => 'General',
            ])->default('General')->rules('required');

            $form->text('frequency', 'Frequency')->help('e.g. "97.7 FM", "UHF 23"');
            $form->textarea('description', 'Description');
        });

        $form->tab('Media & Location', function ($form) {
            $form->text('logo_url', 'Logo URL')->help('Direct URL to station logo image');
            $form->text('website_url', 'Website URL');
            $form->text('country', 'Country')->default('Uganda');
            $form->text('language', 'Language')->default('English');
            $form->text('region', 'Region')->help('e.g. Kampala, Jinja, Gulu');
        });

        $form->tab('Settings', function ($form) {
            $form->radio('status', 'Status')->options([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ])->default('Active')->rules('required');

            $form->switch('is_featured', 'Featured')->default(0);
            $form->number('sort_order', 'Sort Order')->default(0);
            $form->number('votes', 'Votes / Popularity')->default(0);
            $form->number('listeners_count', 'Listeners Count')->default(0);
        });

        $form->tab('Stream URLs', function ($form) {
            $form->hasMany('streamingUrls', 'Stream URLs', function (Form\NestedForm $nestedForm) {
                $nestedForm->text('url', 'Stream URL')->rules('required');
                $nestedForm->text('label', 'Label')->help('e.g. Main, Backup, HD, SD')->default('Main');
                $nestedForm->select('format', 'Format')->options([
                    'hls' => 'HLS (.m3u8)',
                    'mp3' => 'MP3',
                    'aac' => 'AAC',
                    'flv' => 'FLV',
                    'dash' => 'DASH',
                    'other' => 'Other',
                ]);
                $nestedForm->select('quality', 'Quality')->options([
                    'HD' => 'HD',
                    'SD' => 'SD',
                    'Audio' => 'Audio Only',
                ]);
                $nestedForm->number('bitrate', 'Bitrate (kbps)');
                $nestedForm->text('cdn_provider', 'CDN Provider');
                $nestedForm->text('referrer_url', 'Referrer URL')->help('Required referrer header if stream needs it');
                $nestedForm->switch('is_default', 'Default URL')->default(0);
                $nestedForm->switch('needs_token_refresh', 'Needs Token Refresh')->default(0);
                $nestedForm->select('status', 'Status')->options([
                    'Active' => 'Active',
                    'Inactive' => 'Inactive',
                    'Intermittent' => 'Intermittent',
                ])->default('Active');
                $nestedForm->number('sort_order', 'Order')->default(0);
                $nestedForm->text('notes', 'Notes');
            });
        });

        return $form;
    }
}
