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
        
        // ENHANCED INTELLIGENT SEARCH ENGINE
        if ($request->filled('search')) {
            $searchTerm = trim($request->get('search'));
            $isLiveSearch = $request->get('live_search', false); // Check if this is live search
            $perPage = $isLiveSearch ? 25 : $request->get('per_page', 21);
            
            // Words to ignore when breaking down search
            $ignoreWords = ['the', 'a', 'an', 'of', 'in', 'on', 'at', 'for', 'and', 'that', 'with', 'to', 'is', 'are', 'was', 'were'];
            
            // Array to store found movie IDs with relevance scores
            $movieScores = [];
            
            // ===== PHASE 1: SEARCH MOVIES (type = 'Movie') =====
            
            // 1.1: Full search keyword in movie titles
            $fullMatchMovies = MovieModel::where('type', 'Movie')
                ->where('title', 'LIKE', '%' . $searchTerm . '%')
                ->pluck('id')->toArray();
            foreach ($fullMatchMovies as $id) {
                // Highest score for full match
                $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + 1000 : 1000;
            }
            
            // 1.2: Break down by removing last word
            $words = explode(' ', $searchTerm);
            if (count($words) > 1) {
                $tempWords = $words;
                while (count($tempWords) > 0) {
                    array_pop($tempWords); // Remove last word
                    
                    // Skip if only ignored words remain
                    $validWords = array_filter($tempWords, function($word) use ($ignoreWords) {
                        return !in_array(strtolower($word), $ignoreWords);
                    });
                    
                    if (empty($validWords)) {
                        break;
                    }
                    
                    $searchPhrase = implode(' ', $validWords);
                    $matches = MovieModel::where('type', 'Movie')
                        ->where('title', 'LIKE', '%' . $searchPhrase . '%')
                        ->pluck('id')->toArray();
                    
                    foreach ($matches as $id) {
                        $score = 500 / count($words); // Lower score for partial match
                        $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + $score : $score;
                    }
                }
            }
            
            // 1.3: Break down by removing first word
            if (count($words) > 1) {
                $tempWords = $words;
                while (count($tempWords) > 0) {
                    array_shift($tempWords); // Remove first word
                    
                    // Skip if only ignored words remain
                    $validWords = array_filter($tempWords, function($word) use ($ignoreWords) {
                        return !in_array(strtolower($word), $ignoreWords);
                    });
                    
                    if (empty($validWords)) {
                        break;
                    }
                    
                    $searchPhrase = implode(' ', $validWords);
                    $matches = MovieModel::where('type', 'Movie')
                        ->where('title', 'LIKE', '%' . $searchPhrase . '%')
                        ->pluck('id')->toArray();
                    
                    foreach ($matches as $id) {
                        $score = 400 / count($words); // Lower score for partial match
                        $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + $score : $score;
                    }
                }
            }
            
            // ===== PHASE 2: SEARCH SERIES (type = 'Series', is_first_episode = 'Yes') =====
            
            // 2.1: Full search keyword in series titles
            $fullMatchSeries = MovieModel::where('type', 'Series')
                ->where('is_first_episode', 'Yes')
                ->where('title', 'LIKE', '%' . $searchTerm . '%')
                ->pluck('id')->toArray();
            foreach ($fullMatchSeries as $id) {
                $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + 1000 : 1000;
            }
            
            // 2.2: Break down by removing last word for Series
            if (count($words) > 1) {
                $tempWords = $words;
                while (count($tempWords) > 0) {
                    array_pop($tempWords);
                    
                    $validWords = array_filter($tempWords, function($word) use ($ignoreWords) {
                        return !in_array(strtolower($word), $ignoreWords);
                    });
                    
                    if (empty($validWords)) {
                        break;
                    }
                    
                    $searchPhrase = implode(' ', $validWords);
                    $matches = MovieModel::where('type', 'Series')
                        ->where('is_first_episode', 'Yes')
                        ->where('title', 'LIKE', '%' . $searchPhrase . '%')
                        ->pluck('id')->toArray();
                    
                    foreach ($matches as $id) {
                        $score = 500 / count($words);
                        $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + $score : $score;
                    }
                }
            }
            
            // 2.3: Break down by removing first word for Series
            if (count($words) > 1) {
                $tempWords = $words;
                while (count($tempWords) > 0) {
                    array_shift($tempWords);
                    
                    $validWords = array_filter($tempWords, function($word) use ($ignoreWords) {
                        return !in_array(strtolower($word), $ignoreWords);
                    });
                    
                    if (empty($validWords)) {
                        break;
                    }
                    
                    $searchPhrase = implode(' ', $validWords);
                    $matches = MovieModel::where('type', 'Series')
                        ->where('is_first_episode', 'Yes')
                        ->where('title', 'LIKE', '%' . $searchPhrase . '%')
                        ->pluck('id')->toArray();
                    
                    foreach ($matches as $id) {
                        $score = 400 / count($words);
                        $movieScores[$id] = isset($movieScores[$id]) ? $movieScores[$id] + $score : $score;
                    }
                }
            }
            
            // ===== PHASE 3: FETCH MOVIES BY IDs =====
            if (empty($movieScores)) {
                // No results found, return empty
                $response = [
                    'items' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page'     => $perPage,
                        'total'        => 0,
                        'last_page'    => 1,
                    ]
                ];
                return $this->success($response, "No movies found.");
            }
            
            // ===== PHASE 4: SORT BY RELEVANCE =====
            // Sort by score (highest first)
            arsort($movieScores);
            $sortedIds = array_keys($movieScores);
            
            // Limit results
            if ($isLiveSearch) {
                $sortedIds = array_slice($sortedIds, 0, 25);
            }
            
            // Fetch movies in the order of relevance
            $movies = MovieModel::select(['id', 'title', 'url', 'thumbnail_url', 'description', 'year', 'rating', 'genre', 'type', 'category', 'actor', 'vj', 'is_premium'])
                ->whereIn('id', $sortedIds)
                ->get()
                ->keyBy('id');
            
            // Reorder according to relevance score
            $orderedMovies = collect();
            foreach ($sortedIds as $id) {
                if (isset($movies[$id])) {
                    $orderedMovies->push($movies[$id]);
                }
            }
            
            // Paginate for full search
            if (!$isLiveSearch) {
                $currentPage = $request->get('page', 1);
                $orderedMovies = $orderedMovies->forPage($currentPage, $perPage);
                $totalResults = count($sortedIds);
                $lastPage = ceil($totalResults / $perPage);
            } else {
                $currentPage = 1;
                $totalResults = $orderedMovies->count();
                $lastPage = 1;
            }
            
            $movieIds = $orderedMovies->pluck('id')->toArray();
            
            // Get views and likes for results
            $views = MovieView::select('movie_model_id', \DB::raw('count(*) as total'))
                ->whereIn('movie_model_id', $movieIds)
                ->groupBy('movie_model_id')
                ->pluck('total', 'movie_model_id');
            $likes = MovieLike::select('movie_model_id', \DB::raw('count(*) as total'))
                ->whereIn('movie_model_id', $movieIds)
                ->groupBy('movie_model_id')
                ->pluck('total', 'movie_model_id');
                
            $results = $orderedMovies->map(function ($movie) use ($views, $likes) {
                $movie->views_count = $views[$movie->id] ?? 0;
                $movie->likes_count = $likes[$movie->id] ?? 0;
                return $movie;
            });
            
            $response = [
                'items' => $results,
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page'     => $perPage,
                    'total'        => $totalResults,
                    'last_page'    => $lastPage,
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

    /**
     * Get a random active movie for landing page video background
     * This endpoint is publicly accessible (no authentication required)
     */
    public function random_movie(Request $request)
    {
        try {
            // Get a random active movie with video URL (Movies only)
            $movie = MovieModel::query()
                ->select([
                    'id', 'title', 'url', 'firebase_video_url', 'external_url', 
                    'thumbnail_url', 'image_url', 'description', 'year', 
                    'rating', 'genre', 'type', 'category', 'actor', 'vj'
                ])
                ->where('status', 'Active')
                ->where('type', 'Movie')
                ->whereNotNull('url')
                ->where('url', '!=', '')
                ->where('content_is_video', 'Yes')
                ->inRandomOrder()
                ->first();

            if (!$movie) {
                // Fallback: get any movie with video content (Movies only)
                $movie = MovieModel::query()
                    ->select([
                        'id', 'title', 'url', 'firebase_video_url', 'external_url',
                        'thumbnail_url', 'image_url', 'description', 'year', 
                        'rating', 'genre', 'type', 'category', 'actor', 'vj'
                    ])
                    ->where('type', 'Movie')
                    ->whereNotNull('url')
                    ->where('url', '!=', '')
                    ->inRandomOrder()
                    ->first();
            }

            if (!$movie) {
                return $this->error("No movies available", 404);
            }

            // Prepare video URL - prefer Firebase URL, then regular URL
            $videoUrl = null;
            if (!empty($movie->firebase_video_url)) {
                $videoUrl = $movie->firebase_video_url;
            } elseif (!empty($movie->url)) {
                $videoUrl = $movie->url;
            } elseif (!empty($movie->external_url)) {
                $videoUrl = $movie->external_url;
            }

            // Format response for landing page
            $response = [
                'id' => $movie->id,
                'title' => $movie->title ?? 'Unknown Movie',
                'description' => $movie->description ?? 'No description available',
                'video_url' => $videoUrl,
                'thumbnail_url' => $movie->thumbnail_url,
                'image_url' => $movie->image_url,
                'year' => $movie->year,
                'rating' => $movie->rating,
                'genre' => $movie->genre,
                'type' => $movie->type ?? 'Movie',
                'category' => $movie->category,
                'actor' => $movie->actor,
                'vj' => $movie->vj
            ];

            return $this->success($response, "Random movie retrieved successfully.");

        } catch (\Exception $e) {
            return $this->error("Failed to retrieve random movie: " . $e->getMessage(), 500);
        }
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

        // Smart related movies algorithm - unlimited for series, max 30 for movies
        $limit = $movie->type == 'Series' ? 999 : 30;
        $relatedMovies = $this->getRelatedMovies($movie, $limit);

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
     * Enhanced smart algorithm to find related movies
     * Uses advanced scoring system similar to search algorithm
     * Priority: Same series > Genre match > VJ match > Title similarity > Actor > Same year > Same type
     * Max: Unlimited for series, 30 for movies
     */
    private function getRelatedMovies($movie, $limit = 30)
    {
        $movieScores = [];
        $isSeries = $movie->type == 'Series';
        
        // ===== PHASE 1: SERIES EPISODES (UNLIMITED) =====
        if ($isSeries && !empty($movie->category)) {
            $seriesMovies = MovieModel::where('type', 'Series')
                ->where('category', $movie->category)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->get();
            
            foreach ($seriesMovies as $sm) {
                $movieScores[$sm->id] = ['movie' => $sm, 'score' => 10000]; // Highest priority
            }
        }
        
        // ===== PHASE 2: EXACT GENRE MATCH (High Priority) =====
        if (!empty($movie->genre)) {
            $genreMovies = MovieModel::where('genre', $movie->genre)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(50) // Get more candidates
                ->get();
            
            foreach ($genreMovies as $gm) {
                $movieScores[$gm->id] = ['movie' => $gm, 'score' => 5000];
            }
        }
        
        // ===== PHASE 3: SAME VJ/DIRECTOR (High Priority) =====
        if (!empty($movie->vj) || !empty($movie->director)) {
            $director = !empty($movie->vj) ? $movie->vj : $movie->director;
            $directorMovies = MovieModel::where(function($query) use ($director) {
                    $query->where('vj', 'LIKE', '%' . $director . '%')
                          ->orWhere('director', 'LIKE', '%' . $director . '%');
                })
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(40)
                ->get();
            
            foreach ($directorMovies as $dm) {
                $movieScores[$dm->id] = ['movie' => $dm, 'score' => 4000];
            }
        }
        
        // ===== PHASE 4: ADVANCED TITLE SIMILARITY (Similar to Search Algorithm) =====
        $titleWords = $this->extractSignificantWords($movie->title);
        if (count($titleWords) > 0) {
            // 4.1: Full phrase match
            $fullPhrase = implode(' ', $titleWords);
            $fullMatches = MovieModel::where('title', 'LIKE', '%' . $fullPhrase . '%')
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(30)
                ->get();
            
            foreach ($fullMatches as $fm) {
                $movieScores[$fm->id] = ['movie' => $fm, 'score' => 3000];
            }
            
            // 4.2: Individual word matches (progressive breakdown)
            if (count($titleWords) > 1) {
                $tempWords = $titleWords;
                while (count($tempWords) > 0) {
                    array_pop($tempWords); // Remove last word
                    if (empty($tempWords)) break;
                    
                    $searchPhrase = implode(' ', $tempWords);
                    $matches = MovieModel::where('title', 'LIKE', '%' . $searchPhrase . '%')
                        ->where('id', '!=', $movie->id)
                        ->where('status', 'Active')
                        ->whereNotIn('id', array_keys($movieScores))
                        ->limit(20)
                        ->get();
                    
                    $score = 2000 / count($titleWords);
                    foreach ($matches as $m) {
                        $movieScores[$m->id] = ['movie' => $m, 'score' => $score];
                    }
                }
            }
            
            // 4.3: Any word match
            $wordMatches = MovieModel::where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->where(function ($query) use ($titleWords) {
                    foreach ($titleWords as $word) {
                        $query->orWhere('title', 'LIKE', '%' . $word . '%');
                    }
                })
                ->limit(30)
                ->get();
            
            foreach ($wordMatches as $wm) {
                if (!isset($movieScores[$wm->id])) {
                    $movieScores[$wm->id] = ['movie' => $wm, 'score' => 1500];
                }
            }
        }
        
        // ===== PHASE 5: SAME ACTOR =====
        if (!empty($movie->actor)) {
            $actorMovies = MovieModel::where('actor', 'LIKE', '%' . $movie->actor . '%')
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(30)
                ->get();
            
            foreach ($actorMovies as $am) {
                $movieScores[$am->id] = ['movie' => $am, 'score' => 1200];
            }
        }
        
        // ===== PHASE 6: SAME YEAR =====
        if (!empty($movie->year)) {
            $yearMovies = MovieModel::where('year', $movie->year)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->limit(30)
                ->get();
            
            foreach ($yearMovies as $ym) {
                $movieScores[$ym->id] = ['movie' => $ym, 'score' => 800];
            }
        }
        
        // ===== PHASE 7: SAME TYPE (Fallback) =====
        if (count($movieScores) < $limit) {
            $typeMovies = MovieModel::where('type', $movie->type)
                ->where('id', '!=', $movie->id)
                ->where('status', 'Active')
                ->whereNotIn('id', array_keys($movieScores))
                ->inRandomOrder()
                ->limit($limit - count($movieScores))
                ->get();
            
            foreach ($typeMovies as $tm) {
                $movieScores[$tm->id] = ['movie' => $tm, 'score' => 500];
            }
        }
        
        // Sort by score (highest first)
        uasort($movieScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Extract movies and limit
        $relatedMovies = collect(array_map(function($item) {
            return $item['movie'];
        }, $movieScores))->take($limit);
        
        // Add views and likes count
        $movieIds = $relatedMovies->pluck('id')->toArray();
        $views = MovieView::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');
        $likes = MovieLike::select('movie_model_id', \DB::raw('count(*) as total'))
            ->whereIn('movie_model_id', $movieIds)
            ->groupBy('movie_model_id')
            ->pluck('total', 'movie_model_id');

        // Convert to array with numeric keys (not associative)
        $result = $relatedMovies->map(function ($m) use ($views, $likes) {
            $m->views_count = $views[$m->id] ?? 0;
            $m->likes_count = $likes[$m->id] ?? 0;
            return $m;
        })->values();
        
        // Return as array to ensure JSON encodes as array [] not object {}
        return array_values($result->toArray());
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
            'hd', 'full', 'watch', 'online', 'free', 'download', 'streaming', 'vj', 'ice', 'new'
        ];

        // Clean and split title into words
        $title = strtolower($title);
        $title = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $title); // Remove special chars
        $words = array_filter(explode(' ', $title), function($word) use ($stopWords) {
            return strlen(trim($word)) > 2 && !in_array(strtolower(trim($word)), $stopWords);
        });

        // Return first 4 most significant words for matching
        return array_slice(array_values($words), 0, 4);
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

    /**
     * Add movie to watchlist
     */
    public function add_to_watchlist(Request $request)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $request->validate([
                'movie_id' => 'required|integer|exists:movie_models,id'
            ]);

            $watchlist = \App\Models\Watchlist::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'movie_model_id' => $request->movie_id
                ],
                [
                    'status' => 'active',
                    'added_at' => now()
                ]
            );

            return $this->success([
                'watchlist_id' => $watchlist->id,
                'added' => true
            ], 'Added to watchlist');

        } catch (\Exception $e) {
            return $this->error('Failed to add to watchlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove movie from watchlist
     */
    public function remove_from_watchlist(Request $request, $movie_id)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $removed = \App\Models\Watchlist::where('user_id', $user->id)
                ->where('movie_model_id', $movie_id)
                ->delete();

            return $this->success([
                'removed' => $removed > 0
            ], $removed > 0 ? 'Removed from watchlist' : 'Movie not in watchlist');

        } catch (\Exception $e) {
            return $this->error('Failed to remove from watchlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's watchlist
     */
    public function get_watchlist(Request $request)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            $watchlist = \App\Models\Watchlist::with(['movie'])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderBy('added_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedWatchlist = $watchlist->map(function ($item) {
                $movie = $item->movie;
                if (!$movie) return null;

                return [
                    'watchlist_id' => $item->id,
                    'movie_id' => $movie->id,
                    'title' => $movie->title,
                    'thumbnail' => $movie->thumbnail_url,
                    'year' => $movie->year,
                    'type' => $movie->type,
                    'category' => $movie->category,
                    'episode_number' => $movie->episode_number,
                    'added_at' => $item->added_at->toISOString(),
                ];
            })->filter()->values();

            return $this->success([
                'items' => $formattedWatchlist,
                'total' => $watchlist->total(),
                'current_page' => $watchlist->currentPage(),
                'last_page' => $watchlist->lastPage(),
                'per_page' => $watchlist->perPage(),
            ], 'Watchlist retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to get watchlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle like/unlike for a movie
     */
    public function toggle_movie_like(Request $request)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $request->validate([
                'movie_id' => 'required|integer|exists:movie_models,id'
            ]);

            $existingLike = MovieLike::where('user_id', $user->id)
                ->where('movie_model_id', $request->movie_id)
                ->first();

            if ($existingLike) {
                // Unlike
                $existingLike->delete();
                return $this->success([
                    'liked' => false,
                    'action' => 'unliked'
                ], 'Movie unliked');
            } else {
                // Like
                MovieLike::create([
                    'user_id' => $user->id,
                    'movie_model_id' => $request->movie_id,
                    'type' => 'like'
                ]);
                return $this->success([
                    'liked' => true,
                    'action' => 'liked'
                ], 'Movie liked');
            }

        } catch (\Exception $e) {
            return $this->error('Failed to toggle like: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's liked movies
     */
    public function get_liked_movies(Request $request)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            $likes = MovieLike::with(['movie'])
                ->where('user_id', $user->id)
                ->where('type', 'like')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedLikes = $likes->map(function ($like) {
                $movie = $like->movie;
                if (!$movie) return null;

                return [
                    'like_id' => $like->id,
                    'movie_id' => $movie->id,
                    'title' => $movie->title,
                    'thumbnail' => $movie->thumbnail_url,
                    'year' => $movie->year,
                    'type' => $movie->type,
                    'category' => $movie->category,
                    'episode_number' => $movie->episode_number,
                    'liked_at' => $like->created_at->toISOString(),
                ];
            })->filter()->values();

            return $this->success([
                'items' => $formattedLikes,
                'total' => $likes->total(),
                'current_page' => $likes->currentPage(),
                'last_page' => $likes->lastPage(),
                'per_page' => $likes->perPage(),
            ], 'Liked movies retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to get liked movies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get account dashboard summary
     */
    public function get_account_dashboard(Request $request)
    {
        try {
            $user = Utils::get_user($request);
            if (!$user) {
                return $this->error('Authentication required', 401);
            }

            // Get quick stats
            $watchlistCount = \App\Models\Watchlist::where('user_id', $user->id)
                ->where('status', 'active')
                ->count();

            $likesCount = MovieLike::where('user_id', $user->id)
                ->where('type', 'like')
                ->count();

            $watchHistoryCount = MovieView::where('user_id', $user->id)
                ->distinct('movie_model_id')
                ->count();

            // Get recent activity
            $recentWatched = MovieView::with(['movie'])
                ->where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($view) {
                    $movie = $view->movie;
                    if (!$movie) return null;
                    return [
                        'movie_id' => $movie->id,
                        'title' => $movie->title,
                        'thumbnail' => $movie->thumbnail_url,
                        'progress' => floatval($view->progress),
                        'last_watched' => $view->updated_at->toISOString(),
                    ];
                })->filter()->values();

            $recentLikes = MovieLike::with(['movie'])
                ->where('user_id', $user->id)
                ->where('type', 'like')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($like) {
                    $movie = $like->movie;
                    if (!$movie) return null;
                    return [
                        'movie_id' => $movie->id,
                        'title' => $movie->title,
                        'thumbnail' => $movie->thumbnail_url,
                        'liked_at' => $like->created_at->toISOString(),
                    ];
                })->filter()->values();

            return $this->success([
                'stats' => [
                    'watchlist_count' => $watchlistCount,
                    'likes_count' => $likesCount,
                    'watch_history_count' => $watchHistoryCount,
                ],
                'recent_activity' => [
                    'recent_watched' => $recentWatched,
                    'recent_likes' => $recentLikes,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'member_since' => $user->created_at->toISOString(),
                ]
            ], 'Dashboard data retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to get dashboard data: ' . $e->getMessage(), 500);
        }
    }
}
