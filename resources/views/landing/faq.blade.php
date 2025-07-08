@extends('layouts.landing')

@section('title', 'FAQ - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Frequently asked questions about the Luganda Translated Movies app. Find quick answers to common questions about streaming, features, and more.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Frequently Asked Questions</h1>
                    <p class="hero-subtitle">
                        Find quick answers to the most common questions about {{ $siteName }}. 
                        If you can't find what you're looking for, feel free to contact our support team.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="accordion" id="faqAccordion">
                    
                    <!-- General Questions -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">General Questions</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What is {{ $siteName }}?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    {{ $siteName }} is a streaming platform that offers movies and TV series with authentic Luganda translation and subtitles. We provide entertainment content that speaks to your heart in your mother tongue, making it easier for Luganda speakers to enjoy international movies.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How much does it cost?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    We offer various subscription plans to fit different needs and budgets. You can choose from monthly, quarterly, or annual subscriptions. We also offer a free trial period for new users to explore our content before committing to a subscription.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What devices can I use to watch?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    You can watch on iOS devices (iPhone, iPad) running iOS 12.0 or later, and Android devices running Android 7.0 or higher. The app is optimized for mobile viewing but works great on tablets too.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account & Subscription -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">Account & Subscription</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How do I create an account?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Download our app from the App Store or Google Play Store, then tap "Sign Up" on the welcome screen. You can register using your email address or phone number. You'll need to verify your account before you can start watching.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    Can I share my account with family members?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Yes! You can create multiple profiles under one account for different family members. Each profile can have its own viewing history, preferences, and parental controls for kids' accounts.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    How do I cancel my subscription?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    You can cancel your subscription anytime from your account settings in the app. Go to "Account" > "Subscription" > "Cancel Subscription". You'll continue to have access until the end of your current billing period.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Streaming & Technical -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">Streaming & Technical</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                                    What internet speed do I need for streaming?
                                </button>
                            </h2>
                            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    For standard definition (SD): 3 Mbps or higher<br>
                                    For high definition (HD): 5 Mbps or higher<br>
                                    For the best experience: 10 Mbps or higher<br>
                                    The app automatically adjusts video quality based on your internet speed.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                                    Can I download movies to watch offline?
                                </button>
                            </h2>
                            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Yes! You can download select movies and episodes for offline viewing. Look for the download icon next to the movie title. Downloaded content expires after 30 days or when you cancel your subscription.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                                    How much data does streaming use?
                                </button>
                            </h2>
                            <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Data usage varies by video quality:<br>
                                    Low quality: About 300 MB per hour<br>
                                    Medium quality: About 700 MB per hour<br>
                                    High quality: About 1.5 GB per hour<br>
                                    You can adjust video quality in the app settings to control data usage.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content & Translation -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">Content & Translation</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                                    How often is new content added?
                                </button>
                            </h2>
                            <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    We add new movies and episodes regularly. New content is typically added weekly, with special releases during holidays and major events. Follow our social media channels for announcements about new additions.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq11">
                                    Can I request movies to be translated?
                                </button>
                            </h2>
                            <div id="faq11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Yes! We welcome movie requests from our users. You can submit requests through the app's feedback feature or contact our support team. While we can't guarantee all requests will be fulfilled, we consider popular demands in our content planning.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq12">
                                    How accurate are the Luganda translations?
                                </button>
                            </h2>
                            <div id="faq12" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Our translations are done by professional Luganda speakers who understand both the language and cultural context. We strive for accuracy while maintaining the entertainment value and emotional impact of the original content.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Safety & Privacy -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">Safety & Privacy</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq13">
                                    Is the content safe for children?
                                </button>
                            </h2>
                            <div id="faq13" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    We have robust parental controls and content filtering. You can create kid-safe profiles that only show age-appropriate content. All movies and shows are clearly rated, and you can set viewing restrictions based on age ratings.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq14">
                                    How do you protect my personal information?
                                </button>
                            </h2>
                            <div id="faq14" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    We take privacy seriously and follow industry-standard security practices. Your personal information is encrypted and stored securely. We never share your data with third parties without your consent. Read our Privacy Policy for detailed information.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq15">
                                    How can I report inappropriate content?
                                </button>
                            </h2>
                            <div id="faq15" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    You can report content directly from the movie details page by tapping the "Report" button, or contact our support team with details about the content in question. We review all reports promptly and take appropriate action.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Troubleshooting -->
                    <div class="mb-4">
                        <h3 class="text-primary mb-3">Troubleshooting</h3>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq16">
                                    The app keeps crashing. What should I do?
                                </button>
                            </h2>
                            <div id="faq16" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Try these steps: 1) Update the app to the latest version, 2) Restart your device, 3) Clear the app cache, 4) Free up storage space on your device, 5) Uninstall and reinstall the app. If problems persist, contact our support team.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq17">
                                    Videos won't load or keep buffering
                                </button>
                            </h2>
                            <div id="faq17" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    Check your internet connection speed. Close other apps using bandwidth. Try switching between WiFi and mobile data. Lower the video quality in settings if your connection is slow. Restart the app and try again.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item bg-dark-custom border-custom mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-dark-custom text-light border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq18">
                                    I forgot my password. How do I reset it?
                                </button>
                            </h2>
                            <div id="faq18" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted-custom">
                                    On the login screen, tap "Forgot Password?" and enter your email address or phone number. You'll receive a reset link or code. Follow the instructions to create a new password. Make sure to check your spam folder if you don't see the email.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="section bg-dark-custom">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h2 class="section-title">Still Have Questions?</h2>
                <p class="lead text-muted-custom mb-4">
                    Can't find the answer you're looking for? Our support team is here to help you with any additional questions or concerns.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="{{ route('landing.contact') }}" class="btn btn-primary">
                        <i class="bi bi-envelope me-2"></i>Contact Support
                    </a>
                    <a href="{{ route('landing.support') }}" class="btn btn-outline-primary">
                        <i class="bi bi-headset me-2"></i>Support Center
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
