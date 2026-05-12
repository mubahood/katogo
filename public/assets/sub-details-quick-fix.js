(function () {
    var cfg = window.SubFixConfig || {};
    var actionTpl = cfg.actionUrl || '';
    var detailsTpl = cfg.detailsUrl || '';
    var token = cfg.token || '';

    // Details popup
    $(document).off('click.subdet', '.js-sub-details').on('click.subdet', '.js-sub-details', function () {
        var id = $(this).data('id');
        if (!id) return;
        var body = $('#subDetailBody');
        body.html('<div class="text-center" style="padding:30px 0"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
        $('#subDetailFullLink').attr('href', detailsTpl.replace('__ID__', id).replace('/ajax-details', ''));
        $('#subDetailsModal').modal('show');

        $.ajax({
            url: detailsTpl.replace('__ID__', encodeURIComponent(String(id))),
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (!res || !res.success) {
                    body.html('<div class="alert alert-danger">' + (res && res.message ? res.message : 'Failed to load details.') + '</div>');
                    return;
                }
                var d = res.data;
                var s = d.subscription;
                var u = d.user;
                var txns = d.transactions || [];

                function badge(val, map) {
                    var cls = (map && map[val]) ? map[val] : 'default';
                    return '<span class="label label-' + cls + '">' + (val || '-') + '</span>';
                }

                var statusMap = { Active: 'success', Pending: 'warning', Expired: 'danger', Cancelled: 'default', Failed: 'danger' };
                var payMap = { Completed: 'success', Pending: 'warning', Processing: 'info', Failed: 'danger' };

                var txnRows = '';
                for (var i = 0; i < txns.length; i++) {
                    var t = txns[i];
                    txnRows += '<tr><td>' + (t.id || '-') + '</td><td>' + (t.amount || '0') + ' ' + (t.currency || '') + '</td>'
                        + '<td>' + badge(t.status, { success: 'success', pending: 'warning', failed: 'danger' }) + '</td>'
                        + '<td>' + (t.payment_method || '-') + '</td>'
                        + '<td style="font-size:11px">' + (t.created_at || '-') + '</td></tr>';
                }

                var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px">';
                function cell(k, v) { return '<div style="background:#fff;border:1px solid #dde2e7;padding:7px 10px"><div style="font-size:10px;text-transform:uppercase;color:#6c7f8f;margin-bottom:2px">' + k + '</div><div style="font-size:13px;font-weight:700;color:#2d3a47">' + (v || '-') + '</div></div>'; }

                html += cell('Status', badge(s.status, statusMap));
                html += cell('Payment', badge(s.payment_status, payMap));
                html += cell('Plan', s.plan_name || '-');
                html += cell('Amount', (s.currency || 'UGX') + ' ' + (s.amount_paid ? Number(s.amount_paid).toLocaleString() : '0'));
                html += cell('Start', s.start_date_time || '-');
                html += cell('End', s.end_date_time || '-');
                html += cell('App', s.app_type || '-');
                html += cell('Platform', s.platform || '-');
                html += cell('Days', s.days || '-');
                html += '</div>';

                html += '<div style="background:#fff;border:1px solid #dde2e7;padding:8px 10px;margin-bottom:10px">';
                html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#5a6a78;margin-bottom:6px"><i class="fa fa-user"></i> Subscriber</div>';
                html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">';
                html += cell('Name', u.name);
                html += cell('Phone', u.phone ? '<a href="https://wa.me/' + u.phone.replace(/\D/g, '') + '" target="_blank">' + u.phone + '</a>' : '-');
                html += cell('Email', u.email);
                html += cell('Account State', u.account_state || '-');
                html += cell('Member Since', u.created_at || '-');
                html += cell('Last Online', u.last_online_at || '-');
                html += '</div></div>';

                if (s.pesapal_tracking_id) {
                    html += '<div style="background:#fff;border:1px solid #dde2e7;padding:7px 10px;margin-bottom:10px">';
                    html += '<div style="font-size:10px;text-transform:uppercase;color:#6c7f8f;margin-bottom:2px">Pesapal Tracking ID</div>';
                    html += '<code style="font-size:12px">' + s.pesapal_tracking_id + '</code></div>';
                }

                if (txns.length) {
                    html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#5a6a78;margin-bottom:4px"><i class="fa fa-exchange"></i> Transactions (' + txns.length + ')</div>';
                    html += '<div style="overflow-x:auto"><table class="table table-condensed table-bordered" style="background:#fff;font-size:12px;margin-bottom:0">';
                    html += '<thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th></tr></thead>';
                    html += '<tbody>' + txnRows + '</tbody></table></div>';
                } else {
                    html += '<div class="text-muted" style="font-size:12px">No transactions recorded.</div>';
                }

                body.html(html);
            },
            error: function (xhr) {
                var msg = 'Failed to load details.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                body.html('<div class="alert alert-danger">' + msg + '</div>');
            }
        });
    });

    // Quick Fix
    $(document).off('click.subfix', '.js-sub-quick-fix').on('click.subfix', '.js-sub-quick-fix', function () {
        var btn = $(this);
        var id = btn.data('id');
        var action = String(btn.data('action') || '');
        if (!id || !action) return;

        var isRecheck = action === 'recheck-payment';
        var headerColor = isRecheck ? '#b07d00' : '#1a7a3c';
        var icon = isRecheck ? 'fa-refresh' : 'fa-bolt';
        var label = isRecheck ? 'Re-check Payment' : 'Activate Subscription';

        $('#subFixModalHeader').css('background', headerColor);
        $('#subFixModalTitle').html('<i class="fa ' + icon + '"></i> ' + label + ' - #' + id);
        $('#subFixSpinner').show().css('color', headerColor);
        $('#subFixStatusText').text('Sending request...').css('color', '#333');
        $('#subFixLog').html('<span style="color:#888">> Initiating ' + action + ' for subscription #' + id + '...</span>');
        $('#subFixResult').hide().html('');
        $('#subFixCloseBtn').prop('disabled', true);
        $('#subFixModal').modal('show');

        function log(msg, color) {
            var ts = new Date().toTimeString().slice(0, 8);
            $('#subFixLog').append('<br><span style="color:' + (color || '#444') + '">[' + ts + '] ' + msg + '</span>');
            var el = document.getElementById('subFixLog');
            el.scrollTop = el.scrollHeight;
        }

        log('Connected. Running: ' + action + '...');

        $.ajax({
            url: actionTpl.replace('__ID__', encodeURIComponent(String(id))),
            type: 'POST',
            dataType: 'json',
            data: { _token: token, action: action },
            success: function (res) {
                var ok = res && res.success;
                $('#subFixSpinner').hide();

                if (ok) {
                    log('[OK] ' + (res.message || 'Completed successfully.'), '#1a7a3c');
                    if (res.steps && Array.isArray(res.steps)) {
                        for (var i = 0; i < res.steps.length; i++) {
                            log('  - ' + res.steps[i], '#2c6fad');
                        }
                    }
                    $('#subFixStatusText').text('Completed').css('color', '#1a7a3c');
                    $('#subFixResult').show().html(
                        '<div class="alert alert-success" style="margin:0;padding:8px 12px;font-size:13px">'
                        + '<i class="fa fa-check-circle"></i> ' + (res.message || 'Action completed successfully.')
                        + '</div>'
                    );
                    setTimeout(function () {
                        $('#subFixModal').modal('hide');
                        location.reload();
                    }, 1800);
                } else {
                    log('[X] ' + (res && res.message ? res.message : 'Action failed.'), '#c0392b');
                    $('#subFixStatusText').text('Failed').css('color', '#c0392b');
                    $('#subFixResult').show().html(
                        '<div class="alert alert-danger" style="margin:0;padding:8px 12px;font-size:13px">'
                        + '<i class="fa fa-times-circle"></i> ' + (res && res.message ? res.message : 'Action failed.')
                        + '</div>'
                    );
                }
                $('#subFixCloseBtn').prop('disabled', false);
            },
            error: function (xhr) {
                var msg = 'Request failed.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr && xhr.status) {
                    msg = 'HTTP ' + xhr.status + ' error.';
                }
                $('#subFixSpinner').hide();
                log('[X] ' + msg, '#c0392b');
                $('#subFixStatusText').text('Error').css('color', '#c0392b');
                $('#subFixResult').show().html(
                    '<div class="alert alert-danger" style="margin:0;padding:8px 12px;font-size:13px">'
                    + '<i class="fa fa-exclamation-triangle"></i> ' + msg
                    + '</div>'
                );
                $('#subFixCloseBtn').prop('disabled', false);
            }
        });
    });

    if (typeof $ !== 'undefined') {
        $(document).ready(function () {});
        $(document).on('pjax:end', function () {});
    }
}());
