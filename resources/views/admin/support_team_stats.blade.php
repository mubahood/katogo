<div class="row" style="margin-bottom:18px">
    <div class="col-sm-2">
        <div class="info-box bg-aqua">
            <span class="info-box-icon"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Support Team</span>
                <span class="info-box-number">{{ $supportCount }}</span>
            </div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="info-box bg-red">
            <span class="info-box-icon"><i class="fa fa-ticket"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Open Tickets</span>
                <span class="info-box-number">{{ $openTickets }}</span>
            </div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="info-box bg-yellow">
            <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Pending</span>
                <span class="info-box-number">{{ $pendingTickets }}</span>
            </div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="info-box bg-green">
            <span class="info-box-icon"><i class="fa fa-list"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Tickets</span>
                <span class="info-box-number">{{ $totalTickets }}</span>
            </div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="info-box bg-orange">
            <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Unassigned</span>
                <span class="info-box-number">{{ $unassigned }}</span>
            </div>
        </div>
    </div>

    <div class="col-sm-2">
        <a href="{{ admin_url('support-tickets') }}" class="btn btn-primary btn-block" style="margin-top:10px">
            <i class="fa fa-envelope"></i> View All Tickets
        </a>
    </div>
</div>

<div class="row" style="margin-bottom:18px">
    <div class="col-sm-3">
        <div class="info-box bg-green">
            <span class="info-box-icon"><i class="fa fa-whatsapp"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Welcome Queue</span>
                <span class="info-box-number">{{ $welcomeQueueCount ?? 0 }}</span>
            </div>
        </div>
    </div>
</div>
