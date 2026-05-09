(function () {
    function initSubTxFixModal() {
        var cfg = window.SubTxFixConfig || {};
        var inspectUrl = String(cfg.inspectUrl || '');
        var applyFixUrl = String(cfg.applyFixUrl || '');
        var token = String(cfg.token || '');

        if (!inspectUrl || !applyFixUrl || !token) {
            return;
        }

        var state = {
            transactionId: null,
        };

        function nowTs() {
            return new Date().toTimeString().slice(0, 8);
        }

        function logLine(message, color) {
            var el = $('#subTxFixLog');
            if (!el.length) return;
            var c = color || '#9ad1ff';
            var line = '[' + nowTs() + '] ' + message + '\n';
            el.append($('<span>').css('color', c).text(line));
            el.scrollTop(el[0].scrollHeight);
        }

        function setSummary(html) {
            $('#subTxFixSummary').html(html);
        }

        function esc(v) {
            if (v === null || v === undefined) return '-';
            return String(v)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function prettyRaw(data) {
            try {
                return JSON.stringify(data, null, 2);
            } catch (e) {
                return String(data || '');
            }
        }

        function statusPill(value, kind) {
            var v = esc(value || '-');
            var bg = '#1f365b';
            if (kind === 'good') bg = '#1d5f3f';
            if (kind === 'warn') bg = '#715500';
            if (kind === 'bad') bg = '#6a1f2c';
            return '<span class="stx-pill" style="background:' + bg + '">' + v + '</span>';
        }

        function renderCards(data) {
            var s = data.subscription || {};
            var u = data.user || {};
            var t = data.transaction || {};
            var n = data.normalized || {};

            var normKind = n.status_normalized === 'completed'
                ? 'good'
                : (n.status_normalized === 'failed' ? 'bad' : 'warn');

            $('#subTxOutcome').html(
                statusPill((n.status_normalized || 'unknown').toUpperCase(), normKind)
                + ' <span style="margin-left:6px">gateway=' + esc(n.gateway || data.gateway || '-') + '</span>'
                + '<br><span style="font-size:11px;color:#9fb2d9">raw=' + esc(n.status_raw || '-')
                + ' | code=' + esc(n.error_code || '-')
                + ' | message=' + esc(n.message || '-') + '</span>'
            );

            $('#subTxSubCard').html(
                '<b>#' + esc(s.id || data.subscription_id || '-') + '</b> '
                + statusPill(s.status || data.subscription_status || '-', (String(s.status || data.subscription_status || '').toLowerCase() === 'active' ? 'good' : 'warn'))
                + ' ' + statusPill(s.payment_status || data.payment_status || '-', (String(s.payment_status || data.payment_status || '').toLowerCase() === 'completed' ? 'good' : 'warn'))
                + '<br><span style="color:#9fb2d9">Plan:</span> ' + esc(s.plan || '-')
                + ' | <span style="color:#9fb2d9">App:</span> ' + esc(s.app_type || '-')
                + ' | <span style="color:#9fb2d9">Platform:</span> ' + esc(s.platform || '-')
                + '<br><span style="color:#9fb2d9">Amount:</span> ' + esc(s.currency || 'UGX') + ' ' + esc(s.amount_paid || '-')
                + '<br><span style="color:#9fb2d9">Start:</span> ' + esc(s.start_date_time || '-')
                + ' | <span style="color:#9fb2d9">End:</span> ' + esc(s.end_date_time || '-')
            );

            $('#subTxUserCard').html(
                '<b>#' + esc(u.id || '-') + ' ' + esc(u.name || '-')</b>'
                + '<br><span style="color:#9fb2d9">Email:</span> ' + esc(u.email || '-')
                + '<br><span style="color:#9fb2d9">Phone:</span> ' + esc(u.phone_number || '-')
                + '<br><span style="color:#9fb2d9">Account:</span> ' + esc(u.account_state || '-')
                + ' | <span style="color:#9fb2d9">App:</span> ' + esc(u.app_type || '-')
                + ' | <span style="color:#9fb2d9">Platform:</span> ' + esc(u.platform || '-')
                + '<br><span style="color:#9fb2d9">Joined:</span> ' + esc(u.created_at || '-')
            );

            $('#subTxTxnCard').html(
                '<b>#' + esc(t.id || data.transaction_id || '-') + '</b> '
                + statusPill(t.status || '-', (String(t.status || '').toLowerCase() === 'completed' ? 'good' : (String(t.status || '').toLowerCase() === 'failed' ? 'bad' : 'warn')))
                + '<br><span style="color:#9fb2d9">Type:</span> ' + esc(t.transaction_type || '-')
                + ' | <span style="color:#9fb2d9">Method:</span> ' + esc(t.payment_method || '-')
                + '<br><span style="color:#9fb2d9">Amount:</span> ' + esc(t.currency || 'UGX') + ' ' + esc(t.amount || '-')
                + '<br><span style="color:#9fb2d9">Ref:</span> ' + esc(t.merchant_reference || data.reference || '-')
                + '<br><span style="color:#9fb2d9">Tracking:</span> ' + esc(t.tracking_id || '-')
                + ' | <span style="color:#9fb2d9">Confirmation:</span> ' + esc(t.confirmation_code || '-')
            );
        }

        function buildPayload(action) {
            var ref = String($('#subTxFixRef').val() || '').trim();
            var gateway = String($('#subTxFixGateway').val() || 'auto').trim();
            return {
                _token: token,
                action: action,
                transaction_id: state.transactionId,
                reference: ref,
                gateway: gateway,
            };
        }

        function callInspect() {
            var payload = buildPayload('inspect');
            if (!payload.reference && !payload.transaction_id) {
                setSummary('<span class="text-danger">Provide payment reference or open from a transaction row.</span>');
                return;
            }
            logLine('Inspecting gateway status...', '#f8c471');
            $.post(inspectUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Inspect failed: ' + (res && res.message ? res.message : 'unknown error'), '#ff7b7b');
                        setSummary('<span class="text-danger">Inspect failed.</span>');
                        return;
                    }
                    var d = res.data || {};
                    $('#subTxFixRaw').text(prettyRaw(d.raw_gateway_response || {}));
                    renderCards(d);
                    setSummary(
                        '<b>Gateway:</b> ' + (d.gateway || '-') +
                        ' | <b>Subscription:</b> #' + (d.subscription_id || '-') +
                        ' | <b>Tx:</b> #' + (d.transaction_id || '-') +
                        '<br><b>Payment Status:</b> ' + (d.payment_status || '-') +
                        ' | <b>Subscription Status:</b> ' + (d.subscription_status || '-')
                    );
                    logLine('Inspect complete for gateway: ' + (d.gateway || 'unknown'), '#7dffa1');
                })
                .fail(function (xhr) {
                    var msg = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'HTTP error';
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
            logLine('Running fix action: ' + action + ' ...', '#f8c471');
            $.post(applyFixUrl, payload)
                .done(function (res) {
                    if (!res || !res.success) {
                        logLine('Fix failed: ' + (res && res.message ? res.message : 'unknown error'), '#ff7b7b');
                        setSummary('<span class="text-danger">Fix action failed.</span>');
                        return;
                    }
                    var d = res.data || {};
                    if (d.raw_gateway_response) {
                        $('#subTxFixRaw').text(prettyRaw(d.raw_gateway_response));
                    }
                    renderCards(d);
                    logLine('Fix success: ' + (res.message || action), '#7dffa1');
                    setSummary(
                        '<span class="text-success"><b>Done:</b> ' + (res.message || action) + '</span>' +
                        '<br><b>Subscription Status:</b> ' + (d.subscription_status || '-') +
                        ' | <b>Payment Status:</b> ' + (d.payment_status || '-')
                    );
                })
                .fail(function (xhr) {
                    var msg = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'HTTP error';
                    logLine('Fix request error: ' + msg, '#ff7b7b');
                    setSummary('<span class="text-danger">Fix request failed.</span>');
                });
        }

        $(document)
            .off('click.subtxfix', '.js-subtx-fix')
            .on('click.subtxfix', '.js-subtx-fix', function () {
                state.transactionId = $(this).data('id') || null;
                var ref = String($(this).data('ref') || '');
                var gatewayHint = String($(this).data('gateway') || 'auto');

                $('#subTxFixRef').val(ref);
                $('#subTxFixGateway').val(
                    gatewayHint === 'flutterwave'
                        ? 'flutterwave'
                        : (gatewayHint === 'pesapal' ? 'pesapal' : 'auto')
                );
                $('#subTxFixLog').text('');
                $('#subTxFixRaw').text('');
                $('#subTxOutcome').text('Not inspected yet.');
                $('#subTxSubCard').text('-');
                $('#subTxUserCard').text('-');
                $('#subTxTxnCard').text('-');
                setSummary('Loaded transaction #' + state.transactionId + '. Click Inspect Gateway to fetch live status.');
                logLine('Modal opened for transaction #' + state.transactionId, '#9ad1ff');
                $('#subTxFixModal').modal('show');
            });

        $(document).off('click.subtxinspect', '#subTxInspectBtn').on('click.subtxinspect', '#subTxInspectBtn', function () {
            callInspect();
        });
        $(document).off('click.subtxforceverify', '#subTxForceVerifyBtn').on('click.subtxforceverify', '#subTxForceVerifyBtn', function () {
            runFix('force_verify');
        });
        $(document).off('click.subtxactivate', '#subTxActivateBtn').on('click.subtxactivate', '#subTxActivateBtn', function () {
            runFix('force_activate');
        });
        $(document).off('click.subtxmarkdone', '#subTxMarkCompletedBtn').on('click.subtxmarkdone', '#subTxMarkCompletedBtn', function () {
            runFix('mark_tx_completed');
        });
        $(document).off('click.subtxmarkfail', '#subTxMarkFailedBtn').on('click.subtxmarkfail', '#subTxMarkFailedBtn', function () {
            runFix('mark_tx_failed');
        });
    }

    if (typeof $ !== 'undefined') {
        $(document).ready(initSubTxFixModal);
        $(document).on('pjax:end', initSubTxFixModal);
    }
})();
