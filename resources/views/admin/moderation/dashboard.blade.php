<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1>Content Moderation Dashboard</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $stats['pending_reports'] }}</h3>
                            <p>Pending Reports</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <a href="{{ admin_url('moderation/reports?status=pending') }}" class="small-box-footer">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $stats['total_reports'] }}</h3>
                            <p>Total Reports</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-flag"></i>
                        </div>
                        <a href="{{ admin_url('moderation/reports') }}" class="small-box-footer">
                            View All <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $stats['active_blocks'] }}</h3>
                            <p>Active Blocks</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-ban"></i>
                        </div>
                        <a href="{{ admin_url('moderation/blocks?status=active') }}" class="small-box-footer">
                            View Blocks <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $stats['reports_today'] }}</h3>
                            <p>Reports Today</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-calendar-day"></i>
                        </div>
                        <a href="{{ admin_url('moderation/logs') }}" class="small-box-footer">
                            View Logs <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Reports</h3>
                        </div>
                        <div class="card-body">
                            @if($recentReports->count() > 0)
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Reporter</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentReports as $report)
                                        <tr>
                                            <td>{{ $report->reporter->name ?? 'Unknown' }}</td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    {{ ucfirst($report->reported_content_type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $report->status === 'pending' ? 'warning' : ($report->status === 'resolved' ? 'success' : 'secondary') }}">
                                                    {{ ucfirst($report->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $report->created_at->format('M d, H:i') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="text-muted">No recent reports</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Actions -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Moderation Actions</h3>
                        </div>
                        <div class="card-body">
                            @if($recentActions->count() > 0)
                                <div class="timeline">
                                    @foreach($recentActions as $action)
                                    <div class="time-label">
                                        <span class="bg-{{ $action->severity === 'high' ? 'danger' : ($action->severity === 'medium' ? 'warning' : 'info') }}">
                                            {{ $action->created_at->format('M d') }}
                                        </span>
                                    </div>
                                    <div>
                                        <i class="fas fa-{{ $action->action_type === 'user_blocked' ? 'ban' : ($action->action_type === 'content_reported' ? 'flag' : 'shield-alt') }} bg-blue"></i>
                                        <div class="timeline-item">
                                            <h3 class="timeline-header">
                                                <strong>{{ $action->user->name ?? 'Unknown User' }}</strong>
                                                - {{ str_replace('_', ' ', ucfirst($action->action_type)) }}
                                            </h3>
                                            <div class="timeline-body">
                                                @if($action->reason)
                                                    {{ $action->reason }}
                                                @endif
                                                <br><small class="text-muted">
                                                    by {{ $action->moderator->name ?? 'System' }} at {{ $action->created_at->format('H:i') }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted">No recent actions</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <a href="{{ admin_url('moderation/reports') }}" class="btn btn-primary">
                                <i class="fa fa-flag"></i> Review All Reports
                            </a>
                            <a href="{{ admin_url('moderation/reports?status=pending') }}" class="btn btn-warning">
                                <i class="fa fa-clock"></i> Pending Reports
                            </a>
                            <a href="{{ admin_url('moderation/blocks') }}" class="btn btn-danger">
                                <i class="fa fa-ban"></i> View Blocks
                            </a>
                            <a href="{{ admin_url('moderation/logs') }}" class="btn btn-info">
                                <i class="fa fa-list"></i> Moderation Logs
                            </a>
                            <a href="{{ admin_url('moderation/statistics') }}" class="btn btn-success">
                                <i class="fa fa-chart-bar"></i> Statistics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
