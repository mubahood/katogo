<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\User;
use App\Models\MovieView;           // Your "movie_views" model
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Table;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        //
        // 1) Top summaries
        //
        $totalMovies  = MovieModel::count();
        $totalUsers   = User::count();
        $totalViews   = MovieView::count();
        $totalSeconds = MovieView::sum('progress');  // seconds watched
        $totalHours   = round($totalSeconds / 3600, 2);

        $summaries = [
            ['Movies', 'film',  'blue',   admin_url('movie_models'),     number_format($totalMovies)],
            ['Users',  'users', 'green',  admin_url('users'),      number_format($totalUsers)],
            ['Views',  'eye',   'yellow', admin_url('movie-views'), number_format($totalViews)],
            ['Hours',  'clock', 'red',    admin_url('movie-views'), $totalHours . ' h'],
        ];

        //
        // 2) Four months of daily breakdown
        //
        $today = Carbon::today();
        $monthBoxes = [];
        for ($i = 3; $i >= 0; $i--) {
            $month = $today->copy()->subMonths($i);
            $label = $month->format('F Y');
            $start = $month->copy()->firstOfMonth();
            $end   = $month->copy()->endOfMonth();

            $headers = ['Day','New Users','Watch Hours'];
            $rows    = [];

            for ($d = $start; $d->lte($end); $d->addDay()) {
                $dayUsers   = User::whereDate('created_at',$d)->count();
                $daySeconds = MovieView::whereDate('created_at',$d)->sum('progress');
                $dayHours   = round($daySeconds/3600,2);

                $rows[] = [
                    $d->format('d'),
                    $dayUsers,
                    $dayHours,
                ];
            }

            $monthBoxes[] = (new Box($label, new Table($headers, $rows)))
                                ->style('info')
                                ->solid();
        }

        //
        // 3) Top 30 most‑viewed videos
        //
        $topViews = DB::table('movie_views')
            ->select('movie_models.title', DB::raw('COUNT(*) as views'))
            ->join('movie_models','movie_models.id','=','movie_views.movie_model_id')
            ->groupBy('movie_models.title')
            ->orderByDesc('views')
            ->limit(30)
            ->get();

        $topRows = [];
        foreach ($topViews as $rec) {
            $topRows[] = [
                $rec->title,
                $rec->views,
            ];
        }
        $topBox = (new Box('Top 30 Videos by Views', new Table(['Title','Views'], $topRows)))
                      ->style('primary')
                      ->solid();

        //
        // Build the dashboard
        //
        return $content
            ->title('Dashboard')
            
            // Row 1: summaries
            ->row(function(Row $row) use ($summaries) {
                foreach ($summaries as [$label,$icon,$color,$link,$value]) {
                    $row->column(3, function(Column $col) 
                        use ($label,$icon,$color,$link,$value) {
                        $col->append(
                            (new InfoBox($label,$icon,$color,$link,$value))
                                ->solid()
                        );
                    });
                }
            })
            
            // Row 2: monthly breakdown
            ->row(function(Row $row) use ($monthBoxes) {
                foreach ($monthBoxes as $box) {
                    $row->column(3, function(Column $col) use ($box) {
                        $col->append($box);
                    });
                }
            })
            
            // Row 3: top‑viewed videos (full width)
            ->row(function(Row $row) use ($topBox) {
                $row->column(12, function(Column $col) use ($topBox) {
                    $col->append($topBox);
                });
            });
    }
}
