<?php

namespace App\Http\Controllers;

use App\Models\MovieLike;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\User;
use App\Models\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use Dotenv\Validator;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Support\Facades\DB;

class DynamicCrudController extends Controller
{
    use ApiResponser;




    public function users_list(Request $request)
    {


        $u = Utils::get_user($request);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }

        // 1) Check if user is authenticated
        $current = $u;
        if ($current == null) {
            return $this->error('User not logged in.', 401);
        }

        // 1) Check if user is authenticated
        $q = User::query();

        // 4) Incremental sync
        if ($request->filled('last_update_date')) {
            $since = Carbon::parse($request->input('last_update_date'));
            $q->where('updated_at', '>=', $since);
        }

        // 5) Full‐text search
        if ($request->filled('search')) {
            $term = $request->input('search');
            $q->where(function ($qb) use ($term) {
                $qb->where('name',     'like', "%{$term}%")
                    ->orWhere('city',   'like', "%{$term}%")
                    ->orWhere('country',   'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        }

        // 6) Exact filters (whitelist)
        foreach (['status', 'sex', 'country', 'city'] as $field) {
            if ($request->filled($field)) {
                $q->where($field, $request->input($field));
            }
        }

        // 7) Age range filter (via dob)
        if ($request->filled('age_min') || $request->filled('age_max')) {
            $today = Carbon::today();
            if ($request->filled('age_min')) {
                $maxDob = $today->copy()->subYears($request->input('age_min'));
                $q->where('dob', '<=', $maxDob);
            }
            if ($request->filled('age_max')) {
                $minDob = $today->copy()->subYears($request->input('age_max') + 1)->addDay();
                $q->where('dob', '>=', $minDob);
            }
        }

        // 8) Sorting
        $sortBy  = $request->input('sort_by', 'last_online_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $q->orderBy($sortBy, $sortDir);


        if ($request->filled('fields')) {
            $requested = explode(',', $request->input('fields'));
        }
        // $q->select($select);

        // 10) Pagination
        $perPage = $request->input('per_page', 20);
        $page    = $request->input('page', 1);
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        // 11) Return structured response
        return $this->success($paginator, 'Users retrieved successfully.');
    }


    public function save(Request $request)
    {
        $u = Utils::get_user($request);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }
        if (!$u) return $this->error("User not authenticated.");

        $modelName = $request->get('model');
        if (!$modelName) return $this->error("Missing 'model' parameter.");

        $modelClass = "\\App\\Models\\" . Str::studly($modelName);
        if (!class_exists($modelClass)) return $this->error("Model [{$modelName}] does not exist.");

        $modelInstance = new $modelClass;
        $table = $modelInstance->getTable();
        if (!Schema::hasTable($table)) return $this->error("Table [{$table}] does not exist.");

        $validColumns = Schema::getColumnListing($table);
        $recordId = $request->get('id');

        $record = $recordId ? $modelClass::find($recordId) : new $modelClass;
        if ($recordId && !$record) return $this->error("Record with ID [{$recordId}] not found.");

        $isNotForCompany = $request->query('is_not_for_company');
        if ($isNotForCompany !== 'yes' && in_array('enterprise_id', $validColumns)) {
            $record->enterprise_id = $u->enterprise_id;
        }

        $isNotForUser = $request->query('is_not_for_user');
        if ($isNotForUser !== 'yes') {
            if (in_array('administrator_id', $validColumns)) {
                $record->administrator_id = $u->id;
            } elseif (in_array('user_id', $validColumns)) {
                $record->user_id = $u->id;
            }
        }

        foreach ($request->all() as $param => $value) {
            if (in_array($param, ['model', 'id', 'is_not_for_company', 'is_not_for_user'])) continue;
            if (in_array($param, $validColumns) && $value !== null) {
                $record->{$param} = $value;
            }
        }

        try {
            $record->save();
        } catch (\Exception $e) {
            return $this->error("Failed to save record: " . $e->getMessage());
        }

        $record = $modelClass::find($record->id);
        return $this->success($record, "{$modelName} record " . ($recordId ? "updated" : "created") . " successfully.");
    }

    public function index(Request $request)
    {
        $u = Utils::get_user($request);

        if ($u == null) {
            $u = auth('api')->user();
        }

        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }
        if ($u == null) return $this->error("User not authenticated.");

        $u = Administrator::find($u->id);
        if ($u == null) return $this->error("User not authenticated.");

        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }

        $modelName = $request->get('model');
        if (!$modelName) return $this->error("Missing 'model' parameter.");

        $modelClass = "\\App\\Models\\" . Str::studly($modelName);
        if (!class_exists($modelClass)) return $this->error("Model [{$modelName}] does not exist.");

        $modelInstance = new $modelClass;
        $table = $modelInstance->getTable();
        if (!Schema::hasTable($table)) return $this->error("Table [{$table}] does not exist.");

        $validColumns = Schema::getColumnListing($table);
        $query = $modelClass::query();

        $isNotForCompany = $request->query('is_not_for_company');
        if ($isNotForCompany !== 'yes' && !$u->isRole('super-admin') && in_array('enterprise_id', $validColumns)) {
            $query->where('enterprise_id', $u->enterprise_id);
        }

        $isNotForUser = $request->query('is_not_for_user');
        if ($isNotForUser !== 'yes' && !$u->isRole('super-admin')) {
            if (in_array('administrator_id', $validColumns)) {
                $query->where('administrator_id', $u->id);
            } elseif (in_array('user_id', $validColumns)) {
                $query->where('user_id', $u->id);
            }
        }

        // check if model is MovieModel , set status =active
        if ($modelName == 'MovieModel') {
            if (
                !$request->filled('is_first_episode')
                && !$request->filled('type')
                && !$request->filled('category_id')
            ) {
                $query->where('type', 'Movie');
            }
            if ($request->filled('is_first_episode')) {
                $query->where('is_first_episode', $request->get('is_first_episode'));
                $query->where('type', 'Series');
            }
            $query->where('status', 'Active');
            // make order by created_at desc
            // add these 


            $platform_type = Utils::get_platform();

            if ($platform_type == 'ios') {
                $query->where('platform_type', 'ios');
            }


            //if type is set type to Series
            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
                //get only unique by category_id
                // $query->groupBy('category_id');
            }
        }

        $query->orderBy('id', 'desc');
        $reservedKeys = [
            'model',
            'sort_by',
            'sort_dir',
            'page',
            'per_page',
            'is_not_for_company',
            'is_not_for_user',
            'fields',

        ];
        foreach ($request->query() as $param => $value) {
            // if (in_array($param, $reservedKeys)) continue;

            if (preg_match('/^(.*)_like$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, 'LIKE', "%{$value}%");
            } /* elseif (preg_match('/^(.*)_gt$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, '>', $value);
            } elseif (preg_match('/^(.*)_lt$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, '<', $value);
            } elseif (preg_match('/^(.*)_gte$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, '>=', $value);
            } elseif (preg_match('/^(.*)_lte$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, '<=', $value);
            } elseif (in_array($param, $validColumns)) {
                $query->where($param, '=', $value);
            } */
        }

        $sortBy = $request->get('sort_by');
        $sortDir = strtolower($request->get('sort_dir', 'asc'));
        if ($sortBy && in_array($sortBy, $validColumns)) {
            if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';
            $query->orderBy($sortBy, $sortDir);
        }

        $perPage = (int) $request->get('per_page', 21);
        $results = $query->paginate($perPage);

        $fields = $request->query('fields');
        if ($request->has('fields') && is_string($fields)) {
            $fields = json_decode($fields, true);
        } elseif ($request->has('fields') && is_array($fields)) {
            $fields = $fields;
        } else {
            $fields = null;
        }

        $items = collect($results->items())->map(function ($item) use ($fields) {
            $data = $item->toArray();
            return $fields ? collect($data)->only($fields)->toArray() : $data;
        });

        $responseData = [
            'items' => $items,
            'pagination' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ]
        ];

        return $this->success($responseData, "Data retrieved successfully.");
    }

    public function delete(Request $request)
    {
        $u = Utils::get_user($request);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }
        if (!$u) return $this->error("User not authenticated.");

        $modelName = $request->get('model');
        if (!$modelName) return $this->error("Missing 'model' parameter.");

        $modelClass = "\\App\\Models\\" . Str::studly($modelName);
        if (!class_exists($modelClass)) return $this->error("Model [{$modelName}] does not exist.");

        $modelInstance = new $modelClass;
        $table = $modelInstance->getTable();
        if (!Schema::hasTable($table)) return $this->error("Table [{$table}] does not exist.");

        $recordId = $request->get('id');
        if (!$recordId) return $this->error("Missing 'id' parameter.");

        $record = $modelClass::find($recordId);
        if (!$record) return $this->error("Record with ID [{$recordId}] not found.");

        try {
            $record->delete();
        } catch (\Exception $e) {
            return $this->error("Failed to delete record: " . $e->getMessage());
        }

        return $this->success(null, "{$modelName} record with ID [{$recordId}] deleted successfully.");
    }



    public function movies(Request $request)
    {
        $fetchAll = strtoupper($request->query('FETCH_ALL')) === 'YES';
        $query = MovieModel::query();
        if ($fetchAll) {
            $query->select('*');
        } else {
            $query->select(['id', 'title', 'url', 'thumbnail_url', 'description', 'year', 'rating', 'genre', 'type', 'category', 'actor', 'vj', 'is_premium']);
        }
        // Enhanced search parameter that searches movies and series separately
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $perPage = $request->get('per_page', 21);
            
            // Search movies (top 8 results)
            $movieQuery = MovieModel::query()->select(['id', 'title', 'url', 'thumbnail_url', 'description', 'year', 'rating', 'genre', 'type', 'category', 'actor', 'vj', 'is_premium'])
                ->where('type', 'Movie')
                ->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('description', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('genre', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('category', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('actor', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('vj', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orderBy('title', 'asc')
                ->limit(8);
                
            // Search series (top 8 results, only first episodes to avoid repetition)
            $seriesQuery = MovieModel::query()->select(['id', 'title', 'url', 'thumbnail_url', 'description', 'year', 'rating', 'genre', 'type', 'category', 'actor', 'vj', 'is_premium'])
                ->where('type', 'Series')
                ->where('is_first_episode', 'Yes') // Only first episodes to avoid repetition
                ->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('description', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('genre', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('category', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('actor', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('vj', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orderBy('title', 'asc')
                ->limit(8);
                
            $movies = $movieQuery->get();
            $series = $seriesQuery->get();
            
            // Merge results alphabetically
            $mergedResults = $movies->concat($series)->sortBy('title')->take($perPage);
            
            // Convert to collection for pagination-like response
            $movieIds = $mergedResults->pluck('id')->toArray();
            
            // Get views and likes for merged results
            $views = MovieView::select('movie_model_id', \DB::raw('count(*) as total'))
                ->whereIn('movie_model_id', $movieIds)
                ->groupBy('movie_model_id')
                ->pluck('total', 'movie_model_id');
            $likes = MovieLike::select('movie_model_id', \DB::raw('count(*) as total'))
                ->whereIn('movie_model_id', $movieIds)
                ->groupBy('movie_model_id')
                ->pluck('total', 'movie_model_id');
                
            $results = $mergedResults->map(function ($movie) use ($views, $likes) {
                $movie->views_count = $views[$movie->id] ?? 0;
                $movie->likes_count = $likes[$movie->id] ?? 0;
                return $movie;
            });
            
            $response = [
                'items' => $results,
                'pagination' => [
                    'current_page' => 1,
                    'per_page'     => $perPage,
                    'total'        => $results->count(),
                    'last_page'    => 1,
                ]
            ];
            return $this->success($response, "Movies retrieved successfully.");
        }
        
        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->get('title') . '%');
        }
        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }
        if ($request->filled('genre')) {
            $query->where('genre', 'LIKE', '%' . $request->get('genre') . '%');
        }
        if ($request->filled('year')) {
            $query->where('year', $request->get('year'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->get('language'));
        }
        if ($request->filled('is_premium')) {
            $query->where('is_premium', $request->get('is_premium'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->filled('is_first_episode')) {
            $query->where('is_first_episode', $request->get('is_first_episode'));
        }

        $platform_type = Utils::get_platform();

        if ($platform_type == 'ios') {
            $query->where('platform_type', 'ios');
        }


        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        $perPage = $request->get('per_page', 21);
        $movies = $query->paginate($perPage);
        $movieIds = $movies->pluck('id')->toArray();




        $views = MovieView::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');
        $likes = MovieLike::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');
        $results = $movies->getCollection()->map(function ($movie) use ($views, $likes) {
            $movie->views_count = $views[$movie->id] ?? 0;
            $movie->likes_count = $likes[$movie->id] ?? 0;
            return $movie;
        });
        $response = [
            'items' => $results,
            'pagination' => [
                'current_page' => $movies->currentPage(),
                'per_page'     => $movies->perPage(),
                'total'        => $movies->total(),
                'last_page'    => $movies->lastPage(),
            ]
        ];
        return $this->success($response, "Movies retrieved successfully.");
    }




    public function flutterwave_payment_verification(Request $request)
    {
        $fw = FlutterWaveLog::find($request->id);
        if ($fw == null) {
            return Utils::response([
                'status' => 0,
                'message' => "Payment record not found."
            ]);
        }
        $fw->is_order_paid();
        $fw = FlutterWaveLog::find($request->id);
        if ($fw->status == 'Paid') {
            return Utils::response([
                'status' => 1,
                'message' => "Payment successful.",
                'data' => $fw
            ]);
        } else {
            return Utils::response([
                'status' => 0,
                'message' => "Payment not successful.",
                'data' => $fw
            ]);
        }
    }
    public function consultation_flutterwave_payment(Request $request)
    {
        $u = Utils::get_user($request);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }
        if ($u == null) {
            return $this->error('User not found.');
        }
        $administrator_id = $u->id;

        $u = Administrator::find($administrator_id);
        if ($u == null) {
            return $this->error('User not found.');
        }
        //check for consultation_id
        if (
            $request->consultation_id == null ||
            strlen($request->consultation_id) < 1
        ) {
            return $this->error('Consultation ID is missing.');
        }
        $consultation = Consultation::find($request->consultation_id);
        if ($consultation == null) {
            return $this->error('Consultation not found.');
        }

        //validate amount_paid
        if (
            $request->amount_paid == null ||
            strlen($request->amount_paid) < 1
        ) {
            return $this->error('Amount payable is missing.');
        }

        // amount_paid should be less than or equal to amount_paid
        if (
            $request->amount_paid > $consultation->total_due
        ) {
            return $this->error('Amount payable is greater than amount paid.');
        }

        $phone_number = Utils::prepare_phone_number($request->payment_phone_number);

        //check if phone number is valid
        if (!Utils::phone_number_is_valid($phone_number)) {
            return $this->error('Invalid phone number.');
        }

        //amount_payable should be more th 500
        if (
            $request->amount_paid < 500
        ) {
            return $this->error('Amount payable should be more than 500.');
        }

        //validate payment_method
        if (
            $request->payment_method == null ||
            strlen($request->payment_method) < 1
        ) {
            return $this->error('Payment method is missing.');
        }
        $amount = (int)($request->amount_paid);
        FlutterWaveLog::where([
            'status' => 'Pending',
            'consultation_id' => $consultation->id,
        ])->delete();


        $fw = new FlutterWaveLog();
        $fw->consultation_id = $consultation->id;
        $fw->flutterwave_payment_amount = $amount;
        $fw->status = 'Pending';
        $fw->flutterwave_payment_type = 'Consultation';
        $fw->flutterwave_payment_customer_phone_number = $phone_number;
        $fw->flutterwave_payment_status = 'Pending';
        $phone_number_type = substr($phone_number, 0, 6);


        if (
            $phone_number_type == '+25670' ||
            $phone_number_type == '+25675' ||
            $phone_number_type == '+25674'
        ) {
            $phone_number_type = 'AIRTEL';
        } else if (
            $phone_number_type == '+25677' ||
            $phone_number_type == '+25678' ||
            $phone_number_type == '+25676'
        ) {
            $phone_number_type = 'MTN';
        }

        if (
            $phone_number_type != 'MTN' &&
            $phone_number_type != 'AIRTEL'
        ) {
            return Utils::response([
                'status' => 0,
                'message' => "Phone number must be MTN or AIRTEL. ($phone_number_type)"
            ]);
        }

        $phone_number = str_replace([
            '+256'
        ], "0", $phone_number);



        try {
            $fw->uuid = Utils::generate_uuid();
            $payment_link = $fw->generate_payment_link(
                $phone_number,
                $phone_number_type,
                $amount,
                $fw->uuid
            );
            if (strlen($payment_link) < 5) {
                return Utils::response([
                    'status' => 0,
                    'message' => "Failed to generate payment link."
                ]);
            }
            $fw->flutterwave_payment_link = $payment_link;
            $fw->save();
            return Utils::response([
                'status' => 1,
                'message' => "Payment link generated successfully.",
                'data' => $fw
            ]);
        } catch (\Throwable $th) {
            return Utils::response([
                'status' => 0,
                'message' => "Failed because " . $th->getMessage()
            ]);
        }





        return $this->success($paymentRecord, $message = "Payment successful.", 1);
    }

    /**
     * Get single movie by ID with related movies
     * Used for video watch page with smart related movies algorithm
     */
    public function movie(Request $request, $id)
    {
        // Get the current user for authentication
        $u = Utils::get_user($request);
        if ($u != null) {
            $u = User::find($u->id);
            if ($u != null) {
                $u->last_online_at = now();
                $u->save();
            }
        }

        // Check if user is authenticated
        $current = $u;
        if ($current == null) {
            return $this->error('User not logged in.', 401);
        }

        // Find the main movie
        $movie = MovieModel::find($id);
        if (!$movie) {
            return $this->error('Movie not found.', 404);
        }

        // Get movie views and likes count
        $viewsCount = MovieView::where('movie_model_id', $movie->id)->count();
        $likesCount = MovieLike::where('movie_model_id', $movie->id)->count();
        
        $movie->views_count = $viewsCount;
        $movie->likes_count = $likesCount;

        // Smart related movies algorithm using title word matching
        $relatedMovies = $this->getRelatedMovies($movie, 12);

        $response = [
            'movie' => $movie,
            'related_movies' => $relatedMovies,
            'user_interactions' => [
                'has_liked' => MovieLike::where('movie_model_id', $movie->id)
                                       ->where('user_id', $current->id)
                                       ->exists(),
                'has_viewed' => MovieView::where('movie_model_id', $movie->id)
                                        ->where('user_id', $current->id)
                                        ->exists(),
            ]
        ];

        return $this->success($response, "Movie retrieved successfully.");
    }

    /**
     * Smart algorithm to find related movies based on title word matching
     * Priority: Same series > Same genre > Title word matches > Same type
     */
    private function getRelatedMovies($movie, $limit = 12)
    {
        $relatedMovies = collect();

        // 1. If it's a series, get other episodes from same series (highest priority)
        if ($movie->type == 'Series' && !empty($movie->category)) {
            $seriesMovies = MovieModel::where('type', 'Series')
                ->where('category', $movie->category)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->orderBy('episode_number', 'asc')
                ->limit(6)
                ->get();
            $relatedMovies = $relatedMovies->concat($seriesMovies);
        }

        // 2. Get movies from same genre (if not enough from series)
        if ($relatedMovies->count() < $limit && !empty($movie->genre)) {
            $genreMovies = MovieModel::where('genre', $movie->genre)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', $relatedMovies->pluck('id'))
                ->inRandomOrder()
                ->limit($limit - $relatedMovies->count())
                ->get();
            $relatedMovies = $relatedMovies->concat($genreMovies);
        }

        // 3. Smart title word matching algorithm
        if ($relatedMovies->count() < $limit) {
            $titleWords = $this->extractSignificantWords($movie->title);
            
            if (count($titleWords) > 0) {
                $wordMatchMovies = MovieModel::where('id', '!=', $movie->id)
                    ->where('status', 'Active')
                    ->whereNotIn('id', $relatedMovies->pluck('id'))
                    ->where(function ($query) use ($titleWords) {
                        foreach ($titleWords as $word) {
                            $query->orWhere('title', 'LIKE', '%' . $word . '%');
                        }
                    })
                    ->inRandomOrder()
                    ->limit($limit - $relatedMovies->count())
                    ->get();
                $relatedMovies = $relatedMovies->concat($wordMatchMovies);
            }
        }

        // 4. Fill remaining slots with same type movies (Movie/Series)
        if ($relatedMovies->count() < $limit) {
            $typeMovies = MovieModel::where('type', $movie->type)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', $relatedMovies->pluck('id'))
                ->inRandomOrder()
                ->limit($limit - $relatedMovies->count())
                ->get();
            $relatedMovies = $relatedMovies->concat($typeMovies);
        }

        // Add views and likes count to related movies
        $movieIds = $relatedMovies->pluck('id')->toArray();
        $views = MovieView::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');
        $likes = MovieLike::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');

        return $relatedMovies->map(function ($movie) use ($views, $likes) {
            $movie->views_count = $views[$movie->id] ?? 0;
            $movie->likes_count = $likes[$movie->id] ?? 0;
            return $movie;
        })->take($limit);
    }

    /**
     * Extract significant words from movie title for matching
     * Removes common words, numbers, and special characters
     */
    private function extractSignificantWords($title)
    {
        // Common words to exclude from matching
        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'movie', 'film', 'part', 'episode', 'season', '2022', '2023', '2024', '2025',
            'hd', 'full', 'watch', 'online', 'free', 'download', 'streaming'
        ];

        // Clean and split title into words
        $title = strtolower($title);
        $title = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $title); // Remove special chars
        $words = array_filter(explode(' ', $title), function($word) use ($stopWords) {
            return strlen(trim($word)) > 2 && !in_array(strtolower(trim($word)), $stopWords);
        });

        // Return first 3 most significant words for matching
        return array_slice(array_values($words), 0, 3);
    }

    /**
     * Save or update video playback progress
     */
    public function save_video_progress(Request $request)
    {
        try {
            // Get authenticated user
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            // Validate required fields
            $request->validate([
                'movie_model_id' => 'required|integer|exists:movie_models,id',
                'progress' => 'required|numeric|min:0',
                'duration' => 'required|numeric|min:0',
            ]);

            // Calculate additional fields
            $progress = floatval($request->input('progress'));
            $duration = floatval($request->input('duration'));
            $percentage = $duration > 0 ? round(($progress / $duration) * 100, 2) : 0;
            $maxProgress = max($progress, 0);

            // De-duplication guard: Avoid excessive writes if called too frequently with minimal change
            $existing = MovieView::where('movie_model_id', $request->input('movie_model_id'))
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $secondsSinceUpdate = now()->diffInSeconds($existing->updated_at);
                $progressDelta = abs($progress - floatval($existing->progress));
                if ($secondsSinceUpdate < 4 && $progressDelta < 3) {
                    // Too soon with tiny change: skip DB write and return current state
                    return $this->success([
                        'id' => $existing->id,
                        'progress' => floatval($existing->progress),
                        'max_progress' => floatval($existing->max_progress),
                        'percentage' => $duration > 0 ? round(($existing->progress / $duration) * 100, 2) : 0,
                        'status' => $existing->status,
                        'message' => 'Skipped duplicate progress update'
                    ], 'Progress unchanged');
                }
            }

            // Create or update movie view record
            $movieView = MovieView::updateOrCreate(
                [
                    'movie_model_id' => $request->input('movie_model_id'),
                    'user_id' => $user->id,
                ],
                [
                    'progress' => $progress,
                    'max_progress' => max($maxProgress, MovieView::where('movie_model_id', $request->input('movie_model_id'))
                        ->where('user_id', $user->id)->value('max_progress') ?? 0),
                    'ip_address' => $request->ip(),
                    'device' => $request->input('device', 'Unknown'),
                    'platform' => $request->input('platform', 'Web'),
                    'browser' => $request->input('browser', 'Unknown'),
                    'country' => $request->input('country', ''),
                    'city' => $request->input('city', ''),
                    'status' => $percentage >= 90 ? 'Completed' : 'Active',
                ]
            );

            return $this->success([
                'id' => $movieView->id,
                'progress' => $progress,
                'max_progress' => $movieView->max_progress,
                'percentage' => $percentage,
                'status' => $movieView->status,
                'message' => 'Video progress saved successfully'
            ], 'Progress saved');

        } catch (\Exception $e) {
            return $this->error('Failed to save progress: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get video progress for a specific movie
     */
    public function get_video_progress(Request $request, $movie_id)
    {
        try {
            // Get authenticated user
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            // Find the progress record
            $progress = MovieView::where('movie_model_id', $movie_id)
                ->where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->first();

            if (!$progress) {
                return $this->success(null, 'No progress found');
            }

            // Get movie details for duration if not stored
            $movie = MovieModel::find($movie_id);
            $duration = $progress->duration ?? ($movie ? $movie->duration : 0);

            return $this->success([
                'id' => $progress->id,
                'movie_model_id' => $progress->movie_model_id,
                'progress' => floatval($progress->progress),
                'max_progress' => floatval($progress->max_progress),
                'duration' => floatval($duration),
                'percentage' => $duration > 0 ? round(($progress->progress / $duration) * 100, 2) : 0,
                'status' => $progress->status,
                'last_watched_at' => $progress->updated_at->toISOString(),
                'device' => $progress->device,
                'platform' => $progress->platform,
                'browser' => $progress->browser,
            ], 'Progress retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to get progress: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's watch history with progress
     */
    public function get_watch_history(Request $request)
    {
        try {
            // Get authenticated user
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);

            // Get watch history with movie details
            $history = MovieView::where('user_id', $user->id)
                ->where('progress', '>', 120) // Only show videos watched for more than 2 minutes
                ->with(['movie' => function($query) {
                    $query->select(['id', 'title', 'thumbnail_url', 'year', 'type', 'category', 'episode_number']);
                }])
                ->orderBy('updated_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $items = $history->items();
            $formattedHistory = collect($items)->map(function ($view) {
                $movie = $view->movie;
                if (!$movie) return null;

                return [
                    'id' => $view->id,
                    'movie_id' => $movie->id,
                    'movie_title' => $movie->title,
                    'movie_thumbnail' => $movie->thumbnail_url,
                    'movie_year' => $movie->year,
                    'movie_type' => $movie->type,
                    'movie_category' => $movie->category,
                    'episode_number' => $movie->episode_number,
                    'progress' => floatval($view->progress),
                    'max_progress' => floatval($view->max_progress),
                    'percentage' => $view->progress && $view->duration ? 
                        round(($view->progress / $view->duration) * 100, 2) : 0,
                    'status' => $view->status,
                    'last_watched_at' => $view->updated_at->toISOString(),
                    'device' => $view->device,
                    'platform' => $view->platform,
                ];
            })->filter()->values();

            return $this->success([
                'items' => $formattedHistory,
                'total' => $history->total(),
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
            ], 'Watch history retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to get watch history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete video progress (reset progress)
     */
    public function delete_video_progress(Request $request, $movie_id)
    {
        try {
            // Get authenticated user
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            // Delete the progress record
            $deleted = MovieView::where('movie_model_id', $movie_id)
                ->where('user_id', $user->id)
                ->delete();

            if ($deleted > 0) {
                return $this->success(['deleted' => true], 'Progress deleted successfully');
            } else {
                return $this->success(['deleted' => false], 'No progress found to delete');
            }

        } catch (\Exception $e) {
            return $this->error('Failed to delete progress: ' . $e->getMessage(), 500);
        }
    }
}
