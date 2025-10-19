<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Processing Transfer - {{ $transfer->video_title }}</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .transfer-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .transfer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .transfer-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .transfer-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .transfer-body {
            padding: 40px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        
        .status-pending { background: #f0ad4e; color: white; }
        .status-downloading { background: #5bc0de; color: white; }
        .status-uploading { background: #337ab7; color: white; }
        .status-completed { background: #5cb85c; color: white; }
        .status-failed { background: #d9534f; color: white; }
        
        .progress-section {
            margin: 30px 0;
        }
        
        .progress {
            height: 40px;
            border-radius: 20px;
            background: #f5f5f5;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .progress-bar {
            font-size: 16px;
            line-height: 40px;
            font-weight: 600;
            transition: width 0.5s ease;
        }
        
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        
        @keyframes progress-bar-stripes {
            0% { background-position: 40px 0; }
            100% { background-position: 0 0; }
        }
        
        .info-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn-custom {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .spinner {
            display: inline-block;
            margin-right: 10px;
        }
        
        .error-message {
            background: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .success-message {
            background: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="transfer-container">
        <div class="transfer-header">
            <h1><i class="fa fa-cloud-upload"></i> Video Transfer</h1>
            <p>{{ $transfer->video_title ?: 'Untitled Video' }}</p>
        </div>
        
        <div class="transfer-body">
            <!-- Status Badge -->
            <div class="text-center">
                <span class="status-badge status-{{ $transfer->status }}" id="status-badge">
                    <i class="fa fa-circle pulse"></i> {{ strtoupper($transfer->status) }}
                </span>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-section">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         id="progress-bar"
                         style="width: {{ $transfer->progress ?? 0 }}%">
                        <span id="progress-text">{{ $transfer->progress ?? 0 }}%</span>
                    </div>
                </div>
            </div>
            
            <!-- Transfer Info -->
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label"><i class="fa fa-link"></i> Source URL</span>
                    <span class="info-value" id="source-url">
                        <a href="{{ $transfer->source_url }}" target="_blank">{{ Str::limit($transfer->source_url, 50) }}</a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fa fa-database"></i> File Size</span>
                    <span class="info-value" id="file-size">
                        {{ $transfer->source_size ? number_format($transfer->source_size / 1048576, 2) . ' MB' : 'Calculating...' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fa fa-file-video-o"></i> Format</span>
                    <span class="info-value" id="mime-type">{{ $transfer->mime_type ?: 'Detecting...' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fa fa-clock-o"></i> Duration</span>
                    <span class="info-value" id="duration">
                        @if($transfer->duration_seconds)
                            {{ gmdate('H:i:s', $transfer->duration_seconds) }}
                        @else
                            Calculating...
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fa fa-tachometer"></i> Transfer Speed</span>
                    <span class="info-value" id="transfer-speed">{{ $transfer->transfer_speed ?: 'N/A' }}</span>
                </div>
                <div class="info-row" id="drive-url-row" style="display: none;">
                    <span class="info-label"><i class="fa fa-google"></i> Google Drive</span>
                    <span class="info-value" id="drive-url">
                        <a href="#" target="_blank" class="btn btn-xs btn-success">
                            <i class="fa fa-external-link"></i> View on Drive
                        </a>
                    </span>
                </div>
            </div>
            
            <!-- Error Message -->
            <div id="error-container" style="display: none;">
                <div class="error-message">
                    <strong><i class="fa fa-exclamation-triangle"></i> Error:</strong>
                    <p id="error-message"></p>
                </div>
            </div>
            
            <!-- Success Message -->
            <div id="success-container" style="display: none;">
                <div class="success-message">
                    <strong><i class="fa fa-check-circle"></i> Success!</strong>
                    <p>Video has been successfully transferred to Google Drive and is ready to use.</p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button id="start-btn" class="btn btn-primary btn-custom" onclick="startTransfer()">
                    <i class="fa fa-play"></i> Start Transfer
                </button>
                <button id="play-btn" class="btn btn-success btn-custom" style="display: none;" onclick="playVideo()">
                    <i class="fa fa-play-circle"></i> Play Video
                </button>
                <button id="retry-btn" class="btn btn-warning btn-custom" style="display: none;" onclick="retryTransfer()">
                    <i class="fa fa-refresh"></i> Retry Transfer
                </button>
                <a href="{{ admin_url('video-transfers') }}" class="btn btn-default btn-custom">
                    <i class="fa fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <script>
        const transferId = {{ $transfer->id }};
        let statusCheckInterval = null;
        let isTransferring = false;
        
        // Start transfer immediately if status is pending
        @if($transfer->status === 'pending')
            $(document).ready(function() {
                startTransfer();
            });
        @endif
        
        // Check status on load if already processing
        @if(in_array($transfer->status, ['downloading', 'uploading']))
            $(document).ready(function() {
                startStatusPolling();
            });
        @endif
        
        function startTransfer() {
            if (isTransferring) return;
            
            isTransferring = true;
            $('#start-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            $('#error-container').hide();
            
            // Start the transfer via AJAX
            $.ajax({
                url: '{{ url("transfer/start") }}/' + transferId,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        updateUI(response.transfer);
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Transfer failed';
                    showError(message);
                    isTransferring = false;
                    $('#start-btn').prop('disabled', false).html('<i class="fa fa-play"></i> Start Transfer');
                }
            });
            
            // Start polling for status updates
            startStatusPolling();
        }
        
        function retryTransfer() {
            // Reset UI
            $('#retry-btn').hide();
            $('#error-container').hide();
            updateProgressBar(0);
            updateStatus('pending');
            
            // Start transfer
            startTransfer();
        }
        
        function startStatusPolling() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
            
            statusCheckInterval = setInterval(checkStatus, 2000); // Check every 2 seconds
        }
        
        function checkStatus() {
            $.ajax({
                url: '{{ url("transfer/status") }}/' + transferId,
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        updateUI(response.transfer);
                        
                        // Stop polling if transfer is complete or failed
                        if (['completed', 'failed', 'cancelled'].includes(response.transfer.status)) {
                            clearInterval(statusCheckInterval);
                            isTransferring = false;
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Status check failed', xhr);
                }
            });
        }
        
        function updateUI(transfer) {
            // Update status badge
            updateStatus(transfer.status);
            
            // Update progress bar
            updateProgressBar(transfer.progress);
            
            // Update info fields
            if (transfer.source_size) {
                $('#file-size').text((transfer.source_size / 1048576).toFixed(2) + ' MB');
            }
            
            if (transfer.mime_type) {
                $('#mime-type').text(transfer.mime_type);
            }
            
            if (transfer.duration_seconds) {
                const hours = Math.floor(transfer.duration_seconds / 3600);
                const minutes = Math.floor((transfer.duration_seconds % 3600) / 60);
                const seconds = transfer.duration_seconds % 60;
                $('#duration').text(
                    (hours > 0 ? hours + ':' : '') + 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0')
                );
            }
            
            if (transfer.transfer_speed) {
                $('#transfer-speed').text(transfer.transfer_speed);
            }
            
            // Handle completed status
            if (transfer.status === 'completed') {
                $('#success-container').show();
                $('#start-btn').hide();
                $('#retry-btn').hide();
                
                if (transfer.drive_public_url) {
                    $('#drive-url-row').show();
                    $('#drive-url a').attr('href', transfer.drive_public_url);
                    $('#play-btn').show().attr('data-url', transfer.drive_public_url);
                }
            }
            
            // Handle failed status
            if (transfer.status === 'failed') {
                showError(transfer.error_message || 'Transfer failed');
                $('#start-btn').hide();
                $('#retry-btn').show();
            }
            
            // Update buttons based on status
            if (['downloading', 'uploading'].includes(transfer.status)) {
                $('#start-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            }
        }
        
        function updateStatus(status) {
            const badge = $('#status-badge');
            badge.removeClass('status-pending status-downloading status-uploading status-completed status-failed');
            badge.addClass('status-' + status);
            badge.html('<i class="fa fa-circle ' + (status === 'completed' ? '' : 'pulse') + '"></i> ' + status.toUpperCase());
        }
        
        function updateProgressBar(progress) {
            const progressBar = $('#progress-bar');
            const progressText = $('#progress-text');
            
            progressBar.css('width', progress + '%');
            progressText.text(progress + '%');
            
            // Change color based on progress
            progressBar.removeClass('progress-bar-info progress-bar-primary progress-bar-success');
            if (progress < 40) {
                progressBar.addClass('progress-bar-info');
            } else if (progress < 70) {
                progressBar.addClass('progress-bar-primary');
            } else {
                progressBar.addClass('progress-bar-success');
            }
        }
        
        function showError(message) {
            $('#error-message').text(message);
            $('#error-container').show();
        }
        
        function playVideo() {
            const url = $('#play-btn').attr('data-url');
            if (url) {
                window.open(url, '_blank');
            }
        }
        
        // Cleanup on page unload
        $(window).on('beforeunload', function() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
        });
    </script>
</body>
</html>
