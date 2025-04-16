<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MovieModel;
use App\Models\School;
use App\Models\StockRecord;
use App\Models\User;
use App\Models\Utils;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;

class HomeController extends Controller
{
    public function index(Content $content)
    {


        die('This is a test');
        //set timeout to 0
        set_time_limit(0);

        //set memory limit to 512M
        ini_set('memory_limit', '512M');

        //set max execution time to 0
        ini_set('max_execution_time', 0);

        //set max input time to 0
        ini_set('max_input_time', 0);
        //set max input vars to 0
        ini_set('max_input_vars', 0);
        //set max file size to 0
        ini_set('upload_max_filesize', '0');
        ini_set('post_max_size', '0');
        //set max execution time to 0
        ini_set('max_execution_time', 0);
        //set max input time to 0
        ini_set('max_input_time', 0);

        $start_time = microtime(true);

        $max = 2000;
        $movies = MovieModel::where([
            'content_type_processed' => 'No',
        ])->orderBy('id', 'desc')
            ->limit($max)
            ->get();

        foreach ($movies as $key => $movie) {
            $movie->verify_movie();

            echo "<br>";
            echo $movie->id . ". " . $movie->title . " - " . $movie->content_type . " <b>" . $movie->content_is_video . "</b>";
            if ($movie->content_is_video != 'Yes') {
                echo " - <b>Not Video</b>";
                echo "<br>";
            }

            //display play with video 
            echo "<br>";
            // echo "<video width=\"320\" height=\"240\" controls autoplay=\"false\">";
            echo "<source src=\"" . $movie->url . "\" type=\"video/mp4\">";
            echo "Your browser does not support the video tag.";
            echo "</video>";
            echo "<br>";
            echo "<a href=" . url($movie->url) . " target=\'_blank\'>" . url($movie->url) . "</a><hr>";
        }

        echo "<br>";
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);
        $diff = $execution_time / 60;
        //hrs, mins, secs
        $hours = floor($diff / 60);
        $minutes = $diff % 60;
        $seconds = $execution_time % 60;

        echo "<br>";
        echo "Execution time: " . $hours . " hours " . $minutes . " minutes " . $seconds . " seconds";
        echo "<br>";
        echo "<br>";

        die();


        /* 
                  $table->string('content_type')->nullable();
            $table->string('content_is_video')->nullable()->default('No');
            $table->string('content_type_processed')->nullable()->default('No');
            $table->dateTime('content_type_processed_time')->nullable();

        foreach (School::all() as $key => $value) {
            $value->name = html_entity_decode($value->name, ENT_QUOTES, 'UTF-8');
            $value->save();
        } */

        $u = Admin::user();
        $company = Company::find($u->company_id);

        $movies = MovieModel::where([])->get();
        /* foreach ($movies as $key => $value) {
             dd($value);
        } 
        die(); */

        $no_downloading = MovieModel::where([
            'video_is_downloaded_to_server_status' => 'downloading',
        ])->first();

        $now_text = '-';
        if ($no_downloading != null) {
            $now_text = $no_downloading->title;
            //started 
            if ($no_downloading->video_downloaded_to_server_start_time) {
                $now_text .= ' - started at ' . $no_downloading->video_downloaded_to_server_start_time;
            }
        }

        return $content
            ->title($company->name . " - Dashboard")
            ->description('Now Downloading ' . $now_text)
            ->row(function (Row $row) {
                $row->column(3, function (Column $column) {
                    $count = number_format(School::count());
                    $with_email = number_format(School::where('registry_status', 'Yes')->count());
                    $box = new Box('Schools (' . $with_email . ')', '<h3 style="text-align:right; margin: 0; font-size: 40px; font-weight: 800" >' . $count . '</h3>');
                    $box->style('danger')
                        ->solid();
                    $column->append($box);
                });
                $row->column(3, function (Column $column) {

                    $count = number_format(MovieModel::count());
                    $box = new Box('Movies', '<h3 style="text-align:right; margin: 0; font-size: 40px; font-weight: 800" >' . $count . '</h3>');
                    $box->style('danger')
                        ->solid();
                    $column->append($box);
                });
                $row->column(3, function (Column $column) {

                    $total_sales = StockRecord::where(
                        'company_id',
                        Admin::user()->company_id
                    )
                        ->sum('total_sales');
                    $u = Admin::user();

                    $company = MovieModel::where([
                        'video_is_downloaded_to_server_status' => 'success',
                        'video_is_downloaded_to_server' => 'yes',
                    ])->count();
                    $box = new Box('Downloaded Movies', '<h3 style="text-align:right; margin: 0; font-size: 40px; font-weight: 800" >'
                        . " " . number_format($company) .
                        ' Movies</h3>');
                    $box->style('danger')
                        ->solid();
                    $column->append($box);
                });
            });
    }
}
