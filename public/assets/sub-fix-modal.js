/**
 * Subscription Fix Modal  v1
 * ──────────────────────────
 * 1) Single-row Fix Lab  – inspect + apply actions on one subscription
 * 2) Batch Fix Modal     – progress bar, live log, per-item detail pane,
 *                         final summary table
 *
 * Config: window.SubFixConfig = { inspectUrl, applyFixUrl, batchFixSingleUrl, token }
 * Trigger (single): .js-sub-fix-lab  [data-id] [data-ref] [data-gateway]
 * Trigger (batch):  .js-sub-batch-fix-btn
 * Modal IDs: #subFixLabModal (single), #subBatchFixModal (batch)
 */
(function () {
    function initSubFixModal() {
        var cfg = window.SubFixConfig || {};
        var inspectUrl        = String(cfg.inspectUrl        || '');
        var applyFixUrl       = String(cfg.applyFixUrl       || '');
        var batchFixSingleUrl = String(cfg.batchFixSingleUrl || '');
        var token             = String(cfg.token             || '');

        if (!inspectUrl || !applyFixUrl || !token) { return; }

        // ── Batch Fix state ──────────────────────────────────────────────
        var batchState = {
            ids: [], current: 0, total: 0, stopped: false,
            rows: [],
            results: { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 }
        };

        // ── Single-row state ─────────────────────────────────────────────
        var singleState = { subscriptionId: null };

        // ─────────────────────────────────────────────────────────────────
        //  Shared helpers
        // ─────────────────────────────────────────────────────────────────
        function nowTs() { return new Date().toTimeString().slice(0, 8); }

        function esc(v) {
            if (v === null || v === undefined || v === '') return '—';
            return String(v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function prettyRaw(data) {
            try { return JSON.stringify(data, null, 2); } catch (e) { return String(data || ''); }
        }

        function statusPill(value, kind) {
            var v  = esc(value || '—');
            var bg = { good: '#1d5f3f', warn: '#715500', bad: '#6a1f2c' }[kind] || '#1f365b';
            return '<span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:10px;'
                + 'font-weight:700;background:' + bg + ';color:#c8ffd4">' + v + '</span>';
        }

        // ─────────────────────────────────────────────────────────────────
        //  Single-row Fix Lab
        // ─────────────────────────────────────────────────────────────────
        function logLine(msg, color) {
            var el = $('#subFixLabLog');
            if (!el.length) return;
            el.append($('<span>').css('color', color || '#9ad1ff').text('[' + nowTs() + '] ' + msg + '\n'));
            el[0].scrollTop = el[0].scrollHeight;
        }

        function setActionResult(html) {
            $('#subFixLabActionResult').html(html);
        }

        function renderSubSnippet(data) {
            var s = data.subscription  || {};
            var u = data.user          || {};
            var t = data.transaction   || {};
            var n = data.normalized    || {};

            var normKind = n.status_normalized === 'completed' ? 'good'
                         : n.status_normalized === 'failed'    ? 'bad' : 'warn';

            // Normalized banner
            $('#subFixLabNormalized').html(
                statusPill((n.status_normalized || 'unknown').toUpperCase(), normKind)
                + ' <span style="margin-left:6px;font-size:11px;color:#9fb2d9">gateway=' + esc(n.gateway || data.gateway) + '</span>'
                + '<span style="margin-left:8px;font-size:11px;color:#9fb2d9">raw=' + esc(n.status_raw)
                + ' | code=' + esc(n.error_code) + ' | msg=' + esc(n.message) + '</span>'
            );

            // Raw JSON
            $('#subFixLabRaw').text(prettyRaw(data.raw_gateway_response || {}));
            $('#subFixLabInspectBlock').show();

            // Subscription snapshot
            var cells = [
                ['Sub #',         '#' + esc(s.id || data.subscription_id)],
                ['Status',        statusPill(s.status || data.subscription_status, String(s.status || '').toLowerCase() === 'active' ? 'good' : 'warn')],
                ['Payment',       statusPill(s.payment_status || data.payment_status, String(s.payment_status || '').toLowerCase() === 'completed' ? 'good' : 'warn')],
                ['Plan',          esc(s.plan)],
                ['App',           esc(s.app_type)],
                ['Platform',      esc(s.platform)],
                ['Amount',        esc(s.currency || 'UGX') + ' ' + esc(s.amount_paid)],
                ['Start',         esc(s.start_date_time)],
                ['End',           esc(s.end_date_time)],
                ['Days',          esc(s.days)],
                ['Tracking ID',   esc(s.pesapal_tracking_id)],
                ['Merchant Ref',  esc(s.merchant_reference)],
                ['FLW Ref',       esc(s.flutterwave_reference)],
                ['Gateway',       esc(s.payment_gateway)],
            ];

            if (u.id) {
                cells.push(['User #', '#' + esc(u.id) + ' ' + esc(u.name)]);
                cells.push(['Email', esc(u.email)]);
                cells.push(['Phone', esc(u.phone_number)]);
                cells.push(['Account', esc(u.account_state)]);
            }
            if (t && t.id) {
                cells.push(['Tx #', '#' + esc(t.id)]);
                cells.push(['Tx Status', statusPill(t.status, String(t.status || '').toLowerCase() === 'completed' ? 'good' : 'warn')]);
                cells.push(['Tx Method', esc(t.payment_method)]);
                cells.push(['Tx Amount', esc(t.currency || 'UGX') + ' ' + esc(t.amount)]);
            }

            var snapHtml = '';
            cells.forEach(function (c) {
                snapHtml += '<div style="background:#0d1117;border:1px solid #30363d;padding:5px 8px;border-radius:3px">'
                    + '<div style="font-size:9px;text-transform:uppercase;color:#8b949e;margin-bottom:1px">' + c[0] + '</div>'
                    + '<div style="font-size:11px;color:#c9d1d9">' + c[1] + '</div>'
                    + '</div>';
            });

            $('#subFixLabSubSnapBody').html(snapHtml);
            $('#subFixLabSubSnap').show();
        }

        function buildPayload(action) {
            var days = parseInt($('#subFixLabExtendDays').val(), 10) || 30;
            return {
                _token:          token,
                action:          action,
                subscription_id: singleState.subscriptionId,
                reference:       String($('#subFixLabRef').val() || '').trim(),
                gateway:         String($('#subFixLabGateway').val() || 'auto').trim(),
                days:            days,
            };
        }

        function callInspect() {
            var payload = buildPayload('inspect');
            if (!payload.reference && !payload.subscription_id) {
                setActionResult('<span class="text-danger">Provide a payment reference or open from a subscription row.</span>');
                return;
            }
            logLine('Inspecting gateway status…', '#f8c471');
            $.post(inspectUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Inspect failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                        setActionResult('<span class="text-danger">Inspect failed: ' + esc(res && res.message ? res.message : 'unknown') + '</span>');
                        return;
                    }
                    renderSubSnippet(res.data || {});
                    logLine('Inspect complete — gateway: ' + esc((res.data || {}).gateway || 'unknown'), '#7dffa1');
                    setActionResult('<span style="color:#7dffa1">Inspect complete. See snapshot above.</span>');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                    logLine('Inspect error: ' + msg, '#ff7b7b');
                    setActionResult('<span class="text-danger">Inspect request failed: ' + esc(msg) + '</span>');
                });
        }

        function runFix(action) {
            var payload = buildPayload(action);
            if (!payload.reference && !payload.subscription_id) {
                setActionResult('<span class="text-danger">Provide a payment reference or open from a subscription row.</span>');
                return;
            }
            logLine('Running action: ' + action + ' …', '#f8c471');
            $.post(applyFixUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Action failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                        setActionResult('<span class="text-danger">Action failed: ' + esc(res && res.message ? res.message : 'unknown') + '</span>');
                        return;
                    }
                    var d = res.data || {};
                    renderSubSnippet(d);
                    logLine('Action success: ' + (res.message || action), '#7dffa1');
                    setActionResult('<span style="color:#7dffa1"><b>Done:</b> ' + esc(res.message || action)
                        + ' | Sub Status: ' + esc(d.subscription_status) + ' | Payment: ' + esc(d.payment_status) + '</span>');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                    logLine('Action error: ' + msg, '#ff7b7b');
                    setActionResult('<span class="text-danger">Request failed: ' + esc(msg) + '</span>');
                });
        }

        // Bind Fix Lab open button
        $(document)
            .off('click.subfixlab', '.js-sub-fix-lab')
            .on('click.subfixlab', '.js-sub-fix-lab', function () {
                singleState.subscriptionId = $(this).data('id') || null;
                var ref    = String($(this).data('ref')     || '');
                var gwHint = String($(this).data('gateway') || 'auto');

                $('#subFixLabRef').val(ref);
                $('#subFixLabGateway').val(['flutterwave', 'pesapal'].includes(gwHint) ? gwHint : 'auto');
                $('#subFixLabLog').text('');
                $('#subFixLabRaw').text('');
                $('#subFixLabNormalized').html('');
                $('#subFixLabInspectBlock').hide();
                $('#subFixLabSubSnap').hide();
                $('#subFixLabSubSnapBody').html('');
                $('#subFixLabExtendDays').val(30);
                setActionResult('');
                $('#subFixLabTitle').html('<i class="fa fa-flask"></i> Subscription Fix Lab — #' + singleState.subscriptionId);
                logLine('Modal opened for subscription #' + singleState.subscriptionId, '#9ad1ff');
                if (ref) logLine('Reference: ' + ref + ' | Gateway hint: ' + gwHint, '#9ad1ff');
                $('#subFixLabModal').modal('show');
            });

        // Bind Inspect button
        $(document)
            .off('click.subfixinspect', '#subFixLabInspectBtn')
            .on('click.subfixinspect', '#subFixLabInspectBtn', callInspect);

        // Bind action buttons
        $(document)
            .off('click.subfixaction', '.js-subfix-action')
            .on('click.subfixaction', '.js-subfix-action', function () {
                var action = String($(this).data('action') || '');
                if (!action) return;
                runFix(action);
            });

        // ─────────────────────────────────────────────────────────────────
        //  Batch Fix helpers
        // ─────────────────────────────────────────────────────────────────
        function bfLog(msg, color) {
            var el = document.getElementById('subBatchTabLog');
            if (!el) return;
            var span = document.createElement('span');
            span.style.color = color || '#9ad1ff';
            span.textContent = '[' + nowTs() + '] ' + msg + '\n';
            el.appendChild(span);
            el.scrollTop = el.scrollHeight;
        }

        function bfRenderItemDetail(subId, res) {
            var sb  = res.subscription_before || {};
            var sa  = res.subscription_after  || {};
            var result = res.result || 'unknown';
            var raw    = res.raw_gateway_response || {};
            var resultColor = {
                activated: '#7dffa1', still_pending: '#f8c471',
                confirmed_failed: '#ff9f9f', no_reference: '#9fb2d9', error: '#ffb84d'
            }[result] || '#dbe7ff';

            var html = '<div style="padding:10px;font-size:11px;color:#c9d1d9">'
                + '<div style="font-size:14px;font-weight:700;color:' + resultColor + ';margin-bottom:8px">'
                + esc((result).replace(/_/g, ' ').toUpperCase())
                + ' <span style="font-size:11px;color:#9fb2d9">Sub #' + esc(subId)
                + ' · <b>' + esc(res.gateway || '—') + '</b>'
                + ' · ref=' + esc(res.reference || '—') + '</span></div>';

            // Gateway normalized
            html += '<div style="background:#060814;border:1px solid #2f3957;border-radius:4px;padding:8px;margin-bottom:8px">'
                + '<div style="font-size:10px;text-transform:uppercase;color:#93a4c7;margin-bottom:4px">Gateway</div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 16px;line-height:1.6">';

            var gwNorm = res.normalized || {};
            [
                ['Status (raw)', gwNorm.status_raw],
                ['Status (norm)', gwNorm.status_normalized],
                ['Amount', gwNorm.amount ? (gwNorm.currency || 'UGX') + ' ' + gwNorm.amount : null],
                ['Message', gwNorm.message],
                ['Error Code', gwNorm.error_code],
            ].forEach(function (f) {
                if (f[1]) {
                    html += '<div><span style="color:#7a90b9">' + esc(f[0]) + ':</span> <span style="color:#dbe7ff">' + esc(f[1]) + '</span></div>';
                }
            });
            html += '</div></div>';

            // Subscription before/after
            if (sb.id || sa.id) {
                html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">';
                function subCard(label, data, accent) {
                    return '<div style="background:#0a0f1c;border:1px solid ' + accent + ';border-radius:4px;padding:8px">'
                        + '<div style="font-size:9px;text-transform:uppercase;color:' + accent + ';margin-bottom:4px">' + label + '</div>'
                        + '<div style="line-height:1.7">'
                        + '<b>Status:</b> ' + statusPill(data.status, data.status === 'Active' ? 'good' : 'warn') + '<br>'
                        + '<b>Payment:</b> ' + statusPill(data.payment_status, data.payment_status === 'Completed' ? 'good' : 'warn') + '<br>'
                        + '<b>Plan:</b> ' + esc(data.plan) + ' (' + esc(data.days) + ' days)<br>'
                        + '<b>Start:</b> ' + esc(data.start_date_time) + '<br>'
                        + '<b>End:</b> ' + esc(data.end_date_time)
                        + '</div></div>';
                }
                html += subCard('BEFORE', sb, '#2a3351');
                html += subCard('AFTER', sa, result === 'activated' ? '#2d6b42' : '#2a3351');
                html += '</div>';
            }

            // Raw
            if (raw && Object.keys(raw).length) {
                html += '<details style="cursor:pointer"><summary style="font-size:10px;color:#8b949e">Raw Gateway Response</summary>'
                    + '<pre style="background:#0d1117;border:1px solid #30363d;padding:6px;font-size:10px;color:#79c0ff;max-height:120px;overflow:auto;margin-top:4px;white-space:pre-wrap;word-break:break-all">'
                    + esc(prettyRaw(raw)) + '</pre></details>';
            }

            html += '</div>';

            var el = document.getElementById('subBatchTabDetails');
            if (el) { el.innerHTML = html; el.scrollTop = 0; }
        }

        function bfClearItemDetail(subId) {
            var el = document.getElementById('subBatchTabDetails');
            if (el) {
                el.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:24px 0;text-align:center">'
                    + 'Verifying Sub #' + esc(subId) + ' with payment gateway…</div>';
            }
        }

        function updateBatchUI() {
            var r    = batchState.results;
            var done = batchState.current;
            var tot  = batchState.total;
            var pct  = tot > 0 ? Math.round((done / tot) * 100) : 0;
            $('#subBatchProgressBar').css('width', pct + '%');
            $('#subBatchSummaryBar').html(
                '<span style="color:#9ad1ff">Total: <b>' + tot + '</b></span> '
                + '<span style="color:#7dffa1">Activated: <b>' + r.activated + '</b></span> '
                + '<span style="color:#f8c471">Pending: <b>' + r.pending + '</b></span> '
                + '<span style="color:#ff9f9f">Failed: <b>' + r.confirmed_failed + '</b></span> '
                + '<span style="color:#ffb84d">Errors: <b>' + r.errors + '</b></span> '
                + '<span style="color:#9fb2d9">Skipped: <b>' + r.skipped + '</b></span> '
                + '<span style="color:#c9d1d9">Done: <b>' + done + '/' + tot + '</b></span>'
            );
        }

        function buildSummaryTable() {
            var rows = batchState.rows;
            if (!rows.length) {
                return '<div style="color:#7a90b9;font-size:12px;padding:20px;text-align:center">No rows processed.</div>';
            }
            var resultColors = {
                activated: '#7dffa1', still_pending: '#f8c471', confirmed_failed: '#ff9f9f',
                no_reference: '#9fb2d9', error: '#ffb84d', skipped: '#9fb2d9'
            };
            var html = '<div style="overflow-x:auto">'
                + '<table style="width:100%;border-collapse:collapse;font-size:11px;min-width:700px">'
                + '<thead><tr style="background:#111827;color:#93a4c7;text-align:left">'
                + '<th style="padding:6px 10px">Sub #</th>'
                + '<th style="padding:6px 10px">Result</th>'
                + '<th style="padding:6px 10px">Gateway</th>'
                + '<th style="padding:6px 10px">GW Status</th>'
                + '<th style="padding:6px 10px">Sub Status After</th>'
                + '<th style="padding:6px 10px">Activated Period</th>'
                + '<th style="padding:6px 10px">Note</th>'
                + '</tr></thead><tbody>';

            rows.forEach(function (row, i) {
                var n   = row.normalized      || {};
                var sa  = row.subscription_after || {};
                var color = resultColors[row.result] || '#dbe7ff';
                var period = (sa.start_date_time && sa.end_date_time)
                    ? esc(sa.start_date_time) + '<br>→ ' + esc(sa.end_date_time) : '—';

                html += '<tr style="background:' + (i % 2 === 0 ? '#0b0e1a' : '#0e1220') + ';border-bottom:1px solid #1a2236">'
                    + '<td style="padding:5px 10px;color:#9ad1ff">' + esc(row.sub_id) + '</td>'
                    + '<td style="padding:5px 10px;color:' + color + ';font-weight:700">' + esc((row.result || '').replace(/_/g, ' ').toUpperCase()) + '</td>'
                    + '<td style="padding:5px 10px;color:#9fb2d9">' + esc(row.gateway || '—') + '</td>'
                    + '<td style="padding:5px 10px;color:#c8d4ef">' + esc(n.status_raw || '—') + '</td>'
                    + '<td style="padding:5px 10px">'
                    +   statusPill(sa.status || '—', sa.status === 'Active' ? 'good' : 'warn') + ' '
                    +   statusPill(sa.payment_status || '—', sa.payment_status === 'Completed' ? 'good' : 'warn')
                    + '</td>'
                    + '<td style="padding:5px 10px;color:#c8d4ef;white-space:nowrap;font-size:10px">' + period + '</td>'
                    + '<td style="padding:5px 10px;color:#9fb2d9;max-width:200px;word-break:break-word">' + esc(row.message || '') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table></div>';
            return html;
        }

        function batchComplete() {
            var r   = batchState.results;
            var pct = batchState.stopped ? Math.round((batchState.current / batchState.total) * 100) : 100;
            $('#subBatchProgressBar').css('width', pct + '%');

            var tabSum = document.querySelector('#subBatchTabSummary');
            if (tabSum) tabSum.innerHTML = buildSummaryTable();

            bfLog('', '#ffffff');
            bfLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#4a9eff');
            bfLog(batchState.stopped ? '⛔ BATCH STOPPED BY USER' : '✅ BATCH COMPLETE', '#7dffa1');
            bfLog('Total Processed : ' + batchState.current + ' / ' + batchState.total, '#ffffff');
            bfLog('Activated       : ' + r.activated,        '#7dffa1');
            bfLog('Still Pending   : ' + r.pending,          '#f8c471');
            bfLog('Conf. Failed    : ' + r.confirmed_failed, '#ff9f9f');
            bfLog('Errors          : ' + r.errors,           '#ffb84d');
            bfLog('Skipped         : ' + r.skipped,          '#9fb2d9');
            bfLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#4a9eff');

            $('#subBatchCloseBtn').prop('disabled', false);

            // Switch to summary tab
            setTimeout(function () {
                var summaryTab = document.querySelector('.nav-tabs a[href="#subBatchTabSummary"]');
                if (summaryTab) $(summaryTab).tab('show');
            }, 500);
        }

        function processNextBatchItem() {
            if (batchState.stopped || batchState.current >= batchState.total) {
                batchComplete();
                return;
            }

            var subId = batchState.ids[batchState.current];
            updateBatchUI();
            bfClearItemDetail(subId);

            if (!subId || isNaN(parseInt(subId, 10))) {
                batchState.results.skipped++;
                batchState.rows.push({
                    sub_id: subId, result: 'skipped', gateway: '—',
                    normalized: null, subscription_after: null, message: 'Invalid ID'
                });
                batchState.current++;
                bfLog('⏭ Skipping invalid ID: ' + subId, '#9fb2d9');
                setTimeout(processNextBatchItem, 100);
                return;
            }

            bfLog('⚙ Processing Sub #' + subId + ' (' + (batchState.current + 1) + '/' + batchState.total + ')…', '#9ad1ff');

            $.post(batchFixSingleUrl, { _token: token, subscription_id: subId })
                .done(function (res) {
                    batchState.current++;
                    var result = res.result || 'unknown';
                    var n = res.normalized || {};

                    batchState.rows.push({
                        sub_id:               subId,
                        result:               result,
                        gateway:              res.gateway || '—',
                        normalized:           n,
                        subscription_before:  res.subscription_before || null,
                        subscription_after:   res.subscription_after  || null,
                        message:              res.message || '',
                    });

                    bfRenderItemDetail(subId, res);

                    switch (result) {
                        case 'activated':
                            batchState.results.activated++;
                            bfLog('✅ #' + subId + ' ACTIVATED [' + esc(res.gateway) + '] ' + esc(n.status_raw), '#7dffa1');
                            if (res.subscription_after) {
                                var sa = res.subscription_after;
                                bfLog('   Sub #' + esc(res.subscription_id) + ': ' + esc(sa.status)
                                    + ' · Start: ' + esc(sa.start_date_time) + ' · End: ' + esc(sa.end_date_time), '#b8ffd4');
                            }
                            break;
                        case 'still_pending':
                            batchState.results.pending++;
                            bfLog('⏳ #' + subId + ' still pending [' + esc(res.gateway) + '] gw_status=' + esc(n.status_raw), '#f8c471');
                            break;
                        case 'confirmed_failed':
                            batchState.results.confirmed_failed++;
                            bfLog('❌ #' + subId + ' confirmed FAILED [' + esc(res.gateway) + '] gw_status=' + esc(n.status_raw), '#ff9f9f');
                            break;
                        case 'no_reference':
                            batchState.results.skipped++;
                            bfLog('⏭ #' + subId + ' skipped — no payment reference on file', '#9fb2d9');
                            break;
                        default:
                            batchState.results.errors++;
                            bfLog('⚠ #' + subId + ': ' + esc(res.message || result), '#ffb84d');
                    }

                    updateBatchUI();
                    setTimeout(processNextBatchItem, 600);
                })
                .fail(function (xhr) {
                    batchState.current++;
                    batchState.results.errors++;
                    var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                    batchState.rows.push({
                        sub_id: subId, result: 'error', gateway: '—',
                        normalized: null, subscription_after: null, message: msg
                    });
                    bfLog('🚫 #' + subId + ' request error: ' + msg, '#ff5555');
                    updateBatchUI();
                    setTimeout(processNextBatchItem, 600);
                });
        }

        function openBatchFix(ids) {
            if (!batchFixSingleUrl) {
                alert('Batch fix URL not configured. Please reload the page.');
                return;
            }
            batchState = {
                ids: ids, current: 0, total: ids.length, stopped: false, rows: [],
                results: { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 }
            };

            $('#subBatchProgressBar').css('width', '0%');
            $('#subBatchSummaryBar').html('Loaded <b>' + ids.length + '</b> subscription(s). Click Start to begin.');
            $('#subBatchCloseBtn').prop('disabled', true);

            var logEl = document.getElementById('subBatchTabLog');
            if (logEl) logEl.innerHTML = '';
            var detEl = document.getElementById('subBatchTabDetails');
            if (detEl) detEl.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:24px;text-align:center">Item details will appear here as each subscription is processed.</div>';
            var sumEl = document.getElementById('subBatchTabSummary');
            if (sumEl) sumEl.innerHTML = '<div style="color:#7a90b9;font-size:12px;padding:20px;text-align:center">Summary will appear after the batch completes.</div>';

            // Switch to log tab
            var logTab = document.querySelector('.nav-tabs a[href="#subBatchTabLog"]');
            if (logTab) $(logTab).tab('show');

            bfLog('Loaded ' + ids.length + ' subscription ID(s): '
                + ids.slice(0, 15).join(', ') + (ids.length > 15 ? ' … and ' + (ids.length - 15) + ' more' : ''), '#9ad1ff');
            bfLog('Processing will start immediately…', '#f8c471');

            $('#subBatchFixModal').modal('show');

            // Auto-start after a short delay so modal is visible
            setTimeout(function () {
                bfLog('Starting batch fix…', '#f8c471');
                processNextBatchItem();
            }, 400);
        }

        // ── Batch Fix button (grid toolbar) ──────────────────────────────
        $(document)
            .off('click.subbatchfix', '.js-sub-batch-fix-btn')
            .on('click.subbatchfix', '.js-sub-batch-fix-btn', function () {
                var ids = [];
                // Try laravel-admin selected() API
                if ($.admin && $.admin.grid && typeof $.admin.grid.selected === 'function') {
                    $.each($.admin.grid.selected(), function (i, val) {
                        var n = parseInt(val, 10);
                        if (!isNaN(n)) ids.push(n);
                    });
                }
                // Fallback: scan checked row checkboxes
                if (ids.length === 0) {
                    $('.grid-row-checkbox').each(function () {
                        if ($(this).prop('checked') || $(this).parent().hasClass('checked')) {
                            var n = parseInt($(this).data('id'), 10);
                            if (!isNaN(n)) ids.push(n);
                        }
                    });
                }
                if (ids.length === 0) {
                    var notice = 'Please select at least one subscription row first (tick the checkboxes on the left).';
                    if (typeof toastr !== 'undefined') { toastr.warning(notice); }
                    else { alert(notice); }
                    return;
                }
                openBatchFix(ids);
            });

        // ── Batch modal: Close button ─────────────────────────────────────
        $(document)
            .off('click.subbatchclose', '#subBatchCloseBtn')
            .on('click.subbatchclose', '#subBatchCloseBtn', function () {
                $('#subBatchFixModal').modal('hide');
            });
    }

    if (typeof $ !== 'undefined') {
        $(document).ready(initSubFixModal);
        $(document).on('pjax:end', initSubFixModal);
    }
})();
