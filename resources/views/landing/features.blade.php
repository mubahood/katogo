@extends('layouts.landing')

@section('title', 'Features - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Discover all the amazing features of the Luganda Translated Movies app, from authentic translations to offline viewing.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Powerful Features</h1>
                    <p class="hero-subtitle">
                        Discover everything {{ $siteName }} has to offer for the ultimate 
                        Luganda entertainment experience.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Features -->
<section class="section">
    <div class="container">
        <div class="row g-5">
            <!-- Feature 1 -->
            <div class="col-lg-6">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="bi bi-translate text-primary" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 class="mb-3">Authentic Luganda Translation</h3>
                        <p class="text-muted-custom">
                            Experience movies with professional Luganda translation that captures cultural nuances and emotional depth. Our native speakers ensure every dialogue feels natural and authentic.
                        </p>
                        <ul class="text-muted-custom">
                            <li>Native speaker translations</li>
                            <li>Cultural context preservation</li>
                            <li>Emotional accuracy</li>
                            <li>Regular quality reviews</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Feature 2 -->
            <div class="col-lg-6">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="bi bi-download text-primary" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 class="mb-3">Offline Viewing</h3>
                        <p class="text-muted-custom">
                            Download your favorite movies and watch them offline, perfect for commutes, travel, or areas with limited internet connectivity.
                        </p>
                        <ul class="text-muted-custom">
                            <li>Download for offline viewing</li>
                            <li>Multiple quality options</li>
                            <li>Smart storage management</li>
                            <li>Background downloads</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Feature 3 -->
            <div class="col-lg-6">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="bi bi-hd-btn text-primary" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 class="mb-3">High-Quality Streaming</h3>
                        <p class="text-muted-custom">
                            Enjoy crisp, clear video quality up to 1080p with adaptive streaming that automatically adjusts to your internet connection.
                        </p>
                        <ul class="text-muted-custom">
                            <li>Up to 1080p HD quality</li>
                            <li>Adaptive bitrate streaming</li>
                            <li>Auto quality adjustment</li>
                            <li>Smooth playback</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Feature 4 -->
            <div class="col-lg-6">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="text-center">
                            <i class="bi bi-people text-primary" style="font-size: 4rem;"></i>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 class="mb-3">Multiple Profiles</h3>
                        <p class="text-muted-custom">
                            Create separate profiles for family members with personalized recommendations, viewing history, and parental controls.
                        </p>
                        <ul class="text-muted-custom">
                            <li>Individual user profiles</li>
                            <li>Personalized recommendations</li>
                            <li>Separate viewing history</li>
                            <li>Kids profile with parental controls</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Advanced Features -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Advanced Features</h2>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-search text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Smart Search</h5>
                        <p class="card-text text-muted-custom">
                            Find content easily with intelligent search that supports both English and Luganda terms, including actor names, genres, and movie titles.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-heart text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Wishlist & Favorites</h5>
                        <p class="card-text text-muted-custom">
                            Save movies to your wishlist and mark favorites for easy access. Get notified when new episodes or similar content becomes available.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-clock-history text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Continue Watching</h5>
                        <p class="card-text text-muted-custom">
                            Pick up exactly where you left off across all your devices. Your viewing progress syncs automatically for seamless entertainment.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-volume-up text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Audio Options</h5>
                        <p class="card-text text-muted-custom">
                            Choose between original audio with Luganda subtitles or full Luganda dubbing where available. Adjust audio quality to your preference.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-cast text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Chromecast Support</h5>
                        <p class="card-text text-muted-custom">
                            Cast your favorite movies to your TV for a bigger screen experience. Enjoy Luganda entertainment with family and friends.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-star text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Personalized Recommendations</h5>
                        <p class="card-text text-muted-custom">
                            Discover new content based on your viewing history and preferences. Our AI learns what you love and suggests similar movies.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- User Experience Features -->
<section class="section">
    <div class="container">
        <h2 class="section-title">User Experience</h2>
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h3 class="mb-4">Designed for You</h3>
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-phone text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6>Mobile-First Design</h6>
                                <p class="text-muted-custom mb-0">Optimized for mobile devices with intuitive touch controls and responsive design.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-speedometer2 text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6>Fast Loading</h6>
                                <p class="text-muted-custom mb-0">Quick app startup and fast content loading for immediate entertainment gratification.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-palette text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6>Beautiful Interface</h6>
                                <p class="text-muted-custom mb-0">Clean, modern design that puts content first while maintaining cultural aesthetics.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-universal-access text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6>Accessibility</h6>
                                <p class="text-muted-custom mb-0">Built with accessibility in mind, supporting screen readers and large text options.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="position-relative">
                        <div class="bg-primary rounded-circle position-absolute" style="width: 250px; height: 250px; opacity: 0.1; top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
                        <i class="bi bi-ui-checks text-primary" style="font-size: 120px; position: relative; z-index: 2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Safety & Security Features -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Safety & Security</h2>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-shield-check text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Content Moderation</h5>
                        </div>
                        <p class="text-muted-custom">
                            All content is carefully reviewed and moderated to ensure it meets our community standards and cultural values. Family-friendly options are clearly marked.
                        </p>
                        <ul class="text-muted-custom small">
                            <li>Age-appropriate content ratings</li>
                            <li>Community reporting system</li>
                            <li>Regular content review</li>
                            <li>Cultural sensitivity checks</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-lock text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Privacy Protection</h5>
                        </div>
                        <p class="text-muted-custom">
                            Your privacy is our priority. We use industry-standard encryption and never share your personal information without your explicit consent.
                        </p>
                        <ul class="text-muted-custom small">
                            <li>End-to-end encryption</li>
                            <li>Secure payment processing</li>
                            <li>GDPR compliance</li>
                            <li>Transparent privacy policy</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-person-check text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">User Safety</h5>
                        </div>
                        <p class="text-muted-custom">
                            Comprehensive user safety features including blocking, reporting, and community guidelines enforcement to maintain a respectful environment.
                        </p>
                        <ul class="text-muted-custom small">
                            <li>User blocking and reporting</li>
                            <li>Community guidelines</li>
                            <li>24/7 support monitoring</li>
                            <li>Harassment prevention</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-eye-slash text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Parental Controls</h5>
                        </div>
                        <p class="text-muted-custom">
                            Robust parental controls allow parents to manage what their children can access, with age-appropriate content filtering and viewing time limits.
                        </p>
                        <ul class="text-muted-custom small">
                            <li>Age-based content filtering</li>
                            <li>Viewing time restrictions</li>
                            <li>PIN-protected profiles</li>
                            <li>Activity monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Coming Soon Features -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Coming Soon</h2>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center border-2 border-primary">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-chat-dots text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Social Features</h5>
                        <p class="card-text text-muted-custom">
                            Share your favorite moments, discuss movies with friends, and see what the community is watching.
                        </p>
                        <small class="text-primary">Q2 2024</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center border-2 border-primary">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-tv text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Smart TV Apps</h5>
                        <p class="card-text text-muted-custom">
                            Native apps for Android TV, Apple TV, and other smart TV platforms for the ultimate big-screen experience.
                        </p>
                        <small class="text-primary">Q3 2024</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center border-2 border-primary">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-mic text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Voice Control</h5>
                        <p class="card-text text-muted-custom">
                            Control the app with voice commands in both English and Luganda for hands-free navigation.
                        </p>
                        <small class="text-primary">Q4 2024</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="section-title">Experience All These Features</h2>
                <p class="lead text-muted-custom mb-4">
                    Join thousands of users who are already enjoying authentic Luganda entertainment. 
                    Download the app today and discover all these amazing features.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    @if(env('LANDING_APP_STORE_URL'))
                    <a href="{{ env('LANDING_APP_STORE_URL') }}" target="_blank" class="btn btn-primary btn-lg">
                        <i class="bi bi-apple me-2"></i>Download for iOS
                    </a>
                    @endif
                    @if(env('LANDING_PLAY_STORE_URL'))
                    <a href="{{ env('LANDING_PLAY_STORE_URL') }}" target="_blank" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-google-play me-2"></i>Download for Android
                    </a>
                    @endif
                </div>
                <p class="text-muted-custom mt-3 small">
                    Free trial available • No setup fees • Cancel anytime
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
