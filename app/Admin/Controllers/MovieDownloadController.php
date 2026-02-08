<?php

namespace App\Admin\Controllers;

use App\Models\MovieDownload;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\Utils;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Table;
use Illuminate\Support\Facades\DB;

class MovieDownloadController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Movie Downloads';

    /**
     * Index interface with dashboard stats.
     */
    public function index(Content $content)
    {
        return $content
            ->title('📥 Movie Downloads')
            ->description('Monitor download activity across all apps')
            ->row(function (Row $row) {
                $row->column(3, $this->totalDownloadsBox());
                $row->column(3, $this->todayDownloadsBox());
                $row->column(3, $this->uniqueUsersBox());
                $row->column(3, $this->uniqueMoviesBox());
            })
            ->row(function (Row $row) {
                $row->column(3, $this->weekDownloadsBox());
                $row->column(3, $this->monthDownloadsBox());
                $row->column(3, $this->avgDurationBox());
                $row->column(3, $this->totalSizeBox());
            })
            ->row(function (Row $row) {
                $row->column(4, $this->topDownloadedMoviesBox());
                $row->column(4, $this->topUsersBox());
                $row->column(4, $this->vjDistributionBox());
            })
            ->body($this->grid());
    }

    // ─── INFO BOXES ─────────────────────────────────────────────

    protected function totalDownloadsBox()
    {
        $count = MovieDownload::count();
        return new InfoBox('Total Downloads', 'download', 'blue', '/admin/movie-downloads', number_format($count));
    }

    protected function todayDownloadsBox()
    {
        $count = MovieDownload::whereDate('created_at', Carbon::today())->count();
        $yesterday = MovieDownload::whereDate('created_at', Carbon::yesterday())->count();
        $trend = $count > $yesterday ? '↑' : ($count < $yesterday ? '↓' : '→');
        return new InfoBox("Today {$trend}", 'calendar', 'aqua', '#', number_format($count));
    }

    protected function uniqueUsersBox()
    {
        $count = MovieDownload::where('user_id', '>', 0)->distinct('user_id')->count('user_id');
        return new InfoBox('Unique Users', 'users', 'green', '#', number_format($count));
    }

    protected function uniqueMoviesBox()
    {
        $count = MovieDownload::distinct('movie_model_id')->count('movie_model_id');
        return new InfoBox('Unique Movies', 'film', 'yellow', '#', number_format($count));
    }

    protected function weekDownloadsBox()
    {
        $count = MovieDownload::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $prevWeek = MovieDownload::whereBetween('created_at', [Carbon::now()->subDays(14), Carbon::now()->subDays(7)])->count();
        $trend = $count > $prevWeek ? '↑' : ($count < $prevWeek ? '↓' : '→');
        return new InfoBox("This Week {$trend}", 'calendar-check-o', 'olive', '#', number_format($count));
    }

    protected function monthDownloadsBox()
    {
        $count = MovieDownload::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        return new InfoBox('This Month', 'calendar-o', 'purple', '#', number_format($count));
    }

    protected function avgDurationBox()
    {
        $avg = MovieDownload::where('download_duration', '>', 0)->avg('download_duration');
        $avgMin = $avg ? round($avg / 60, 1) : 0;
        return new InfoBox('Avg Duration', 'clock-o', 'maroon', '#', $avgMin . ' min');
    }

    protected function totalSizeBox()
    {
        // Parse file_size strings like "344.45 MB", "1.2 GB"
        $downloads = MovieDownload::whereNotNull('file_size')->where('file_size', '!=', '')->pluck('file_size');
        $totalMb = 0;
        foreach ($downloads as $sizeStr) {
            if (preg_match('/^([\d.]+)\s*(MB|GB|KB)/i', $sizeStr, $m)) {
                $val = (float) $m[1];
                $unit = strtoupper($m[2]);
                if ($unit === 'GB') $totalMb += $val * 1024;
                elseif ($unit === 'KB') $totalMb += $val / 1024;
                else $totalMb += $val;
            }
        }
        $display = $totalMb >= 1024 ? round($totalMb / 1024, 1) . ' GB' : round($totalMb) . ' MB';
        return new InfoBox('Total Size', 'database', 'teal', '#', $display);
    }

    // ─── TABLE WIDGETS ──────────────────────────────────────────

    protected function topDownloadedMoviesBox()
    {
        $movies = MovieDownload::whereNotNull('movie_model_id')
            ->selectRaw('movie_model_id, COUNT(*) as dl_count, MAX(title) as title')
            ->groupBy('movie_model_id')
            ->orderByDesc('dl_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($movies as $m) {
            $displayTitle = $m->title ?: 'Movie #' . $m->movie_model_id;
            $displayTitle = mb_strlen($displayTitle) > 30 ? mb_substr($displayTitle, 0, 30) . '...' : $displayTitle;
            $statusClass = $rank <= 3 ? 'success' : ($rank <= 6 ? 'info' : 'default');
            $rows[] = [
                "#{$rank}",
                "<a href='movies-movies/{$m->movie_model_id}' title='" . htmlspecialchars($m->title ?? '') . "'>{$displayTitle}</a>",
                "<span class='label label-{$statusClass}'>" . number_format($m->dl_count) . "</span>",
            ];
            $rank++;
        }
        if (empty($rows)) $rows[] = ['-', 'No data yet', '-'];

        $table = new Table(['#', 'Movie', 'Downloads'], $rows);
        $box = new Box('🔥 Most Downloaded', $table);
        $box->style('success');
        $box->solid();
        return $box;
    }

    protected function topUsersBox()
    {
        $users = MovieDownload::where('user_id', '>', 0)
            ->selectRaw('user_id, COUNT(*) as dl_count')
            ->groupBy('user_id')
            ->orderByDesc('dl_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($users as $u) {
            $user = User::find($u->user_id);
            $userName = $user ? $user->name : 'User #' . $u->user_id;
            $displayName = mb_strlen($userName) > 25 ? mb_substr($userName, 0, 25) . '...' : $userName;
            $rows[] = [
                "#{$rank}",
                "<a href='users/{$u->user_id}' title='" . htmlspecialchars($userName) . "'>{$displayName}</a>",
                "<span class='label label-info'>" . number_format($u->dl_count) . "</span>",
            ];
            $rank++;
        }
        if (empty($rows)) $rows[] = ['-', 'No data yet', '-'];

        $table = new Table(['#', 'User', 'Downloads'], $rows);
        $box = new Box('👤 Top Downloaders', $table);
        $box->style('info');
        $box->solid();
        return $box;
    }

    protected function vjDistributionBox()
    {
        $vjs = MovieDownload::whereNotNull('vj')->where('vj', '!=', '')
            ->selectRaw('vj, COUNT(*) as dl_count')
            ->groupBy('vj')
            ->orderByDesc('dl_count')
            ->limit(10)
            ->get();

        $rows = [];
        $rank = 1;
        foreach ($vjs as $v) {
            $rows[] = [
                "#{$rank}",
                $v->vj,
                "<span class='label label-warning'>" . number_format($v->dl_count) . "</span>",
            ];
            $rank++;
        }
        if (empty($rows)) $rows[] = ['-', 'No data yet', '-'];

        $table = new Table(['#', 'VJ', 'Downloads'], $rows);
        $box = new Box('🎙️ VJ Popularity', $table);
        $box->style('warning');
        $box->solid();
        return $box;
    }

    // ─── GRID ───────────────────────────────────────────────────

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new MovieDownload());
        $grid->model()->orderBy('created_at', 'desc');

        $grid->perPages([10, 20, 50, 100, 200, 500]);

        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('title', 'Title');
            $filter->like('url', 'URL');
            $filter->equal('status', 'Status')->select([
                'complete' => 'Complete',
                'Completed' => 'Completed',
                'Pending' => 'Pending',
                'In Progress' => 'In Progress',
                'Failed' => 'Failed',
            ]);
            $filter->equal('user_id', 'User ID');
            $filter->equal('movie_model_id', 'Movie ID');
            $filter->like('genre', 'Genre');
            $filter->like('vj', 'VJ');
            $filter->between('created_at', 'Downloaded At')->datetime();
        });

        $grid->quickSearch('title', 'url', 'genre', 'vj', 'description');

        // Thumbnail
        $grid->column('thumbnail_url', __('Thumb'))
            ->display(function ($url) {
                if (empty($url)) return '<span class="text-muted">-</span>';
                return "<img src='{$url}' style='width:40px;height:55px;object-fit:cover;border-radius:4px;' />";
            })->width(60);

        $grid->column('title', __('Title'))
            ->display(function ($title) {
                $display = mb_strlen($title) > 45 ? mb_substr($title, 0, 45) . '...' : $title;
                $display = str_replace('UGFLIX-', '', $display);
                return $display;
            })->sortable()->width(280);

        $grid->column('user_id', __('User'))
            ->display(function ($user_id) {
                if (empty($user_id) || $user_id == 0) {
                    return '<span class="label label-default">Guest</span>';
                }
                if ($this->user) {
                    $name = mb_strlen($this->user->name) > 20 ? mb_substr($this->user->name, 0, 20) . '..' : $this->user->name;
                    return "<a href='users/{$user_id}'>{$name}</a>";
                }
                return "<span class='text-muted'>#{$user_id}</span>";
            })->sortable();

        $grid->column('movie_model_id', __('Movie'))
            ->display(function ($id) {
                if (empty($id)) return '-';
                return "<a href='movies-movies/{$id}' class='label label-primary'>#{$id}</a>";
            })->sortable();

        $grid->column('genre', __('Genre'))
            ->display(function ($genre) {
                if (empty($genre)) return '-';
                $genre = mb_strlen($genre) > 15 ? mb_substr($genre, 0, 15) . '..' : $genre;
                return "<span class='label label-default'>{$genre}</span>";
            })->sortable()
            ->filter([
                'Series' => 'Series',
                'Action' => 'Action',
                'Romance' => 'Romance',
                'Drama' => 'Drama',
                'Comedy' => 'Comedy',
                'Thriller' => 'Thriller',
                'Horror' => 'Horror',
            ]);

        $grid->column('vj', __('VJ'))
            ->display(function ($vj) {
                return $vj ?: '-';
            })->sortable()
            ->filter('like');

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                $s = strtolower($status ?? '');
                if ($s === 'complete' || $s === 'completed') {
                    return '<span class="label label-success">Complete</span>';
                } elseif ($s === 'failed') {
                    return '<span class="label label-danger">Failed</span>';
                } elseif ($s === 'in progress' || $s === 'downloading') {
                    return '<span class="label label-warning">In Progress</span>';
                } elseif ($s === 'pending') {
                    return '<span class="label label-info">Pending</span>';
                }
                return '<span class="label label-default">' . ($status ?: 'Unknown') . '</span>';
            })->sortable()
            ->filter([
                'complete' => 'Complete',
                'Completed' => 'Completed',
                'Pending' => 'Pending',
                'In Progress' => 'In Progress',
                'Failed' => 'Failed',
            ]);

        $grid->column('file_size', __('Size'))
            ->display(function ($size) {
                return $size ?: '-';
            })->sortable();

        $grid->column('download_duration', __('Duration'))
            ->display(function ($dur) {
                if (empty($dur) || $dur <= 0) {
                    // Fallback: calculate from timestamps
                    if ($this->download_completed_at && $this->download_started_at) {
                        $dur = strtotime($this->download_completed_at) - strtotime($this->download_started_at);
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
                }
                if ($dur < 60) return $dur . 's';
                if ($dur < 3600) return round($dur / 60, 1) . 'm';
                return round($dur / 3600, 1) . 'h';
            })->sortable();

        $grid->column('download_progress', __('Progress'))
            ->display(function ($prog) {
                if ($prog === null || $prog === '') return '-';
                $pct = intval($prog);
                $color = $pct >= 100 ? 'success' : ($pct >= 50 ? 'info' : 'warning');
                return "<div class='progress' style='margin:0;min-width:60px;height:18px;'>"
                    . "<div class='progress-bar progress-bar-{$color}' style='width:{$pct}%;line-height:18px;font-size:11px;'>{$pct}%</div>"
                    . "</div>";
            });

        $grid->column('url', __('URL'))
            ->display(function ($url) {
                if (empty($url)) return '-';
                return "<a href='{$url}' target='_blank' title='" . htmlspecialchars($url) . "'><i class='fa fa-play-circle'></i> Play</a>";
            })->width(80);

        $grid->column('created_at', __('Downloaded'))
            ->display(function ($date) {
                if (!$date) return '-';
                $carbon = Carbon::parse($date);
                $diff = $carbon->diffForHumans();
                return "<span title='" . $carbon->format('Y-m-d H:i:s') . "'>{$diff}</span>";
            })->sortable();

        // Hidden but available columns
        $grid->column('episode_number', __('Episode'))->sortable()->hide();
        $grid->column('is_premium', __('Premium'))->hide();
        $grid->column('is_first_episode', __('First Ep'))->hide();
        $grid->column('content_type', __('Content Type'))->hide();
        $grid->column('content_is_video', __('Is Video'))->hide();
        $grid->column('error_message', __('Error'))->hide();
        $grid->column('local_video_link', __('Local Path'))->hide();
        $grid->column('watch_progress', __('Watch Progress'))->hide();
        $grid->column('image_url', __('Image URL'))->hide();
        $grid->column('local_image_url', __('Local Image'))->hide();
        $grid->column('description', __('Description'))->hide();
        $grid->column('download_started_at', __('Started At'))->hide();
        $grid->column('download_completed_at', __('Completed At'))->hide();

        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            $actions->disableEdit();
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
        $show = new Show(MovieDownload::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('title', __('Title'));
        $show->field('url', __('URL'))->as(function ($url) {
            return "<a href='{$url}' target='_blank'>{$url}</a>";
        })->unescape();
        $show->field('status', __('Status'));
        $show->field('file_size', __('File Size'));
        $show->field('download_duration', __('Download Duration'))->as(function ($dur) {
            if (!$dur) return '-';
            if ($dur < 60) return $dur . ' seconds';
            if ($dur < 3600) return round($dur / 60, 1) . ' minutes';
            return round($dur / 3600, 1) . ' hours';
        });
        $show->field('download_progress', __('Progress'));
        $show->field('watch_progress', __('Watch Progress'));

        $show->divider();
        $show->field('user_id', __('User ID'));
        $show->field('movie_model_id', __('Movie ID'));

        $show->divider();
        $show->field('genre', __('Genre'));
        $show->field('vj', __('VJ'));
        $show->field('episode_number', __('Episode Number'));
        $show->field('is_first_episode', __('Is First Episode'));
        $show->field('content_type', __('Content Type'));
        $show->field('content_is_video', __('Content Is Video'));
        $show->field('is_premium', __('Is Premium'));

        $show->divider();
        $show->field('thumbnail_url', __('Thumbnail'))->image();
        $show->field('image_url', __('Image URL'));
        $show->field('description', __('Description'));

        $show->divider();
        $show->field('download_started_at', __('Download Started'));
        $show->field('download_completed_at', __('Download Completed'));
        $show->field('local_video_link', __('Local Video Link'));
        $show->field('error_message', __('Error Message'));

        $show->divider();
        $show->field('created_at', __('Created'));
        $show->field('updated_at', __('Updated'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new MovieDownload());

        $form->text('title', __('Title'));
        $form->text('url', __('URL'));
        $form->number('user_id', __('User ID'));
        $form->number('movie_model_id', __('Movie ID'));
        $form->select('status', __('Status'))->options([
            'Pending' => 'Pending',
            'In Progress' => 'In Progress',
            'complete' => 'Complete',
            'Failed' => 'Failed',
        ]);
        $form->text('file_size', __('File Size'));
        $form->number('download_duration', __('Download Duration (seconds)'));
        $form->text('download_progress', __('Download Progress'));
        $form->text('watch_progress', __('Watch Progress'));
        $form->textarea('error_message', __('Error Message'));
        $form->text('local_video_link', __('Local Video Link'));
        $form->datetime('download_started_at', __('Download Started'));
        $form->datetime('download_completed_at', __('Download Completed'));

        $form->divider('Media Info');
        $form->text('image_url', __('Image URL'));
        $form->text('local_image_url', __('Local Image URL'));
        $form->text('thumbnail_url', __('Thumbnail URL'));
        $form->textarea('description', __('Description'));
        $form->text('genre', __('Genre'));
        $form->text('vj', __('VJ'));
        $form->text('content_type', __('Content Type'));
        $form->text('content_is_video', __('Content Is Video'));
        $form->text('is_premium', __('Is Premium'));
        $form->text('episode_number', __('Episode Number'));
        $form->text('is_first_episode', __('Is First Episode'));

        return $form;
    }
}
