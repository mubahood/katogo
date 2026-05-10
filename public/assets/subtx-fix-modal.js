/**
 * Subscription Transaction Fix Modal  v2
 * ──────────────────────────────────────
 * 1) Single-row Fix Lab  – inspect + apply actions on one transaction
 * 2) Batch Fix Modal     – full-screen, rich gateway context per item,
 *                         progress bar, live log, per-item detail pane,
 *                         final summary table
 */
(function () {
    function initSubTxFixModal() {
        var cfg = window.SubTxFixConfig || {};
        var inspectUrl        = String(cfg.inspectUrl        || '');
        var applyFixUrl       = String(cfg.applyFixUrl       || '');
        var batchFixSingleUrl = String(cfg.batchFixSingleUrl || '');
        var token             = String(cfg.token             || '');

        if (!inspectUrl || !applyFixUrl || !token) { return; }

        // ── Batch Fix state ───────────────────────────────────────────────
        var batchState = {
            ids: [], current: 0, total: 0, stopped: false,
            rows: [],   // per-item result records for summary table
            results: { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 }
        };

        // ── Single-row state ──────────────────────────────────────────────
        var singleState = { transactionId: null };

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
        function setSummary(html)  { $('#subTxFixSummary').html(html); }

        function logLine(msg, color) {
            var el = $('#subTxFixLog');
            if (!el.length) return;
            el.append($('<span>').css('color', color || '#9ad1ff').text('[' + nowTs() + '] ' + msg + '\n'));
            el.scrollTop(el[0].scrollHeight);
        }

        function renderCards(data) {
            var s = data.subscription || {};
            var u = data.user         || {};
            var t = data.transaction  || {};
            var n = data.normalized   || {};
            var normKind = n.status_normalized === 'completed' ? 'good'
                         : n.status_normalized === 'failed'    ? 'bad' : 'warn';

            $('#subTxOutcome').html(
                statusPill((n.status_normalized || 'unknown').toUpperCase(), normKind)
                + ' <span style="margin-left:6px">gateway=' + esc(n.gateway || data.gateway) + '</span>'
                + '<br><span style="font-size:11px;color:#9fb2d9">raw=' + esc(n.status_raw)
                + ' | code=' + esc(n.error_code) + ' | msg=' + esc(n.message) + '</span>'
            );

            $('#subTxSubCard').html(
                '<b>#' + esc(s.id || data.subscription_id) + '</b> '
                + statusPill(s.status || data.subscription_status, (String(s.status || '').toLowerCase() === 'active' ? 'good' : 'warn'))
                + ' ' + statusPill(s.payment_status || data.payment_status, (String(s.payment_status || '').toLowerCase() === 'completed' ? 'good' : 'warn'))
                + '<br><span style="color:#9fb2d9">Plan:</span> ' + esc(s.plan)
                + ' | <span style="color:#9fb2d9">App:</span> ' + esc(s.app_type)
                + ' | <span style="color:#9fb2d9">Platform:</span> ' + esc(s.platform)
                + '<br><span style="color:#9fb2d9">Amount:</span> ' + esc(s.currency || 'UGX') + ' ' + esc(s.amount_paid)
                + '<br><span style="color:#9fb2d9">Start:</span> ' + esc(s.start_date_time)
                + ' | <span style="color:#9fb2d9">End:</span> ' + esc(s.end_date_time)
            );

            $('#subTxUserCard').html(
                '<b>#' + esc(u.id) + ' ' + esc(u.name) + '</b>'
                + '<br><span style="color:#9fb2d9">Email:</span> ' + esc(u.email)
                + '<br><span style="color:#9fb2d9">Phone:</span> ' + esc(u.phone_number)
                + '<br><span style="color:#9fb2d9">Account:</span> ' + esc(u.account_state)
                + ' | <span style="color:#9fb2d9">App:</span> ' + esc(u.app_type)
            );

            $('#subTxTxnCard').html(
                '<b>#' + esc(t.id || data.transaction_id) + '</b> '
                + statusPill(t.status, (String(t.status || '').toLowerCase() === 'completed' ? 'good'
                    : String(t.status || '').toLowerCase() === 'failed' ? 'bad' : 'warn'))
                + '<br><span style="color:#9fb2d9">Method:</span> ' + esc(t.payment_method)
                + '<br><span style="color:#9fb2d9">Amount:</span> ' + esc(t.currency || 'UGX') + ' ' + esc(t.amount)
                + '<br><span style="color:#9fb2d9">Ref:</span> ' + esc(t.merchant_reference || data.reference)
                + '<br><span style="color:#9fb2d9">Tracking:</span> ' + esc(t.tracking_id)
                + ' | <span style="color:#9fb2d9">Confirm:</span> ' + esc(t.confirmation_code)
            );
        }

        function buildPayload(action) {
            return {
                _token:         token,
                action:         action,
                transaction_id: singleState.transactionId,
                reference:      String($('#subTxFixRef').val() || '').trim(),
                gateway:        String($('#subTxFixGateway').val() || 'auto').trim(),
            };
        }

        function callInspect() {
            var payload = buildPayload('inspect');
            if (!payload.reference && !payload.transaction_id) {
                setSummary('<span class="text-danger">Provide payment reference or open from a transaction row.</span>');
                return;
            }
            logLine('Inspecting gateway status…', '#f8c471');
            $.post(inspectUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Inspect failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                        setSummary('<span class="text-danger">Inspect failed.</span>');
                        return;
                    }
                    var d = res.data || {};
                    $('#subTxFixRaw').text(prettyRaw(d.raw_gateway_response || {}));
                    renderCards(d);
                    setSummary('<b>Gateway:</b> ' + esc(d.gateway)
                        + ' | <b>Sub:</b> #' + esc(d.subscription_id)
                        + ' | <b>Tx:</b> #' + esc(d.transaction_id)
                        + '<br><b>Payment:</b> ' + esc(d.payment_status)
                        + ' | <b>Sub Status:</b> ' + esc(d.subscription_status));
                    logLine('Inspect complete — gateway: ' + esc(d.gateway || 'unknown'), '#7dffa1');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON || {}).message || 'HTTP error';
                    logLine('Inspect error: ' + msg, '#ff7b7b');
                    setSummary('<span class="text-danger">Inspect request failed.</span>');
                });
        }

        function runFix(action) {
            var payload = buildPayload(action);
            if (!payload.reference && !payload.transaction_id) {
                setSummary('<span class="text-danger">Provide payment reference or open from a transaction row.</span>');
                return;
            }
            logLine('Running fix action: ' + action + ' …', '#f8c471');
            $.post(applyFixUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Fix failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                        setSummary('<span class="text-danger">Fix action failed.</span>');
                        return;
                    }
                    var d = res.data || {};
                    if (d.raw_gateway_response) { $('#subTxFixRaw').text(prettyRaw(d.raw_gateway_response)); }
                    renderCards(d);
                    logLine('Fix success: ' + (res.message || action), '#7dffa1');
                    setSummary('<span class="text-success"><b>Done:</b> ' + esc(res.message || action) + '</span>'
                        + '<br><b>Sub Status:</b> ' + esc(d.subscription_status)
                        + ' | <b>Payment:</b> ' + esc(d.payment_status));
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON || {}).message || 'HTTP error';
                    logLine('Fix request error: ' + msg, '#ff7b7b');
                    setSummary('<span class="text-danger">Fix request failed.</span>');
                });
        }

        $(document)
            .off('click.subtxfix', '.js-subtx-fix')
            .on('click.subtxfix', '.js-subtx-fix', function () {
                singleState.transactionId = $(this).data('id') || null;
                var ref    = String($(this).data('ref')     || '');
                var gwHint = String($(this).data('gateway') || 'auto');
                $('#subTxFixRef').val(ref);
                $('#subTxFixGateway').val(['flutterwave', 'pesapal'].includes(gwHint) ? gwHint : 'auto');
                $('#subTxFixLog, #subTxFixRaw').text('');
                $('#subTxOutcome').text('Not inspected yet.');
                $('#subTxSubCard, #subTxUserCard, #subTxTxnCard').text('—');
                setSummary('Loaded transaction #' + singleState.transactionId + '. Click Inspect Gateway to fetch live status.');
                logLine('Modal opened for transaction #' + singleState.transactionId, '#9ad1ff');
                $('#subTxFixModal').modal('show');
            });

        $(document)
            .off('click.subtxinspect',     '#subTxInspectBtn')     .on('click.subtxinspect',     '#subTxInspectBtn',      callInspect)
            .off('click.subtxforceverify', '#subTxForceVerifyBtn') .on('click.subtxforceverify', '#subTxForceVerifyBtn',  function () { runFix('force_verify'); })
            .off('click.subtxactivate',    '#subTxActivateBtn')    .on('click.subtxactivate',    '#subTxActivateBtn',     function () { runFix('force_activate'); })
            .off('click.subtxmarkdone',    '#subTxMarkCompletedBtn').on('click.subtxmarkdone',   '#subTxMarkCompletedBtn',function () { runFix('mark_tx_completed'); })
            .off('click.subtxmarkfail',    '#subTxMarkFailedBtn')  .on('click.subtxmarkfail',    '#subTxMarkFailedBtn',   function () { runFix('mark_tx_failed'); });

        // ─────────────────────────────────────────────────────────────────
        //  Batch Fix – helpers
        // ─────────────────────────────────────────────────────────────────
        function bfLog(msg, color) {
            var el = document.getElementById('bfLog');
            if (!el) return;
            var span = document.createElement('span');
            span.style.color   = color || '#9ad1ff';
            span.textContent   = '[' + nowTs() + '] ' + msg + '\n';
            el.appendChild(span);
            el.scrollTop = el.scrollHeight;
        }

        // Render a rich detail card for the current/most-recent item in the right pane
        function bfRenderItemDetail(txId, res) {
            var gd  = res.gateway_details    || {};
            var sb  = res.subscription_before || {};
            var sa  = res.subscription_after  || {};
            var aa  = res.activation_audit    || null;
            var result = res.result || 'unknown';
            var resultColor = {
                activated: '#7dffa1', still_pending: '#f8c471',
                confirmed_failed: '#ff9f9f', no_reference: '#9fb2d9', error: '#ffb84d'
            }[result] || '#dbe7ff';

            var html = '<div style="margin-bottom:10px">'
                + '<span style="font-size:14px;font-weight:700;color:' + resultColor + '">'
                + esc((result).replace(/_/g, ' ').toUpperCase()) + '</span>'
                + ' <span style="font-size:11px;color:#9fb2d9">Tx #' + esc(txId)
                + (res.subscription_id ? ' · Sub #' + esc(res.subscription_id) : '')
                + ' · <b>' + esc(res.gateway || gd.gateway || '—') + '</b></span>'
                + '</div>';

            // ── Gateway response section ───────────────────────────────
            html += '<div style="background:#060814;border:1px solid #2f3957;border-radius:4px;padding:10px 12px;margin-bottom:10px">'
                + '<div style="font-size:10px;text-transform:uppercase;color:#93a4c7;margin-bottom:8px;letter-spacing:.5px">Gateway Response</div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:5px 20px;font-size:11px;line-height:1.6">';

            var gwFields = [
                ['Status (raw)',       esc(gd.status_raw)],
                ['Status (normalized)',esc(gd.status_normalized)],
                ['Amount',             gd.amount ? esc(gd.currency || 'UGX') + ' ' + esc(gd.amount) : null],
                ['Payment Date',       esc(gd.payment_date)],
                ['Customer Name',      esc(gd.customer_name)],
                ['Customer Phone',     esc(gd.customer_phone)],
                ['Customer Email',     esc(gd.customer_email)],
                ['Payment Method',     esc(gd.payment_method)],
                ['Network / Operator', esc(gd.network_operator)],
                ['Error Code',         esc(gd.error_code)],
                ['Error Message',      esc(gd.error_message)],
                ['Description',        esc(gd.description)],
            ];
            gwFields.forEach(function (f) {
                if (f[1] && f[1] !== '—') {
                    html += '<div><span style="color:#7a90b9">' + f[0] + ':</span> '
                         + '<span style="color:#dbe7ff">' + f[1] + '</span></div>';
                }
            });
            html += '</div></div>';

            // ── Subscription before → after ────────────────────────────
            if (sb.id || sa.id) {
                html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">';

                function subCard(label, data, accentColor) {
                    return '<div style="background:#0a0f1c;border:1px solid ' + accentColor + ';border-radius:4px;padding:10px 12px">'
                        + '<div style="font-size:10px;color:' + accentColor + ';text-transform:uppercase;margin-bottom:6px;letter-spacing:.5px">'
                        + label + '</div>'
                        + '<div style="font-size:11px;color:#c8d4ef;line-height:1.7">'
                        + (data.id ? '<span style="color:#9ad1ff">Sub #' + esc(data.id) + '</span><br>' : '')
                        + '<b>Status:</b> ' + statusPill(data.status, data.status === 'Active' ? 'good' : 'warn') + '<br>'
                        + '<b>Payment:</b> ' + statusPill(data.payment_status, data.payment_status === 'Completed' ? 'good' : 'warn') + '<br>'
                        + '<b>Plan:</b> ' + esc(data.plan) + ' (' + esc(data.days) + ' days)<br>'
                        + '<b>Start:</b> ' + esc(data.start_date_time) + '<br>'
                        + '<b>End:</b> '   + esc(data.end_date_time)
                        + (data.grace_period_end ? '<br><b>Grace until:</b> ' + esc(data.grace_period_end) : '')
                        + '</div></div>';
                }

                html += subCard('Subscription BEFORE', sb, '#2a3351');
                html += subCard('Subscription AFTER', sa, result === 'activated' ? '#2d6b42' : '#2a3351');
                html += '</div>';

                // Activation anchor info
                if (aa && aa.anchor_source) {
                    html += '<div style="font-size:10px;color:#7a90b9;padding:4px 0 6px">'
                        + 'Activation anchor: <span style="color:#f8c471">' + esc(aa.anchor_source) + '</span>'
                        + (aa.activation_anchor ? ' at <span style="color:#c8d4ef">' + esc(aa.activation_anchor) + '</span>' : '')
                        + (aa.duration_days ? ' · <span style="color:#9fb2d9">' + esc(aa.duration_days) + ' days</span>' : '')
                        + '</div>';
                }
            }

            var el = document.getElementById('bfItemDetail');
            if (el) { el.innerHTML = html; el.scrollTop = 0; }
        }

        function bfClearItemDetail(txId) {
            var el = document.getElementById('bfItemDetail');
            if (el) {
                el.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:24px 0;text-align:center">'
                    + 'Verifying Tx #' + esc(txId) + ' with payment gateway…</div>';
            }
        }

        function updateBatchUI() {
            var r    = batchState.results;
            var done = batchState.current;
            var tot  = batchState.total;
            var pct  = tot > 0 ? Math.round((done / tot) * 100) : 0;
            $('#bfProgressBar').css('width', pct + '%').text(pct + '%');
            $('#bfTotal').text(tot);
            $('#bfDone').text(done);
            $('#bfActivated').text(r.activated);
            $('#bfPending').text(r.pending);
            $('#bfConfFailed').text(r.confirmed_failed);
            $('#bfErrors').text(r.errors);
            $('#bfSkipped').text(r.skipped);
            $('#bfStatusLine').text('Processing ' + done + ' / ' + tot + '…');
        }

        function buildSummaryTable() {
            var rows = batchState.rows;
            if (!rows.length) return '<div style="color:#7a90b9;font-size:12px;padding:20px;text-align:center">No rows processed.</div>';

            var resultColors = {
                activated: '#7dffa1', still_pending: '#f8c471', confirmed_failed: '#ff9f9f',
                no_reference: '#9fb2d9', error: '#ffb84d', skipped: '#9fb2d9'
            };

            var html = '<div style="overflow-x:auto">'
                + '<table style="width:100%;border-collapse:collapse;font-size:11px;min-width:780px">'
                + '<thead><tr style="background:#111827;color:#93a4c7;text-align:left">'
                + '<th style="padding:6px 10px">Tx #</th>'
                + '<th style="padding:6px 10px">Sub #</th>'
                + '<th style="padding:6px 10px">Result</th>'
                + '<th style="padding:6px 10px">Gateway</th>'
                + '<th style="padding:6px 10px">Amount</th>'
                + '<th style="padding:6px 10px">GW Status</th>'
                + '<th style="padding:6px 10px">Method</th>'
                + '<th style="padding:6px 10px">Sub Status After</th>'
                + '<th style="padding:6px 10px">Activated Period</th>'
                + '<th style="padding:6px 10px">Note</th>'
                + '</tr></thead><tbody>';

            rows.forEach(function (row, i) {
                var gd  = row.gateway_details    || {};
                var sa  = row.subscription_after || {};
                var color = resultColors[row.result] || '#dbe7ff';
                var amt = gd.amount ? (esc(gd.currency || 'UGX') + ' ' + esc(gd.amount)) : '—';
                var period = (sa.start_date_time && sa.end_date_time)
                    ? esc(sa.start_date_time) + '<br>→ ' + esc(sa.end_date_time) : '—';

                html += '<tr style="background:' + (i % 2 === 0 ? '#0b0e1a' : '#0e1220') + ';border-bottom:1px solid #1a2236">'
                    + '<td style="padding:5px 10px;color:#dbe7ff">' + esc(row.tx_id) + '</td>'
                    + '<td style="padding:5px 10px;color:#9ad1ff">' + esc(row.subscription_id || '—') + '</td>'
                    + '<td style="padding:5px 10px;color:' + color + ';font-weight:700">' + esc((row.result || '').replace(/_/g, ' ').toUpperCase()) + '</td>'
                    + '<td style="padding:5px 10px;color:#9fb2d9">' + esc(row.gateway || '—') + '</td>'
                    + '<td style="padding:5px 10px;color:#dbe7ff">' + amt + '</td>'
                    + '<td style="padding:5px 10px;color:#c8d4ef">' + esc(gd.status_raw || '—') + '</td>'
                    + '<td style="padding:5px 10px;color:#c8d4ef">' + esc(gd.payment_method || '—') + '</td>'
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

            $('#bfProgressBar')
                .css('width', pct + '%').text(pct + '%')
                .removeClass('active progress-bar-striped')
                .css('background', batchState.stopped ? '#e67e22' : r.activated > 0 ? '#28a745' : '#6c757d');

            // Populate summary table
            $('#bfSummaryContent').html(buildSummaryTable());

            bfLog('', '#ffffff');
            bfLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#4a9eff');
            bfLog(batchState.stopped ? '⛔ BATCH STOPPED BY USER' : '✅ BATCH COMPLETE', '#7dffa1');
            bfLog('Total Processed : ' + batchState.current + ' / ' + batchState.total, '#ffffff');
            bfLog('Activated       : ' + r.activated,        '#7dffa1');
            bfLog('Still Pending   : ' + r.pending,          '#f8c471');
            bfLog('Conf. Failed    : ' + r.confirmed_failed, '#ff9f9f');
            bfLog('Errors          : ' + r.errors,           '#ffb84d');
            bfLog('Skipped         : ' + r.skipped,          '#9fb2d9');
            bfLog('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#4a9eff');

            $('#bfStatusLine').text('Done! ' + r.activated + ' activated, ' + r.errors + ' errors. See Summary tab.');
            $('#bfStopBtn').hide();
            $('#bfStartBtn').show().text(batchState.stopped ? '▶ Resume / Restart' : '🔁 Fix Again').prop('disabled', false);

            // Auto-switch to summary tab
            setTimeout(function () { $('#bfSummaryTab').tab('show'); }, 500);

            // No auto-refresh — close the modal manually and reload the grid when ready.
        }

        function processNextBatchItem() {
            if (batchState.stopped || batchState.current >= batchState.total) {
                batchComplete();
                return;
            }

            var txId = batchState.ids[batchState.current];
            updateBatchUI();
            bfClearItemDetail(txId);

            if (!txId || isNaN(parseInt(txId, 10))) {
                batchState.results.skipped++;
                batchState.rows.push({
                    tx_id: txId, subscription_id: null, result: 'skipped',
                    gateway: '—', gateway_details: null, subscription_after: null, message: 'Invalid ID'
                });
                batchState.current++;
                bfLog('⏭ Skipping invalid ID: ' + txId, '#9fb2d9');
                setTimeout(processNextBatchItem, 100);
                return;
            }

            bfLog('⚙ Processing Tx #' + txId + ' (' + (batchState.current + 1) + '/' + batchState.total + ')…', '#9ad1ff');

            $.post(batchFixSingleUrl, { _token: token, transaction_id: txId })
                .done(function (res) {
                    batchState.current++;
                    var result = res.result || 'unknown';
                    var gd = res.gateway_details || {};
                    var amtStr = gd.amount ? (' | ' + (gd.currency || 'UGX') + ' ' + gd.amount) : '';

                    // Record for summary table
                    batchState.rows.push({
                        tx_id:              txId,
                        subscription_id:    res.subscription_id    || null,
                        result:             result,
                        gateway:            res.gateway            || gd.gateway || '—',
                        gateway_details:    gd,
                        subscription_before: res.subscription_before || null,
                        subscription_after:  res.subscription_after  || null,
                        activation_audit:    res.activation_audit    || null,
                        message:            res.message || '',
                    });

                    // Render rich detail pane
                    bfRenderItemDetail(txId, res);

                    switch (result) {
                        case 'activated':
                            batchState.results.activated++;
                            bfLog('✅ #' + txId + ' ACTIVATED [' + esc(res.gateway) + '] ' + esc(res.normalized_status) + amtStr, '#7dffa1');
                            if (res.subscription_after) {
                                var sa = res.subscription_after;
                                bfLog('   Sub #' + esc(res.subscription_id) + ': '
                                    + esc(sa.status) + ' · Start: ' + esc(sa.start_date_time)
                                    + ' · End: ' + esc(sa.end_date_time), '#b8ffd4');
                            }
                            break;
                        case 'still_pending':
                            batchState.results.pending++;
                            bfLog('⏳ #' + txId + ' still pending [' + esc(res.gateway) + '] gw_status=' + esc(gd.status_raw) + ' — ' + esc(res.message), '#f8c471');
                            break;
                        case 'confirmed_failed':
                            batchState.results.confirmed_failed++;
                            bfLog('❌ #' + txId + ' confirmed FAILED [' + esc(res.gateway) + '] gw_status=' + esc(gd.status_raw)
                                + (gd.error_message && gd.error_message !== '—' ? ' — ' + esc(gd.error_message) : ''), '#ff9f9f');
                            break;
                        case 'no_reference':
                            batchState.results.skipped++;
                            bfLog('⏭ #' + txId + ' skipped — no payment reference on file', '#9fb2d9');
                            break;
                        default:
                            batchState.results.errors++;
                            bfLog('⚠ #' + txId + ': ' + esc(res.message || result), '#ffb84d');
                    }

                    updateBatchUI();
                    setTimeout(processNextBatchItem, 600); // 600ms — respects gateway rate limits
                })
                .fail(function (xhr) {
                    batchState.current++;
                    batchState.results.errors++;
                    var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                    batchState.rows.push({
                        tx_id: txId, subscription_id: null, result: 'error',
                        gateway: '—', gateway_details: null, subscription_after: null, message: msg
                    });
                    bfLog('🚫 #' + txId + ' request error: ' + msg, '#ff5555');
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

            $('#bfProgressBar')
                .css('width', '0%').text('0%')
                .addClass('progress-bar-striped active')
                .css('background', 'linear-gradient(90deg,#2196F3,#21CBF3)');
            $('#bfTotal').text(ids.length);
            $('#bfDone, #bfActivated, #bfPending, #bfConfFailed, #bfErrors, #bfSkipped').text(0);
            $('#bfStatusLine').text('Ready. Click ▶ Start Fix to process ' + ids.length + ' transaction(s).');
            $('#bfLog').text('');
            $('#bfSummaryContent').html('<div style="color:#7a90b9;font-size:12px;padding:20px;text-align:center">Summary will appear here after the batch completes.</div>');

            var detailEl = document.getElementById('bfItemDetail');
            if (detailEl) {
                detailEl.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:24px 0;text-align:center">Item details will appear here as each transaction is processed.</div>';
            }

            $('#bfStartBtn').show().text('▶ Start Fix').prop('disabled', false);
            $('#bfStopBtn').hide();
            $('#bfLogTab').tab('show');

            bfLog('Loaded ' + ids.length + ' transaction ID(s): '
                + ids.slice(0, 15).join(', ') + (ids.length > 15 ? ' … and ' + (ids.length - 15) + ' more' : ''), '#9ad1ff');
            bfLog('Click "▶ Start Fix" to begin gateway verification.', '#f8c471');

            $('#subTxBatchFixModal').modal('show');
        }

        // ── Batch Fix button (grid tools bar) ─────────────────────────────
        $(document)
            .off('click.batchfixbtn', '.js-batch-fix-btn')
            .on('click.batchfixbtn', '.js-batch-fix-btn', function () {
                var ids = [];
                if ($.admin && $.admin.grid && typeof $.admin.grid.selected === 'function') {
                    $.each($.admin.grid.selected(), function (i, val) {
                        var n = parseInt(val, 10);
                        if (!isNaN(n)) ids.push(n);
                    });
                }
                // Fallback: scan data-id on checked iCheck wrappers
                if (ids.length === 0) {
                    $('.grid-row-checkbox').each(function () {
                        if ($(this).prop('checked') || $(this).parent().hasClass('checked')) {
                            var n = parseInt($(this).data('id'), 10);
                            if (!isNaN(n)) ids.push(n);
                        }
                    });
                }
                if (ids.length === 0) {
                    var notice = 'Please select at least one transaction row first (tick the checkboxes on the left).';
                    if (typeof toastr !== 'undefined') { toastr.warning(notice); }
                    else { alert(notice); }
                    return;
                }
                openBatchFix(ids);
            });

        // ── Batch modal: Start button ─────────────────────────────────────
        $(document)
            .off('click.bfstart', '#bfStartBtn')
            .on('click.bfstart', '#bfStartBtn', function () {
                if (batchState.total === 0) return;
                $(this).prop('disabled', true);
                $('#bfStopBtn').show();
                batchState.stopped = false;
                batchState.current = 0;
                batchState.rows    = [];
                batchState.results = { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 };
                $('#bfLog').text('');
                $('#bfLogTab').tab('show');
                bfLog('Starting batch fix for ' + batchState.total + ' transaction(s)…', '#f8c471');
                processNextBatchItem();
            });

        // ── Batch modal: Stop button ──────────────────────────────────────
        $(document)
            .off('click.bfstop', '#bfStopBtn')
            .on('click.bfstop', '#bfStopBtn', function () {
                batchState.stopped = true;
                $(this).hide();
                $('#bfStartBtn').show().text('▶ Resume').prop('disabled', false);
                bfLog('⛔ Stop requested — finishing current request before halting…', '#e67e22');
            });
    }

    if (typeof $ !== 'undefined') {
        $(document).ready(initSubTxFixModal);
        $(document).on('pjax:end', initSubTxFixModal);
    }
})();
