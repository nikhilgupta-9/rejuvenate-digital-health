<?php
include "config/connect.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Server Error</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --danger: #dc3545;
            --dark: #2f2e41;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
            padding: 2rem;
            animation: fadeIn 0.8s ease-out;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: var(--danger);
            line-height: 1;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .error-code::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 4px;
            background: var(--danger);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin: 1.5rem 0;
        }
        
        .error-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.8;
        }
        
        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
            padding: 0.5rem 1.5rem;
        }
        
        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #bb2d3b;
        }
        
        .error-animation {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--danger);
        }
        
        .server-details {
            background: rgba(220, 53, 69, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 150px;
            overflow-y: auto;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .shaking {
            animation: shake 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-animation">
                <i class="fas fa-server"></i>
            </div>
            <div class="error-code">500</div>
            <h1 class="error-title">Internal Server Error</h1>
            <p class="error-message">Something went wrong on our end. We're working to fix it!</p>
            
            <div class="server-details">
                <div><strong>Error:</strong> Server encountered an unexpected condition</div>
                <div><strong>Time:</strong> <span id="error-time"></span></div>
                <div><strong>Reference:</strong> #<span id="error-ref"></span></div>
            </div>
            
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <button onclick="window.location.reload()" class="btn btn-danger btn-lg px-4 gap-3">
                    <i class="fas fa-sync-alt me-2"></i>Try Again
                </button>
                <a href="<?=$site?>" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
                <a href="mailto:support@example.com" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-envelope me-2"></i>Contact Support
                </a>
            </div>
            
            <p class="mt-3 small text-muted">Technical team has been notified</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Generate error details
        document.getElementById('error-time').textContent = new Date().toLocaleString();
        document.getElementById('error-ref').textContent = Math.random().toString(36).substr(2, 8).toUpperCase();
        
        // Add shaking animation to server icon when hovered
        const serverIcon = document.querySelector('.error-animation');
        serverIcon.addEventListener('mouseenter', () => {
            serverIcon.classList.add('shaking');
            setTimeout(() => serverIcon.classList.remove('shaking'), 500);
        });
        
        // Auto-retry after 30 seconds (optional)
        setTimeout(() => {
            document.querySelector('.error-message').innerHTML += '<br><span class="text-muted small">Auto-retrying in 5 seconds...</span>';
            setTimeout(() => window.location.reload(), 5000);
        }, 30000);
    </script>
</body>
</html>