@extends('layouts.landing')

@section('title', env('LANDING_SITE_NAME', 'Luganda Translated Movies') . ' - Stream Authentic Luganda Movies')
@section('description', 'Experience authentic Luganda translated movies with subtitles. Stream the latest movies and series with Luganda translation on any device.')
@section('keywords', 'Luganda movies, translated movies, Uganda movies, streaming, entertainment, African cinema, local content')

@section('content')
<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content fade-in-up">
                    <h1 class="hero-title">Experience Movies in Your Language</h1>
                    <p class="hero-subtitle">
                        Stream the latest movies and series with authentic Luganda translation and subtitles. 
                        Enjoy entertainment that speaks to your heart in your mother tongue.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        @if($appStoreUrl)
                        <a href="{{ $appStoreUrl }}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-apple me-2"></i>Download for iOS
                        </a>
                        @endif
                        @if($playStoreUrl)
                        <a href="{{ $playStoreUrl }}" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-google-play me-2"></i>Download for Android
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="position-relative">
                        <div class="bg-primary rounded-circle position-absolute" style="width: 300px; height: 300px; opacity: 0.1; top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
                        <i class="bi bi-play-circle-fill text-primary" style="font-size: 200px; position: relative; z-index: 2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Why Choose {{ $siteName }}?</h2>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-translate text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Authentic Translation</h5>
                        <p class="card-text text-muted-custom">
                            Experience movies with professional Luganda translation that captures the essence and cultural context of every dialogue.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-film text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Latest Movies</h5>
                        <p class="card-text text-muted-custom">
                            Stay up-to-date with the newest releases from Hollywood, Nollywood, and beyond, all translated into Luganda.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-phone text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Any Device</h5>
                        <p class="card-text text-muted-custom">
                            Watch on your phone, tablet, or smart TV. Our app works seamlessly across all your devices.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-hd-btn text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">HD Quality</h5>
                        <p class="card-text text-muted-custom">
                            Enjoy crisp, clear video quality up to 1080p with adaptive streaming for the best viewing experience.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-download text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Offline Viewing</h5>
                        <p class="card-text text-muted-custom">
                            Download your favorite movies and watch them offline, perfect for when you're on the go or have limited internet.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-people text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Family Friendly</h5>
                        <p class="card-text text-muted-custom">
                            Create multiple profiles for family members with parental controls and kid-safe content filtering.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="section">
    <div class="container">
        <h2 class="section-title">How It Works</h2>
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <span class="text-white fw-bold fs-4">1</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Download the App</h5>
                                <p class="text-muted-custom">Get our app from the App Store or Google Play Store on your mobile device.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <span class="text-white fw-bold fs-4">2</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Create Your Account</h5>
                                <p class="text-muted-custom">Sign up with your email or phone number to create your personalized profile.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <span class="text-white fw-bold fs-4">3</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5>Start Watching</h5>
                                <p class="text-muted-custom">Browse our collection and start streaming movies in Luganda right away!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="position-relative">
                        <div class="bg-primary rounded-circle position-absolute" style="width: 250px; height: 250px; opacity: 0.1; top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
                        <i class="bi bi-phone text-primary" style="font-size: 150px; position: relative; z-index: 2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-lg-3 col-md-6">
                <div class="card bg-transparent border-0">
                    <div class="card-body">
                        <h2 class="text-primary fw-bold mb-2">1000+</h2>
                        <p class="text-muted-custom">Movies Available</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card bg-transparent border-0">
                    <div class="card-body">
                        <h2 class="text-primary fw-bold mb-2">50K+</h2>
                        <p class="text-muted-custom">Happy Users</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card bg-transparent border-0">
                    <div class="card-body">
                        <h2 class="text-primary fw-bold mb-2">99.9%</h2>
                        <p class="text-muted-custom">Uptime</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card bg-transparent border-0">
                    <div class="card-body">
                        <h2 class="text-primary fw-bold mb-2">24/7</h2>
                        <p class="text-muted-custom">Support Available</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="section-title">Ready to Start Watching?</h2>
                <p class="lead text-muted-custom mb-4">
                    Join thousands of users who are already enjoying their favorite movies in Luganda. 
                    Download the app today and start your entertainment journey!
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    @if($appStoreUrl)
                    <a href="{{ $appStoreUrl }}" target="_blank" class="btn btn-primary btn-lg">
                        <i class="bi bi-apple me-2"></i>Download for iOS
                    </a>
                    @endif
                    @if($playStoreUrl)
                    <a href="{{ $playStoreUrl }}" target="_blank" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-google-play me-2"></i>Download for Android
                    </a>
                    @endif
                </div>
                <p class="text-muted-custom mt-3 small">
                    Available on iOS 12.0+ and Android 7.0+
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    // Add fade-in animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);

    // Observe all cards and sections
    document.querySelectorAll('.card, .section').forEach(el => {
        observer.observe(el);
    });
</script>
@endpush
