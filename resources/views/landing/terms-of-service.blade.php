@extends('layouts.landing')

@section('title', 'Terms of Service - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Read the terms and conditions for using the Luganda Translated Movies app and services.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Terms of Service</h1>
                    <p class="hero-subtitle">
                        Please read these terms carefully before using our service.
                    </p>
                    <p class="text-muted-custom">
                        <small>Last updated: {{ date('F j, Y') }}</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Terms Content -->
<section class="content-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">
                        
                        <!-- Introduction -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Agreement to Terms</h3>
                            <p class="text-muted-custom">
                                These Terms of Service ("Terms") constitute a legally binding agreement between you and {{ $companyName }} ("Company," "we," "our," or "us") regarding your use of the {{ $siteName }} mobile application and related services (collectively, the "Service").
                            </p>
                            <p class="text-muted-custom">
                                By accessing or using our Service, you agree to be bound by these Terms. If you do not agree to these Terms, you may not access or use the Service.
                            </p>
                        </div>
                        
                        <!-- Acceptance and Eligibility -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Acceptance and Eligibility</h3>
                            
                            <h5 class="mb-3">Age Requirements</h5>
                            <p class="text-muted-custom">
                                You must be at least 13 years old to use our Service. If you are between 13 and 18 years old, you must have parental or guardian consent to use the Service.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Account Registration</h5>
                            <p class="text-muted-custom">
                                To access certain features of our Service, you must create an account. You agree to:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Provide accurate, current, and complete information during registration</li>
                                <li>Maintain and update your account information</li>
                                <li>Keep your account credentials secure and confidential</li>
                                <li>Notify us immediately of any unauthorized use of your account</li>
                                <li>Accept responsibility for all activities under your account</li>
                            </ul>
                        </div>
                        
                        <!-- Use of Service -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Use of Service</h3>
                            
                            <h5 class="mb-3">License Grant</h5>
                            <p class="text-muted-custom">
                                Subject to these Terms, we grant you a limited, non-exclusive, non-transferable, revocable license to access and use the Service for personal, non-commercial purposes.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Permitted Uses</h5>
                            <p class="text-muted-custom">You may use the Service to:</p>
                            <ul class="text-muted-custom">
                                <li>Stream and download movies and TV shows for personal viewing</li>
                                <li>Create user profiles and manage account settings</li>
                                <li>Provide feedback and communicate with our support team</li>
                                <li>Share content through approved social media features</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Prohibited Uses</h5>
                            <p class="text-muted-custom">You agree not to:</p>
                            <ul class="text-muted-custom">
                                <li>Use the Service for any illegal or unauthorized purpose</li>
                                <li>Share your account credentials with others</li>
                                <li>Attempt to circumvent security measures or access restrictions</li>
                                <li>Download, copy, or distribute content outside the app</li>
                                <li>Use automated systems (bots, scripts) to access the Service</li>
                                <li>Reverse engineer, decompile, or modify the app</li>
                                <li>Upload malicious code or attempt to harm our systems</li>
                                <li>Interfere with other users' enjoyment of the Service</li>
                                <li>Use the Service to distribute spam or promotional materials</li>
                            </ul>
                        </div>
                        
                        <!-- Content and Intellectual Property -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Content and Intellectual Property</h3>
                            
                            <h5 class="mb-3">Our Content</h5>
                            <p class="text-muted-custom">
                                All content available through the Service, including movies, TV shows, translations, subtitles, images, text, and software, is owned by us or our licensors and is protected by copyright, trademark, and other intellectual property laws.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Translation Rights</h5>
                            <p class="text-muted-custom">
                                The Luganda translations and subtitles provided through our Service are our proprietary work or licensed content. You may not reproduce, distribute, or create derivative works from these translations without our express written permission.
                            </p>
                            
                            <h5 class="mb-3 mt-4">User-Generated Content</h5>
                            <p class="text-muted-custom">
                                If you submit content to us (reviews, feedback, suggestions), you grant us a worldwide, royalty-free, perpetual license to use, modify, and distribute such content for business purposes.
                            </p>
                        </div>
                        
                        <!-- Subscription and Payment -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Subscription and Payment</h3>
                            
                            <h5 class="mb-3">Subscription Plans</h5>
                            <p class="text-muted-custom">
                                We offer various subscription plans with different features and pricing. Subscription fees are charged in advance and are non-refundable except as required by law.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Free Trial</h5>
                            <p class="text-muted-custom">
                                We may offer free trial periods for new subscribers. You must provide payment information to start a free trial. If you don't cancel before the trial ends, you'll be charged for the subscription.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Automatic Renewal</h5>
                            <p class="text-muted-custom">
                                Subscriptions automatically renew at the end of each billing period unless cancelled. You can cancel your subscription through your account settings or the app store where you purchased it.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Price Changes</h5>
                            <p class="text-muted-custom">
                                We reserve the right to change subscription prices with 30 days' notice. Price changes will take effect at your next billing cycle.
                            </p>
                        </div>
                        
                        <!-- Privacy and Data -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Privacy and Data</h3>
                            <p class="text-muted-custom">
                                Your privacy is important to us. Our collection and use of personal information is governed by our Privacy Policy, which is incorporated into these Terms by reference. By using the Service, you consent to our data practices as described in the Privacy Policy.
                            </p>
                        </div>
                        
                        <!-- Service Availability -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Service Availability</h3>
                            
                            <h5 class="mb-3">Uptime and Maintenance</h5>
                            <p class="text-muted-custom">
                                While we strive to provide continuous service, we do not guarantee 100% uptime. The Service may be temporarily unavailable due to maintenance, updates, or technical issues.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Content Availability</h5>
                            <p class="text-muted-custom">
                                Content availability may vary by region and may change without notice due to licensing agreements. We are not responsible for the removal or unavailability of specific content.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Geographic Restrictions</h5>
                            <p class="text-muted-custom">
                                The Service is primarily designed for users in specific geographic regions. Access may be restricted in certain countries due to legal or licensing limitations.
                            </p>
                        </div>
                        
                        <!-- User Conduct and Community Guidelines -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">User Conduct and Community Guidelines</h3>
                            <p class="text-muted-custom">
                                We are committed to providing a safe and respectful environment for all users. You agree to:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Treat other users with respect and courtesy</li>
                                <li>Not engage in harassment, bullying, or discriminatory behavior</li>
                                <li>Report inappropriate content or behavior through our reporting features</li>
                                <li>Not share content that is illegal, harmful, or offensive</li>
                                <li>Respect cultural sensitivities and community standards</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Content Moderation</h5>
                            <p class="text-muted-custom">
                                We reserve the right to review, moderate, and remove user-generated content that violates these Terms or our community guidelines. We may also suspend or terminate accounts that repeatedly violate our policies.
                            </p>
                        </div>
                        
                        <!-- Termination -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Termination</h3>
                            
                            <h5 class="mb-3">Termination by You</h5>
                            <p class="text-muted-custom">
                                You may terminate your account at any time by cancelling your subscription and deleting your account through the app settings.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Termination by Us</h5>
                            <p class="text-muted-custom">
                                We may suspend or terminate your account immediately if you violate these Terms, engage in fraudulent activity, or for any other reason at our sole discretion.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Effect of Termination</h5>
                            <p class="text-muted-custom">
                                Upon termination, your right to use the Service will cease immediately. Downloaded content will no longer be accessible, and your account data may be deleted according to our data retention policies.
                            </p>
                        </div>
                        
                        <!-- Disclaimers and Limitations -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Disclaimers and Limitations</h3>
                            
                            <h5 class="mb-3">Service Disclaimer</h5>
                            <p class="text-muted-custom">
                                The Service is provided "as is" and "as available" without warranties of any kind, either express or implied, including but not limited to warranties of merchantability, fitness for a particular purpose, or non-infringement.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Limitation of Liability</h5>
                            <p class="text-muted-custom">
                                To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, or other intangible losses.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Third-Party Services</h5>
                            <p class="text-muted-custom">
                                Our Service may integrate with third-party services (payment processors, analytics providers). We are not responsible for the availability, accuracy, or content of third-party services.
                            </p>
                        </div>
                        
                        <!-- Indemnification -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Indemnification</h3>
                            <p class="text-muted-custom">
                                You agree to indemnify and hold harmless {{ $companyName }}, its affiliates, officers, directors, employees, and agents from any claims, damages, losses, or expenses arising from your use of the Service, violation of these Terms, or infringement of any rights of another party.
                            </p>
                        </div>
                        
                        <!-- Governing Law -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Governing Law and Dispute Resolution</h3>
                            
                            <h5 class="mb-3">Governing Law</h5>
                            <p class="text-muted-custom">
                                These Terms shall be governed by and construed in accordance with the laws of Uganda, without regard to its conflict of law provisions.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Dispute Resolution</h5>
                            <p class="text-muted-custom">
                                Any disputes arising from these Terms or your use of the Service shall be resolved through binding arbitration in accordance with the rules of the Uganda Arbitration and Conciliation Act, or through the courts of Uganda.
                            </p>
                        </div>
                        
                        <!-- Changes to Terms -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Changes to Terms</h3>
                            <p class="text-muted-custom">
                                We reserve the right to modify these Terms at any time. We will provide notice of material changes through the app or by email. Your continued use of the Service after changes take effect constitutes acceptance of the new Terms.
                            </p>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Contact Information</h3>
                            <p class="text-muted-custom">
                                If you have questions about these Terms, please contact us:
                            </p>
                            <ul class="list-unstyled text-muted-custom">
                                <li><strong>Email:</strong> <a href="mailto:{{ $contactEmail }}" class="text-primary">{{ $contactEmail }}</a></li>
                                <li><strong>Company:</strong> {{ $companyName }}</li>
                                <li><strong>Service:</strong> {{ $siteName }}</li>
                            </ul>
                        </div>
                        
                        <!-- Severability -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Severability</h3>
                            <p class="text-muted-custom">
                                If any provision of these Terms is found to be invalid or unenforceable, the remaining provisions will continue to be valid and enforceable to the fullest extent permitted by law.
                            </p>
                        </div>
                        
                        <!-- Entire Agreement -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Entire Agreement</h3>
                            <p class="text-muted-custom">
                                These Terms, together with our Privacy Policy and any other legal notices published by us on the Service, constitute the entire agreement between you and {{ $companyName }} regarding the Service.
                            </p>
                        </div>
                        
                        <!-- Effective Date -->
                        <div class="border-top border-custom pt-4">
                            <p class="text-muted-custom small mb-0">
                                These Terms of Service are effective as of {{ date('F j, Y') }} and apply to all users of {{ $siteName }}.
                            </p>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
