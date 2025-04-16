<?php

namespace App\Http\Controllers;

use App\Models\MovieLike;
use App\Models\MovieModel;
use App\Models\MovieView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Support\Facades\DB;

class DynamicCrudController extends Controller
{
    use ApiResponser;

    public function save(Request $request)
    {
        $u = auth('api')->user();
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
        $u = auth('api')->user();
        $u = Administrator::find(1);

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

        //check if model is MovieModel , set status =active
        if ($modelName == 'MovieModel') {
            $query->where('status', 'Active');
            //make order by created_at desc
            $query->orderBy('created_at', 'desc');
        }

        $reservedKeys = ['model', 'sort_by', 'sort_dir', 'page', 'per_page', 'is_not_for_company', 'is_not_for_user', 'fields'];
        foreach ($request->query() as $param => $value) {
            if (in_array($param, $reservedKeys)) continue;

            if (preg_match('/^(.*)_like$/', $param, $matches)) {
                $field = $matches[1];
                if (in_array($field, $validColumns)) $query->where($field, 'LIKE', "%{$value}%");
            } elseif (preg_match('/^(.*)_gt$/', $param, $matches)) {
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
            }
        }

        $sortBy = $request->get('sort_by');
        $sortDir = strtolower($request->get('sort_dir', 'asc'));
        if ($sortBy && in_array($sortBy, $validColumns)) {
            if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';
            $query->orderBy($sortBy, $sortDir);
        }

        $perPage = (int) $request->get('per_page', 20);
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
        $u = auth('api')->user();
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
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        $perPage = $request->get('per_page', 20);
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
}
