<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Experience authentic Luganda translated movies with subtitles. Stream the latest movies and series with Luganda translation.')">
    <meta name="keywords" content="@yield('keywords', 'Luganda movies, translated movies, Uganda movies, streaming, entertainment')">
    <title>@yield('title', env('LANDING_SITE_NAME', 'Luganda Translated Movies'))</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #e50914;
            --primary-dark: #b2070f;
            --secondary-color: #ffd700;
            --accent-yellow: #ffeb3b;
            --bright-yellow: #fff200;
            --dark-bg: #0d1117;
            --darker-bg: #010409;
            --light-text: #f0f6fc;
            --muted-text: #7d8590;
            --border-color: #21262d;
            --card-bg: #161b22;
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            --gradient-yellow: linear-gradient(135deg, var(--bright-yellow) 0%, var(--secondary-color) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--light-text);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background-color: var(--darker-bg) !important;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background-color: rgba(1, 4, 9, 0.95) !important;
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .navbar-nav .nav-link {
            color: var(--light-text) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
        }

        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Cards */
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .card-body {
            padding: 2rem;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--darker-bg) 0%, var(--dark-bg) 100%);
            padding: 8rem 0 6rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" style="stop-color:%23e50914;stop-opacity:0.1"/><stop offset="100%" style="stop-color:%23e50914;stop-opacity:0"/></radialGradient></defs><circle cx="50%" cy="50%" r="50%" fill="url(%23a)"/></svg>') center/cover;
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: var(--gradient-yellow);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(255, 235, 59, 0.3);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--muted-text);
            margin-bottom: 2.5rem;
            max-width: 600px;
        }

        /* Sections */
        .section {
            padding: 6rem 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: var(--bright-yellow);
            text-shadow: 0 0 20px rgba(255, 242, 0, 0.4);
        }

        /* Card and Content Titles */
        .card-title, h5 {
            color: var(--accent-yellow) !important;
            font-weight: 600;
        }

        h6, .footer-brand {
            color: var(--secondary-color) !important;
            font-weight: 600;
        }

        h2, h3, h4 {
            color: var(--bright-yellow);
            text-shadow: 0 0 15px rgba(255, 242, 0, 0.3);
        }

        /* Footer */
        .footer {
            background-color: var(--darker-bg);
            border-top: 1px solid var(--border-color);
            padding: 4rem 0 2rem;
        }

        .footer-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: var(--muted-text);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            color: var(--light-text);
            text-decoration: none;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--gradient-primary);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* App Store Badges */
        .app-badges img {
            height: 60px;
            margin: 0 0.5rem 1rem;
            transition: transform 0.3s ease;
        }

        .app-badges img:hover {
            transform: scale(1.05);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .navbar-brand {
                font-size: 1.25rem;
            }
        }

        /* Page specific styles */
        .content-section {
            padding: 2rem 0;
        }

        .text-muted-custom {
            color: var(--muted-text) !important;
        }

        .bg-dark-custom {
            background-color: var(--card-bg) !important;
        }

        .border-custom {
            border-color: var(--border-color) !important;
        }

        /* Form styles */
        .form-control {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--light-text);
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            background-color: var(--card-bg);
            border-color: var(--primary-color);
            color: var(--light-text);
            box-shadow: 0 0 0 0.2rem rgba(229, 9, 20, 0.25);
        }

        .form-control::placeholder {
            color: var(--muted-text);
        }

        .form-label {
            color: var(--light-text);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Alert styles */
        .alert-success {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: rgba(25, 135, 84, 0.3);
            color: #75b798;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
            color: #f1b2b8;
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('landing.index') }}">
                <i class="bi bi-play-circle-fill me-2"></i>{{ env('LANDING_SITE_NAME', 'Luganda Movies') }}
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('landing.index') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('landing.about') }}">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('landing.features') }}">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('landing.support') }}">Support</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('landing.contact') }}">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    @if(env('LANDING_APP_STORE_URL'))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ env('LANDING_APP_STORE_URL') }}" target="_blank">
                            <i class="bi bi-apple"></i> App Store
                        </a>
                    </li>
                    @endif
                    @if(env('LANDING_PLAY_STORE_URL'))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ env('LANDING_PLAY_STORE_URL') }}" target="_blank">
                            <i class="bi bi-google-play"></i> Play Store
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">{{ env('LANDING_SITE_NAME', 'Luganda Movies') }}</div>
                    <p class="text-muted-custom">Experience authentic Luganda translated movies with subtitles. Stream the latest movies and series with Luganda translation.</p>
                    
                    @if(env('LANDING_APP_STORE_URL') || env('LANDING_PLAY_STORE_URL'))
                    <div class="app-badges mt-3">
                        @if(env('LANDING_APP_STORE_URL'))
                        <a href="{{ env('LANDING_APP_STORE_URL') }}" target="_blank">
                            <img src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg" alt="Download on App Store">
                        </a>
                        @endif
                        @if(env('LANDING_PLAY_STORE_URL'))
                        <a href="{{ env('LANDING_PLAY_STORE_URL') }}" target="_blank">
                            <img src="https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png" alt="Get it on Google Play">
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Quick Links</h6>
                    <div class="footer-links">
                        <div class="mb-2"><a href="{{ route('landing.index') }}">Home</a></div>
                        <div class="mb-2"><a href="{{ route('landing.about') }}">About</a></div>
                        <div class="mb-2"><a href="{{ route('landing.features') }}">Features</a></div>
                        <div class="mb-2"><a href="{{ route('landing.support') }}">Support</a></div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Support</h6>
                    <div class="footer-links">
                        <div class="mb-2"><a href="{{ route('landing.faq') }}">FAQ</a></div>
                        <div class="mb-2"><a href="{{ route('landing.contact') }}">Contact Us</a></div>
                        <div class="mb-2"><a href="{{ route('landing.support') }}">Help Center</a></div>
                        @if(env('LANDING_SUPPORT_EMAIL'))
                        <div class="mb-2"><a href="mailto:{{ env('LANDING_SUPPORT_EMAIL') }}">Email Support</a></div>
                        @endif
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Legal</h6>
                    <div class="footer-links">
                        <div class="mb-2"><a href="{{ route('landing.privacy-policy') }}">Privacy Policy</a></div>
                        <div class="mb-2"><a href="{{ route('landing.terms-of-service') }}">Terms of Service</a></div>
                        <div class="mb-2"><a href="{{ route('landing.eula') }}">EULA</a></div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="text-light mb-3">Follow Us</h6>
                    <div class="social-icons">
                        @if(env('LANDING_FACEBOOK_URL'))
                        <a href="{{ env('LANDING_FACEBOOK_URL') }}" target="_blank"><i class="bi bi-facebook"></i></a>
                        @endif
                        @if(env('LANDING_TWITTER_URL'))
                        <a href="{{ env('LANDING_TWITTER_URL') }}" target="_blank"><i class="bi bi-twitter"></i></a>
                        @endif
                        @if(env('LANDING_INSTAGRAM_URL'))
                        <a href="{{ env('LANDING_INSTAGRAM_URL') }}" target="_blank"><i class="bi bi-instagram"></i></a>
                        @endif
                        @if(env('LANDING_YOUTUBE_URL'))
                        <a href="{{ env('LANDING_YOUTUBE_URL') }}" target="_blank"><i class="bi bi-youtube"></i></a>
                        @endif
                    </div>
                </div>
            </div>
            
            <hr class="border-custom my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted-custom mb-0">&copy; {{ date('Y') }} {{ env('LANDING_COMPANY_NAME', 'Luganda Movies Ltd') }}. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted-custom mb-0">Made with <i class="bi bi-heart-fill text-danger"></i> in Uganda</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
