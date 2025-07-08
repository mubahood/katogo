@extends('layouts.landing')

@section('title', 'About - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Learn about our mission to bring authentic Luganda translated entertainment to Uganda and the world.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">About {{ $siteName }}</h1>
                    <p class="hero-subtitle">
                        Bringing authentic Luganda entertainment to your fingertips, 
                        preserving culture while embracing global content.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Story Section -->
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h2 class="section-title text-start">Our Story</h2>
                <p class="text-muted-custom lead">
                    {{ $siteName }} was born from a simple yet powerful vision: to make world-class entertainment accessible in Luganda, the heart language of millions of Ugandans.
                </p>
                <p class="text-muted-custom">
                    We recognized that while global entertainment content was abundant, very little was available in Luganda with authentic, culturally-sensitive translations. Our founders, passionate about both technology and Ugandan culture, set out to bridge this gap.
                </p>
                <p class="text-muted-custom">
                    Today, we're proud to offer a growing library of movies and TV shows with professional Luganda translations, making entertainment truly accessible to our community while preserving the richness of our local language and culture.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="position-relative">
                        <div class="bg-primary rounded-circle position-absolute" style="width: 300px; height: 300px; opacity: 0.1; top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
                        <i class="bi bi-globe-africa text-primary" style="font-size: 150px; position: relative; z-index: 2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-bullseye text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-3">Our Mission</h3>
                    <p class="text-muted-custom">
                        To preserve and promote the Luganda language by providing high-quality, culturally authentic translations of global entertainment content, making it accessible to Luganda speakers worldwide.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-eye text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-3">Our Vision</h3>
                    <p class="text-muted-custom">
                        To become the leading platform for Luganda entertainment content, fostering cultural pride and linguistic preservation while connecting our community to global stories and perspectives.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-heart text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-3">Our Values</h3>
                    <p class="text-muted-custom">
                        Cultural authenticity, quality excellence, community connection, technological innovation, and accessibility for all Luganda speakers, regardless of their location or background.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- What Makes Us Different -->
<section class="section">
    <div class="container">
        <h2 class="section-title">What Makes Us Different</h2>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-translate text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Authentic Translation</h5>
                        </div>
                        <p class="text-muted-custom">
                            Our translations are crafted by native Luganda speakers who understand both the language and cultural nuances. We don't just translate words—we translate meaning, emotion, and cultural context.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-people text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Community-Driven</h5>
                        </div>
                        <p class="text-muted-custom">
                            We listen to our community. User feedback drives our content selection, and we regularly incorporate suggestions from our viewers to improve the platform and expand our library.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-award text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Quality First</h5>
                        </div>
                        <p class="text-muted-custom">
                            Every piece of content undergoes rigorous quality checks. From video clarity to translation accuracy, we maintain high standards to ensure the best viewing experience for our users.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-globe text-primary me-3" style="font-size: 2.5rem;"></i>
                            <h5 class="mb-0">Cultural Bridge</h5>
                        </div>
                        <p class="text-muted-custom">
                            We serve as a bridge between global entertainment and local culture, making international content accessible while promoting and preserving the beauty of the Luganda language.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Team -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Our Commitment</h2>
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <p class="lead text-muted-custom mb-4">
                    We are committed to building more than just a streaming platform. We're creating a cultural hub that celebrates Luganda heritage while embracing global entertainment.
                </p>
                
                <div class="row g-4 mt-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-shield-check text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3 text-start">
                                <h6>Cultural Preservation</h6>
                                <p class="text-muted-custom small mb-0">Keeping Luganda alive and thriving in the digital age</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-universal-access text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3 text-start">
                                <h6>Accessibility</h6>
                                <p class="text-muted-custom small mb-0">Making entertainment accessible to all Luganda speakers</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-lightbulb text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3 text-start">
                                <h6>Innovation</h6>
                                <p class="text-muted-custom small mb-0">Using technology to preserve and promote our culture</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-heart text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3 text-start">
                                <h6>Community Focus</h6>
                                <p class="text-muted-custom small mb-0">Building stronger connections within our community</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Impact -->
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h2 class="section-title text-start">Our Impact</h2>
                <p class="text-muted-custom">
                    Since our launch, {{ $siteName }} has made a significant impact on how Luganda speakers consume entertainment content:
                </p>
                <ul class="text-muted-custom">
                    <li><strong>Language Preservation:</strong> Helping to maintain and promote Luganda usage among younger generations</li>
                    <li><strong>Cultural Connection:</strong> Keeping diaspora communities connected to their linguistic roots</li>
                    <li><strong>Educational Value:</strong> Providing exposure to global perspectives while maintaining cultural identity</li>
                    <li><strong>Community Building:</strong> Creating shared experiences through culturally relevant entertainment</li>
                    <li><strong>Economic Impact:</strong> Supporting local translators and content creators</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="row g-4 text-center">
                    <div class="col-6">
                        <div class="card bg-transparent border-0">
                            <div class="card-body">
                                <h2 class="text-primary fw-bold mb-2">50K+</h2>
                                <p class="text-muted-custom">Active Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-transparent border-0">
                            <div class="card-body">
                                <h2 class="text-primary fw-bold mb-2">1000+</h2>
                                <p class="text-muted-custom">Translated Titles</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-transparent border-0">
                            <div class="card-body">
                                <h2 class="text-primary fw-bold mb-2">25+</h2>
                                <p class="text-muted-custom">Countries Reached</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-transparent border-0">
                            <div class="card-body">
                                <h2 class="text-primary fw-bold mb-2">98%</h2>
                                <p class="text-muted-custom">User Satisfaction</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Join Us CTA -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="section-title">Join Our Mission</h2>
                <p class="lead text-muted-custom mb-4">
                    Be part of preserving and promoting Luganda culture through entertainment. 
                    Download the app today and experience movies like never before.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    @if(env('LANDING_APP_STORE_URL'))
                    <a href="{{ env('LANDING_APP_STORE_URL') }}" target="_blank" class="btn btn-primary">
                        <i class="bi bi-apple me-2"></i>Download for iOS
                    </a>
                    @endif
                    @if(env('LANDING_PLAY_STORE_URL'))
                    <a href="{{ env('LANDING_PLAY_STORE_URL') }}" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-google-play me-2"></i>Download for Android
                    </a>
                    @endif
                </div>
                <p class="text-muted-custom mt-3 small">
                    Start your cultural entertainment journey today
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
