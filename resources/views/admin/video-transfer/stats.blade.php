<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-database"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Transfers</span>
                <span class="info-box-number">{{ number_format($total) }}</span>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Completed</span>
                <span class="info-box-number">{{ number_format($completed) }}</span>
                @if($total > 0)
                    <small class="text-muted">{{ round(($completed / $total) * 100, 1) }}%</small>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-refresh fa-spin"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Transfers</span>
                <span class="info-box-number">{{ number_format($active) }}</span>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Failed</span>
                <span class="info-box-number">{{ number_format($failed) }}</span>
                @if($total > 0)
                    <small class="text-muted">{{ round(($failed / $total) * 100, 1) }}%</small>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="fa fa-lightbulb-o"></i>
    <strong>Quick Tips:</strong>
    <ul style="margin-bottom: 0; margin-top: 10px;">
        <li>Videos are automatically transferred when you create a new record</li>
        <li>Click "Retry" on failed transfers to try again</li>
        <li>Use the "Play" button to preview completed videos</li>
        <li>Copy the "Embed URL" to use videos in your mobile app</li>
    </ul>
</div>
