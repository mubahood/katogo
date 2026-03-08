<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\StreamingStation;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;

class StreamingController extends Controller
{
    use ApiResponser;

    protected const PER_PAGE = 50;
    protected const MAX_PER_PAGE = 200;

    protected const STATION_LIST_FIELDS = [
        'id', 'name', 'slug', 'type', 'category', 'frequency',
        'logo_url', 'language', 'region', 'votes', 'listeners_count',
        'is_featured', 'sort_order',
    ];

    protected const STATION_DETAIL_FIELDS = [
        'id', 'name', 'slug', 'type', 'category', 'frequency',
        'description', 'logo_url', 'country', 'language', 'region',
        'website_url', 'votes', 'listeners_count', 'is_featured',
        'sort_order', 'created_at',
    ];

    /**
     * GET /api/v2/streaming/stations
     *
     * List all active stations. Filterable by type, category, featured.
     * Returns stations with their default (or first active) stream URL.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', self::PER_PAGE), self::MAX_PER_PAGE);

        $query = StreamingStation::active()
            ->select(self::STATION_LIST_FIELDS)
            ->with(['activeUrls' => function ($q) {
                $q->select(['id', 'streaming_station_id', 'url', 'label', 'format', 'quality', 'bitrate', 'is_default', 'status', 'referrer_url', 'needs_token_refresh'])
                  ->orderByDesc('is_default')
                  ->orderBy('sort_order');
            }]);

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by category
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        // Filter featured only
        if ($request->get('featured')) {
            $query->featured();
        }

        // Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('frequency', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->get('sort', 'popular');
        switch ($sort) {
            case 'name':
                $query->orderBy('name');
                break;
            case 'latest':
                $query->orderByDesc('created_at');
                break;
            case 'popular':
            default:
                $query->orderByDesc('is_featured')
                      ->orderBy('sort_order')
                      ->orderByDesc('votes');
                break;
        }

        $stations = $query->paginate($perPage);

        // Transform: attach primary stream_url for quick access
        $items = collect($stations->items())->map(function ($station) {
            $data = $station->toArray();
            $urls = $data['active_urls'] ?? [];
            $data['stream_url'] = !empty($urls) ? $urls[0]['url'] : null;
            $data['stream_format'] = !empty($urls) ? $urls[0]['format'] : null;
            $data['all_urls'] = $urls;
            unset($data['active_urls']);
            return $data;
        });

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $stations->currentPage(),
                'last_page' => $stations->lastPage(),
                'per_page' => $stations->perPage(),
                'total' => $stations->total(),
            ],
        ]);
    }

    /**
     * GET /api/v2/streaming/stations/{id}
     *
     * Get a single station with all its stream URLs.
     */
    public function show($id)
    {
        $station = StreamingStation::active()
            ->select(self::STATION_DETAIL_FIELDS)
            ->with(['streamingUrls' => function ($q) {
                $q->select(['id', 'streaming_station_id', 'url', 'label', 'format', 'quality', 'bitrate', 'cdn_provider', 'referrer_url', 'is_default', 'needs_token_refresh', 'status', 'notes'])
                  ->orderByDesc('is_default')
                  ->orderBy('sort_order');
            }])
            ->find($id);

        if (!$station) {
            return $this->error('Station not found', 404);
        }

        return $this->success($station);
    }

    /**
     * GET /api/v2/streaming/categories
     *
     * List distinct categories with station counts.
     */
    public function categories(Request $request)
    {
        $type = $request->get('type'); // tv or radio

        $query = StreamingStation::active()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count');

        if ($type) {
            $query->where('type', $type);
        }

        return $this->success($query->get());
    }

    /**
     * GET /api/v2/streaming/home
     *
     * Optimized home endpoint for the streaming section.
     * Returns featured stations + stations grouped by category for TV and Radio.
     */
    public function home()
    {
        $featured = StreamingStation::active()
            ->featured()
            ->select(self::STATION_LIST_FIELDS)
            ->with(['activeUrls' => function ($q) {
                $q->select(['id', 'streaming_station_id', 'url', 'label', 'format', 'quality', 'bitrate', 'is_default', 'referrer_url', 'needs_token_refresh'])
                  ->orderByDesc('is_default')
                  ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->orderByDesc('votes')
            ->limit(20)
            ->get();

        // Get TV channels grouped by category
        $tvChannels = StreamingStation::active()
            ->tv()
            ->select(self::STATION_LIST_FIELDS)
            ->with(['activeUrls' => function ($q) {
                $q->select(['id', 'streaming_station_id', 'url', 'label', 'format', 'quality', 'bitrate', 'is_default', 'referrer_url', 'needs_token_refresh'])
                  ->orderByDesc('is_default')
                  ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->orderByDesc('votes')
            ->get();

        // Get Radio stations grouped by category
        $radioStations = StreamingStation::active()
            ->radio()
            ->select(self::STATION_LIST_FIELDS)
            ->with(['activeUrls' => function ($q) {
                $q->select(['id', 'streaming_station_id', 'url', 'label', 'format', 'quality', 'bitrate', 'is_default', 'referrer_url', 'needs_token_refresh'])
                  ->orderByDesc('is_default')
                  ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->orderByDesc('votes')
            ->get();

        // Transform helper
        $transform = function ($stations) {
            return $stations->map(function ($station) {
                $data = $station->toArray();
                $urls = $data['active_urls'] ?? [];
                $data['stream_url'] = !empty($urls) ? $urls[0]['url'] : null;
                $data['stream_format'] = !empty($urls) ? $urls[0]['format'] : null;
                $data['all_urls'] = $urls;
                unset($data['active_urls']);
                return $data;
            });
        };

        // Group by category
        $tvByCategory = $transform($tvChannels)->groupBy('category');
        $radioByCategory = $transform($radioStations)->groupBy('category');

        // Build sections
        $tvSections = [];
        foreach ($tvByCategory as $category => $items) {
            $tvSections[] = [
                'category' => $category,
                'items' => $items->values(),
            ];
        }

        $radioSections = [];
        foreach ($radioByCategory as $category => $items) {
            $radioSections[] = [
                'category' => $category,
                'items' => $items->values(),
            ];
        }

        return $this->success([
            'featured' => $transform($featured)->values(),
            'tv_sections' => $tvSections,
            'radio_sections' => $radioSections,
            'stats' => [
                'tv_count' => StreamingStation::active()->tv()->count(),
                'radio_count' => StreamingStation::active()->radio()->count(),
            ],
        ]);
    }
}
