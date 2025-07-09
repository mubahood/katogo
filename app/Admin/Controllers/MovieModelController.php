<?php

namespace App\Admin\Controllers;

use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class MovieModelController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movies';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {

        $grid = new Grid(new MovieModel());

        $url_segs = explode('/', request()->url());
        if (in_array('movies-active', $url_segs)) {
            $grid->model()->where('status', 'Active');
        } else if (in_array('movies-series', $url_segs)) {
            $grid->model()->where('type', 'Series');
        } else if (in_array('movies-movies', $url_segs)) {
            $grid->model()->where('type', 'Movie');
        } else if (in_array('movies-inactive', $url_segs)) {
            $grid->model()->where('status', 'Inactive');
        } else if (in_array('movies-processed', $url_segs)) {
            $grid->model()->where('content_type_processed', 'Yes');
        } else if (in_array('movies-not-processed', $url_segs)) {
            $grid->model()->where('content_type_processed', 'No');
        } else if (in_array('movies-content-is-video', $url_segs)) {
            $grid->model()->where('content_is_video', 'Yes');
        }


        $grid->model()->orderBy('updated_at', 'desc');



        //add filters including filter by category
        //add MovieStatusChange batch\
        $grid->perPages([10, 20, 50, 100, 200, 500, 1000]);
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\Post\MovieStatusChange());
        });
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('title', __('Title'));

            $filter->equal('category_id', __('Category'))
                ->select(SeriesMovie::all()->pluck('title', 'id'));
            $filter->between('created_at', __('Created at'))->datetime();
        });

        $grid->column('thumbnail_url', __('Thumbnail'))
            ->width(100)
            ->lightbox(['width' => 50, 'height' => 100])
            ->sortable();
        $grid->column('views_time_count', 'View Time')
            ->display(function ($views_time_count) {
                if ($views_time_count == null || $views_time_count == '') {
                    return '0 hours';
                }
                if ($views_time_count < 60) {
                    return $views_time_count . ' Seconds';
                }
                if ($views_time_count < 3600) {
                    return number_format($views_time_count / 60, 2) . ' minutes';
                }
                if ($views_time_count < 86400) {
                    return number_format($views_time_count / 3600, 2) . ' hours';
                }
                return number_format($views_time_count / 86400, 2) . ' days';
            })->sortable();


        //downloads_count
        $grid->column('downloads_count', __('Downloads count'))
            ->display(function ($downloads_count) {
                if ($downloads_count == null || $downloads_count == '') {
                    return '0';
                }
                return $downloads_count;
            })->sortable();

        //platform_type grid 
        $grid->column('platform_type', __('Platform type'))
            ->display(function ($platform_type) {
                if ($platform_type == null || $platform_type == '') {
                    return 'All';
                }
                return $platform_type;
            })->sortable()
            ->filter([
                'all' => 'All',
                'android' => 'Android',
                'ios' => 'iOS',
            ])->sortable();

        //url link
        $grid->column('url_link', __('Url'))
            ->display(function ($url) {
                return '<a href="' . $this->url . '" target="_blank">' . $this->url . '</a>';
            })->width(200)
            ->copyable();

        //views_count
        $grid->column('views_count', __('Views count'))
            ->display(function ($views_count) {
                if ($views_count == null || $views_count == '') {
                    return '0';
                }
                return $views_count;
            })->sortable();

        $grid->column('type', __('Type'))->sortable()
            ->filter([
                'Movie' => 'Movie',
                'Series' => 'Series',
            ])
            ->label([
                'Movie' => 'success',
                'Series' => 'danger',
            ]);
        $grid->column('category', __('Category'))->sortable();
        $grid->column('category_id', __('Category id'))->display(function ($category_id) {
            $category = SeriesMovie::find($category_id);
            if ($category) {
                return $category->title;
            }
            return 'N/A';
        })->sortable()->hide();

        //is_first_episode
        $grid->column('is_first_episode', __('Is first episode'))
            ->display(function ($is_first_episode) {
                if ($is_first_episode == 'Yes') {
                    return '<span class="label label-success">Yes</span>';
                } else {
                    return '<span class="label label-danger">No</span>';
                }
            })->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ])->hide();



        $grid->column('episode_number', __('EP No.'))->sortable()
            ->editable();
        $grid->column('country', __('Position'))->sortable()
            ->hide();
        $grid->column('vj', __('VJ'))->sortable()->hide();

        $grid->quickSearch('title', 'url', 'external_url', 'local_video_link');
        $grid->column('id', __('Id'))->sortable()->hide();
        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return date('Y-m-d H:i:s', strtotime($created_at));
            })->sortable()->hide();
        $grid->column('updated_at', __('Updated'))
            ->display(function ($updated_at) {
                return date('Y-m-d H:i:s', strtotime($updated_at));
            })->sortable()->hide();
        $grid->column('title', __('Title'))->sortable()
            ->editable('text')
            ->width(300);

        $grid->column('external_url', __('external_url'))->sortable()->copyable()->width(200)
            ->filter('like')
            ->hide();


        $grid->column('my_url', __('My url'))
            ->display(function ($url) {
                return $this->url;
            })->width(200)
            ->hide();

        $grid->column('url', __('url'))

            ->video(['videoWidth' => 720, 'videoHeight' => 480])->sortable();
        /* 
        $this->content_type_processed = 'Yes';
        $this->content_type_processed_time = date('Y-m-d H:i:s');
        $this->content_is_video = 'No';
        $this->content_type =  $contentType;
*/

        $grid->column('imdb_url', __('imdb url'))->sortable()
            ->hide();


        $grid->column('description', __('Description'))->hide();
        $grid->column('year', __('Year'))->sortable()->hide();
        $grid->column('content_type_processed', __('content processed'))
            ->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ])
            ->label([
                'Yes' => 'success',
                'No' => 'danger',
            ])->sortable()->hide();

        $grid->column('content_is_video', __('Content is video'))
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ])->sortable()
            ->label([
                'Yes' => 'success',
                'No' => 'danger',
            ])->sortable()->hide();
        //content_type
        $grid->column('content_type', __('Content type'))->hide();
        /*         
        $grid->column('rating', __('Rating'));
        $grid->column('duration', __('Duration'));
        $grid->column('size', __('Size'));
        $grid->column('genre', __('Genre'));
        $grid->column('director', __('Director'));
        $grid->column('stars', __('Stars'));
        $grid->column('country', __('Country'));
        $grid->column('language', __('Language'));
        $grid->column('imdb_url', __('Imdb url'));
        $grid->column('imdb_rating', __('Imdb rating'));
        $grid->column('imdb_votes', __('Imdb votes'));
        $grid->column('imdb_id', __('Imdb id')); */

        $grid->column('error', __('Error'))->hide();
        $grid->column('error_message', __('Error message'))->hide();
        $grid->column('views_count', __('Views count'))->hide();
        $grid->column('likes_count', __('Likes count'))->hide();
        $grid->column('dislikes_count', __('Dislikes count'))->hide();
        $grid->column('comments_count', __('Comments count'))->hide();
        $grid->column('comments', __('Comments'))->hide();
        //is_processed


        $grid->column('video_is_downloaded_to_server', __('Downloaded'))->sortable()
            ->filter([
                'yes' => 'Yes',
                'no' => 'No',
            ])->hide();
        $grid->column('video_downloaded_to_server_start_time', __('Doenload Start Time'))
            ->display(function ($video_downloaded_to_server_start_time) {
                return date('Y-m-d H:i:s', strtotime($video_downloaded_to_server_start_time));
            })->sortable()->hide();
        $grid->column('video_downloaded_to_server_end_time', __('Downloaded End time'))
            ->display(function ($video_downloaded_to_server_end_time) {
                return date('Y-m-d H:i:s', strtotime($video_downloaded_to_server_end_time));
            })->sortable()
            ->hide();

        $grid->column('video_downloaded_to_server_duration', __('Video downloaded to server duration'))
            ->display(function ($video_downloaded_to_server_duration) {
                //convert seconds to minutes
                $minutes = floor($video_downloaded_to_server_duration / 60);
                $seconds = $video_downloaded_to_server_duration % 60;
                return $minutes . ':' . $seconds;
            })->sortable()->hide();
        $grid->column('video_is_downloaded_to_server_status', __('downloaded status'))
            ->filter([
                'downloading' => 'Downloading',
                'error' => 'eorr',
                'success' => 'success',
            ])->sortable()->hide();
        $grid->column('video_is_downloaded_to_server_error_message', __('Video is downloaded to server error message'))->hide();


        //status
        $grid->column('status_1', __('Status'))
            ->display(function ($status) {
                $status = $this->status;
                if ($status == 'Active') {
                    return '<span class="label label-success">Active</span>';
                } else {
                    return '<span class="label label-danger">Inactive</span>';
                }
            });

        $grid->column('status', __('Status'))
            ->filter([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ])->sortable()
            ->editable('select', [
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ]);
        $grid->column('temp_status', __('Temp Status'))
            ->filter([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ])->sortable()
            ->editable('select', [
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ]);
     

        $grid->column('downloaded_to_new_server', __('Downloaded to new server'))->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ])->sortable()
            ->hide();
        //new_server_path
        $grid->column('new_server_path', __('New server path'))->sortable()
            ->display(function ($new_server_path) {
                if ($new_server_path == null || $new_server_path == '') {
                    return 'N/A';
                }
                $url = url('play?id=' . $this->id);
                return '<a href="' . $url . '" target="_blank">' . 'PLAY ' . $new_server_path . '</a>';
            })
            ->hide();

        return $grid;

        $grid->column('plays_on_google', __('Plays on google'))->sortable()
            ->filter([
                'Yes' => 'Yes',
                'No' => 'No',
            ])->editable('select', [
                'Yes' => 'Yes',
                'No' => 'No',
            ]);
        //downloaded_to_new_server

        return $grid;
    }/* 
https://storage.googleapis.com/mubahood-movies/m.schooldynamics.ug/storage/videos/1716608729_78492.mp4
https://storage.googleapis.com/mubahood-movies/m.schooldynamics.ug/storage/videos/1716608729_78492.mp4



    */

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(MovieModel::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('title', __('Title'));
        $show->field('external_url', __('External url'));
        $show->field('url', __('Url'));
        $show->field('image_url', __('Image url'));
        $show->field('thumbnail_url', __('Thumbnail url'));
        $show->field('description', __('Description'));
        $show->field('year', __('Year'));
        $show->field('rating', __('Rating'));
        $show->field('duration', __('Duration'));
        $show->field('size', __('Size'));
        $show->field('genre', __('Genre'));
        $show->field('director', __('Director'));
        $show->field('stars', __('Stars'));
        $show->field('country', __('Country'));
        $show->field('language', __('Language'));
        $show->field('imdb_url', __('Imdb url'));
        $show->field('imdb_rating', __('Imdb rating'));
        $show->field('imdb_votes', __('Imdb votes'));
        $show->field('imdb_id', __('Imdb id'));
        $show->field('type', __('Type'));
        $show->field('status', __('Status'));
        $show->field('error', __('Error'));
        $show->field('error_message', __('Error message'));
        $show->field('downloads_count', __('Downloads count'));
        $show->field('views_count', __('Views count'));
        $show->field('likes_count', __('Likes count'));
        $show->field('dislikes_count', __('Dislikes count'));
        $show->field('comments_count', __('Comments count'));
        $show->field('comments', __('Comments'));
        $show->field('video_is_downloaded_to_server', __('Video is downloaded to server'));
        $show->field('video_downloaded_to_server_start_time', __('Video downloaded to server start time'));
        $show->field('video_downloaded_to_server_end_time', __('Video downloaded to server end time'));
        $show->field('video_downloaded_to_server_duration', __('Video downloaded to server duration'));
        $show->field('video_is_downloaded_to_server_status', __('Video is downloaded to server status'));
        $show->field('video_is_downloaded_to_server_error_message', __('Video is downloaded to server error message'));
        $show->field('category', __('Category'));
        $show->field('category_id', __('Category id'));
        $show->field('is_processed', __('Is processed'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieModel());
        $form->text('title', __('Title'))->rules('required');
        $form->image('thumbnail_url', __('Thumbnail'))
            ->removable()
            ->downloadable();


        $form->radio('stars', 'Source Type')
            ->options([
                'file' => 'FILE',
                'url' => 'URL',
            ])
            ->when('file', function (Form $form) {
                $form->file('local_video_link', __('Movie file'))->removable();
            })->when('url', function (Form $form) {
                $form->text('url', __('Movie url'));
            })->rules('required');

        //platform_type
        $form->radio('platform_type', __('Platform type'))
            ->options([
                'all' => 'All',
                'android' => 'Android',
                'ios' => 'iOS',
            ])
            ->default('all')
            ->rules('required');


        if ($form->isCreating()) {
            $active_serrie = SeriesMovie::where('is_active', 'Yes')->first();
            $has_active_series = $active_serrie ? 'Series' : 'Movie';


            $form->radio('genre', __('genre'))
                ->options([
                    'Action' => 'Action',
                    'Adventure' => 'Adventure',
                    'Animation' => 'Animation',
                    'Biography' => 'Biography',
                    'Comedy' => 'Comedy',
                    'Crime' => 'Crime',
                    'Documentary' => 'Documentary',
                    'Drama' => 'Drama',
                    'Family' => 'Family',
                    'Fantasy' => 'Fantasy',
                    'History' => 'History',
                    'Horror' => 'Horror',
                    'Music' => 'Music',
                    'Musical' => 'Musical',
                    'Mystery' => 'Mystery',
                    'Romance' => 'Romance',
                    'Sci-Fi' => 'Sci-Fi',
                    'Short' => 'Short',
                    'Sport' => 'Sport',
                    'Thriller' => 'Thriller',
                    "War" => "War",
                    'Western' => 'Western',
                ])->rules('required');

            $form->radio('vj', __('VJ'))
                ->options(
                    Utils::$JV
                )->rules('required');
            $form->radio('type', __('Type'))
                ->options([
                    'Movie' => 'Movie',
                    'Series' => 'Series',
                ])
                ->when('Series', function (Form $form) {
                    $active_serrie = SeriesMovie::where('is_active', 'Yes')->first();
                    $serrie_id = null;
                    $number_of_episodes = 0;
                    if ($active_serrie) {
                        $count = MovieModel::where('category_id', $active_serrie->id)->count();
                        if ($count > 0) {
                            $number_of_episodes = $count;
                        }
                        $serrie_id = $active_serrie->id;
                    }
                    $number_of_episodes += 1;
                    $form->radio('category_id', __('Select Series'))->rules('required')
                        ->options(SeriesMovie::all()->pluck('title', 'id'))
                        ->default($serrie_id);
                    $form->decimal('episode_number', 'Episode Position')->rules('required')
                        ->default($number_of_episodes);
                })->when('Movie', function (Form $form) {
                    $form->radio('category', __('Category'))
                        ->options(
                            Utils::$CATEGORIES
                        )->rules('required');
                })->default($has_active_series);
        } else {

            $form->radio('genre', __('VJ'))
                ->options(
                    Utils::$JV
                )->rules('required');
            $form->radio('type', __('Type'))
                ->options([
                    'Movie' => 'Movie',
                    'Series' => 'Series',
                ])
                ->when('Series', function (Form $form) {
                    $form->radio('category_id', __('Select Series'))->rules('required')
                        ->options(SeriesMovie::all()->pluck('title', 'id'));
                    $form->decimal('country', 'Position')->rules('required');
                })->when('Movie', function (Form $form) {
                    $form->radio('category', __('Category'))
                        ->options(
                            Utils::$CATEGORIES
                        )->rules('required');
                });
        }
        $form->radio('is_processed', __('Is Processed'))
            ->options([
                'Yes' => 'Yes',
                'No' => 'No',
            ])
            ->default('No');

        $form->radio('director', __('Advanced Information'))
            ->options([
                'Basic' => 'Basic',
                'Advanced' => 'Advanced',
            ])
            ->when('Advanced', function (Form $form) {
                $form->text('language', __('Language'));
                $form->text('imdb_url', __('Imdb url'));
                $form->decimal('imdb_rating', __('Imdb rating'));
                $form->decimal('imdb_votes', __('Imdb votes'));
                $form->text('imdb_id', __('Imdb id'));
                $form->text('views_count', __('Views count'));
                $form->text('likes_count', __('Likes count'));
                $form->text('dislikes_count', __('Dislikes count'));
                $form->text('comments_count', __('Comments count'));
                $form->text('comments', __('Comments'));

                $description = 'This is a movie';


                $form->divider();
                $form->decimal('year', __('Year'));
                $form->decimal('rating', __('Rating'));
                $form->decimal('duration', __('Duration'));
                $form->quill('description', __('Movie Description'));
                $form->text('image_url', __('Image url'));
                $form->decimal('size', __('Size'));
            })->default('Basic');
        $form->disableReset();
        $form->radio('status', __('Status'))
            ->options([
                'Active' => 'Active',
                'Inactive' => 'Inactive',
            ])
            ->default('Active')
            ->rules('required');
        //plays_on_google
        $form->radio('plays_on_google', __('Plays on google'))
            ->options([
                'Yes' => 'Yes',
                'No' => 'No',
            ])
            ->default('No');
        return $form;
    }
}
