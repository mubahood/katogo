<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\StockSubCategory;
use App\Models\User;
use App\Models\Utils;
use Dflydev\DotAccessData\Util;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiController extends BaseController
{


    public function file_uploading(Request $r)
    {
        $path = Utils::file_upload($r->file('photo'));
        if ($path == '') {
            Utils::error("File not uploaded.");
        }
        Utils::success([
            'file_name' => $path,
        ], "File uploaded successfully.");
    }

    public function manifest(Request $r)
    {
        $take_only = ['id', 'title', 'url', 'thumbnail_url', 'description',   'genre', 'type', 'vj', 'is_premium'];
        $topMovie = MovieModel::where([
            'id' => 11350
        ])->orderBy('id', 'desc')
            ->limit(1)
            ->get($take_only);
        $unique_genres = [];
        $sql = "SELECT DISTINCT genre FROM movie_models";
        $genres = DB::select($sql);
        foreach ($genres as $key => $genre) {
            $slilts = explode(",", $genre->genre);
            foreach ($slilts as $key => $slit) {
                $slit = trim($slit);
                if (!in_array($slit, $unique_genres)) {
                    $unique_genres[] = $slit;
                }
            }
        }

        $temp_genres = $unique_genres;
        $unique_genres = [];
        //slits using /
        foreach ($temp_genres as $key => $genre) {
            $slilts = explode("/", $genre);
            foreach ($slilts as $key => $slit) {
                $slit = trim($slit);
                if (strlen($slit) < 2) {
                    continue;
                }
                if (!in_array($slit, $unique_genres)) {
                    $unique_genres[] = $slit;
                }
            }
        }

        $unique_vj = [];
        $sql = "SELECT DISTINCT vj FROM movie_models";
        $vjs = DB::select($sql);
        foreach ($vjs as $key => $vj) {
            $slilts = explode(",", $vj->vj);
            foreach ($slilts as $key => $slit) {
                $slit = trim($slit);
                //remove vj from vj
                $slit = str_replace("vj", "", $slit);
                $slit = str_replace("VJ", "", $slit);
                $slit = str_replace("Vj", "", $slit);
                $slit = str_replace("Vj", "", $slit);
                $slit = str_replace("vj", "", $slit);
                $slit = str_replace(" ", "", $slit);
                $slit = str_replace("-", "", $slit);
                if (!in_array($slit, $unique_vj)) {
                    $unique_vj[] = $slit;
                }
            }
        }


        $lists = [];

        //top movies
        $movies = MovieModel::where([
            'is_premium' => 'No',
            'status' => 'Active',
            'type' => 'Movie',
        ])->orderBy('id', 'desc')
            ->limit(100)
            ->get($take_only);

        //top take 10
        $my_list['title'] = "Top Movies";
        $my_list['movies'] = $movies->take(10);
        $lists[] = $my_list;

        if (count($movies) > 10) {
            $my_list['title'] = "For You";
            $my_list['movies'] = $movies->skip(10)->take(10);
            $lists[] = $my_list;
        }

        //trending movies
        if (count($movies) > 20) {
            $my_list['title'] = "Trending Movies";
            $my_list['movies'] = $movies->skip(20)->take(10);
            $lists[] = $my_list;
        }
        //continue watching
        if (count($movies) > 30) {
            $my_list['title'] = "Continue Watching";
            $my_list['movies'] = $movies->skip(30)->take(10);
            $lists[] = $my_list;
        }
        //latest movies
        if (count($movies) > 40) {
            $my_list['title'] = "Latest Movies";
            $my_list['movies'] = $movies->skip(40)->take(10);
            $lists[] = $my_list;
        }

        //my watch list
        if (count($movies) > 50) {
            $my_list['title'] = "My Watch List";
            $my_list['movies'] = $movies->skip(50)->take(10);
            $lists[] = $my_list;
        }

        //drama movies
        if (count($movies) > 60) {
            $my_list['title'] = "Drama Movies";
            $my_list['movies'] = $movies->skip(60)->take(10);
            $lists[] = $my_list;
        }
        //action movies
        if (count($movies) > 70) {
            $my_list['title'] = "Action Movies";
            $my_list['movies'] = $movies->skip(70)->take(10);
            $lists[] = $my_list;
        }

        //comedy movies
        if (count($movies) > 80) {
            $my_list['title'] = "Comedy Movies";
            $my_list['movies'] = $movies->skip(80)->take(10);
            $lists[] = $my_list;
        }
        //horror movies
        if (count($movies) > 90) {
            $my_list['title'] = "Horror Movies";
            $my_list['movies'] = $movies->skip(90)->take(10);
            $lists[] = $my_list;
        }
        //romantic movies
        if (count($movies) > 100) {
            $my_list['title'] = "Romantic Movies";
            $my_list['movies'] = $movies->skip(100)->take(10);
            $lists[] = $my_list;
        }
        //action movies
        if (count($movies) > 110) {
            $my_list['title'] = "Action Movies";
            $my_list['movies'] = $movies->skip(110)->take(10);
            $lists[] = $my_list;
        }

        $manifest = [
            'top_movie' => $topMovie,
            'vj' => $unique_vj,
            'genres' => $unique_genres,
            'lists' => $lists,
        ];

        Utils::success($manifest, "Listed successfully.");
        dd($manifest);
    }

    public function my_list(Request $r, $model)
    {
        /* $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        } */
        $model = "App\Models\\" . $model;
        $data = $model::where([])->limit(1000000)->get();
        Utils::success($data, "Listed successfully.");
    }

    public function get_movies(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }
        $model = "App\Models\\MovieModel";
        $data = [];
        $temp_data = $model::where([])->limit(1000000)->get();
        foreach ($temp_data as $key => $movie) {
            $view = DB::table('movie_views')->where([
                'movie_model_id' => $movie->id,
                'user_id' => $u->id,
            ])->first();
            if ($view != null) {
                $movie->watched_movie = 'Yes';
                $movie->watch_progress = $view->progress;
                $movie->max_progress = $view->max_progress;
                $movie->watch_status = $view->status;

                /*                 $movie->watch_progress = 90;
                $movie->max_progress = 100; */
            } else {
                $movie->watched_movie = 'No';
                $movie->watch_progress = 0;
                $movie->max_progress = 0;
                $movie->watch_status = '';
            }


            $liked = DB::table('movie_likes')->where('movie_model_id', $movie->id)->where('user_id', $u->id)
                ->where('status', 'Active')->first();
            if ($liked != null) {
                $movie->liked_movie = 'Yes';
            } else {
                $movie->liked_movie = 'No';
            }
            $data[] = $movie;
        }

        Utils::success($data, "Listed successfully.");
    }





    public function save_view_progress(Request $r)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }
        $movie = MovieModel::find($r->get('movie_id'));
        if ($movie == null) {
            Utils::error("Movie not found.");
        }

        $view = MovieView::where([
            'movie_model_id' => $movie->id,
            'user_id' => $u->id,
        ])->first();
        if ($view == null) {
            $view = new MovieView();
            $view->movie_model_id = $movie->id;
            $view->user_id = $u->id;
        }
        $view->progress = $r->get('progress');
        $view->max_progress = $r->get('max_progress');
        $view->status = $r->get('status');
        $view->save();
        Utils::success($view, "Progress saved successfully.");
    }
    public function my_update(Request $r, $model)
    {
        $u = Utils::get_user($r);
        if ($u == null) {
            Utils::error("Unauthonticated.");
        }
        $model = "App\Models\\" . $model;
        $object = $model::find($r->id);
        $isEdit = true;
        if ($object == null) {
            $object = new $model();
            $isEdit = false;
        }


        $table_name = $object->getTable();
        $columns = Schema::getColumnListing($table_name);
        $except = ['id', 'created_at', 'updated_at'];
        $data = $r->all();

        foreach ($data as $key => $value) {
            if (!in_array($key, $columns)) {
                continue;
            }
            if (in_array($key, $except)) {
                continue;
            }
            $object->$key = $value;
        }
        $object->company_id = $u->company_id;


        //temp_image_field
        if ($r->temp_file_field != null) {
            if (strlen($r->temp_file_field) > 1) {
                $file  = $r->file('photo');
                if ($file != null) {
                    $path = "";
                    try {
                        $path = Utils::file_upload($r->file('photo'));
                    } catch (\Exception $e) {
                        $path = "";
                    }
                    if (strlen($path) > 3) {
                        $fiel_name = $r->temp_file_field;
                        $object->$fiel_name = $path;
                    }
                }
            }
        }

        try {
            $object->save();
        } catch (\Exception $e) {
            Utils::error($e->getMessage());
        }
        $new_object = $model::find($object->id);

        if ($isEdit) {
            Utils::success($new_object, "Updated successfully.");
        } else {
            Utils::success($new_object, "Created successfully.");
        }
    }




    public function login(Request $r)
    {
        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        $user = User::where('email', $r->email)->first();
        if ($user == null) {
            Utils::error("Account not found.");
        }

        if (!password_verify($r->password, $user->password)) {
            Utils::error("Invalid password.");
        }



        $token = auth('api')->setTTL(60 * 24 * 365 * 5)->attempt([
            'id' => $user->id,
            'password' => trim($r->password),
        ]);


        if ($token == null) {
            return $this->error('Wrong credentials.');
        }
        $user->token = $token;
        $user->remember_token = $token;


        $company = Company::find($user->company_id);
        if ($company == null) {
            Utils::error("Company not found.");
        }

        Utils::success([
            'user' => $user,
            'company' => $company,
        ], "Login successful.");
    }


    public function register(Request $r)
    {



        if ($r->name == null) {
            Utils::error("First name is required.");
        }

        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $r->email)->first();
        if ($u != null) {
            Utils::error("Email is already registered.");
        }
        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        $name = $r->name;
        $names = explode(" ", $name);
        $first_name = null;
        $last_name = null;
        if (count($names) == 1) {
            $first_name = $names[0];
            $last_name = "";
        } else {
            $first_name = $names[0];
            $last_name = $names[1];
        }

        $new_user = new User();
        $new_user->first_name = $first_name;
        $new_user->last_name = $last_name;
        $new_user->username = $r->email;
        $new_user->email = $r->email;
        $new_user->password = password_hash($r->password, PASSWORD_DEFAULT);
        $new_user->name = $first_name . " " . $last_name;
        $new_user->phone_number = $r->email;
        $new_user->company_id = 1;
        $new_user->status = "Active";
        try {
            $new_user->save();
        } catch (\Exception $e) {
            Utils::error($e->getMessage());
        }

        $registered_user = User::find($new_user->id);
        if ($registered_user == null) {
            Utils::error("Failed to register user.");
        }


        //DB instert into admin_role_users
        DB::table('admin_role_users')->insert([
            'user_id' => $registered_user->id,
            'role_id' => 2,
        ]);

        Utils::success([
            'user' => $registered_user,
            'company' => Company::find(1),
        ], "Registration successful.");
    }



    public function password_reset(Request $r)
    {

        if ($r->code == null) {
            Utils::error("Secret code is required.");
        }

        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $r->email)->first();
        if ($u == null) {
            Utils::error("Account not found with $r->email.");
        }
        //check if password is provided
        if ($r->password == null) {
            Utils::error("Password is required.");
        }

        //check code
        if ($u->secret_code != $r->code) {
            Utils::error("Invalid secret code.");
        }
        //set new password
        $u->password = password_hash($r->password, PASSWORD_DEFAULT);
        $u->secret_code = null;
        $u->save();
        $u = User::find($u->id);
        Utils::success([
            'user' => $u,
            'company' => Company::find(1),
        ], "Password reset successful.");
    }


    public function request_password_reset_code(Request $r)
    {



        //check if email is provided
        if ($r->email == null) {
            Utils::error("Email is required.");
        }
        //check if email is valid
        if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
            Utils::error("Email is invalid.");
        }

        //check if email is already registered
        $u = User::where('email', $r->email)->first();
        if ($u == null) {
            Utils::error("Account not found with $r->email.");
        }
        $code = rand(100000, 999999);
        $u->secret_code = $code;
        $u->save();

        $mail_body = <<<EOD
            <p>Dear {$u->name},</p>
            <p>Your password reset code is <b><code>$code</code></b></p>
            <p>Thank you.</p>
            EOD;
        $data['email'] = $u->email;
        $date = date('Y-m-d');
        $data['subject'] = "Password Reset Code - " . env('APP_NAME');
        $data['body'] = $mail_body;
        $data['data'] = $data['body'];
        $data['name'] = $u->name;
        try {
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            return Utils::error($th->getMessage());
        }
        $u = User::find($u->id);
        Utils::success([
            'user' => $u,
        ], "Code sent successfully.");
    }
}
