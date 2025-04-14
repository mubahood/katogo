<?php

use App\Models\Gen;
use App\Models\MovieModel;
use App\Models\Utils;
use Dflydev\DotAccessData\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/* Route::get('/', function () {
    return die('welcome');
});
Route::get('/home', function () {
    return die('welcome home');
});
*/


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
Route::get('download-to-new-server', function () {

    Utils::get_remote_movies_links_3();

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
