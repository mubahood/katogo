<?php

use App\Http\Controllers\ApiController;
use App\Models\Gen;
use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Models\Utils;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


/* Route::get('/', function () {
    return die('welcome');
});
Route::get('/home', function () {
    return die('welcome home');
});
*/


Route::get('process-movies', function (Request $request) {
    //https://movies.ug/videos/Leighton%20Meester-The%20Weekend%20Away%20(2022).mp4

    //set unlimited time
    ini_set('memory_limit', -1);
    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);
    //get movies that does not have http in url
    $sql = "SELECT id FROM `movie_models` WHERE `url` NOT LIKE '%http%'";
    $ids = DB::select($sql);
    $ids = collect($ids)->pluck('id')->toArray();
    $movies = MovieModel::whereIn('id', $ids)
        ->orderBy('id', 'asc')
        ->limit(200000)
        ->get();
    $x = 0;
    echo "<h1>Movies</h1>";

    foreach ($movies as $key => $movie) {
        $url = $movie->url;
        echo "<hr> $x. ";



        //echo irl
        echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . '<br>';
        //if has not http
        //check if  is content_is_video and display colour button
        if ($movie->content_is_video == 'Yes') {
            echo "<br><span style='color:green'>IS_VIDEO</span>";
            $x++;
            //check if url is contains http
            if (!str_contains($url, 'http')) {
                $url = 'https://movies.ug/' . $url;
                $movie->url = $url;
                $movie->external_url = $url;
                $movie->save();
                echo "<br>updated url to " . $url;
            }
            continue;
        } else {
            echo "<span style='color:red'>NOT_VIDEO</span>";
            //delete movie
            $movie->delete();
            echo "<br>deleted movie";
        } 
        //        $this->content_type_processed_time = Carbon::now();
        $last_time = $movie->content_type_processed_time;
        $last_time = Carbon::parse($last_time);
        $now = Carbon::now();
        $diff = $last_time->diffInMinutes($now);
        //if less than 5 minutes, continue
        if ($diff < 100) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . ' |||SKIP|||<br>';
            continue;
        }
        //chek
        if ($movie->content_is_video == 'Yes' && str_contains($url, 'http')) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . ' |||IS_ALREADY_VIDEO|||<br>';
            continue;
        }
        echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . '>>>>>CHECKING<<======<br>';

        $m = $movie->verify_movie();
        if ($m  == null) {
            echo $movie->id . ' - ' . $movie->title . " : " . $movie->url . '>>>>>NOT_VIDEO DELETED<<======<br>';
            continue;
        }
        //ECHO URL
        $url = $m->url;
        //if has not http
        if (!str_contains($url, 'http')) {
            $url = 'https://movies.ug/' . $url;
        }

        //check content_is_video and display colour button
        if ($m->content_is_video == 'Yes') {
            echo "<span style='color:green'>IS_VIDEO</span>";
        } else {
            echo "<span style='color:red'>NOT_VIDEO</span>";
        }

        echo "<a target='_blank' href='" . $url . "'>" . $url . "</a><br>";
    }
    dd('process-movies');
});
Route::get('process-series', function (Request $request) {
    $series = SeriesMovie::where([])
        ->orderBy('id', 'asc')
        ->limit(10000)
        ->get();

    //set unlimited time
    ini_set('memory_limit', -1);

    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);


    foreach ($series as $key => $ser) {
        $other_with_external_url = SeriesMovie::where([
            'external_url' => $ser->external_url,
        ])
            ->where('id', '!=', $ser->id)
            ->get();

        if ($other_with_external_url->count()  > 0) {
            foreach ($other_with_external_url as $key => $other) {
                $eps = MovieModel::where([
                    'category_id' => $other->id,
                ])
                    ->update([
                        'category_id' => $ser->id,
                    ]);
                $other->delete();
            }
        }
        $other_with_external_bu_title = SeriesMovie::where([
            'title' => $ser->title,
        ])
            ->where('id', '!=', $ser->id)
            ->get();
        if ($other_with_external_bu_title->count()  > 0) {
            foreach ($other_with_external_bu_title as $key => $other) {
                $eps = MovieModel::where([
                    'category_id' => $other->id,
                ])
                    ->update([
                        'category_id' => $ser->id,
                    ]);
                $other->delete();
            }
        }


        foreach (
            MovieModel::where([
                'category_id' => $ser->id,
            ])
                ->get() as $key => $episode
        ) {
            $episode_number = (int) $episode->episode_number;
            if ($episode_number == 0) {
                $country = (int) $episode->country;
                if ($country > 0) {
                    $episode->episode_number = $country;
                    $episode->save();
                }
            }
        }

        $episodes = MovieModel::where([
            'category_id' => $ser->id,
        ])
            ->orderBy('episode_number', 'asc')
            ->get();
        $first_episode_found = false;
        $ser->is_active = 'No';
        $ser->save();
        foreach ($episodes as $key => $episode) {
            if ($episode->episode_number != 1) {
                continue;
            }
            $episode->is_first_episode = 'Yes';
            $episode->save();
            echo $episode->id . '. - first episode found for ==>  ' . $episode->title . '<br>';
            $ser->is_active = 'Yes';
            $ser->save();
            $first_episode_found = true;
            break;
        }
        if ($first_episode_found == false) {
            echo  $ser->id . '. |||||No first episode||||| found for ==>  ' . $ser->title . '<br>';
        }
    }
    /* 
 
   "id" => 1
    "created_at" => "2024-03-12 14:06:31"
    "updated_at" => "2024-03-12 15:36:38"
    "title" => "Feng Ku The Master of Kung Fu"
    "Category" => "Action"
    "description" => "<p>Huang Fei-Hung, famous Chinese boxer, teaches his martial arts at Pao Chih Lin Institute, in Canton. Gordon is a European businessman, dealing in import and  ▶"
    "thumbnail" => "images/MV5BYzZhZjE5NDgtNDk2OS00ZGNkLWFjYjktNmY1ZmZhY2VjZjBlXkEyXkFqcGdeQXVyOTMzMDk1NTY@._V1_ (1).jpg"
    "total_seasons" => 3
    "total_episodes" => 10
    "total_views" => 249
    "total_rating" => 4
    "is_active" => "No"
    "external_url" => null
    "is_premium" => "No"*/

    dd($series);
});
Route::get('remove-dupes', function (Request $request) {

    $max = 100000;
    $recs =  MovieModel::where([
        'plays_on_google' => 'dupes',
    ])
        ->orderBy('id', 'desc')
        ->limit($max)
        ->get();


    //set unlimited time
    ini_set('memory_limit', -1);

    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    ini_set('max_input_vars', -1);

    $i = 0;

    foreach ($recs as $key => $rec) {
        if ($i > $max) {
            break;
        }
        $i++;
        if ($i > $max) {
            break;
        }
        $otherMovies = MovieModel::where([
            'url' => $rec->url
        ])
            ->where('id', '!=', $rec->id)
            ->get();
        if ($otherMovies->count() == 0) {
            die("<hr>");
            echo $i . '. NOT DUPE for : ' . $rec->title . '<br>';
            $rec->plays_on_google = 'Yes';
            die("<hr>");
            $rec->save();
            continue;
        }

        $otherMovies = MovieModel::where([
            'url' => $rec->url
        ])
            ->get();
        echo "<hr>";
        foreach ($otherMovies as $key => $dp) {
            if ($rec->id == $dp->id) {
                continue;
            }
            echo $dp->delete();
            echo $dp->id . '. ' . $dp->title . ' ===> ' . $dp->url . '<br>';
            //display thumbnaildd 
            echo '<img src="' . $dp->thumbnail_url . '" width="100" height="100" alt="">';
            echo '<br>';
        }
        continue;

        die("<br>");

        echo $i . 'dupes for ' . $rec->title . '<br>';
    }

    die('remove-dupes');

    dd('remove-dupes');
});
Route::get('manifest', function (Request $request) {
    $apiController = new ApiController();
    $apiController->manifest($request);
});
Route::get('play', function (Request $request) {
    $moviemodel = MovieModel::find($request->id);
    if ($moviemodel == null) {
        return die('Movie not found');
    }
    $newUrl = url('storage/' . $moviemodel->new_server_path);
    //html player for new and old links
    $html = '<video width="320" height="240" controls>
                <source src="' . $moviemodel->url . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
    $html .= '<br><video width="320" height="240" controls>
                <source src="' . $newUrl . '" type="video/mp4">
                Your browser does not support the video tag.
            </video>';
    echo $html;
});
Route::get('download-to-new-server-get-images', function () {
    Utils::get_remote_movies_links_4_get_images();
    die("get_remote_movies_links_4_get_images");
});
Route::get('download-to-new-server-namzentertainment', function () {
    Utils::get_remote_movies_links_namzentertainment();
    die('download-to-new-namzentertainment');
});

Route::get('download-to-new-server', function () {
    //8019

    // return  view('test');

    Utils::get_remote_movies_links_4();
    die('download-to-new-server');
    // Utils::get_remote_movies_links_3();

    dd('download-to-new-server');
    //increase the memory limit
    ini_set('memory_limit', -1);
    //increase the execution time
    ini_set('max_execution_time', -1);
    //increase the time limit
    set_time_limit(0);
    //increase the time limit
    ignore_user_abort(true);
    //die("time to download");


    $movies = MovieModel::where([
        'uploaded_to_from_google' => 'Yes',
        'downloaded_to_new_server' => 'No',
    ])
        ->orderBy('id', 'asc')
        ->limit(100)
        ->get();
    if (isset($_GET['reset'])) {
        MovieModel::where([
            'uploaded_to_from_google' => 'Yes',
        ])->update([
            'downloaded_to_new_server' => 'No',
        ]);
    }
    /* 
            $table->string('downloaded_to_new_server')->default('No');
            $table->text('new_server_path')->nullable();
            server_fail_reason
*/

    $i = 0;
    foreach ($movies as $key => $value) {
        $url = $value->url;

        $filename = time() . '-' . rand(1000000, 10000000) . '-' . rand(1000000, 10000000) . '.mp4';
        $path = public_path('storage/files/' . $filename);
        if (file_exists($path)) {
            $value->downloaded_to_new_server = 'Yes';
            $value->save();
            continue;
        }

        try {
            if ($i > 10) {
                break;
            }
            $i++;
            if (Utils::is_localhost_server()) {
                echo 'localhost server';
                die();
            }

            $value->downloaded_to_new_server = 'Yes';
            $value->new_server_path = 'files/' . $filename;
            $value->save();
            $new_link = url('storage/' . $value->new_server_path);
            echo 'downloaded to ' . $new_link . '<hr>';
            //check if directtoryy exists

            try {
                $file = file_get_contents($url);
                file_put_contents($path, $file);
                echo '<h1>Downloaded: ' . $url . '</h1>';
            } catch (\Throwable $th) {
                echo 'failed to download ' . $url . '<br>';
                echo $th->getMessage();
                die();
            }

            $d_exists = '';
            if (!file_exists(public_path('storage/files'))) {
                $d_exists = 'does not exist';
                mkdir(public_path('storage/files'));
            } else {
                $d_exists = 'exists';
            }
            echo 'directory ' . $d_exists . '<br>';

            //html player for new and old links
            $html = '<video width="100" height="120" controls>
                <source src="' . $value->url . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
            $html .= '<br><video width="100" height="120" controls>
                <source src="' . $new_link . '" type="video/mp4">
                Your browser does not support the video tag. 
            </video>';
            echo $html;
        } catch (\Throwable $th) {
            $value->downloaded_to_new_server = 'Failed';
            $value->server_fail_reason = $th->getMessage();
            $value->save();
            echo 'failed to download ' . $url . '<br>';
            echo $th->getMessage();
        }
    }
});

Route::get('sync-with-google', function () {
    Utils::download_movies_from_google();
});
Route::get('/gen-form', function () {
    die(Gen::find($_GET['id'])->make_forms());
})->name("gen-form");


Route::get('generate-class', [MainController::class, 'generate_class']);
Route::get('/gen', function () {
    die(Gen::find($_GET['id'])->do_get());
})->name("register");

Route::post('/africa', function () {
    $m = new \App\Models\AfricaTalkingResponse();
    $m->sessionId = request()->get('sessionId');
    $m->status = request()->get('status');
    $m->phoneNumber = request()->get('phoneNumber');
    $m->errorMessage = request()->get('errorMessage');
    $m->post = json_encode($_POST);
    $m->get = json_encode($_GET);
    try {
        $m->save();
    } catch (\Throwable $th) {
        //throw $th;
    }

    //change response to xml
    header('Content-type: text/plain');

    echo '<Response>
            <Play url="https://www2.cs.uic.edu/~i101/SoundFiles/gettysburg10.wav"/>
    </Response>';
    die();
});
Route::get('/make-tsv', function () {
    $exists = [];
    foreach (
        MovieModel::where([
            'uploaded_to_from_google' => 'No',
        ])->get() as $key => $value
    ) {

        //check if not contain ranslatedfilms.com and continue
        if (!(strpos($value->external_url, 'ranslatedfilms.com') !== false)) {
            continue;
        }
        $exists[] = $value->external_url;
        continue;
        //check if file exists
        // $value->url = 'videos/test.mp4';
        if ($value->url == null) continue;
        if (strlen($value->url) < 5) continue;
        $path = public_path('storage/' . $value->url);
        if (!file_exists($path)) {
            echo $value->title . ' - does not exist<br>';
            continue;
        }
        //echo $value->title . ' - do exists<br>';
        $exists[] = url('storage/' . $value->url);
    }

    //create a tsv file
    $path = public_path('storage/movies-1.tsv');
    $file = fopen($path, 'w');
    //add TsvHttpData-1.0 on top of the tsv file content
    fputcsv($file, [
        'TsvHttpData-1.0'
    ], "\t");

    //put only data in $exists
    foreach ($exists as $key => $value) {
        fputcsv($file, [
            $value
        ], "\t");
    }
    fclose($file);
    //download the file link echo
    echo '<a href="' . url('storage/movies-1.tsv') . '">Download</a>';
    die();
});
Route::get('/down', function () {
    Utils::system_boot();
});
