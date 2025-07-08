@extends('layouts.landing')

@section('title', 'Contact Us - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Get in touch with the Luganda Translated Movies team. We\'re here to help with any questions, feedback, or support you need.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Get in Touch</h1>
                    <p class="hero-subtitle">
                        We'd love to hear from you! Whether you have questions, feedback, or need support, 
                        our team is here to help you have the best experience with {{ $siteName }}.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form & Info Section -->
<section class="section">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="mb-4">Send us a Message</h3>
                        
                        @if(session('success'))
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                        </div>
                        @endif
                        
                        @if(session('error'))
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                        </div>
                        @endif
                        
                        <form action="{{ route('landing.contact.submit') }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           id="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-12">
                                    <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <select class="form-control @error('subject') is-invalid @enderror" 
                                            id="subject" name="subject" required>
                                        <option value="">Select a subject...</option>
                                        <option value="Technical Support" {{ old('subject') == 'Technical Support' ? 'selected' : '' }}>Technical Support</option>
                                        <option value="Account & Billing" {{ old('subject') == 'Account & Billing' ? 'selected' : '' }}>Account & Billing</option>
                                        <option value="Content Request" {{ old('subject') == 'Content Request' ? 'selected' : '' }}>Content Request</option>
                                        <option value="Translation Feedback" {{ old('subject') == 'Translation Feedback' ? 'selected' : '' }}>Translation Feedback</option>
                                        <option value="Report Inappropriate Content" {{ old('subject') == 'Report Inappropriate Content' ? 'selected' : '' }}>Report Inappropriate Content</option>
                                        <option value="Feature Request" {{ old('subject') == 'Feature Request' ? 'selected' : '' }}>Feature Request</option>
                                        <option value="Partnership Inquiry" {{ old('subject') == 'Partnership Inquiry' ? 'selected' : '' }}>Partnership Inquiry</option>
                                        <option value="General Inquiry" {{ old('subject') == 'General Inquiry' ? 'selected' : '' }}>General Inquiry</option>
                                        <option value="Other" {{ old('subject') == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-12">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('message') is-invalid @enderror" 
                                              id="message" name="message" rows="6" 
                                              placeholder="Please provide as much detail as possible..." required>{{ old('message') }}</textarea>
                                    @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="mb-4">Contact Information</h3>
                        
                        @if($contactEmail)
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-envelope text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Email</h6>
                                <a href="mailto:{{ $contactEmail }}" class="text-muted-custom">{{ $contactEmail }}</a>
                            </div>
                        </div>
                        @endif
                        
                        @if($phone)
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-telephone text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Phone</h6>
                                <a href="tel:{{ $phone }}" class="text-muted-custom">{{ $phone }}</a>
                            </div>
                        </div>
                        @endif
                        
                        @if($address)
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-geo-alt text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Address</h6>
                                <p class="text-muted-custom mb-0">{{ $address }}</p>
                            </div>
                        </div>
                        @endif
                        
                        <hr class="border-custom my-4">
                        
                        <h6 class="mb-3">Business Hours</h6>
                        <div class="text-muted-custom small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Monday - Friday</span>
                                <span>9:00 AM - 6:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Saturday</span>
                                <span>10:00 AM - 4:00 PM</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Sunday</span>
                                <span>Closed</span>
                            </div>
                            <p class="mt-2 mb-0">
                                <small>All times are East Africa Time (EAT)</small>
                            </p>
                        </div>
                        
                        <hr class="border-custom my-4">
                        
                        <h6 class="mb-3">Follow Us</h6>
                        <div class="d-flex gap-2">
                            @if(env('LANDING_FACEBOOK_URL'))
                            <a href="{{ env('LANDING_FACEBOOK_URL') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-facebook"></i>
                            </a>
                            @endif
                            @if(env('LANDING_TWITTER_URL'))
                            <a href="{{ env('LANDING_TWITTER_URL') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-twitter"></i>
                            </a>
                            @endif
                            @if(env('LANDING_INSTAGRAM_URL'))
                            <a href="{{ env('LANDING_INSTAGRAM_URL') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-instagram"></i>
                            </a>
                            @endif
                            @if(env('LANDING_YOUTUBE_URL'))
                            <a href="{{ env('LANDING_YOUTUBE_URL') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-youtube"></i>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="card mt-4">
                    <div class="card-body p-4">
                        <h6 class="mb-3">Quick Links</h6>
                        <div class="d-grid gap-2">
                            <a href="{{ route('landing.support') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-headset me-2"></i>Support Center
                            </a>
                            <a href="{{ route('landing.faq') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-question-circle me-2"></i>FAQ
                            </a>
                            <a href="{{ route('landing.privacy-policy') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-shield-check me-2"></i>Privacy Policy
                            </a>
                            <a href="{{ route('landing.terms-of-service') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-text me-2"></i>Terms of Service
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Response Time Info -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h3 class="mb-4">What to Expect</h3>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <i class="bi bi-clock text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h6>Quick Response</h6>
                        <p class="text-muted-custom small">
                            We aim to respond to all inquiries within 24 hours during business days.
                        </p>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h6>Expert Support</h6>
                        <p class="text-muted-custom small">
                            Our knowledgeable team is trained to help with technical and account issues.
                        </p>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <i class="bi bi-heart text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h6>Personal Touch</h6>
                        <p class="text-muted-custom small">
                            We treat every message with care and provide personalized assistance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    // Auto-hide success/error messages after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
@endpush
