<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MainController;
use App\Models\Gen;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\SeriesMovie;
use App\Models\TrendingNotification;
use App\Models\Utils;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Landing Site Routes
|--------------------------------------------------------------------------
*/

// Landing page (homepage)
Route::get('/', function () {
    $loggedInUser = Admin::user();
    if ($loggedInUser != null) {
        return redirect(admin_url('/dashboard'));
    }
    return app(LandingController::class)->index();
})->name('landing.index');

// Static pages
Route::get('/about', [LandingController::class, 'about'])->name('landing.about');
Route::get('/features', [LandingController::class, 'features'])->name('landing.features');

// Support pages
Route::get('/support', [LandingController::class, 'support'])->name('landing.support');
Route::get('/faq', [LandingController::class, 'faq'])->name('landing.faq');

// Contact pages
Route::get('/contact', [LandingController::class, 'contact'])->name('landing.contact');
Route::post('/contact', [LandingController::class, 'contactSubmit'])->name('landing.contact.submit');

// Legal pages
Route::get('/privacy-policy', [LandingController::class, 'privacyPolicy'])->name('landing.privacy-policy');
Route::get('/terms-of-service', [LandingController::class, 'termsOfService'])->name('landing.terms-of-service');
Route::get('/eula', [LandingController::class, 'eula'])->name('landing.eula');

/*
|--------------------------------------------------------------------------
| API and Admin Routes (existing)
|--------------------------------------------------------------------------
*/


Route::get('check-ffmpeg', function (Request $request) {
    // Path to the FFmpeg binary.
    // On cPanel, it might be in a common system path, or sometimes hosts provide a specific path.
    // If 'ffmpeg' isn't found, you might need to try common paths like '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg',
    // or check with your hosting provider for the exact path.
    $ffmpegBinary = 'ffmpeg'; // Start by trying the common system path alias

    // Use Symfony Process component (already included in Laravel) for safer execution.
    // This avoids direct shell_exec/exec which can be risky and harder to debug.
    $process = new Process([$ffmpegBinary, '-version']);
    $process->setTimeout(10); // Set a timeout to prevent hanging

    try {
        $process->run();

        // Executes the command and returns the exit code, throws an exception on error
        if (!$process->isSuccessful()) {
            // FFmpeg command failed, likely because it's not found or not executable.
            // Capture error output for debugging.
            $errorMessage = "FFmpeg command failed or not found: " . $process->getErrorOutput();
            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'is_ffmpeg_installed' => false
            ], 200); // Use 200 as it's a successful response to the check, even if FFmpeg isn't there.
        }

        // If successful, FFmpeg is installed. Get the version output.
        $output = $process->getOutput();
        $versionLine = '';
        if (preg_match('/ffmpeg version (\S+)/', $output, $matches)) {
            $versionLine = 'FFmpeg version: ' . $matches[1];
        } else {
            $versionLine = 'FFmpeg found, but version could not be parsed. Full output: ' . substr($output, 0, 200) . '...';
        }

        return response()->json([
            'status' => 'success',
            'message' => 'FFmpeg is installed and executable.',
            'is_ffmpeg_installed' => true,
            'version_info' => $versionLine,
            'full_output_snippet' => substr($output, 0, 500) . '...' // Include a snippet for more context
        ]);
    } catch (ProcessFailedException $exception) {
        // This catches exceptions if the process itself couldn't be run (e.g., command not found).
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to run FFmpeg command. It might not be installed or the path is incorrect. Error: ' . $exception->getMessage(),
            'is_ffmpeg_installed' => false
        ], 200); // Still 200 for a successful response to the check request.
    } catch (\Exception $e) {
        // Catch any other unexpected exceptions
        return response()->json([
            'status' => 'error',
            'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            'is_ffmpeg_installed' => false
        ], 500);
    }
});
Route::get('process-views', function (Request $request) {
    $views = MovieView::all();
    foreach ($views as $key => $v) {
        if ($v->movie == null) {
            echo "<br>Movie not found : => " . $v->movie_model_id;
            continue;
        }
        $v->update_views();
        echo $v->movie->views_time_count . " Secs<br>";
        continue;
    }

    die();
});
Route::get('send-notifications', function (Request $request) {

    try {
        $trending =  TrendingNotification::getTendingMovie();
    } catch (\Throwable $th) {
        //throw $th;
        echo $th->getMessage();
        die();
    }
    if ($trending == null) {
        echo 'No trending movie found';
        die();
    }
    $movie = $trending;
    if ($movie == null) {
        echo 'No movie found';
        die();
    }
    echo 'Movie found<br>';
    echo $movie->id . ' - ' . $movie->title . '<br>';
    echo $movie->url . '<br>';
    echo '<img src="' . $movie->thumbnail_url . '" width="100" height="100" alt=""><br>';
    echo 'Sending notification...<br>';
    die();
});
Route::get('fix-serries-movies', function (Request $request) {
    //where url like namzentertainment


    if (isset($_GET['id'])) {
        $id = $request->get('id');

        $series = SeriesMovie::where('id', $id)
            ->get();
    } else {



        $series = SeriesMovie::where('external_url', 'like', '%namzentertainment%')
            ->where(['is_active' => 'No'])
            ->orderBy('id', 'asc')
            ->limit(1000000)
            ->get();
    }

    //set limited time
    ini_set('memory_limit', -1);
    ini_set('max_execution_time', -1);
    ini_set('max_input_time', -1);
    ini_set('upload_max_filesize', -1);
    ini_set('post_max_size', -1);
    foreach ($series as $key => $ser) {

        $my_html = null;
        $url = $ser->external_url;

        try {
            $my_html = Utils::get_url_2($url);
        } catch (\Throwable $th) {
            //throw $th;
            echo $th->getMessage();
            echo "<hr>";
            continue;
        }

        if ($my_html == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found<br>';
            $ser->is_active = 'Failed';
            $ser->description .=  ' - Episodes ARE NULL';
            $ser->save();
            continue;
        }


        $html = str_get_html($my_html);
        //.details__title


        $episodes = [];
        $mCustomScrollbar = $html->find('.accordion__list', 0);
        if ($mCustomScrollbar == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->description .=  ' - Episodes ARE NULL';
            $ser->is_active = 'Failed';
            $ser->save();
            continue;
        }

        if ($mCustomScrollbar != null) {
            $links = $mCustomScrollbar->find('tr');


            if ($links != null) {
                $count = 0;
                foreach ($links as $key => $value) {
                    $tds = $value->find('td');
                    if ($tds == null) {
                        continue;
                    }
                    $td = $value->find('td', 0);
                    if ($td == null) {
                        continue;
                    }
                    $data_target = $value->getAttribute('data-target');
                    $ep_name = trim($td->plaintext);
                    $ep_url = $data_target;
                    $ep['title'] = $ep_name;
                    $ep['url'] = $ep_url;
                    $splits = explode(' ', $ep_name);
                    $count++;
                    $num = $count;
                    foreach ($splits as $key => $value) {
                        $_num = trim($value);
                        if (is_numeric($_num)) {
                            $num = $value;
                        }
                    }
                    $ep['number'] = $num;
                    $episodes[] = $ep;
                }
            }
        }

        if ($episodes == null) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->is_active = 'Failed';
            $ser->description .= ' - Episodes ARE NULL';
            $ser->save();
            continue;
        }
        if (count($episodes) == 0) {
            echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - not found IS NOT SERIES<br>';
            $ser->is_active = 'Failed';
            $ser->description .=  ' - Episodes not found';
            $ser->save();
            continue;
        }


        $imgObj = $html->find('.card__cover img', 0);
        if ($imgObj != null) {
            $img_url = $imgObj->getAttribute('src');
            if ($img_url != null) {
                //if not contain http
                $img_url = trim($img_url);
                if (strpos($img_url, 'http') === false) {
                    $img_url = 'https://namzentertainment.com/' . $img_url;
                }
                // $ser->thumbnail = $img_url;
            }
        }




        $serie = $ser;
        if ($episodes != null && count($episodes) > 0) {



            foreach ($episodes as $key => $value) {
                $ep_url = $value['url'];
                $ep_title = $value['title'];

                $ep = MovieModel::where([
                    'external_url' => $ep_url
                ])->first();

                if ($ep == null) {
                    $ep = MovieModel::where([
                        'url' => $ep_url
                    ])->first();
                }
                $isEdit = false;
                if ($ep != null) {
                    $isEdit = true;
                } else {
                    $isEdit = false;
                }

                if ($ep == null) {
                    $ep = new MovieModel();
                }

                $ep->title = $serie->title . ' - ' . $ep_title;
                $ep->external_url = $ep_url;
                $ep->url = $ep_url;
                $ep->category_id = $serie->id;
                $ep->category_id = $serie->id;
                $ep->category = $serie->title;
                $ep->description = $serie->description;
                $ep->thumbnail_url = $serie->thumbnail;
                $ep->content_type = 'video/mp4';
                $ep->content_is_video = 'Yes';
                $ep->content_type_processed = 'No';
                $ep->content_type_processed_time = null;
                $ep->type = 'Series';
                $ep->is_premium = 'No';
                if (isset($value['number'])) {
                    $ep->episode_number = $value['number'];
                    $ep->country = $value['number'];
                }


                if ($isEdit) {
                    echo ' - edit - ';
                } else {
                    echo ' - new - ';
                }
                //save
                try {
                    $ep->save();
                    echo $ep->id . ' - saved - ===> ' . $ep->title . "<br>";
                    echo '<a href="' . $ep->url . '" target="_blank">Watch Video => ' . $ep->url . '</a><br>';
                } catch (\Throwable $th) {
                    echo ' - error - ';
                    echo '<br>';
                    echo '<pre>';
                    print_r($th);
                    echo '</pre>';
                }
            }
        }

        echo "<hr>";
        //echo done
        echo $ser->id . ' - ' . $ser->title . ' - ' . $ser->external_url . ' - done<br>';
        $ser->is_active = 'Yes';
        $ser->description .=  ' - Episodes found';
        $ser->save();
        echo '<img src="' . $ser->thumbnail . '" width="100" height="100" alt="">';
        echo "<hr>";

        continue;
    }
    dd($series);
});

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

    /*     MovieModel::where('type','Movie')
        ->update(['content_type_processed'=>'No']); */

    $movies = MovieModel::where('url', 'like', '%movies.ug%')
        ->orderBy('id', 'asc')
        ->limit(10000)
        ->get();
    $x = 0;
    echo "<h1>Movies (" . $movies->count() . ")</h1>";

    foreach ($movies as $key => $movie) {
        $url = $movie->url;
        $segs = explode('/', $url);
        if (in_array('movies.ug', $segs)) {
            $movie->status = 'Inactive';
            $movie->content_type_processed = 'Yes';
            echo "<br>Movie not found : => " . $movie->id . " - " . $movie->title;
            $movie->save();
            continue;
        }
        continue;
        if (!in_array('https:', $segs)) {
            $movie->status = 'Inactive';
            $movie->content_type_processed = 'Yes';
            $movie->save();
            echo "<br>Movie not found : => " . $movie->id . " - " . $movie->title;
            continue;
        }
        echo "<hr> $x. ";

        $movie->verify_movie();
        if ($movie  == null) {
            continue;
        }
        $movie = MovieModel::find($movie->id);


        if ($movie  == null) {
            continue;
        }
        //echo irl
        echo $movie->id . ' - ' . $movie->title . " : <a target='_blank' href='" . $movie->url . "'>" . $movie->url . "</a><br>";
        //if has not http
        //check if  is content_is_video and display colour button
        if ($movie->content_is_video == 'Yes') {
            echo "<br><span style='color:green'>IS_VIDEO</span><br>";
            $x++;
        } else {
            echo "<span style='color:red'>NOT_VIDEO</span><br>";
            //delete movie
            // $movie->delete();
            $movie->satus = 'Inactive';
            $movie->save();
            echo "<br>deleted movie";
        }

        echo "<hr>";
        continue;
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
        ->limit(500)
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
