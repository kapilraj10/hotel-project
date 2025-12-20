<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>हाम्रो थकाली भान्साघर - Site Maintenance</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #d32f2f;
            --secondary-color: #b71c1c;
            --accent-color: #ff9800;
            --dark-color: #333;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--dark-color);
        }
        
        .company-name {
            font-family: 'Mukta', 'Noto Sans Devanagari', sans-serif;
            font-weight: 700;
        }
        
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--secondary-color) !important;
            letter-spacing: 1px;
        }
        
        .navbar-brand i {
            color: var(--accent-color);
            margin-right: 8px;
        }
        
        .maintenance-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .maintenance-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 3rem;
            max-width: 700px;
            width: 100%;
            text-align: center;
            border-top: 8px solid var(--accent-color);
            animation: fadeIn 1s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .maintenance-icon {
            font-size: 5rem;
            color: var(--accent-color);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        h1 {
            color: var(--secondary-color);
            font-weight: 800;
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }
        
        .maintenance-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 2.5rem;
            max-width: 85%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: #666;
            font-size: 0.9rem;
            background-color: rgba(255, 255, 255, 0.7);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .contact-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .contact-link:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .nepali-text {
            font-family: 'Mukta', 'Noto Sans Devanagari', sans-serif;
            font-size: 1.2rem;
            color: #444;
            margin-top: 1rem;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .maintenance-card {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 2.3rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-buttons .btn {
                width: 100%;
                max-width: 250px;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .maintenance-icon {
                font-size: 4rem;
            }
            
            .nepali-text {
                font-size: 1rem;
            }
        }
    </style>
    <!-- Google Fonts for Devanagari support -->
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-utensils"></i><span class="company-name">हाम्रो थकाली भान्साघर</span>
            </a>
            <div class="navbar-nav ms-auto align-items-center">
                <!-- Contact link that redirects to Facebook -->
                <a href="https://facebook.com" target="_blank" class="btn btn-outline-primary me-3 d-none d-md-inline-block">
                    <i class="fab fa-facebook-f me-1"></i> सम्पर्क गर्नुहोस्
                </a>
                <!-- Admin Login Button -->
                <a href="http://localhost/hotel/assets/admin/login.php" class="btn btn-primary px-4">
                    <i class="fas fa-sign-in-alt me-1"></i> Admin Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="maintenance-container">
        <div class="maintenance-card">
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>
            
            <h1 class="company-name">हाम्रो थकाली भान्साघर</h1>
            <h2>Site is under maintenance</h2>
            
            <p class="maintenance-text">
                We're working hard to improve the user experience. Our team is currently performing scheduled maintenance to bring you an even better service. We appreciate your patience and will be back online shortly.
            </p>
            
        
            
            <!-- Mobile contact button (hidden on larger screens) -->
            <div class="d-block d-md-none mb-4">
                <a href="https://facebook.com" target="_blank" class="btn btn-outline-primary">
                    <i class="fab fa-facebook-f me-1"></i> फेसबुकमा सम्पर्क गर्नुहोस्
                </a>
            </div>
            
            <div class="action-buttons">
                <button id="reloadBtn" class="btn btn-primary">
                    <i class="fas fa-redo me-1"></i> पृष्ठ पुनः लोड गर्नुहोस्
                </button>
                <a href="https://facebook.com" target="_blank" class="btn btn-outline-primary d-none d-md-inline-block">
                    <i class="fab fa-facebook-f me-1"></i> फेसबुक पृष्ठ भ्रमण गर्नुहोस्
                </a>
            </div>
            
            <div class="mt-5 pt-3 border-top">
                <p class="text-muted">
                    For urgent inquiries, please contact us via our 
                    <a href="https://facebook.com" target="_blank" class="contact-link">Facebook page</a> 
                    or email us at <strong>support@hamrothakalibhansaghar.com</strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <p>&copy; 2025 <span class="company-name">हाम्रो थकाली भान्साघर</span>. All rights reserved. | <span id="current-date"></span></p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set current date in footer
        const now = new Date();
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
        
        // Reload button functionality
        document.getElementById('reloadBtn').addEventListener('click', function() {
            // Show loading animation
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> पुनः लोड हुँदैछ...';
            this.disabled = true;
            
            // Reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 800);
        });
        
        // Add smooth animation to maintenance card on load
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.maintenance-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>