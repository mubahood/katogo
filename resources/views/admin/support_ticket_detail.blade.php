@extends('admin::layouts.content')

@section('content-header')
    <h1>Ticket #{{ $ticket->id }}
        @php
            $statusColors = [
                'open' => 'primary', 'pending' => 'warning', 'in_progress' => 'info',
                'resolved' => 'success', 'closed' => 'default', 'escalated' => 'danger',
            ];
            $color = $statusColors[$ticket->status] ?? 'default';
        @endphp
        <span class="label label-{{ $color }}">{{ $ticket->status }}</span>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ admin_url('dashboard') }}"><i class="fa fa-home"></i> Home</a></li>
        <li><a href="{{ admin_url('support-tickets') }}">Support Tickets</a></li>
        <li class="active">Ticket #{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
@php
    $totalRecords = $records->count();
    $supportRecordsCount = $records->where('sender_type', 'support_team')->count();
    $userRecordsCount = $records->where('sender_type', 'user')->count();
    $internalNotesCount = $records->where('is_internal_note', true)->count();
    $visibleToCustomerCount = $records->where('show_to_customer', true)->count();
    $latestSupportRecord = $records->where('sender_type', 'support_team')->sortByDesc('created_at')->first();
    $latestUserRecord = $records->where('sender_type', 'user')->sortByDesc('created_at')->first();
    $customerPhone = trim((string) ($ticket->user->phone_number ?? $ticket->user->phone ?? ''));
    $movieRequestPayload = is_array($ticket->movie_request_payload ?? null) ? $ticket->movie_request_payload : [];
@endphp

<style>
    .ticket-kpi {
        border: 1px solid #edf0f5;
        border-radius: 8px;
        padding: 12px 14px;
        background: #fff;
        margin-bottom: 12px;
    }
    .ticket-kpi .kpi-label {
        font-size: 11px;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 3px;
    }
    .ticket-kpi .kpi-value {
        font-size: 20px;
        font-weight: 700;
        line-height: 1.2;
        color: #2c3e50;
    }
    .ticket-meta-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .ticket-meta-list li {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 12px;
        color: #4f5b66;
        padding: 6px 0;
        border-bottom: 1px dashed #f0f2f5;
    }
    .ticket-meta-list li:last-child {
        border-bottom: 0;
    }
    .record-flags {
        margin-top: 6px;
    }
    .record-flags .label {
        margin-right: 5px;
        margin-top: 4px;
        display: inline-block;
    }
</style>

<div class="row" style="margin-bottom:2px;">
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">Total Records</div>
            <div class="kpi-value">{{ $totalRecords }}</div>
        </div>
    </div>
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">User Messages</div>
            <div class="kpi-value">{{ $userRecordsCount }}</div>
        </div>
    </div>
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">Support Messages</div>
            <div class="kpi-value">{{ $supportRecordsCount }}</div>
        </div>
    </div>
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">Internal Notes</div>
            <div class="kpi-value">{{ $internalNotesCount }}</div>
        </div>
    </div>
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">Visible Records</div>
            <div class="kpi-value">{{ $visibleToCustomerCount }}</div>
        </div>
    </div>
    <div class="col-sm-2 col-xs-6">
        <div class="ticket-kpi">
            <div class="kpi-label">Replies Count</div>
            <div class="kpi-value">{{ (int) ($ticket->reply_count ?? 0) }}</div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Left: conversation thread --}}
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Conversation</h3>
                <div class="box-tools">
                    <span class="badge bg-{{ $color }}">{{ strtoupper($ticket->status) }}</span>
                </div>
            </div>
            <div class="box-body" style="max-height:62vh;overflow-y:auto;padding:20px" id="chat-thread">
                @forelse($records as $rec)
                    @php
                        $isSupport = $rec->sender_type === 'support_team';
                        $isSystem  = $rec->sender_type === 'system';
                        $isInternal = $rec->is_internal_note;
                        $messageBody = trim((string) ($rec->message_body ?? $rec->message));
                        $movieSuggestions = is_array($rec->movie_suggestions ?? null) ? $rec->movie_suggestions : [];
                        $senderName = $isSystem
                            ? 'System'
                            : ($rec->sender ? $rec->sender->name : ucfirst($rec->sender_type));
                    @endphp

                    @if($isSystem)
                        <div style="text-align:center;margin:8px 0">
                            <span class="label label-default">{{ $rec->message }}</span>
                            <div class="record-flags">
                                <span class="label label-primary">System</span>
                                @if($rec->show_to_customer)
                                    <span class="label label-success">Visible</span>
                                @else
                                    <span class="label label-default">Hidden</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ admin_url('support-ticket-records/' . $rec->id . '/visibility') }}" style="margin-top:6px;display:inline-block;background:#fff;padding:6px 8px;border:1px solid #ddd;border-radius:6px;">
                                @csrf
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="show_to_customer" value="1" {{ $rec->show_to_customer ? 'checked' : '' }}> Show</label>
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="customer_seen" value="1" {{ $rec->customer_seen ? 'checked' : '' }}> Seen</label>
                                <button type="submit" class="btn btn-xs btn-default">Update</button>
                            </form>
                        </div>
                    @elseif($isInternal)
                        <div style="background:#fffbe6;border:1px dashed #f0c040;border-radius:8px;padding:10px 14px;margin:8px 0">
                            <small class="text-muted"><i class="fa fa-lock"></i> Internal note — {{ $senderName }} &bull; {{ $rec->created_at }}</small>
                            <p style="margin:6px 0 0">{{ $messageBody }}</p>
                            <div class="record-flags">
                                <span class="label label-warning">Internal Note</span>
                                <span class="label label-default">Not Customer Visible</span>
                                @if($rec->action_type && $rec->action_type !== 'none')
                                    <span class="label label-info">{{ ucwords(str_replace('_', ' ', $rec->action_type)) }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ admin_url('support-ticket-records/' . $rec->id . '/visibility') }}" style="margin-top:6px;display:inline-block;background:#fff;padding:6px 8px;border:1px solid #ddd;border-radius:6px;">
                                @csrf
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="show_to_customer" value="1" {{ $rec->show_to_customer ? 'checked' : '' }}> Show</label>
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="customer_seen" value="1" {{ $rec->customer_seen ? 'checked' : '' }}> Seen</label>
                                <button type="submit" class="btn btn-xs btn-default">Update</button>
                            </form>
                        </div>
                    @elseif($isSupport)
                        <div style="text-align:right;margin:10px 0">
                            <div style="display:inline-block;max-width:70%;background:#337ab7;color:#fff;border-radius:12px 12px 0 12px;padding:10px 14px">
                                <div style="text-align:left">
                                    <div style="color:#ffe08a;font-size:11px;font-weight:600;margin-bottom:6px">Support</div>
                                    @if($messageBody !== '')
                                        <p style="margin:0 0 {{ count($movieSuggestions) ? '10px' : '0' }} 0;white-space:pre-line;">{{ $messageBody }}</p>
                                    @endif
                                    @if(count($movieSuggestions))
                                        <div style="background:rgba(8,18,33,.22);border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:10px 10px 2px;min-width:300px;">
                                            <div style="font-size:12px;font-weight:700;margin-bottom:8px;letter-spacing:.2px;">Suggested Movies</div>
                                            <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-start;">
                                                @foreach($movieSuggestions as $movie)
                                                    @php
                                                        $thumb = trim((string) ($movie['thumbnail_url'] ?? $movie['image_url'] ?? ''));
                                                        $metaParts = array_values(array_filter([
                                                            trim((string) ($movie['type'] ?? '')),
                                                            trim((string) ($movie['year'] ?? '')),
                                                            trim((string) ($movie['vj'] ?? '')),
                                                        ]));
                                                    @endphp
                                                    <div style="width:140px;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.12);border-radius:10px;overflow:hidden;">
                                                        <div style="height:120px;background:#173153;display:flex;align-items:center;justify-content:center;">
                                                            @if($thumb !== '')
                                                                <img src="{{ $thumb }}" alt="{{ $movie['title'] ?? 'Movie' }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                                                            @else
                                                                <i class="fa fa-film" style="font-size:28px;color:rgba(255,255,255,.55)"></i>
                                                            @endif
                                                        </div>
                                                        <div style="padding:10px;">
                                                            <div style="font-size:12px;font-weight:700;line-height:1.35;min-height:32px;">{{ $movie['title'] ?? 'Movie' }}</div>
                                                            @if(count($metaParts))
                                                                <div style="font-size:11px;color:rgba(255,255,255,.78);line-height:1.35;margin-top:5px;">{{ implode(' • ', $metaParts) }}</div>
                                                            @endif
                                                            <div style="margin-top:8px;">
                                                                @if(!empty($movie['is_available']))
                                                                    <span class="label label-success">Available</span>
                                                                @else
                                                                    <span class="label label-default">Suggestion</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @if($rec->action_type && $rec->action_type !== 'none')
                                <br><span class="label label-info" style="margin-top:4px;display:inline-block">{{ ucwords(str_replace('_', ' ', $rec->action_type)) }}</span>
                            @endif
                            @if($rec->show_to_customer)
                                <br><span class="label label-success" style="margin-top:4px;display:inline-block">Visible to customer</span>
                                @if($rec->customer_seen)
                                    <span class="label label-primary" style="margin-top:4px;display:inline-block">Customer seen</span>
                                @else
                                    <span class="label label-warning" style="margin-top:4px;display:inline-block">Customer not seen</span>
                                @endif
                            @else
                                <br><span class="label label-default" style="margin-top:4px;display:inline-block">Hidden from customer</span>
                            @endif
                            <form method="POST" action="{{ admin_url('support-ticket-records/' . $rec->id . '/visibility') }}" style="margin-top:6px;display:inline-block;background:#fff;padding:6px 8px;border:1px solid #ddd;border-radius:6px;">
                                @csrf
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="show_to_customer" value="1" {{ $rec->show_to_customer ? 'checked' : '' }}> Show</label>
                                <label style="font-weight:400;margin-right:10px;"><input type="checkbox" name="customer_seen" value="1" {{ $rec->customer_seen ? 'checked' : '' }}> Seen</label>
                                <button type="submit" class="btn btn-xs btn-default">Update</button>
                            </form>
                            <br><small class="text-muted">{{ $senderName }} &bull; {{ $rec->created_at }}</small>
                        </div>
                    @else
                        <div style="text-align:left;margin:10px 0">
                            <div style="display:inline-block;max-width:70%;background:#f4f4f4;border-radius:12px 12px 12px 0;padding:10px 14px">
                                <p style="margin:0;white-space:pre-line;">{{ $messageBody }}</p>
                            </div>
                            <div class="record-flags">
                                <span class="label label-default">Customer</span>
                                @if($rec->action_type && $rec->action_type !== 'none')
                                    <span class="label label-default">{{ ucwords(str_replace('_', ' ', $rec->action_type)) }}</span>
                                @endif
                            </div>
                            @if($rec->action_type && $rec->action_type !== 'none')
                                <br><span class="label label-default" style="margin-top:4px;display:inline-block">{{ ucwords(str_replace('_', ' ', $rec->action_type)) }}</span>
                            @endif
                            <br><small class="text-muted">{{ $senderName }} &bull; {{ $rec->created_at }}</small>
                        </div>
                    @endif
                @empty
                    <p class="text-muted text-center">No messages yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Reply form --}}
        @if($ticket->status !== 'closed')
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Send Reply</h3>
            </div>
            <form method="POST" action="{{ admin_url('support-tickets/' . $ticket->id . '/reply') }}">
                @csrf
                <div class="box-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="4" placeholder="Type your reply here..." required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Action Type</label>
                                <select name="action_type" class="form-control">
                                    @foreach($validActionTypes as $actionType)
                                        <option value="{{ $actionType }}">{{ ucfirst(str_replace('_', ' ', $actionType)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Update Status</label>
                                <select name="status" class="form-control">
                                    <option value="">Keep current</option>
                                    @foreach($validStatuses as $s)
                                        <option value="{{ $s }}" {{ $ticket->status === $s ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Assign To</label>
                                <select name="assigned_to" class="form-control">
                                    <option value="">Keep current</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $ticket->assigned_to == $agent->id ? 'selected' : '' }}>
                                            {{ $agent->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Ticket Type</label>
                                <select name="ticket_type" class="form-control">
                                    <option value="">Keep current</option>
                                    @foreach($validTicketTypes as $ticketType)
                                        <option value="{{ $ticketType }}" {{ $ticket->ticket_type === $ticketType ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $ticketType)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Resolution State</label>
                                <select name="resolution_state" class="form-control">
                                    <option value="">Keep current</option>
                                    @foreach($validResolutionStates as $state)
                                        <option value="{{ $state }}" {{ $ticket->resolution_state === $state ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $state)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Satisfaction Rating (1-5)</label>
                                <input type="number" min="1" max="5" name="rating_of_satisfaction" class="form-control" value="{{ $ticket->rating_of_satisfaction }}" placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_internal_note" value="1"> Internal note only (hidden from user)
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_to_customer" value="1" checked> Show this record to customer
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="customer_seen" value="1"> Mark this record as customer seen
                        </label>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-send"></i> Send Reply</button>
                </div>
            </form>
        </div>
        @else
            <div class="alert alert-info">This ticket is closed. No further replies can be sent.</div>
        @endif
    </div>

    {{-- Right: ticket info sidebar --}}
    <div class="col-md-4">
        <div class="box box-default">
            <div class="box-header"><h3 class="box-title">Ticket Info</h3></div>
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>ID</dt>       <dd>#{{ $ticket->id }}</dd>
                    <dt>Status</dt>   <dd><span class="label label-{{ $color }}">{{ $ticket->status }}</span></dd>
                    <dt>Type</dt>     <dd>{{ $ticket->ticket_type ? ucfirst(str_replace('_', ' ', $ticket->ticket_type)) : 'General' }}</dd>
                    <dt>Resolution</dt><dd>{{ $ticket->resolution_state ? ucfirst(str_replace('_', ' ', $ticket->resolution_state)) : 'Unresolved' }}</dd>
                    <dt>Subject</dt>  <dd>{{ $ticket->subject ?: '(none)' }}</dd>
                    <dt>App</dt>      <dd>{{ $ticket->app_type ?? '—' }}</dd>
                    <dt>Platform Type</dt> <dd>{{ $ticket->platform_type ?? '—' }}</dd>
                    <dt>Platform</dt> <dd>{{ $ticket->platform ?? '—' }}</dd>
                    <dt>Agent Contacted</dt> <dd>{{ $ticket->agent_has_contacted_customer ? 'Yes' : 'No' }}</dd>
                    <dt>User Responded</dt> <dd>{{ $ticket->customer_has_responded ? 'Yes' : 'No' }}</dd>
                    <dt>Rating</dt>   <dd>{{ $ticket->rating_of_satisfaction ?? '—' }}</dd>
                    <dt>Replies</dt>  <dd>{{ $ticket->reply_count }}</dd>
                    <dt>Created</dt>  <dd>{{ $ticket->created_at }}</dd>
                    <dt>Last Reply</dt><dd>{{ $ticket->last_reply_at ?? '—' }}</dd>
                    <dt>Assigned</dt><dd>{{ $ticket->assignedAgent?->name ?? 'Unassigned' }}</dd>
                    <dt>Unread User</dt><dd>{{ $ticket->has_unread_user ? 'Yes' : 'No' }}</dd>
                    <dt>Unread Support</dt><dd>{{ $ticket->has_unread_support ? 'Yes' : 'No' }}</dd>
                </dl>

                <hr style="margin:10px 0 12px;">
                <ul class="ticket-meta-list">
                    <li><span>Latest support reply</span><strong>{{ $latestSupportRecord?->created_at ?? '—' }}</strong></li>
                    <li><span>Latest customer reply</span><strong>{{ $latestUserRecord?->created_at ?? '—' }}</strong></li>
                </ul>
            </div>
        </div>

        @if($ticket->user)
        <div class="box box-info">
            <div class="box-header"><h3 class="box-title">User Info</h3></div>
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>Name</dt>  <dd>{{ $ticket->user->name }}</dd>
                    <dt>Email</dt> <dd>{{ $ticket->user->email }}</dd>
                    <dt>Phone</dt> <dd>{{ $customerPhone !== '' ? $customerPhone : '—' }}</dd>
                    <dt>Origin</dt>
                    <dd>
                        @if($ticket->account_origin === 'auto_device')
                            <span class="label label-warning">Auto-Device</span>
                        @elseif($ticket->account_origin === 'google')
                            <span class="label label-info">Google</span>
                        @else
                            <span class="label label-default">Manual</span>
                        @endif
                    </dd>
                    <dt>State</dt>
                    <dd>
                        @if($ticket->user->account_state === 'auto_created')
                            <span class="label label-warning">Auto Created</span>
                        @else
                            <span class="label label-success">Registered</span>
                        @endif
                    </dd>
                </dl>
                <a href="{{ admin_url('users/' . $ticket->user->id) }}" class="btn btn-xs btn-default">
                    <i class="fa fa-user"></i> View User Profile
                </a>
                @if($customerPhone !== '')
                    @php
                        $phoneSanitized = preg_replace('/[^0-9]/', '', $customerPhone) ?: $customerPhone;
                    @endphp
                    <a href="https://wa.me/{{ $phoneSanitized }}" class="btn btn-xs btn-success" target="_blank" rel="noopener" style="margin-left:6px;">
                        <i class="fa fa-whatsapp"></i> WhatsApp
                    </a>
                @endif
            </div>
        </div>
        @endif

        @if(!empty($movieRequestPayload))
        <div class="box box-warning">
            <div class="box-header"><h3 class="box-title">Movie Request Payload</h3></div>
            <div class="box-body">
                <p class="text-muted" style="margin-bottom:8px;">Attached structured payload from the movie request flow.</p>
                <details>
                    <summary style="cursor:pointer;font-weight:600;">View payload JSON</summary>
                    <pre style="margin-top:10px;max-height:220px;overflow:auto;white-space:pre-wrap;background:#f7f7f9;padding:10px;border-radius:6px;">{{ json_encode($movieRequestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </div>
        </div>
        @endif

        <div class="box box-default">
            <div class="box-header"><h3 class="box-title">Recent Activity</h3></div>
            <div class="box-body" style="padding-top:4px;">
                <ul class="ticket-meta-list">
                    @foreach($records->sortByDesc('created_at')->take(6) as $activity)
                        <li>
                            <span>
                                {{ ucfirst(str_replace('_', ' ', $activity->sender_type)) }}
                                @if($activity->is_internal_note)
                                    (internal)
                                @endif
                            </span>
                            <strong>{{ $activity->created_at }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll chat to bottom
    var thread = document.getElementById('chat-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    var internalNoteInput = document.querySelector('input[name="is_internal_note"]');
    var showToCustomerInput = document.querySelector('input[name="show_to_customer"]');
    var customerSeenInput = document.querySelector('input[name="customer_seen"]');

    if (internalNoteInput && showToCustomerInput && customerSeenInput) {
        internalNoteInput.addEventListener('change', function () {
            if (internalNoteInput.checked) {
                showToCustomerInput.checked = false;
                customerSeenInput.checked = false;
            } else {
                showToCustomerInput.checked = true;
            }
        });

        showToCustomerInput.addEventListener('change', function () {
            if (!showToCustomerInput.checked) {
                customerSeenInput.checked = false;
            }
        });
    }
</script>
@endsection
