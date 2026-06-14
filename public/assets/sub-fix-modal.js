/**
 * Subscription Fix Modal  v2
 * ──────────────────────────
 * Two-column layout:
 *   LEFT  — subscription summary + full payment-transaction list (clickable)
 *   RIGHT — selected transaction gateway inspector + fix actions + log
 *
 * Config: window.SubFixConfig = { inspectUrl, applyFixUrl, batchFixSingleUrl, initiatePaymentUrl, token }
 * Trigger (single): .js-sub-fix-lab  [data-id] [data-ref] [data-gateway]
 * Trigger (batch):  .js-sub-batch-fix-btn
 */
(function () {
    function initSubFixModal() {
        var cfg = window.SubFixConfig || {};
        var inspectUrl          = String(cfg.inspectUrl          || '');
        var applyFixUrl         = String(cfg.applyFixUrl         || '');
        var batchFixSingleUrl   = String(cfg.batchFixSingleUrl   || '');
        var initiatePaymentUrl  = String(cfg.initiatePaymentUrl  || '');
        var token               = String(cfg.token               || '');

        if (!inspectUrl || !applyFixUrl || !token) { return; }

        // ── Batch Fix state ──────────────────────────────────────────────
        var batchState = {
            ids: [], current: 0, total: 0, stopped: false,
            rows: [],
            results: { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 }
        };

        // ── Single-row state ─────────────────────────────────────────────
        var singleState = {
            subscriptionId: null,
            activeTx: null,         // currently selected transaction object
            allTransactions: []
        };

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

        function txStatusKind(status) {
            var s = String(status || '').toLowerCase();
            return s === 'completed' ? 'good' : (s === 'failed' ? 'bad' : 'warn');
        }

        // ─────────────────────────────────────────────────────────────────
        //  Logging
        // ─────────────────────────────────────────────────────────────────
        function logLine(msg, color) {
            var el = document.getElementById('subFixLabLog');
            if (!el) return;
            var span = document.createElement('span');
            span.style.color = color || '#9ad1ff';
            span.textContent = '[' + nowTs() + '] ' + msg + '\n';
            el.appendChild(span);
            el.scrollTop = el.scrollHeight;
        }

        function setActionResult(html) {
            var el = document.getElementById('subFixLabActionResult');
            if (el) el.innerHTML = html;
        }

        // ─────────────────────────────────────────────────────────────────
        //  Subscription summary (left panel top)
        // ─────────────────────────────────────────────────────────────────
        function renderSubSummary(s, u) {
            if (!s) return;
            var subKind = String(s.status || '').toLowerCase() === 'active' ? 'good'
                        : String(s.status || '').toLowerCase() === 'expired' ? 'bad' : 'warn';
            var payKind = String(s.payment_status || '').toLowerCase() === 'completed' ? 'good'
                        : String(s.payment_status || '').toLowerCase() === 'failed' ? 'bad' : 'warn';

            var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;font-size:11px;line-height:1.6">'
                + '<div>' + statusPill(s.status, subKind) + ' ' + statusPill(s.payment_status, payKind) + '</div>'
                + '<div style="text-align:right;color:#8b949e">Sub #' + esc(s.id) + '</div>'
                + '<div><span style="color:#8b949e">Plan:</span> <b style="color:#e6edf3">' + esc(s.plan) + '</b></div>'
                + '<div><span style="color:#8b949e">Amt:</span> <b style="color:#e6edf3">' + esc(s.currency || 'UGX') + ' ' + esc(s.amount_paid) + '</b></div>'
                + '<div><span style="color:#8b949e">App:</span> ' + esc(s.app_type) + '</div>'
                + '<div><span style="color:#8b949e">Days:</span> ' + esc(s.days) + '</div>'
                + '<div style="grid-column:1/-1"><span style="color:#8b949e">Start:</span> ' + esc(s.start_date_time) + ' → ' + esc(s.end_date_time) + '</div>'
                + '</div>';

            if (u) {
                html += '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #30363d;font-size:11px;line-height:1.6">'
                    + '<div><i class="fa fa-user" style="color:#8b949e"></i> <b style="color:#e6edf3">' + esc(u.name) + '</b> <span style="color:#8b949e">#' + esc(u.id) + '</span></div>'
                    + '<div style="color:#8b949e">' + esc(u.email) + '</div>'
                    + '<div style="color:#8b949e">' + esc(u.phone_number) + ' · ' + esc(u.account_state) + '</div>'
                    + '</div>';
            }

            var el = document.getElementById('subFixLabSubSummary');
            if (el) {
                el.innerHTML = '<div style="font-size:10px;text-transform:uppercase;color:#8b949e;margin-bottom:6px;letter-spacing:.5px"><i class="fa fa-credit-card"></i> Subscription</div>'
                    + html;
            }
        }

        // ─────────────────────────────────────────────────────────────────
        //  Transaction list (left panel scrollable)
        // ─────────────────────────────────────────────────────────────────
        function renderTxList(transactions) {
            var el = document.getElementById('subFixLabTxList');
            var countEl = document.getElementById('subFixLabTxCount');
            if (!el) return;

            if (countEl) countEl.textContent = transactions.length + ' tx';

            if (!transactions.length) {
                el.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:20px;text-align:center">No transactions found for this subscription.</div>';
                return;
            }

            var html = '';
            transactions.forEach(function (tx) {
                var kind = txStatusKind(tx.status);
                var dotColor = kind === 'good' ? '#3fb950' : kind === 'bad' ? '#f85149' : '#d29922';
                var ref = tx.pesapal_tracking_id || tx.merchant_reference || '—';
                var isFixed = tx.is_fixed ? '<span style="margin-left:4px;font-size:9px;color:#3fb950;font-weight:700">FIXED</span>' : '';

                // Pay button: only if payment_url is stored for this tx
                var payBtn = '';
                if (tx.payment_url) {
                    payBtn = '<a href="' + esc(tx.payment_url) + '" target="_blank" rel="noopener noreferrer"'
                        + ' class="btn btn-xs js-subfix-pay-link"'
                        + ' style="font-size:10px;padding:1px 7px;background:#1f6feb;color:#fff;border:0;border-radius:3px;text-decoration:none;margin-left:4px"'
                        + ' onclick="event.stopPropagation()"><i class="fa fa-external-link"></i> Pay</a>';
                }

                html += '<div class="js-subfix-tx-row" data-tx-id="' + tx.id + '"'
                    + ' style="padding:9px 12px;border-bottom:1px solid #21262d;cursor:pointer;transition:background .15s"'
                    + ' onmouseover="this.style.background=\'#1f2937\'" onmouseout="if(!this.classList.contains(\'active-tx\'))this.style.background=\'\'">'
                    + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:3px">'
                    +   '<span style="font-size:12px;font-weight:700;color:#e6edf3">Tx #' + esc(tx.id) + '</span>'
                    +   '<span style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">'
                    +     '<span style="width:7px;height:7px;border-radius:50%;background:' + dotColor + ';display:inline-block"></span>'
                    +     '<span style="font-size:11px;color:' + dotColor + '">' + esc(tx.status) + '</span>'
                    +     isFixed
                    +     payBtn
                    +   '</span>'
                    + '</div>'
                    + '<div style="display:flex;gap:10px;font-size:11px;color:#8b949e">'
                    +   '<span>' + esc(tx.currency || 'UGX') + ' ' + esc(tx.amount) + '</span>'
                    +   '<span>' + esc(tx.payment_method || '—') + '</span>'
                    +   '<span>' + esc(tx.transaction_type || '—') + '</span>'
                    + '</div>'
                    + '<div style="font-size:10px;color:#6e7681;margin-top:2px;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(ref) + '">'
                    +   esc(ref.length > 32 ? ref.slice(0, 32) + '…' : ref)
                    + '</div>'
                    + '<div style="font-size:10px;color:#6e7681">' + esc(tx.created_at) + '</div>'
                    + (tx.error_message ? '<div style="font-size:10px;color:#f85149;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(tx.error_message) + '">⚠ ' + esc(tx.error_message.length > 50 ? tx.error_message.slice(0, 50) + '…' : tx.error_message) + '</div>' : '')
                    + '</div>';
            });

            el.innerHTML = html;
        }

        // Highlight the active row
        function highlightActiveTx(txId) {
            $('#subFixLabTxList .js-subfix-tx-row').each(function () {
                var isActive = String($(this).data('txId')) === String(txId);
                $(this).toggleClass('active-tx', isActive);
                this.style.background = isActive ? '#1f2937' : '';
                $(this).find('span:first').css('color', isActive ? '#58a6ff' : '#e6edf3');
            });
        }

        // ─────────────────────────────────────────────────────────────────
        //  Inspector area (right panel top)
        // ─────────────────────────────────────────────────────────────────
        function renderInspectArea(tx, d) {
            var n   = d.normalized || {};
            var raw = d.raw_gateway_response || {};
            var normKind = n.status_normalized === 'completed' ? 'good'
                         : n.status_normalized === 'failed'    ? 'bad' : 'warn';

            var html = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">'
                + statusPill((n.status_normalized || 'unknown').toUpperCase(), normKind)
                + '<span style="font-size:11px;color:#9fb2d9">gateway=<b>' + esc(n.gateway || d.gateway) + '</b></span>'
                + '<span style="font-size:11px;color:#9fb2d9">raw=<b>' + esc(n.status_raw) + '</b></span>'
                + (n.error_code ? '<span style="font-size:11px;color:#f85149">code=' + esc(n.error_code) + '</span>' : '')
                + (n.message ? '<span style="font-size:11px;color:#f85149">' + esc(n.message) + '</span>' : '')
                + '</div>';

            // Tx details row
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:4px 10px;font-size:11px;color:#c9d1d9;margin-bottom:8px">';
            var txFields = [
                ['Tx #', '#' + esc(tx.id)],
                ['Tx Status', statusPill(tx.status, txStatusKind(tx.status))],
                ['Amount', esc(tx.currency || 'UGX') + ' ' + esc(tx.amount)],
                ['Method', esc(tx.payment_method)],
                ['Type', esc(tx.transaction_type)],
                ['Tracking ID', esc(tx.pesapal_tracking_id)],
                ['Merchant Ref', esc(tx.merchant_reference)],
                ['Confirm Code', esc(tx.confirmation_code)],
                ['Date', esc(tx.created_at)],
                ['Fixed?', tx.is_fixed ? '<span style="color:#3fb950;font-weight:700">Yes (' + esc(tx.fix_successful) + ')</span>' : 'No'],
            ];
            txFields.forEach(function (f) {
                if (f[1] && f[1] !== '—') {
                    html += '<div><span style="color:#6e7681">' + f[0] + ':</span> ' + f[1] + '</div>';
                }
            });
            html += '</div>';

            // Raw response collapsible
            if (raw && Object.keys(raw).length) {
                html += '<details style="cursor:pointer"><summary style="font-size:10px;color:#8b949e;user-select:none">Raw Gateway Response</summary>'
                    + '<pre style="background:#060d18;border:1px solid #30363d;padding:8px;font-size:10px;color:#79c0ff;max-height:180px;overflow:auto;margin-top:4px;white-space:pre-wrap;word-break:break-all">'
                    + esc(prettyRaw(raw)) + '</pre></details>';
            }

            var el = document.getElementById('subFixLabInspectArea');
            if (el) el.innerHTML = html;
        }

        function clearInspectArea(msg) {
            var el = document.getElementById('subFixLabInspectArea');
            if (el) el.innerHTML = '<div style="color:#4a5878;font-size:12px;text-align:center;padding:14px 0">' + esc(msg || '') + '</div>';
        }

        // ─────────────────────────────────────────────────────────────────
        //  Select a transaction and inspect it
        // ─────────────────────────────────────────────────────────────────
        function selectTx(tx) {
            singleState.activeTx = tx;
            highlightActiveTx(tx.id);

            var badge = document.getElementById('subFixLabActiveTxBadge');
            if (badge) badge.textContent = '— Tx #' + tx.id + ' (' + (tx.status || '') + ')';

            // Resolve reference and gateway from this tx
            var txRef = tx.pesapal_tracking_id || tx.merchant_reference || '';
            var txGw  = String(tx.payment_method || '').toLowerCase().indexOf('flutter') >= 0 ? 'flutterwave' : 'pesapal';

            $('#subFixLabRef').val(txRef);
            $('#subFixLabGateway').val(txGw);

            if (!txRef) {
                clearInspectArea('This transaction has no payment reference — gateway inspect unavailable.');
                logLine('Tx #' + tx.id + ' has no payment reference.', '#f8c471');
                return;
            }

            logLine('Inspecting Tx #' + tx.id + ' [' + txGw + '] ref=' + txRef + ' …', '#f8c471');
            clearInspectArea('Fetching gateway status…');

            $.post(inspectUrl, {
                _token:          token,
                subscription_id: singleState.subscriptionId,
                transaction_id:  tx.id,
                reference:       txRef,
                gateway:         txGw,
            })
            .done(function (res) {
                if (!res || !res.success) {
                    clearInspectArea('Inspect failed: ' + (res && res.message ? res.message : 'unknown'));
                    logLine('Inspect failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                    return;
                }
                var d = res.data || {};
                renderInspectArea(tx, d);
                // Refresh tx list if updated
                if (d.all_transactions && d.all_transactions.length) {
                    singleState.allTransactions = d.all_transactions;
                    renderTxList(d.all_transactions);
                    highlightActiveTx(tx.id);
                }
                logLine('Inspect OK — gateway=' + esc(d.gateway) + ' status=' + esc((d.normalized || {}).status_normalized), '#7dffa1');
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                clearInspectArea('Inspect request failed: ' + msg);
                logLine('Inspect error: ' + msg, '#ff7b7b');
            });
        }

        // ─────────────────────────────────────────────────────────────────
        //  Initial subscription load (open modal)
        // ─────────────────────────────────────────────────────────────────
        function loadSubscription(subscriptionId) {
            var subEl = document.getElementById('subFixLabSubSummary');
            var txEl  = document.getElementById('subFixLabTxList');
            if (subEl) subEl.innerHTML = '<div style="font-size:10px;text-transform:uppercase;color:#8b949e;margin-bottom:6px;letter-spacing:.5px"><i class="fa fa-credit-card"></i> Subscription</div><div style="color:#4a5878">Loading…</div>';
            if (txEl)  txEl.innerHTML  = '<div style="color:#4a5878;font-size:12px;padding:24px;text-align:center">Loading…</div>';
            clearInspectArea('Loading subscription…');

            $.post(inspectUrl, {
                _token:          token,
                subscription_id: subscriptionId,
                reference:       '',    // empty = just load sub + tx list, skip gateway
                gateway:         'auto',
            })
            .done(function (res) {
                if (!res || !res.success) {
                    logLine('Load failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                    clearInspectArea('Failed to load subscription.');
                    return;
                }
                var d = res.data || {};
                singleState.allTransactions = d.all_transactions || [];
                renderSubSummary(d.subscription, d.user);
                renderTxList(singleState.allTransactions);

                logLine('Loaded Sub #' + esc(subscriptionId) + ' — ' + singleState.allTransactions.length + ' transaction(s).', '#9ad1ff');

                // Auto-select: prefer pending/processing, then failed, then first
                var bestTx = null;
                for (var i = 0; i < singleState.allTransactions.length; i++) {
                    var s = String(singleState.allTransactions[i].status || '').toLowerCase();
                    if (s === 'pending' || s === 'processing') { bestTx = singleState.allTransactions[i]; break; }
                }
                if (!bestTx) {
                    for (var j = 0; j < singleState.allTransactions.length; j++) {
                        if (String(singleState.allTransactions[j].status || '').toLowerCase() === 'failed') {
                            bestTx = singleState.allTransactions[j]; break;
                        }
                    }
                }
                if (!bestTx && singleState.allTransactions.length) {
                    bestTx = singleState.allTransactions[0];
                }

                if (bestTx) {
                    logLine('Auto-selecting Tx #' + bestTx.id + ' (' + bestTx.status + ')…', '#9ad1ff');
                    selectTx(bestTx);
                } else {
                    clearInspectArea('No transactions found for this subscription.');
                }
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                logLine('Load error: ' + msg, '#ff7b7b');
                clearInspectArea('Failed to load: ' + msg);
            });
        }

        // ─────────────────────────────────────────────────────────────────
        //  Apply fix action
        // ─────────────────────────────────────────────────────────────────
        function runFix(action) {
            var tx  = singleState.activeTx;
            var ref = String($('#subFixLabRef').val() || '').trim();
            var gw  = String($('#subFixLabGateway').val() || 'auto').trim();

            if (!singleState.subscriptionId) {
                setActionResult('<span class="text-danger">No subscription loaded.</span>');
                return;
            }

            var days = parseInt($('#subFixLabExtendDays').val(), 10) || 30;

            logLine('Running action: ' + action + (tx ? ' on Tx #' + tx.id : '') + ' …', '#f8c471');

            $.post(applyFixUrl, {
                _token:          token,
                action:          action,
                subscription_id: singleState.subscriptionId,
                transaction_id:  tx ? tx.id : null,
                reference:       ref,
                gateway:         gw,
                days:            days,
            })
            .done(function (res) {
                if (!res || !res.success) {
                    logLine('Action failed: ' + (res && res.message ? res.message : 'unknown'), '#ff7b7b');
                    setActionResult('<span class="text-danger">Failed: ' + esc(res && res.message ? res.message : 'unknown') + '</span>');
                    return;
                }
                var d = res.data || {};
                logLine('✅ ' + (res.message || action), '#7dffa1');
                logLine('   Sub status: ' + esc(d.subscription_status) + ' | Payment: ' + esc(d.payment_status), '#b8ffd4');
                setActionResult('<span style="color:#7dffa1"><b>Done:</b> ' + esc(res.message || action) + '</span>');

                // Refresh tx list and subscription summary
                if (d.all_transactions) {
                    singleState.allTransactions = d.all_transactions;
                    renderTxList(d.all_transactions);
                    if (singleState.activeTx) {
                        // Find updated tx
                        var updated = d.all_transactions.find(function(t) { return t.id === singleState.activeTx.id; });
                        if (updated) { singleState.activeTx = updated; }
                        highlightActiveTx(singleState.activeTx ? singleState.activeTx.id : null);
                    }
                }
                if (d.subscription) renderSubSummary(d.subscription, d.user);
                if (d.normalized && singleState.activeTx) renderInspectArea(singleState.activeTx, d);
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                logLine('Action error: ' + msg, '#ff7b7b');
                setActionResult('<span class="text-danger">Request failed: ' + esc(msg) + '</span>');
            });
        }

        // ─────────────────────────────────────────────────────────────────
        //  Event bindings — single Fix Lab
        // ─────────────────────────────────────────────────────────────────

        // Open modal from row Fix Lab button
        $(document)
            .off('click.subfixlab', '.js-sub-fix-lab')
            .on('click.subfixlab', '.js-sub-fix-lab', function () {
                var subId  = $(this).data('id') || null;
                singleState = { subscriptionId: subId, activeTx: null, allTransactions: [] };

                // Reset UI
                $('#subFixLabLog').empty();
                setActionResult('');
                clearInspectArea('Loading…');
                $('#subFixLabActiveTxBadge').text('');
                $('#subFixLabRef').val('');
                $('#subFixLabGateway').val('auto');
                $('#subFixLabExtendDays').val(30);
                $('#subFixLabTitle').html('<i class="fa fa-flask"></i> Subscription Fix Lab — #' + subId);

                $('#subFixLabModal').modal('show');

                logLine('Loading subscription #' + subId + '…', '#9ad1ff');
                loadSubscription(subId);
            });

        // Click on a transaction row to select it
        $(document)
            .off('click.subfixrow', '#subFixLabTxList')
            .on('click.subfixrow', '#subFixLabTxList', function (e) {
                var row = $(e.target).closest('.js-subfix-tx-row');
                if (!row.length) return;
                var txId = row.data('txId');
                var tx   = singleState.allTransactions.find(function (t) { return String(t.id) === String(txId); });
                if (tx) selectTx(tx);
            });

        // Re-Inspect button (footer)
        $(document)
            .off('click.subfixinspect', '#subFixLabInspectBtn')
            .on('click.subfixinspect', '#subFixLabInspectBtn', function () {
                if (singleState.activeTx) {
                    selectTx(singleState.activeTx);
                } else {
                    logLine('No transaction selected.', '#f8c471');
                }
            });

        // Action buttons
        $(document)
            .off('click.subfixaction', '.js-subfix-action')
            .on('click.subfixaction', '.js-subfix-action', function () {
                var action = String($(this).data('action') || '');
                if (!action) return;
                runFix(action);
            });

        // ─────────────────────────────────────────────────────────────────
        //  New Payment modal
        // ─────────────────────────────────────────────────────────────────

        // Open New Payment modal
        $(document)
            .off('click.subfixnewpay', '#subFixLabNewPayBtn')
            .on('click.subfixnewpay', '#subFixLabNewPayBtn', function () {
                if (!singleState.subscriptionId) {
                    logLine('No subscription loaded.', '#f8c471');
                    return;
                }
                // Pre-fill phone from subscription user if available
                var subEl = document.getElementById('subFixLabSubSummary');
                $('#subInitPayPhone').val('');
                $('#subInitPayGateway').val('flutterwave');
                $('#subInitPayResult').html('');
                $('#subInitPaySubmitBtn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Initiate Payment');
                $('#subInitPayModal').modal('show');
            });

        // Update hint text when gateway changes
        $(document)
            .off('change.subinitgw', '#subInitPayGateway')
            .on('change.subinitgw', '#subInitPayGateway', function () {
                var gw = $(this).val();
                var hint = gw === 'flutterwave'
                    ? 'With Flutterwave + phone: backend solves captcha and sends USSD push automatically'
                    : 'Phone required for Pesapal';
                $('#subInitPayPhoneHint').text(hint);
            });

        // Submit new payment initiation
        $(document)
            .off('click.subinitpay', '#subInitPaySubmitBtn')
            .on('click.subinitpay', '#subInitPaySubmitBtn', function () {
                if (!initiatePaymentUrl) {
                    $('#subInitPayResult').html('<span style="color:#f85149">initiatePaymentUrl not configured.</span>');
                    return;
                }
                var phone   = String($('#subInitPayPhone').val() || '').trim();
                var gateway = String($('#subInitPayGateway').val() || 'flutterwave').trim();

                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Initiating…');

                var modeLabel = (gateway === 'flutterwave' && phone)
                    ? 'Sending USSD push via Flutterwave…'
                    : (gateway === 'flutterwave' ? 'Generating hosted checkout link…' : 'Contacting Pesapal…');
                $('#subInitPayResult').html('<span style="color:#f8c471">' + modeLabel + '</span>');

                $.post(initiatePaymentUrl, {
                    _token:          token,
                    subscription_id: singleState.subscriptionId,
                    phone:           phone,
                    gateway:         gateway,
                })
                .done(function (res) {
                    $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Initiate Payment');
                    if (!res || !res.success) {
                        $('#subInitPayResult').html('<span style="color:#f85149">Failed: ' + esc((res && res.message) || 'unknown error') + '</span>');
                        return;
                    }

                    // Refresh tx list + sub summary immediately
                    var d = (res.data || {});
                    if (d.all_transactions) {
                        singleState.allTransactions = d.all_transactions;
                        renderTxList(d.all_transactions);
                    }
                    if (d.subscription) renderSubSummary(d.subscription, d.user);

                    if (res.auto_push) {
                        // Backend dispatched SolveFLWCaptchaJob — show live status, poll until confirmed
                        var subscId  = res.subscription_id || singleState.subscriptionId;
                        var trackId  = res.tracking_id || '—';
                        var captchaUrl = res.redirect_url || '';
                        $('#subInitPayResult').html(
                            '<div style="color:#7dffa1;font-size:13px;margin-bottom:8px">'
                            + '<i class="fa fa-check-circle"></i> <b>USSD push dispatched!</b></div>'
                            + '<div style="font-size:11px;color:#c9d1d9;margin-bottom:8px">'
                            + 'Backend is solving the math captcha automatically.<br>'
                            + 'The customer will receive a USSD PIN prompt on their phone.</div>'
                            + '<div id="subInitPayStatus" style="font-size:11px;padding:6px 10px;background:#0d1117;border:1px solid #30363d;border-radius:4px;color:#f8c471">'
                            + '<i class="fa fa-spinner fa-spin"></i> Waiting for captcha solve…</div>'
                            + '<div style="font-size:10px;color:#6e7681;margin-top:6px">Tracking: ' + esc(trackId) + '</div>'
                        );
                        logLine('✅ FLW USSD push dispatched via backend | Sub #' + subscId + ' | Tracking: ' + esc(trackId), '#7dffa1');

                        // Poll inspect endpoint every 5s for up to ~2 minutes
                        var pollCount    = 0;
                        var maxPolls     = 24;
                        var pollInterval = setInterval(function () {
                            pollCount++;
                            if (pollCount > maxPolls) {
                                clearInterval(pollInterval);
                                $('#subInitPayStatus').html('<span style="color:#f8c471">Timed out — check status manually via Re-Inspect.</span>');
                                return;
                            }
                            $.get(inspectUrl, { subscription_id: subscId })
                            .done(function (ir) {
                                var payStatus = ((ir.data || {}).payment_status || '').toLowerCase();
                                var subStatus = ((ir.data || {}).subscription_status || '').toLowerCase();
                                var sub       = (ir.data || {}).subscription || {};
                                var usr       = (ir.data || {}).user || {};

                                if (subStatus === 'active' || payStatus === 'completed' || payStatus === 'active') {
                                    clearInterval(pollInterval);
                                    $('#subInitPayStatus').html('<span style="color:#7dffa1"><i class="fa fa-check-circle"></i> <b>Payment confirmed! Subscription is now active.</b></span>');
                                    logLine('✅ Payment confirmed — subscription #' + subscId + ' is now active', '#7dffa1');
                                    if (sub.id) renderSubSummary(sub, usr);
                                } else if (payStatus === 'awaitingpin') {
                                    $('#subInitPayStatus').html('<span style="color:#58a6ff"><i class="fa fa-mobile"></i> USSD sent — customer needs to enter PIN on their phone.</span>');
                                } else if (payStatus === 'captchafailed') {
                                    clearInterval(pollInterval);
                                    var fbHtml = '<span style="color:#f85149"><i class="fa fa-exclamation-triangle"></i> Captcha auto-solve failed.</span>';
                                    if (captchaUrl) {
                                        fbHtml += ' <a href="' + esc(captchaUrl) + '" target="_blank" rel="noopener noreferrer"'
                                            + ' style="display:inline-block;margin-left:6px;padding:3px 10px;background:#1f6feb;color:#fff;border-radius:4px;text-decoration:none;font-size:11px">'
                                            + '<i class="fa fa-external-link"></i> Open Captcha Page</a>';
                                    }
                                    $('#subInitPayStatus').html(fbHtml);
                                    logLine('⚠ Captcha solve failed for sub #' + subscId, '#f8c471');
                                } else {
                                    $('#subInitPayStatus').html('<span style="color:#f8c471"><i class="fa fa-spinner fa-spin"></i> Status: ' + esc(payStatus || 'processing') + '…</span>');
                                }
                            });
                        }, 5000);

                    } else {
                        // Link mode (no-phone FLW hosted checkout, or Pesapal)
                        var payUrl = res.redirect_url || '';
                        var payMsg = res.payment_message || '';

                        // Auto-open link immediately (inside click handler so popup won't be blocked)
                        var popupOpened = false;
                        if (payUrl) {
                            try {
                                var popup = window.open(payUrl, '_blank', 'noopener,noreferrer');
                                popupOpened = popup !== null && popup !== undefined;
                            } catch (e) { popupOpened = false; }
                        }

                        var resultHtml = '<span style="color:#7dffa1"><i class="fa fa-check-circle"></i> <b>Payment initiated!</b></span>';
                        if (payUrl) {
                            if (popupOpened) {
                                resultHtml += '<div style="margin-top:6px;font-size:12px;color:#58a6ff">'
                                    + '<i class="fa fa-external-link"></i> Payment page opened in new tab.</div>';
                            } else {
                                resultHtml += '<div style="margin-top:6px;font-size:11px;color:#f8c471">'
                                    + '&#9888; Popup may have been blocked — click below to open manually.</div>';
                            }
                            resultHtml += '<div style="margin-top:8px">'
                                + '<a href="' + esc(payUrl) + '" target="_blank" rel="noopener noreferrer"'
                                + ' style="display:inline-block;padding:5px 14px;background:#1f6feb;color:#fff;border-radius:4px;text-decoration:none;font-size:12px">'
                                + '<i class="fa fa-external-link"></i> Open Payment Link</a></div>'
                                + '<div style="margin-top:6px;font-size:10px;color:#6e7681;word-break:break-all">'
                                + esc(payUrl) + '</div>';
                        } else if (payMsg) {
                            resultHtml += '<div style="margin-top:6px;font-size:11px;color:#f8c471">' + esc(payMsg) + '</div>';
                        }
                        resultHtml += '<div style="font-size:10px;color:#6e7681;margin-top:6px">Tracking: ' + esc(res.tracking_id || '—') + '</div>';
                        $('#subInitPayResult').html(resultHtml);

                        logLine('✅ Payment link generated via ' + esc(gateway) + ' | Tracking: ' + esc(res.tracking_id || '—'), '#7dffa1');
                        if (payUrl) {
                            logLine('   Pay URL: ' + payUrl, '#58a6ff');
                            logLine('   Auto-opened in new tab: ' + (popupOpened ? 'yes' : 'blocked by browser'), popupOpened ? '#7dffa1' : '#f8c471');
                        }
                    }
                })
                .fail(function (xhr) {
                    $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Initiate Payment');
                    var msg = (xhr.responseJSON || {}).message || ('HTTP ' + (xhr.status || 'error'));
                    $('#subInitPayResult').html('<span style="color:#f85149">Request failed: ' + esc(msg) + '</span>');
                    logLine('Initiate payment error: ' + msg, '#ff7b7b');
                });
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

            var gwNorm = res.normalized || {};
            html += '<div style="background:#060814;border:1px solid #2f3957;border-radius:4px;padding:8px;margin-bottom:8px">'
                + '<div style="font-size:10px;text-transform:uppercase;color:#93a4c7;margin-bottom:4px">Gateway</div>'
                + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 16px;line-height:1.6">';
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
            if (!batchFixSingleUrl) { alert('Batch fix URL not configured.'); return; }
            batchState = {
                ids: ids, current: 0, total: ids.length, stopped: false, rows: [],
                results: { activated: 0, pending: 0, confirmed_failed: 0, errors: 0, skipped: 0 }
            };

            $('#subBatchProgressBar').css('width', '0%');
            $('#subBatchSummaryBar').html('Loaded <b>' + ids.length + '</b> subscription(s). Starting…');
            $('#subBatchCloseBtn').prop('disabled', true);

            var logEl = document.getElementById('subBatchTabLog');
            if (logEl) logEl.innerHTML = '';
            var detEl = document.getElementById('subBatchTabDetails');
            if (detEl) detEl.innerHTML = '<div style="color:#4a5878;font-size:12px;padding:24px;text-align:center">Item details will appear here as each subscription is processed.</div>';
            var sumEl = document.getElementById('subBatchTabSummary');
            if (sumEl) sumEl.innerHTML = '<div style="color:#7a90b9;font-size:12px;padding:20px;text-align:center">Summary will appear after the batch completes.</div>';

            var logTab = document.querySelector('.nav-tabs a[href="#subBatchTabLog"]');
            if (logTab) $(logTab).tab('show');

            bfLog('Loaded ' + ids.length + ' subscription ID(s): '
                + ids.slice(0, 15).join(', ') + (ids.length > 15 ? ' … and ' + (ids.length - 15) + ' more' : ''), '#9ad1ff');

            $('#subBatchFixModal').modal('show');

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
                if ($.admin && $.admin.grid && typeof $.admin.grid.selected === 'function') {
                    $.each($.admin.grid.selected(), function (i, val) {
                        var n = parseInt(val, 10);
                        if (!isNaN(n)) ids.push(n);
                    });
                }
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
                    if (typeof toastr !== 'undefined') { toastr.warning(notice); } else { alert(notice); }
                    return;
                }
                openBatchFix(ids);
            });

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
