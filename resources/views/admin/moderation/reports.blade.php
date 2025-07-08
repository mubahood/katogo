@extends('admin::layout.main')

@section('title', 'Content Reports')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-0">Content Reports</h1>
            <p class="text-muted">Review and manage content reports from users</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['total_reports'] ?? 0 }}</h5>
                            <p class="card-text">Total Reports</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-flag fa-2x"></i>
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
                            <h5 class="card-title">{{ $stats['pending_reports'] ?? 0 }}</h5>
                            <p class="card-text">Pending Reports</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
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
                            <h5 class="card-title">{{ $stats['resolved_reports'] ?? 0 }}</h5>
                            <p class="card-text">Resolved Reports</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['urgent_reports'] ?? 0 }}</h5>
                            <p class="card-text">Urgent Reports</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="reviewing" {{ request('status') == 'reviewing' ? 'selected' : '' }}>Reviewing</option>
                                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="dismissed" {{ request('status') == 'dismissed' ? 'selected' : '' }}>Dismissed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Content Type</label>
                            <select name="content_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="movie" {{ request('content_type') == 'movie' ? 'selected' : '' }}>Movie</option>
                                <option value="user" {{ request('content_type') == 'user' ? 'selected' : '' }}>User</option>
                                <option value="comment" {{ request('content_type') == 'comment' ? 'selected' : '' }}>Comment</option>
                                <option value="chat_message" {{ request('content_type') == 'chat_message' ? 'selected' : '' }}>Chat Message</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reason</label>
                            <select name="reason" class="form-select">
                                <option value="">All Reasons</option>
                                <option value="copyright_infringement" {{ request('reason') == 'copyright_infringement' ? 'selected' : '' }}>Copyright</option>
                                <option value="inappropriate_content" {{ request('reason') == 'inappropriate_content' ? 'selected' : '' }}>Inappropriate</option>
                                <option value="spam" {{ request('reason') == 'spam' ? 'selected' : '' }}>Spam</option>
                                <option value="harassment" {{ request('reason') == 'harassment' ? 'selected' : '' }}>Harassment</option>
                            </select>
                        </div>
                        <div class="col-md-3">
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

    <!-- Reports Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Reports List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reporter</th>
                                    <th>Content Type</th>
                                    <th>Content ID</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                <tr>
                                    <td>{{ $report->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <span class="avatar-initials bg-primary">
                                                    {{ substr($report->reporter->name ?? 'U', 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $report->reporter->name ?? 'Unknown' }}</div>
                                                <small class="text-muted">{{ $report->reporter->email ?? '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ ucfirst($report->content_type) }}</span>
                                    </td>
                                    <td>{{ $report->content_id }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $report->reason)) }}</span>
                                    </td>
                                    <td>
                                        @switch($report->status)
                                            @case('pending')
                                                <span class="badge bg-warning">Pending</span>
                                                @break
                                            @case('reviewing')
                                                <span class="badge bg-info">Reviewing</span>
                                                @break
                                            @case('resolved')
                                                <span class="badge bg-success">Resolved</span>
                                                @break
                                            @case('dismissed')
                                                <span class="badge bg-secondary">Dismissed</span>
                                                @break
                                            @default
                                                <span class="badge bg-light text-dark">{{ ucfirst($report->status) }}</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @switch($report->priority)
                                            @case('urgent')
                                                <span class="badge bg-danger">Urgent</span>
                                                @break
                                            @case('high')
                                                <span class="badge bg-warning">High</span>
                                                @break
                                            @case('medium')
                                                <span class="badge bg-info">Medium</span>
                                                @break
                                            @case('low')
                                                <span class="badge bg-secondary">Low</span>
                                                @break
                                            @default
                                                <span class="badge bg-light text-dark">Normal</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        <small>{{ $report->created_at->format('M d, Y') }}</small><br>
                                        <small class="text-muted">{{ $report->created_at->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="viewReport({{ $report->id }})">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if($report->status === 'pending')
                                            <button type="button" class="btn btn-outline-success" onclick="updateReportStatus({{ $report->id }}, 'reviewing')">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            @endif
                                            @if(in_array($report->status, ['pending', 'reviewing']))
                                            <button type="button" class="btn btn-outline-danger" onclick="updateReportStatus({{ $report->id }}, 'resolved')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>No reports found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($reports->hasPages())
                <div class="card-footer">
                    {{ $reports->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="takeAction()">Take Action</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
function viewReport(reportId) {
    fetch(`/admin/moderation/reports/${reportId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('reportModalBody').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Report Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>${data.id}</td></tr>
                            <tr><td><strong>Content Type:</strong></td><td>${data.content_type}</td></tr>
                            <tr><td><strong>Content ID:</strong></td><td>${data.content_id}</td></tr>
                            <tr><td><strong>Reason:</strong></td><td>${data.reason}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${data.status}</td></tr>
                            <tr><td><strong>Priority:</strong></td><td>${data.priority || 'Normal'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Reporter Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${data.reporter?.name || 'Unknown'}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${data.reporter?.email || 'Unknown'}</td></tr>
                            <tr><td><strong>Reported:</strong></td><td>${new Date(data.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <div class="bg-light p-3 rounded">
                            ${data.description || 'No description provided'}
                        </div>
                    </div>
                </div>
                ${data.admin_notes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Admin Notes</h6>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            ${data.admin_notes}
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        })
        .catch(error => {
            console.error('Error loading report:', error);
            alert('Failed to load report details');
        });
}

function updateReportStatus(reportId, status) {
    if (!confirm(`Are you sure you want to mark this report as ${status}?`)) {
        return;
    }
    
    fetch(`/admin/moderation/reports/${reportId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update report status');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        alert('Failed to update report status');
    });
}

function takeAction() {
    // Implementation for taking moderation actions
    alert('Action functionality to be implemented');
}
</script>
@endsection
