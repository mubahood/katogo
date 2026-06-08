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
use Encore\Admin\Layout\Content;

class MovieModelController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movies';

    // ─── Slug detection helper ────────────────────────────
    private function detectSlug(): string
    {
        $segments = request()->segments();
        foreach ($segments as $seg) {
            if (in_array($seg, [
                'movies-movies-pending', 'movies-movies-fixed', 'movies-movies-failed',
                'movies-active', 'movies-series', 'movies-movies',
                'movies-inactive', 'movies-content-is-video',
                'movies-processed', 'movies-not-processed', 'movies',
            ])) {
                return $seg;
            }
        }
        return 'movies';
    }

    private function slugLabel(): string
    {
        return match ($this->detectSlug()) {
            'movies-movies-pending' => 'Movies — Pending Fix',
            'movies-movies-fixed'   => 'Movies — Fixed',
            'movies-movies-failed'  => 'Movies — Failed / Error',
            'movies-active'         => 'Active Movies',
            'movies-series'         => 'Series Episodes',
            'movies-movies'         => 'Movies (Type: Movie)',
            'movies-inactive'       => 'Inactive Movies',
            'movies-content-is-video'  => 'Content is Video',
            'movies-processed'      => 'Processed',
            'movies-not-processed'  => 'Not Processed',
            default                 => 'All Movies',
        };
    }

    /**
     * Index with optional compact dashboard for fix-filtered views.
     */
    public function index(Content $content)
    {
        $slug = $this->detectSlug();
        $content->title($this->slugLabel());

        // Show fix navigation tabs for movie slug views
        if (str_starts_with($slug, 'movies-movies')) {
            $content->body($this->fixNavigationTabs($slug));
        }

        return $content->body($this->grid());
    }

    /**
     * Fix navigation tabs for slug-filtered views.
     */
    protected function fixNavigationTabs(string $slug): string
    {
        $total      = MovieModel::count();
        $fixPending = MovieModel::where('fix_status', 'pending')->count();
        $fixFixed   = MovieModel::where('fix_status', 'fixed')->count();
        $fixError   = MovieModel::where('fix_status', 'error')->count();

        $html = '<style>
.mmc-nav{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.mmc-nav a{display:inline-block;padding:5px 12px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;border:1px solid #ddd;color:#555;transition:all .15s}
.mmc-nav a:hover{border-color:#3498db;color:#3498db}
.mmc-nav a.active{background:#3498db;color:#fff;border-color:#3498db}
</style>';

        $slugs = [
            'movies-movies'         => ['All ('.$total.')', 'fa-film'],
            'movies-movies-pending' => ['Pending ('.$fixPending.')', 'fa-clock-o'],
            'movies-movies-fixed'   => ['Fixed ('.$fixFixed.')', 'fa-check-circle'],
            'movies-movies-failed'  => ['Failed ('.$fixError.')', 'fa-times-circle'],
        ];
        $html .= '<div class="mmc-nav">';
        foreach ($slugs as $s => [$label, $icon]) {
            $cls = ($slug === $s) ? 'active' : '';
            $html .= "<a href='" . admin_url($s) . "' class='{$cls}'><i class='fa {$icon}'></i> {$label}</a>";
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {

        $grid = new Grid(new MovieModel());
        $grid->model()->orderBy('id', 'desc');

        // ── Auto-filter by slug ──
        $slug = $this->detectSlug();
        switch ($slug) {
            case 'movies-movies-pending':
                $grid->model()->where('fix_status', 'pending');
                break;
            case 'movies-movies-fixed':
                $grid->model()->where('fix_status', 'fixed');
                break;
            case 'movies-movies-failed':
                $grid->model()->where('fix_status', 'error');
                break;
            case 'movies-active':
                $grid->model()->where('status', 'Active');
                break;
            case 'movies-series':
                $grid->model()->where('type', 'Series');
                break;
            case 'movies-movies':
                $grid->model()->where('type', 'Movie');
                break;
            case 'movies-inactive':
                $grid->model()->where('status', 'Inactive');
                break;
            case 'movies-content-is-video':
                $grid->model()->where('content_is_video', 'Yes');
                break;
            case 'movies-processed':
                $grid->model()->where('content_type_processed', 'Yes');
                break;
            case 'movies-not-processed':
                $grid->model()->where('content_type_processed', 'No');
                break;
        }
        /*
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


        $grid->model()->orderBy('id', 'asc');*/
        $grid->column('id', __('Id'))->sortable()->editable();

        $grid->column('stars', __('FROM MY VJ'))
            ->sortable()
            ->hide()
            ->filter();
        //is_muno
        $grid->column('is_muno', __('FROM MUNO'))
            ->sortable()
            ->filter(
                [
                    'Yes' => 'Yes',
                    'No' => 'No',
                    '' => 'Empty',
                    null => 'N/A',
                ]
            );
        //muno_processed
        $grid->column('muno_processed', __('Muno Processed'))
            ->sortable()
            ->filter(
                [
                    'Yes' => 'Yes',
                    'No' => 'No',
                    '' => 'Empty',
                    null => 'N/A',
                ]
            );

        //add filters including filter by category
        //add MovieStatusChange batch\
        $grid->perPages([10, 20, 50, 100, 200, 500, 1000]);
        $grid->batchActions(function ($batch) {
            $batch->add(new \App\Admin\Actions\Post\MovieStatusChange());
            $batch->add(new \App\Admin\Actions\Post\BatchFixMovies());
        });
        $grid->filter(function ($filter) {
            // $filter->disableIdFilter();
            $filter->like('title', __('Title'));

            $filter->equal('category_id', __('Category'))
                ->select(SeriesMovie::all()->pluck('title', 'id'));
            $filter->between('created_at', __('Created at'))->datetime();
            $filter->equal('fix_status', __('Fix Status'))->select([
                'pending' => 'Pending',
                'fixed'   => 'Fixed',
                'error'   => 'Error',
            ]);
        });

        $grid->column('thumbnail_url', __('Thumbnail'))
            ->width(100)
            ->lightbox(['width' => 50, 'height' => 100])
            ->sortable();
        $grid->column('views_time_count', 'View Time')
            ->hide()
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
            })->sortable()
            ->hide()
        ;

        //platform_type grid
        $grid->column('platform_type', __('Platform'))
            ->editable('select', [
                'all'     => 'All',
                'android' => 'Android',
                'ios'     => 'iOS',
            ])
            ->display(function ($value) {
                $badges = [
                    'all'     => '<span style="background:#28a745;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">All</span>',
                    'android' => '<span style="background:#3ddc84;color:#000;padding:2px 8px;border-radius:4px;font-size:11px;">Android</span>',
                    'ios'     => '<span style="background:#007aff;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">iOS</span>',
                ];
                return $badges[$value] ?? $badges['all'];
            })
            ->filter([
                'all'     => 'All',
                'android' => 'Android',
                'ios'     => 'iOS',
            ])
            ->sortable();

        //url link
        $grid->column('url', __('Url'))
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
        $grid->column('category', __('Category'))->sortable()->hide();
        $grid->column('category_id', __('Category id'))->display(function ($category_id) {
            $category = SeriesMovie::find($category_id);
            if ($category) {
                return $category->title;
            }
            return 'N/A';
        })->sortable();

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
            ]);



        $grid->column('episode_number', __('EP No.'))->sortable()
            ->editable();
        $grid->column('country', __('Position'))->sortable()
            ->hide();
        $grid->column('vj', __('VJ'))->sortable()->hide();

        $grid->quickSearch('title', 'url', 'external_url', 'local_video_link');

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

        /*         $grid->column('url', __('url'))
            ->display(function ($url) {
                if (empty($url) || is_null($url)) {
                    return '<span class="text-muted">No video URL</span>';
                }
                return $url;
            })
            ->hide()
            ->video(['videoWidth' => 720, 'videoHeight' => 480])->sortable(); */
        /* 
        $this->content_type_processed = 'Yes';
        $this->content_type_processed_time = date('Y-m-d H:i:s');
        $this->content_is_video = 'No';
        $this->content_type =  $contentType;
*/




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
        $grid->column('muno_message', __('Muno Message'))->sortable()->hide(); 
        //is_processed
        // Debug Player — Play button that mimics mobile app playback
        $grid->column('debug_play', __('Debug Play'))->display(function () {
            $movieData = json_encode([
                'id' => $this->id,
                'title' => $this->title,
                'url' => $this->url,
                'external_url' => $this->external_url,
                'firebase_video_url' => $this->firebase_video_url,
                'old_video_url' => $this->old_video_url,
                'type' => $this->type,
                'status' => $this->status,
                'genre' => $this->genre,
                'vj' => $this->vj,
                'thumbnail_url' => $this->thumbnail_url,
                'content_type' => $this->content_type,
                'content_is_video' => $this->content_is_video,
                'firebase_transfer_successful' => $this->firebase_transfer_successful,
                'category' => $this->category,
                'episode_number' => $this->episode_number,
                'season_number' => $this->season_number,
                'series_title' => $this->series_title,
                'episode_title' => $this->episode_title,
                'duration' => $this->duration,
                'year' => $this->year,
                'language' => $this->language,
                'munowatch_id' => $this->munowatch_id,
                'views_count' => $this->views_count,
                'fix_status' => $this->fix_status,
                'fix_counter' => $this->fix_counter,
                'fix_date' => $this->fix_date,
                'fix_error_message' => $this->fix_error_message,
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
            return '<button class="btn btn-xs btn-primary ugflix-debug-play-btn" data-movie="' . htmlspecialchars($movieData, ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-play"></i> Play</button>';
        });

        // ── Fix tracking columns ──
        $grid->column('fix_status', __('Fix'))
            ->sortable()
            ->editable('select', ['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error'])
            ->filter(['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error']);

        $grid->column('fix_counter', __('#Fix'))
            ->sortable()
            ->display(function ($val) {
                $color = $val > 5 ? '#e74c3c' : ($val > 0 ? '#f39c12' : '#bdc3c7');
                return "<span style='color:{$color};font-weight:600'>{$val}</span>";
            });

        $grid->column('fix_date', __('Fixed At'))
            ->sortable()
            ->display(function ($val) {
                if (!$val) return '<span class="text-muted">—</span>';
                return \Carbon\Carbon::parse($val)->diffForHumans();
            })->hide();

        $grid->column('fix_error_message', __('Fix Error'))
            ->display(function ($val) {
                if (empty($val)) return '<span class="text-muted">—</span>';
                $short = mb_strlen($val) > 50 ? mb_substr($val, 0, 50) . '…' : $val;
                return "<span style='color:#e74c3c;font-size:11px' title='" . htmlspecialchars($val) . "'>{$short}</span>";
            })->hide();

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
            ->hide()
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
            ->hide()
            ->hide();

        $grid->column('video_url_tested_by_curl', __('Video URL Tested by Curl'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('video_url_tested_by_curl_works', __('Video URL Curl Works'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('video_url_tested_by_human', __('Video URL Tested by Human'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('video_url_tested_by_human_works', __('Video URL Human Works'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_transfer_attempted', __('Firebase Transfer Attempted'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_transfer_transfer_in_progress', __('Firebase Transfer In Progress'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_transfer_successful', __('Firebase Transfer Successful'))
            ->sortable()
            ->filter([
                'Yes' => 'Yes',
                'FIX-FAIL' => 'FIX-FAIL',
                'FIX-PASS' => 'FIX-PASS',
                'No' => 'No'
            ])->hide();

        $grid->column('firebase_transfer_failure_reason', __('Firebase Transfer Failure Reason'))
            ->editable('text')
            ->sortable()
            ->hide()
            ->filter('like');

        $grid->column('firebase_transfer_path', __('Firebase Transfer Path'))
            ->editable('text')
            ->sortable()
            ->hide()
            ->filter('like');

        $grid->column('firebase_video_url', __('Firebase Video URL'))
            ->editable('text')
            ->sortable()
            ->hide()
            ->filter('like');

        $grid->column('firebase_video_url_expires_at', __('Firebase Video URL Expires At'))
            ->sortable()
            ->hide()
            ->filter('range', 'datetime');

        $grid->column('firebase_video_tested_by_curl', __('Firebase Video Tested by Curl'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_video_tested_by_curl_works', __('Firebase Video Curl Works'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_video_tested_by_human', __('Firebase Video Tested by Human'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('firebase_video_tested_by_human_works', __('Firebase Video Human Works'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->sortable()
            ->hide()
            ->filter(['Yes' => 'Yes', 'No' => 'No']);

        $grid->column('old_video_url', __('Old Video URL'))
            ->editable('text')
            ->sortable()
            ->hide()
            ->filter('like');

        // ── CSV Export — strip HTML from display columns ──
        $grid->export(function ($export) {
            $export->filename('Movies_' . date('Y-m-d_H-i'));
            $export->except(['actions']);
            $export->column('url', function ($value) {
                return strip_tags($value);
            });
            $export->column('is_first_episode', function ($value) {
                return strip_tags($value);
            });
            $export->column('status_1', function ($value) {
                return strip_tags($value);
            });
            $export->column('fix_counter', function ($value) {
                return strip_tags($value);
            });
            $export->column('fix_error_message', function ($value) {
                return strip_tags($value);
            });
            $export->column('new_server_path', function ($value) {
                return strip_tags($value);
            });
        });

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

        $show->divider();
        $show->field('fix_status', __('Fix Status'));
        $show->field('fix_error_message', __('Fix Error Message'));
        $show->field('fix_date', __('Fix Date'));
        $show->field('fix_counter', __('Fix Attempts'));

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

        $form->divider('Video URL Testing');
        $form->radio('video_url_tested_by_curl', __('Video URL Tested by Curl'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('video_url_tested_by_curl_works', __('Video URL Curl Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('video_url_tested_by_human', __('Video URL Tested by Human'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('video_url_tested_by_human_works', __('Video URL Human Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');

        $form->divider('Firebase Transfer');
        $form->radio('firebase_transfer_attempted', __('Firebase Transfer Attempted'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('firebase_transfer_transfer_in_progress', __('Firebase Transfer In Progress'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('firebase_transfer_successful', __('Firebase Transfer Successful'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->text('firebase_transfer_failure_reason', __('Firebase Transfer Failure Reason'));
        $form->text('firebase_transfer_path', __('Firebase Transfer Path'));
        $form->text('firebase_video_url', __('Firebase Video URL'));
        $form->datetime('firebase_video_url_expires_at', __('Firebase Video URL Expires At'));

        $form->divider('Firebase Video URL Testing');
        $form->radio('firebase_video_tested_by_curl', __('Firebase Video Tested by Curl'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('firebase_video_tested_by_curl_works', __('Firebase Video Curl Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('firebase_video_tested_by_human', __('Firebase Video Tested by Human'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');
        $form->radio('firebase_video_tested_by_human_works', __('Firebase Video Human Works'))
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No');

        $form->text('old_video_url', __('Old Video URL'));

        $form->divider('Fix Tracking');
        $form->select('fix_status', __('Fix Status'))
            ->options(['pending' => 'Pending', 'fixed' => 'Fixed', 'error' => 'Error'])
            ->default('pending');
        $form->textarea('fix_error_message', __('Fix Error Message'))->rows(2);
        $form->datetime('fix_date', __('Fix Date'));
        $form->number('fix_counter', __('Fix Attempts'))->default(0);

        return $form;
    }
}
