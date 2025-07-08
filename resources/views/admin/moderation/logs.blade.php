@extends('admin::layout.main')

@section('title', 'Moderation Logs')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-0">Moderation Logs</h1>
            <p class="text-muted">Track all moderation actions and system events</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['total_logs'] ?? 0 }}</h5>
                            <p class="card-text">Total Logs</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-list fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['today_logs'] ?? 0 }}</h5>
                            <p class="card-text">Today's Actions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-day fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['admin_actions'] ?? 0 }}</h5>
                            <p class="card-text">Admin Actions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['system_actions'] ?? 0 }}</h5>
                            <p class="card-text">System Actions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cog fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Action Type</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <option value="report_created" {{ request('action') == 'report_created' ? 'selected' : '' }}>Report Created</option>
                                <option value="report_updated" {{ request('action') == 'report_updated' ? 'selected' : '' }}>Report Updated</option>
                                <option value="user_blocked" {{ request('action') == 'user_blocked' ? 'selected' : '' }}>User Blocked</option>
                                <option value="user_unblocked" {{ request('action') == 'user_unblocked' ? 'selected' : '' }}>User Unblocked</option>
                                <option value="content_removed" {{ request('action') == 'content_removed' ? 'selected' : '' }}>Content Removed</option>
                                <option value="content_restored" {{ request('action') == 'content_restored' ? 'selected' : '' }}>Content Restored</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Actor Type</label>
                            <select name="actor_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="admin" {{ request('actor_type') == 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="user" {{ request('actor_type') == 'user' ? 'selected' : '' }}>User</option>
                                <option value="system" {{ request('actor_type') == 'system' ? 'selected' : '' }}>System</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Content Type</label>
                            <select name="content_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="movie" {{ request('content_type') == 'movie' ? 'selected' : '' }}>Movie</option>
                                <option value="user" {{ request('content_type') == 'user' ? 'selected' : '' }}>User</option>
                                <option value="comment" {{ request('content_type') == 'comment' ? 'selected' : '' }}>Comment</option>
                                <option value="report" {{ request('content_type') == 'report' ? 'selected' : '' }}>Report</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-select">
                                <option value="">All Time</option>
                                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                                <option value="week" {{ request('date_range') == 'week' ? 'selected' : '' }}>This Week</option>
                                <option value="month" {{ request('date_range') == 'month' ? 'selected' : '' }}>This Month</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Actor or content..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ url()->current() }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Moderation Activity Log</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>Content</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>
                                        @switch($log->action)
                                            @case('report_created')
                                                <span class="badge bg-warning">Report Created</span>
                                                @break
                                            @case('report_updated')
                                                <span class="badge bg-info">Report Updated</span>
                                                @break
                                            @case('user_blocked')
                                                <span class="badge bg-danger">User Blocked</span>
                                                @break
                                            @case('user_unblocked')
                                                <span class="badge bg-success">User Unblocked</span>
                                                @break
                                            @case('content_removed')
                                                <span class="badge bg-dark">Content Removed</span>
                                                @break
                                            @case('content_restored')
                                                <span class="badge bg-primary">Content Restored</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ ucfirst($log->action) }}</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($log->actor)
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    <span class="avatar-initials bg-{{ $log->actor_type === 'admin' ? 'danger' : 'primary' }}">
                                                        {{ substr($log->actor->name ?? 'S', 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $log->actor->name ?? 'System' }}</div>
                                                    <small class="text-muted">{{ ucfirst($log->actor_type) }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    <span class="avatar-initials bg-secondary">S</span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">System</div>
                                                    <small class="text-muted">Auto</small>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->content_type)
                                            <span class="badge bg-info">{{ ucfirst($log->content_type) }}</span>
                                            @if($log->content_id)
                                                <br><small class="text-muted">ID: {{ $log->content_id }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->target_user)
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    <span class="avatar-initials bg-warning">
                                                        {{ substr($log->target_user->name ?? 'U', 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $log->target_user->name ?? 'Unknown' }}</div>
                                                    <small class="text-muted">{{ $log->target_user->email ?? '' }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->details)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewLogDetails({{ $log->id }})">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $log->created_at->format('M d, Y') }}</small><br>
                                        <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="viewLogDetails({{ $log->id }})">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if($log->content_type && $log->content_id)
                                            <button type="button" class="btn btn-outline-info" onclick="viewRelatedContent('{{ $log->content_type }}', '{{ $log->content_id }}')">
                                                <i class="fas fa-external-link-alt"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-history fa-3x mb-3"></i>
                                            <p>No moderation logs found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($logs->hasPages())
                <div class="card-footer">
                    {{ $logs->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
function viewLogDetails(logId) {
    fetch(`/admin/moderation/logs/${logId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('logModalBody').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Log Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>${data.id}</td></tr>
                            <tr><td><strong>Action:</strong></td><td>${data.action}</td></tr>
                            <tr><td><strong>Content Type:</strong></td><td>${data.content_type || 'N/A'}</td></tr>
                            <tr><td><strong>Content ID:</strong></td><td>${data.content_id || 'N/A'}</td></tr>
                            <tr><td><strong>Timestamp:</strong></td><td>${new Date(data.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Actor Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Actor Type:</strong></td><td>${data.actor_type}</td></tr>
                            <tr><td><strong>Actor Name:</strong></td><td>${data.actor?.name || 'System'}</td></tr>
                            <tr><td><strong>Actor Email:</strong></td><td>${data.actor?.email || 'N/A'}</td></tr>
                            <tr><td><strong>Target User:</strong></td><td>${data.target_user?.name || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                ${data.details ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Action Details</h6>
                        <div class="bg-light p-3 rounded">
                            <pre>${JSON.stringify(data.details, null, 2)}</pre>
                        </div>
                    </div>
                </div>
                ` : ''}
                ${data.ip_address ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Technical Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>IP Address:</strong></td><td>${data.ip_address}</td></tr>
                            <tr><td><strong>User Agent:</strong></td><td>${data.user_agent || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>
                ` : ''}
            `;
            new bootstrap.Modal(document.getElementById('logModal')).show();
        })
        .catch(error => {
            console.error('Error loading log:', error);
            alert('Failed to load log details');
        });
}

function viewRelatedContent(contentType, contentId) {
    // Navigate to related content based on type
    switch(contentType) {
        case 'report':
            window.location.href = `/admin/moderation/reports?id=${contentId}`;
            break;
        case 'user':
            window.location.href = `/admin/users/${contentId}`;
            break;
        case 'movie':
            window.location.href = `/admin/movies/${contentId}`;
            break;
        default:
            alert(`Cannot view content of type: ${contentType}`);
    }
}
</script>
@endsection
