<?php

namespace App\Admin\Controllers;

use App\Models\MovieModel;
use App\Models\SubtitleFile;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SubtitleFileController extends AdminController
{
    protected $title = 'Subtitle Files';

    protected function grid()
    {
        $grid = new Grid(new SubtitleFile());

        $grid->model()->latest();

        $grid->column('id', 'ID')->sortable();
        $grid->column('movie_id', 'Movie')->display(function ($id) {
            $movie = MovieModel::find($id);
            return $movie ? "<a href='/admin/movies/{$id}/edit'>{$movie->title}</a>" : "Movie #{$id}";
        })->html();
        $grid->column('language', 'Lang')->label();
        $grid->column('label', 'Label');
        $grid->column('url', 'File URL')->display(function ($url) {
            if (!$url) return '—';
            $short = strlen($url) > 60 ? '...' . substr($url, -50) : $url;
            return "<a href='{$url}' target='_blank'>{$short}</a>";
        })->html();
        $grid->column('is_default', 'Default')->bool();
        $grid->column('created_at', 'Added')->sortable()->display(function ($v) {
            return $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '—';
        });

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('label', 'Label');
            $filter->equal('language', 'Language')->select([
                'en' => 'English',
                'lg' => 'Luganda',
                'sw' => 'Swahili',
                'fr' => 'French',
            ]);
            $filter->equal('is_default', 'Default?')->select([1 => 'Yes', 0 => 'No']);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(SubtitleFile::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('movie_id', 'Movie ID');
        $show->field('language', 'Language');
        $show->field('label', 'Label');
        $show->field('url', 'File URL');
        $show->field('is_default', 'Default');
        $show->field('created_at', 'Created');

        return $show;
    }

    protected function form()
    {
        $form = new Form(new SubtitleFile());

        $movieOptions = MovieModel::orderBy('title')
            ->limit(2000)
            ->pluck('title', 'id')
            ->toArray();

        $form->select('movie_id', 'Movie')
            ->options($movieOptions)
            ->rules('required');

        $form->select('language', 'Language Code')
            ->options([
                'en' => 'en — English',
                'lg' => 'lg — Luganda',
                'sw' => 'sw — Swahili',
                'fr' => 'fr — French',
                'ar' => 'ar — Arabic',
            ])
            ->default('en')
            ->rules('required');

        $form->text('label', 'Display Label')
            ->placeholder('e.g. English, Luganda')
            ->rules('required|max:50');

        $form->url('url', 'Subtitle File URL')
            ->placeholder('https://cdn.example.com/subs/movie-en.vtt')
            ->help('WebVTT (.vtt) or SRT (.srt) file URL. Must be publicly accessible.')
            ->rules('required|url|max:500');

        $form->switch('is_default', 'Set as Default')->default(0);

        $form->saved(function (Form $form) {
            if ($form->model()->is_default) {
                SubtitleFile::where('movie_id', $form->model()->movie_id)
                    ->where('id', '!=', $form->model()->id)
                    ->update(['is_default' => false]);
            }
        });

        return $form;
    }
}
