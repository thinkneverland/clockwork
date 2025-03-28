<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Tapped</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 2rem;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .error-icon {
            margin-bottom: 1.5rem;
            color: #e53e3e;
            font-size: 3rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        .error-message {
            margin-bottom: 1.5rem;
            color: #4a5568;
        }
        .error-code {
            display: inline-block;
            background-color: #edf2f7;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            color: #718096;
            margin-bottom: 1.5rem;
        }
        .error-actions {
            margin-top: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #4299e1;
            color: white;
            border-radius: 0.25rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #3182ce;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="error-container">
        <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
            </svg>
        </div>
        
        <h1 class="error-title">{{ $errorMessage ?? 'An error occurred' }}</h1>
        
        <p class="error-message">
            We apologize for the inconvenience. Our team has been notified of this issue.
        </p>
        
        @if(isset($errorCode))
            <div class="error-code">
                Error Code: {{ $errorCode }}
            </div>
        @endif
        
        @if(isset($requestId))
            <div class="error-code">
                Request ID: {{ $requestId }}
            </div>
        @endif
        
        <div class="error-actions">
            @if($recoverable ?? true)
                <a href="javascript:window.location.reload();" class="btn">Retry</a>
            @endif
            
            <a href="{{ url('/') }}" class="btn btn-secondary">Go to Homepage</a>
            
            <a href="{{ url('/contact') }}" class="btn btn-secondary">Contact Support</a>
        </div>
    </div>
    
    <script>
        // Report the error to our analytics if needed
        document.addEventListener('DOMContentLoaded', function() {
            if (window.errorReporter) {
                window.errorReporter.reportPageError({
                    errorCode: '{{ $errorCode ?? "UNKNOWN" }}',
                    requestId: '{{ $requestId ?? "" }}',
                    url: window.location.href
                });
            }
        });
    </script>
</body>
</html>
