@extends('admin::layout.main')

@section('title', 'Moderation Statistics')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-0">Moderation Statistics</h1>
            <p class="text-muted">Comprehensive analytics and trends for content moderation</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-flag fa-2x mb-2"></i>
                    <h4>{{ $stats['total_reports'] ?? 0 }}</h4>
                    <p class="mb-0">Total Reports</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h4>{{ $stats['pending_reports'] ?? 0 }}</h4>
                    <p class="mb-0">Pending Reports</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-ban text-white">
                <div class="card-body text-center">
                    <i class="fas fa-ban fa-2x mb-2"></i>
                    <h4>{{ $stats['total_blocks'] ?? 0 }}</h4>
                    <p class="mb-0">User Blocks</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-check fa-2x mb-2"></i>
                    <h4>{{ $stats['resolved_reports'] ?? 0 }}</h4>
                    <p class="mb-0">Resolved</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-list fa-2x mb-2"></i>
                    <h4>{{ $stats['total_logs'] ?? 0 }}</h4>
                    <p class="mb-0">Total Actions</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-percentage fa-2x mb-2"></i>
                    <h4>{{ number_format($stats['resolution_rate'] ?? 0, 1) }}%</h4>
                    <p class="mb-0">Resolution Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Period Selector -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Time Period</label>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="7" {{ request('period', '7') == '7' ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="30" {{ request('period') == '30' ? 'selected' : '' }}>Last 30 Days</option>
                                <option value="90" {{ request('period') == '90' ? 'selected' : '' }}>Last 3 Months</option>
                                <option value="365" {{ request('period') == '365' ? 'selected' : '' }}>Last Year</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="exportStats()">
                                    <i class="fas fa-download"></i> Export Data
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="refreshStats()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Reports Over Time -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Reports Over Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="reportsChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <!-- Report Status Distribution -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Report Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- More Charts -->
    <div class="row mb-4">
        <!-- Content Types Reported -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Content Types Reported</h5>
                </div>
                <div class="card-body">
                    <canvas id="contentTypesChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <!-- Report Reasons -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Most Common Report Reasons</h5>
                </div>
                <div class="card-body">
                    <canvas id="reasonsChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Statistics Tables -->
    <div class="row mb-4">
        <!-- Top Reporters -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Reporters</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Reports</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($top_reporters ?? [] as $reporter)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <span class="avatar-initials bg-primary">
                                                    {{ substr($reporter->name ?? 'U', 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $reporter->name ?? 'Unknown' }}</div>
                                                <small class="text-muted">{{ $reporter->email ?? '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary">{{ $reporter->reports_count ?? 0 }}</span></td>
                                    <td><span class="badge bg-success">{{ number_format($reporter->success_rate ?? 0, 1) }}%</span></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No data available</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Most Reported Content -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Most Reported Content</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Content</th>
                                    <th>Type</th>
                                    <th>Reports</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($most_reported ?? [] as $content)
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-semibold">{{ $content->title ?? 'Content #' . $content->content_id }}</div>
                                            <small class="text-muted">ID: {{ $content->content_id }}</small>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info">{{ ucfirst($content->content_type) }}</span></td>
                                    <td><span class="badge bg-warning">{{ $content->reports_count ?? 0 }}</span></td>
                                    <td>
                                        @if($content->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Removed</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No data available</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Moderation Performance Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-primary">{{ number_format($metrics['avg_response_time'] ?? 0, 1) }}</h3>
                                <p class="text-muted mb-0">Avg Response Time (hours)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-success">{{ number_format($metrics['resolution_rate'] ?? 0, 1) }}%</h3>
                                <p class="text-muted mb-0">Resolution Rate</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-info">{{ number_format($metrics['active_moderators'] ?? 0) }}</h3>
                                <p class="text-muted mb-0">Active Moderators</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">{{ number_format($metrics['backlog_size'] ?? 0) }}</h3>
                                <p class="text-muted mb-0">Current Backlog</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('css')
<style>
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-initials {
    font-size: 14px;
    font-weight: 500;
}
.bg-ban {
    background-color: #e74c3c !important;
}
</style>
@endpush

@section('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart.js configurations
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom'
        }
    }
};

// Reports Over Time Chart
const reportsCtx = document.getElementById('reportsChart').getContext('2d');
new Chart(reportsCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($chart_data['reports_timeline']['labels'] ?? []) !!},
        datasets: [{
            label: 'Reports',
            data: {!! json_encode($chart_data['reports_timeline']['data'] ?? []) !!},
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.1
        }]
    },
    options: chartOptions
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($chart_data['status_distribution']['labels'] ?? []) !!},
        datasets: [{
            data: {!! json_encode($chart_data['status_distribution']['data'] ?? []) !!},
            backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#6c757d']
        }]
    },
    options: chartOptions
});

// Content Types Chart
const contentTypesCtx = document.getElementById('contentTypesChart').getContext('2d');
new Chart(contentTypesCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($chart_data['content_types']['labels'] ?? []) !!},
        datasets: [{
            label: 'Reports',
            data: {!! json_encode($chart_data['content_types']['data'] ?? []) !!},
            backgroundColor: '#17a2b8'
        }]
    },
    options: {
        ...chartOptions,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Reasons Chart
const reasonsCtx = document.getElementById('reasonsChart').getContext('2d');
new Chart(reasonsCtx, {
    type: 'horizontalBar',
    data: {
        labels: {!! json_encode($chart_data['reasons']['labels'] ?? []) !!},
        datasets: [{
            label: 'Reports',
            data: {!! json_encode($chart_data['reasons']['data'] ?? []) !!},
            backgroundColor: '#28a745'
        }]
    },
    options: {
        ...chartOptions,
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});

function exportStats() {
    const period = document.querySelector('[name="period"]').value;
    window.location.href = `/admin/moderation/statistics/export?period=${period}`;
}

function refreshStats() {
    location.reload();
}
</script>
@endsection
