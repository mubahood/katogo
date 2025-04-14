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

        /* foreach (School::all() as $key => $value) {
            $value->name = html_entity_decode($value->name, ENT_QUOTES, 'UTF-8');
            $value->save();
        } */

        $u = Admin::user();
        $company = Company::find($u->company_id);

        $movies = MovieModel::where([
        ])->get();
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
                    $box = new Box('Schools ('.$with_email.')', '<h3 style="text-align:right; margin: 0; font-size: 40px; font-weight: 800" >' . $count . '</h3>');
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
