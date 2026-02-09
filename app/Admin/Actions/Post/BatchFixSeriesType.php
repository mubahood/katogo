<?php

namespace App\Admin\Actions\Post;

use App\Models\SeriesMovie;
use App\Models\MovieModel;
use App\Models\Utils;
use Encore\Admin\Actions\BatchAction;
use Encore\Admin\Admin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Series Type — Detects SeriesMovie records that are actually
 * standalone Movies on munowatch (not TV series) and deletes them.
 *
 * Features a LIVE PROGRESS MODAL that processes each series one-by-one
 * via AJAX, showing real-time results in a popup dialog.
 *
 * Multi-signal analysis per series:
 *  1. Counts local episodes → if > 0, SKIP
 *  2. Resolves munowatch video ID
 *  3. Fetches preview API → multi-signal scoring
 *  4. Checks episodes/range API
 *  5. Score < 3 → confirmed Movie → DELETE
 */
class BatchFixSeriesType extends BatchAction
{
    public $name = 'Fix Series Type (Detect Movies)';

    protected $selector = '.batch-fix-series-type';

    protected const MUNOWATCH_API_BASE = 'https://munowatch.org/api';
    protected const MUNOWATCH_USER_ID  = 169464;
    protected const MUNOWATCH_JWT      = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

    // ══════════════════════════════════════════
    //  CUSTOM JS: Live Progress Modal
    // ══════════════════════════════════════════

    /**
     * Override the default script injection to use a custom progress modal
     * instead of the default single-AJAX batch handler.
     */
    protected function addScript()
    {
        $apiUrl = admin_url('api/fix-series-type-single');

        $script = <<<SCRIPT

(function ($) {
    // Inject modal HTML once
    if ($('#bfst-modal').length === 0) {
        $('body').append(
            '<div class="modal fade" id="bfst-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">' +
                '<div class="modal-dialog modal-lg" role="document">' +
                    '<div class="modal-content" style="border-radius:0;overflow:hidden;background:#0d0d1a;border:1px solid #23233a;box-shadow:0 8px 32px rgba(0,0,0,.6)">' +

                        /* ── Header ── */
                        '<div class="modal-header" style="background:#111126;color:#e2e8f0;padding:10px 16px;border:0;border-bottom:1px solid #23233a;display:flex;align-items:center;justify-content:space-between">' +
                            '<h4 class="modal-title" style="font-size:13px;font-weight:600;color:#4fc3f7;margin:0;letter-spacing:.3px">' +
                                '<i class="fa fa-cogs" style="margin-right:6px;opacity:.7"></i>FIX SERIES TYPE' +
                            '</h4>' +
                            '<span id="bfst-header-count" style="font-size:11px;color:#555;font-weight:500">0 selected</span>' +
                        '</div>' +

                        '<div class="modal-body" style="padding:0">' +

                            /* ── Stat cards row ── */
                            '<div id="bfst-summary" style="display:flex;gap:0;border-bottom:1px solid #23233a">' +
                                '<div style="flex:1;text-align:center;padding:10px 8px;border-right:1px solid #23233a">' +
                                    '<div style="font-size:20px;font-weight:700;color:#4fc3f7;line-height:1" id="bfst-total">0</div>' +
                                    '<div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;font-weight:500">Total</div>' +
                                '</div>' +
                                '<div style="flex:1;text-align:center;padding:10px 8px;border-right:1px solid #23233a">' +
                                    '<div style="font-size:20px;font-weight:700;color:#ff9800;line-height:1" id="bfst-processing">0</div>' +
                                    '<div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;font-weight:500">Queue</div>' +
                                '</div>' +
                                '<div style="flex:1;text-align:center;padding:10px 8px;border-right:1px solid #23233a">' +
                                    '<div style="font-size:20px;font-weight:700;color:#ef5350;line-height:1" id="bfst-deleted">0</div>' +
                                    '<div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;font-weight:500">Deleted</div>' +
                                '</div>' +
                                '<div style="flex:1;text-align:center;padding:10px 8px;border-right:1px solid #23233a">' +
                                    '<div style="font-size:20px;font-weight:700;color:#4caf50;line-height:1" id="bfst-kept">0</div>' +
                                    '<div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;font-weight:500">Kept</div>' +
                                '</div>' +
                                '<div style="flex:1;text-align:center;padding:10px 8px">' +
                                    '<div style="font-size:20px;font-weight:700;color:#666;line-height:1" id="bfst-skipped">0</div>' +
                                    '<div style="font-size:9px;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;font-weight:500">Skipped</div>' +
                                '</div>' +
                            '</div>' +

                            /* ── Progress bar ── */
                            '<div style="padding:10px 16px 0;border-bottom:1px solid #23233a;padding-bottom:10px">' +
                                '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">' +
                                    '<div id="bfst-current" style="font-size:11px;color:#888;min-height:14px;font-weight:500">Ready...</div>' +
                                    '<span id="bfst-progress-text" style="font-size:11px;font-weight:600;color:#4fc3f7">0 / 0</span>' +
                                '</div>' +
                                '<div style="background:#1a1a2e;height:3px;overflow:hidden">' +
                                    '<div id="bfst-progress-bar" style="height:100%;width:0%;background:#4fc3f7;transition:width 0.3s"></div>' +
                                '</div>' +
                            '</div>' +

                            /* ── Log area ── */
                            '<div id="bfst-log" style="background:#060612;color:#d4d4d4;font-family:SF Mono,Fira Code,Consolas,monospace;font-size:11px;line-height:1.7;padding:10px 16px;max-height:340px;overflow-y:auto;min-height:140px"></div>' +

                        '</div>' +

                        /* ── Footer ── */
                        '<div class="modal-footer" style="padding:8px 16px;border-top:1px solid #23233a;background:#0a0a18;display:flex;align-items:center;justify-content:space-between">' +
                            '<span id="bfst-status" style="font-size:11px;color:#555;font-weight:500">Waiting...</span>' +
                            '<button type="button" id="bfst-close-btn" disabled onclick="$(\'#bfst-modal\').modal(\'hide\');$.admin.reload();" ' +
                                'style="border:0;border-radius:0;padding:6px 16px;font-size:11px;font-weight:600;background:rgba(255,255,255,.04);color:#999;border:1px solid rgba(255,255,255,.12);cursor:pointer;letter-spacing:.3px">' +
                                'CLOSE & REFRESH</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        /* Override Bootstrap modal backdrop + dialog styles for dark theme */
        $('<style>' +
            '#bfst-modal .modal-dialog{margin-top:60px}' +
            '#bfst-modal .modal-content *{border-radius:0!important}' +
            '#bfst-modal .modal-header .close{display:none}' +
            '#bfst-modal .modal-backdrop{background:#000}' +
            '#bfst-log::-webkit-scrollbar{width:4px}' +
            '#bfst-log::-webkit-scrollbar-track{background:#060612}' +
            '#bfst-log::-webkit-scrollbar-thumb{background:#23233a}' +
            '#bfst-close-btn:hover:not(:disabled){background:rgba(79,195,247,.1)!important;color:#4fc3f7!important;border-color:#4fc3f7!important}' +
            '#bfst-close-btn:disabled{opacity:.3;cursor:not-allowed}' +
        '</style>').appendTo('head');
    }

    // Click handler
    $('{$this->selector($this->selectorPrefix)}').off('click').on('click', function() {
        var keys = $.admin.grid.selected();
        if (keys.length === 0) {
            $.admin.toastr.warning('No series selected.', '', {positionClass: 'toast-top-center'});
            return;
        }
        if (keys.length > 200) {
            $.admin.toastr.warning('Max 200 at a time (' + keys.length + ' selected).', '', {positionClass: 'toast-top-center'});
            return;
        }

        var total = keys.length;
        var processed = 0, deleted = 0, kept = 0, skipped = 0;
        $('#bfst-total').text(total);
        $('#bfst-processing').text(total);
        $('#bfst-deleted').text('0');
        $('#bfst-kept').text('0');
        $('#bfst-skipped').text('0');
        $('#bfst-header-count').text(total + ' selected');
        $('#bfst-progress-bar').css('width', '0%');
        $('#bfst-progress-text').text('0 / ' + total);
        $('#bfst-current').text('Starting analysis...');
        $('#bfst-log').html('');
        $('#bfst-close-btn').prop('disabled', true).css({'background':'rgba(255,255,255,.04)','color':'#999','border-color':'rgba(255,255,255,.12)'});
        $('#bfst-status').text('Processing...').css('color', '#ff9800');

        $('#bfst-modal').modal('show');

        function logLine(html) {
            var el = $('#bfst-log');
            el.append(html + '<br>');
            el.scrollTop(el[0].scrollHeight);
        }

        function updateProgress() {
            processed++;
            var pct = Math.round((processed / total) * 100);
            $('#bfst-progress-bar').css('width', pct + '%');
            $('#bfst-progress-text').text(processed + ' / ' + total);
            $('#bfst-processing').text(Math.max(0, total - processed));
        }

        var idx = 0;
        function processNext() {
            if (idx >= keys.length) {
                /* Done */
                $('#bfst-current').html('<span style="color:#4caf50;font-weight:600">Complete</span>');
                $('#bfst-status').text(deleted + ' deleted / ' + kept + ' kept / ' + skipped + ' skipped').css('color', '#4caf50');
                $('#bfst-close-btn').prop('disabled', false).css({'background':'#4fc3f7','color':'#0a0a18','border-color':'#4fc3f7'});
                $('#bfst-progress-bar').css('background', '#4caf50');
                logLine('<span style="color:#4caf50;font-weight:600">--- COMPLETE ---</span>');
                logLine('<span style="color:#e2e8f0">Deleted: ' + deleted + '  Kept: ' + kept + '  Skipped: ' + skipped + '  Total: ' + total + '</span>');
                return;
            }

            var seriesId = keys[idx];
            idx++;
            var num = idx;
            $('#bfst-current').html('Analyzing <span style="color:#4fc3f7;font-weight:600">#' + seriesId + '</span> (' + num + '/' + total + ')');
            logLine('<span style="color:#3a3a4a">[' + num + '/' + total + '] #' + seriesId + '</span>');

            $.ajax({
                method: 'POST',
                url: '{$apiUrl}',
                data: { _token: $.admin.token, series_id: seriesId },
                timeout: 60000,
                success: function(resp) {
                    if (!resp || !resp.action) {
                        skipped++;
                        $('#bfst-skipped').text(skipped);
                        logLine('<span style="color:#ff9800">  ! #' + seriesId + ': unexpected response</span>');
                        updateProgress();
                        processNext();
                        return;
                    }

                    var title = resp.title || '?';
                    var shortTitle = title.length > 40 ? title.substring(0, 40) + '...' : title;

                    if (resp.action === 'deleted') {
                        deleted++;
                        $('#bfst-deleted').text(deleted);
                        logLine('<span style="color:#ef5350">  DEL  #' + seriesId + ' "' + shortTitle + '" <span style="color:#c62828">DELETED</span> score=' + resp.score + '</span>');
                        if (resp.signals) logLine('<span style="color:#3a3a4a">        ' + resp.signals + '</span>');
                    } else if (resp.action === 'skipped_has_episodes') {
                        skipped++;
                        $('#bfst-skipped').text(skipped);
                        logLine('<span style="color:#ff9800">  SKIP #' + seriesId + ' "' + shortTitle + '" has ' + (resp.episode_count||0) + ' ep(s)</span>');
                    } else if (resp.action === 'skipped_no_muno_info') {
                        skipped++;
                        $('#bfst-skipped').text(skipped);
                        logLine('<span style="color:#555">  SKIP #' + seriesId + ' "' + shortTitle + '" no munowatch info</span>');
                    } else if (resp.action === 'kept_is_series') {
                        kept++;
                        $('#bfst-kept').text(kept);
                        logLine('<span style="color:#4caf50">  KEEP #' + seriesId + ' "' + shortTitle + '" confirmed series score=' + resp.score + '</span>');
                        if (resp.signals) logLine('<span style="color:#3a3a4a">        ' + resp.signals + '</span>');
                    } else if (resp.action === 'not_found') {
                        skipped++;
                        $('#bfst-skipped').text(skipped);
                        logLine('<span style="color:#555">  SKIP #' + seriesId + ' not found</span>');
                    } else if (resp.action === 'error') {
                        skipped++;
                        $('#bfst-skipped').text(skipped);
                        logLine('<span style="color:#ef5350">  ERR  #' + seriesId + ' "' + shortTitle + '" ' + (resp.error||'?') + '</span>');
                    }

                    updateProgress();
                    setTimeout(processNext, 200);
                },
                error: function(xhr) {
                    skipped++;
                    $('#bfst-skipped').text(skipped);
                    var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('HTTP ' + xhr.status);
                    logLine('<span style="color:#ef5350">  ERR  #' + seriesId + ' AJAX: ' + errMsg + '</span>');
                    updateProgress();
                    setTimeout(processNext, 500);
                }
            });
        }

        logLine('<span style="color:#4fc3f7;font-weight:600">Fix Series Type: ' + total + ' series queued</span>');
        processNext();
    });
})(jQuery);

SCRIPT;

        Admin::script($script);
    }

    // ══════════════════════════════════════════
    //  SINGLE SERIES API (called by AJAX)
    // ══════════════════════════════════════════

    /**
     * Process a single series and return JSON result.
     * Called from the dedicated API route: POST admin/api/fix-series-type-single
     */
    public static function processSingle(Request $request): array
    {
        $seriesId = (int) $request->input('series_id');

        $series = SeriesMovie::find($seriesId);
        if (!$series) {
            return ['action' => 'not_found', 'title' => '', 'series_id' => $seriesId];
        }

        $instance = new static();

        try {
            $result = $instance->analyzeAndFix($series);
            $result['title']     = $series->title ?? '(deleted)';
            $result['series_id'] = $seriesId;
            return $result;
        } catch (\Throwable $e) {
            Log::error("[BatchFixSeriesType] Exception processing #{$seriesId}: " . $e->getMessage());
            return [
                'action'    => 'error',
                'title'     => $series->title ?? '?',
                'series_id' => $seriesId,
                'error'     => $e->getMessage(),
            ];
        }
    }

    // ══════════════════════════════════════════
    //  FALLBACK: Standard batch handle()
    // ══════════════════════════════════════════

    public function retrieveModel(Request $request)
    {
        if (!$key = $request->get('_key')) {
            return false;
        }
        if (is_string($key)) {
            $key = explode(',', $key);
        }
        return SeriesMovie::find($key);
    }

    public function handle(Collection $collection, Request $request)
    {
        return $this->response()
            ->success("The progress modal should have opened. If not, please refresh and try again.")
            ->refresh();
    }

    // ══════════════════════════════════════════
    //  CORE ANALYSIS
    // ══════════════════════════════════════════

    protected function analyzeAndFix(SeriesMovie $series): array
    {
        $seriesId = $series->id;
        $localEpisodeCount = MovieModel::where('category_id', $seriesId)->count();

        if ($localEpisodeCount > 0) {
            return [
                'action'        => 'skipped_has_episodes',
                'episode_count' => $localEpisodeCount,
            ];
        }

        $videoId = $this->resolveVideoId($series);

        if (empty($videoId)) {
            if (!empty($series->series_code)) {
                $rangeResult = $this->checkEpisodesRangeApi($series->series_code, $series->series_code);
                if (!$rangeResult['has_episodes']) {
                    Log::info("[BatchFixSeriesType] #{$seriesId}: 0 local eps, no video ID, range API no episodes. Deleting.");
                    $this->deleteSeries($series);
                    return [
                        'action'  => 'deleted',
                        'score'   => 0,
                        'signals' => 'no_video_id, range_api_no_episodes, 0_local_eps',
                    ];
                }
            }
            return ['action' => 'skipped_no_muno_info'];
        }

        $previewResult = $this->fetchMunowatchPreview($videoId);

        if (!$previewResult['success']) {
            Log::info("[BatchFixSeriesType] #{$seriesId}: Preview API failed ({$previewResult['error']}). Deleting as orphan.");
            $this->deleteSeries($series);
            return [
                'action'  => 'deleted',
                'score'   => 0,
                'signals' => "preview_api_failed({$previewResult['error']}), 0_local_eps",
            ];
        }

        $preview = $previewResult['preview'];

        $scoreResult = $this->calculateSeriesScore($videoId, $preview);
        $signalStrength = $scoreResult['score'];
        $signals = $scoreResult['signals'];

        $seriesCode = $preview['series_code'] ?? $series->series_code ?? '';
        if (!empty($seriesCode) && (string)$seriesCode !== (string)$videoId) {
            $rangeResult = $this->checkEpisodesRangeApi($videoId, $seriesCode);
            if ($rangeResult['has_episodes']) {
                $signalStrength += 3;
                $signals[] = "range_api_has_episodes({$rangeResult['episode_count']})";
            } else {
                $signals[] = 'range_api_no_episodes';
            }
        }

        $signalStr = implode(', ', $signals);
        Log::info("[BatchFixSeriesType] #{$seriesId} \"{$series->title}\": score={$signalStrength}, signals=[{$signalStr}]");

        if ($signalStrength < 3) {
            $this->deleteSeries($series);
            return [
                'action'  => 'deleted',
                'score'   => $signalStrength,
                'signals' => $signalStr,
            ];
        }

        return [
            'action'  => 'kept_is_series',
            'score'   => $signalStrength,
            'signals' => $signalStr,
        ];
    }

    // ══════════════════════════════════════════
    //  SIGNAL SCORING
    // ══════════════════════════════════════════

    protected function calculateSeriesScore(string $videoId, array $preview): array
    {
        $signalStrength = 0;
        $signals = [];

        $apiVideoId  = $preview['id'] ?? $preview['vid'] ?? $videoId;
        $seriesCode  = $preview['series_code'] ?? $preview['seriesCode'] ?? '';
        $genre       = strtolower($preview['genre'] ?? '');
        $episodes    = (int)($preview['episodes'] ?? 0);
        $epState     = strtoupper($preview['episode_state'] ?? '');
        $nxtEpsId    = (int)($preview['nxt_eps_id'] ?? 0);
        $contentType = (int)($preview['category_id'] ?? 0);

        if (strpos($genre, 'series') !== false) {
            $signalStrength += 3;
            $signals[] = 'genre_series';
        }
        if ($episodes > 1) {
            $signalStrength += 3;
            $signals[] = "multi_episode({$episodes})";
        }
        if (!empty($seriesCode) && (string)$seriesCode !== (string)$apiVideoId) {
            $signalStrength += 2;
            $signals[] = "has_series_code({$seriesCode}!={$apiVideoId})";
        }
        if (in_array($epState, ['NEXT', 'PREV'])) {
            $signalStrength += 2;
            $signals[] = "episode_state({$epState})";
        }
        if ($nxtEpsId > 0 && $nxtEpsId != (int)($apiVideoId ?? 0)) {
            $signalStrength += 2;
            $signals[] = "has_nxt_eps_id({$nxtEpsId})";
        }
        if ($contentType === 5) {
            $signalStrength += 1;
            $signals[] = 'content_type_tv_series';
        }

        return ['score' => $signalStrength, 'signals' => $signals];
    }

    // ══════════════════════════════════════════
    //  MUNOWATCH API HELPERS
    // ══════════════════════════════════════════

    protected function resolveVideoId(SeriesMovie $series): ?string
    {
        if (!empty($series->external_url)) {
            if (preg_match('/preview\/v2\/(\d+)\//', $series->external_url, $m)) {
                return $m[1];
            }
        }
        if (!empty($series->munowatch_id) && is_numeric($series->munowatch_id)) {
            return $series->munowatch_id;
        }
        if (!empty($series->series_code) && is_numeric($series->series_code)) {
            return $series->series_code;
        }
        $sampleEp = MovieModel::where('category_id', $series->id)
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '')
            ->first();
        if ($sampleEp) {
            return $sampleEp->munowatch_id;
        }
        return null;
    }

    protected function fetchMunowatchPreview(string $videoId): array
    {
        $apiUrl = self::MUNOWATCH_API_BASE . '/preview/v2/' . $videoId . '/' . self::MUNOWATCH_USER_ID;
        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];
            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) return ['success' => false, 'error' => 'Empty response'];
            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'JSON parse error'];
            }
            $preview = $json['preview'] ?? $json['movie'] ?? $json['data'] ?? $json;
            return ['success' => true, 'preview' => $preview, 'api_url' => $apiUrl];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkEpisodesRangeApi(string $showId, string $seriesCode, int $season = 1): array
    {
        $apiUrl = self::MUNOWATCH_API_BASE . "/episodes/range/{$showId}/{$seriesCode}/{$season}";
        try {
            $headers = [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'User-Agent'    => 'okhttp/4.9.0',
                'Authorization' => 'Bearer ' . self::MUNOWATCH_JWT,
                'X-Api-Key'     => self::MUNOWATCH_JWT,
            ];
            $raw = Utils::get_url_with_auth($apiUrl, $headers);
            if (empty($raw)) return ['has_episodes' => false, 'episode_count' => 0];
            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }
            if (isset($json['error'])) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }
            if (!is_array($json) || empty($json) || !isset($json[0]['eps'])) {
                return ['has_episodes' => false, 'episode_count' => 0];
            }
            $total = 0;
            foreach ($json as $range) {
                if (isset($range['eps'])) {
                    if (preg_match('/(\d+)\s*-\s*(\d+)/', $range['eps'], $m)) {
                        $total += ((int)$m[2] - (int)$m[1] + 1);
                    } elseif (preg_match('/\d+/', $range['eps'], $m)) {
                        $total += (int)$m[0];
                    }
                }
            }
            return ['has_episodes' => $total > 0, 'episode_count' => $total];
        } catch (\Throwable $e) {
            Log::warning("[BatchFixSeriesType] Range API error: " . $e->getMessage());
            return ['has_episodes' => false, 'episode_count' => 0];
        }
    }

    // ══════════════════════════════════════════
    //  DELETION
    // ══════════════════════════════════════════

    protected function deleteSeries(SeriesMovie $series): void
    {
        $seriesId = $series->id;
        $title    = $series->title;

        $orphanCount = MovieModel::where('category_id', $seriesId)->count();
        if ($orphanCount > 0) {
            Log::warning("[BatchFixSeriesType] #{$seriesId} has {$orphanCount} orphan episodes — converting to Movie.");
            MovieModel::where('category_id', $seriesId)->update([
                'type'             => 'Movie',
                'category_id'      => null,
                'episode_number'   => null,
                'season_number'    => null,
                'series_title'     => null,
                'episode_title'    => null,
                'is_first_episode' => null,
            ]);
        }

        Log::info("[BatchFixSeriesType] DELETING series #{$seriesId} \"{$title}\"");
        DB::table('series_movies')->where('id', $seriesId)->delete();
    }
}
