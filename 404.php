<?php
include "config/connect.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page Not Found</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c63ff;
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
            color: var(--primary);
            line-height: 1;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .error-code::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 4px;
            background: var(--primary);
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
        
        .search-box {
            position: relative;
            max-width: 400px;
            margin: 0 auto 2rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.5rem 1.5rem;
        }
        
        .btn-primary:hover {
            background-color: #5a52e0;
            border-color: #5a52e0;
        }
        
        .error-animation {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-animation floating">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="error-code">404</div>
            <h1 class="error-title">Oops! Page Not Found</h1>
            <p class="error-message">The page you're looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
            
            <div class="search-box">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="Search our site..." id="searchInput">
                    <button class="btn btn-primary" type="button" id="searchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="<?=$site?>" class="btn btn-primary btn-lg px-4 gap-3">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
                <a href="mailto:support@example.com" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-envelope me-2"></i>Contact Support
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple search functionality
        document.getElementById('searchBtn').addEventListener('click', function() {
            const query = document.getElementById('searchInput').value.trim();
            if(query) {
                window.location.href = `/search?q=${encodeURIComponent(query)}`;
            }
        });
        
        // Allow Enter key to trigger search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
        
        // Animation for the error code
        const errorCode = document.querySelector('.error-code');
        errorCode.addEventListener('mouseover', () => {
            errorCode.style.transform = 'scale(1.05)';
            errorCode.style.transition = 'transform 0.3s ease';
        });
        
        errorCode.addEventListener('mouseout', () => {
            errorCode.style.transform = 'scale(1)';
        });
    </script>
</body>
</html>