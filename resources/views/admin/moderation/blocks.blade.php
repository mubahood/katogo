@extends('admin::layout.main')

@section('title', 'User Blocks')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-0">User Blocks</h1>
            <p class="text-muted">Manage user blocking relationships</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $stats['total_blocks'] ?? 0 }}</h5>
                            <p class="card-text">Total Blocks</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-ban fa-2x"></i>
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
                            <h5 class="card-title">{{ $stats['active_blocks'] ?? 0 }}</h5>
                            <p class="card-text">Active Blocks</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-slash fa-2x"></i>
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
                            <h5 class="card-title">{{ $stats['today_blocks'] ?? 0 }}</h5>
                            <p class="card-text">Today's Blocks</p>
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
                            <h5 class="card-title">{{ $stats['unique_blockers'] ?? 0 }}</h5>
                            <p class="card-text">Unique Blockers</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search User</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or email..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-select">
                                <option value="">All Time</option>
                                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                                <option value="week" {{ request('date_range') == 'week' ? 'selected' : '' }}>This Week</option>
                                <option value="month" {{ request('date_range') == 'month' ? 'selected' : '' }}>This Month</option>
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

    <!-- Blocks Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Blocks</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Blocker</th>
                                    <th>Blocked User</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Blocked Date</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($blocks as $block)
                                <tr>
                                    <td>{{ $block->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <span class="avatar-initials bg-primary">
                                                    {{ substr($block->blocker->name ?? 'U', 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $block->blocker->name ?? 'Unknown' }}</div>
                                                <small class="text-muted">{{ $block->blocker->email ?? '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm me-2">
                                                <span class="avatar-initials bg-danger">
                                                    {{ substr($block->blockedUser->name ?? 'U', 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $block->blockedUser->name ?? 'Unknown' }}</div>
                                                <small class="text-muted">{{ $block->blockedUser->email ?? '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($block->reason)
                                            <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $block->reason)) }}</span>
                                        @else
                                            <span class="text-muted">No reason</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($block->is_active)
                                            <span class="badge bg-danger">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $block->created_at->format('M d, Y') }}</small><br>
                                        <small class="text-muted">{{ $block->created_at->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $block->created_at->diffForHumans() }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="viewBlock({{ $block->id }})">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @if($block->is_active)
                                            <button type="button" class="btn btn-outline-success" onclick="unblockUser({{ $block->id }})">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteBlock({{ $block->id }})">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <p>No user blocks found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($blocks->hasPages())
                <div class="card-footer">
                    {{ $blocks->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Block Details Modal -->
<div class="modal fade" id="blockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Block Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="blockModalBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="manageBlock()">Manage Block</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
function viewBlock(blockId) {
    fetch(`/admin/moderation/blocks/${blockId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('blockModalBody').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Block Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>${data.id}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${data.is_active ? 'Active' : 'Inactive'}</td></tr>
                            <tr><td><strong>Reason:</strong></td><td>${data.reason || 'No reason provided'}</td></tr>
                            <tr><td><strong>Created:</strong></td><td>${new Date(data.created_at).toLocaleString()}</td></tr>
                            ${data.unblocked_at ? `<tr><td><strong>Unblocked:</strong></td><td>${new Date(data.unblocked_at).toLocaleString()}</td></tr>` : ''}
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Users Involved</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Blocker:</strong></td><td>${data.blocker?.name || 'Unknown'}</td></tr>
                            <tr><td><strong>Blocker Email:</strong></td><td>${data.blocker?.email || 'Unknown'}</td></tr>
                            <tr><td><strong>Blocked User:</strong></td><td>${data.blocked_user?.name || 'Unknown'}</td></tr>
                            <tr><td><strong>Blocked Email:</strong></td><td>${data.blocked_user?.email || 'Unknown'}</td></tr>
                        </table>
                    </div>
                </div>
                ${data.notes ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Additional Notes</h6>
                        <div class="bg-light p-3 rounded">
                            ${data.notes}
                        </div>
                    </div>
                </div>
                ` : ''}
            `;
            new bootstrap.Modal(document.getElementById('blockModal')).show();
        })
        .catch(error => {
            console.error('Error loading block:', error);
            alert('Failed to load block details');
        });
}

function unblockUser(blockId) {
    if (!confirm('Are you sure you want to unblock this user?')) {
        return;
    }
    
    fetch(`/admin/moderation/blocks/${blockId}/unblock`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to unblock user');
        }
    })
    .catch(error => {
        console.error('Error unblocking user:', error);
        alert('Failed to unblock user');
    });
}

function deleteBlock(blockId) {
    if (!confirm('Are you sure you want to delete this block record? This action cannot be undone.')) {
        return;
    }
    
    fetch(`/admin/moderation/blocks/${blockId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to delete block record');
        }
    })
    .catch(error => {
        console.error('Error deleting block:', error);
        alert('Failed to delete block record');
    });
}

function manageBlock() {
    // Implementation for managing blocks
    alert('Block management functionality to be implemented');
}
</script>
@endsection
