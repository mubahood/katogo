@extends('layouts.landing')

@section('title', 'Support - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Get help and support for the Luganda Translated Movies app. Find answers to common questions and contact our support team.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">How Can We Help You?</h1>
                    <p class="hero-subtitle">
                        Find answers to common questions or get in touch with our support team. 
                        We're here to make your experience with {{ $siteName }} as smooth as possible.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Help Section -->
<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-question-circle text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">FAQ</h5>
                        <p class="card-text text-muted-custom">
                            Find quick answers to the most frequently asked questions about our app and services.
                        </p>
                        @if($faqUrl)
                        <a href="{{ $faqUrl }}" class="btn btn-outline-primary">View FAQ</a>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-envelope text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Email Support</h5>
                        <p class="card-text text-muted-custom">
                            Send us an email and we'll get back to you within 24 hours during business days.
                        </p>
                        @if($supportEmail)
                        <a href="mailto:{{ $supportEmail }}" class="btn btn-outline-primary">
                            <i class="bi bi-envelope me-2"></i>{{ $supportEmail }}
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-4">
                            <i class="bi bi-telephone text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Phone Support</h5>
                        <p class="card-text text-muted-custom">
                            Call us during business hours for immediate assistance with urgent issues.
                        </p>
                        @if($phone)
                        <a href="tel:{{ $phone }}" class="btn btn-outline-primary">
                            <i class="bi bi-telephone me-2"></i>{{ $phone }}
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Support Categories -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Support Categories</h2>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-gear text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">Technical Issues</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>App crashes or freezing</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Video playback problems</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Download and offline viewing issues</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Audio/subtitle sync problems</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Login and authentication errors</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-person text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">Account & Billing</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Account registration and verification</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Password reset and recovery</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Subscription and payment issues</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Profile management</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Account deletion requests</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-film text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">Content & Features</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Movie requests and suggestions</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Translation quality feedback</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Content availability inquiries</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Feature requests and feedback</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Inappropriate content reporting</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-shield text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">Privacy & Security</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Privacy policy questions</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Data usage and storage</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Security concerns and reporting</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>GDPR and data protection</li>
                            <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Terms of service clarifications</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- System Requirements -->
<section class="section">
    <div class="container">
        <h2 class="section-title">System Requirements</h2>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-apple text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">iOS Requirements</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><strong>iOS Version:</strong> 12.0 or later</li>
                            <li class="mb-2"><strong>Compatible with:</strong> iPhone, iPad, iPod touch</li>
                            <li class="mb-2"><strong>Storage:</strong> At least 200MB free space</li>
                            <li class="mb-2"><strong>Internet:</strong> 3G, 4G, 5G, or WiFi connection</li>
                            <li class="mb-2"><strong>Recommended:</strong> iPhone 8 or newer for best performance</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-android2 text-primary me-3" style="font-size: 2rem;"></i>
                            <h5 class="mb-0">Android Requirements</h5>
                        </div>
                        <ul class="list-unstyled text-muted-custom">
                            <li class="mb-2"><strong>Android Version:</strong> 7.0 (API level 24) or higher</li>
                            <li class="mb-2"><strong>Architecture:</strong> ARM64, ARMv7, or x86</li>
                            <li class="mb-2"><strong>Storage:</strong> At least 200MB free space</li>
                            <li class="mb-2"><strong>Internet:</strong> 3G, 4G, 5G, or WiFi connection</li>
                            <li class="mb-2"><strong>Recommended:</strong> 2GB RAM or more for smooth streaming</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Troubleshooting Tips -->
<section class="section bg-dark-custom">
    <div class="container">
        <h2 class="section-title">Common Troubleshooting Tips</h2>
        <div class="row g-4">
            <div class="col-12">
                <div class="accordion" id="troubleshootingAccordion">
                    <div class="accordion-item bg-dark-custom border-custom">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                Video won't play or keeps buffering
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                            <div class="accordion-body text-muted-custom">
                                <ul>
                                    <li>Check your internet connection speed (minimum 5 Mbps for HD streaming)</li>
                                    <li>Close other apps that might be using bandwidth</li>
                                    <li>Try switching between WiFi and mobile data</li>
                                    <li>Restart the app and try again</li>
                                    <li>Clear the app cache from your device settings</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item bg-dark-custom border-custom">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                App crashes or freezes frequently
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                            <div class="accordion-body text-muted-custom">
                                <ul>
                                    <li>Update the app to the latest version</li>
                                    <li>Restart your device</li>
                                    <li>Free up storage space on your device</li>
                                    <li>Close background apps to free up memory</li>
                                    <li>Uninstall and reinstall the app if problems persist</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item bg-dark-custom border-custom">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                Can't log into my account
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                            <div class="accordion-body text-muted-custom">
                                <ul>
                                    <li>Double-check your email and password</li>
                                    <li>Use the "Forgot Password" feature to reset your password</li>
                                    <li>Check if your account is verified (check your email for verification link)</li>
                                    <li>Ensure you're using the same login method (email vs. phone number)</li>
                                    <li>Contact support if you still can't access your account</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item bg-dark-custom border-custom">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                Audio and subtitles are out of sync
                            </button>
                        </h2>
                        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                            <div class="accordion-body text-muted-custom">
                                <ul>
                                    <li>Pause and resume the video</li>
                                    <li>Restart the video from the beginning</li>
                                    <li>Close and reopen the app</li>
                                    <li>Check if the issue persists with other movies</li>
                                    <li>Report the specific movie if sync issues continue</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="section">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="section-title">Still Need Help?</h2>
                <p class="lead text-muted-custom mb-4">
                    Can't find the answer you're looking for? Our support team is ready to help you with any questions or issues you might have.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="{{ route('landing.contact') }}" class="btn btn-primary">
                        <i class="bi bi-envelope me-2"></i>Contact Support
                    </a>
                    @if($faqUrl)
                    <a href="{{ $faqUrl }}" class="btn btn-outline-primary">
                        <i class="bi bi-question-circle me-2"></i>View FAQ
                    </a>
                    @endif
                </div>
                
                @if($supportEmail)
                <p class="text-muted-custom mt-3 small">
                    Response time: Within 24 hours during business days
                </p>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
