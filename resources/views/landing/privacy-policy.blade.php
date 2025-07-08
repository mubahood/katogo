@extends('layouts.landing')

@section('title', 'Privacy Policy - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'Learn how we collect, use, and protect your personal information when using the Luganda Translated Movies app and services.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Privacy Policy</h1>
                    <p class="hero-subtitle">
                        We are committed to protecting your privacy and ensuring the security of your personal information.
                    </p>
                    <p class="text-muted-custom">
                        <small>Last updated: {{ date('F j, Y') }}</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Privacy Policy Content -->
<section class="content-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">
                        
                        <!-- Introduction -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Introduction</h3>
                            <p class="text-muted-custom">
                                {{ $companyName }} ("we," "our," or "us") operates the {{ $siteName }} mobile application and related services (the "Service"). This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Service.
                            </p>
                            <p class="text-muted-custom">
                                By using our Service, you agree to the collection and use of information in accordance with this Privacy Policy. If you do not agree with the terms of this Privacy Policy, please do not access or use the Service.
                            </p>
                        </div>
                        
                        <!-- Information We Collect -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Information We Collect</h3>
                            
                            <h5 class="mb-3">Personal Information</h5>
                            <p class="text-muted-custom">We may collect the following personal information when you use our Service:</p>
                            <ul class="text-muted-custom">
                                <li>Name and contact information (email address, phone number)</li>
                                <li>Account credentials (username, password)</li>
                                <li>Profile information and preferences</li>
                                <li>Payment information (processed securely by third-party payment processors)</li>
                                <li>Communications with our support team</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Usage Information</h5>
                            <p class="text-muted-custom">We automatically collect certain information when you use our Service:</p>
                            <ul class="text-muted-custom">
                                <li>Device information (device type, operating system, unique device identifiers)</li>
                                <li>App usage data (features used, time spent, viewing history)</li>
                                <li>Performance data (app crashes, loading times, errors)</li>
                                <li>Network information (IP address, internet service provider)</li>
                                <li>Location data (if you enable location services)</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Cookies and Tracking Technologies</h5>
                            <p class="text-muted-custom">
                                We use cookies, local storage, and similar tracking technologies to enhance your experience, remember your preferences, and analyze how you use our Service.
                            </p>
                        </div>
                        
                        <!-- How We Use Your Information -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">How We Use Your Information</h3>
                            <p class="text-muted-custom">We use the collected information for various purposes:</p>
                            <ul class="text-muted-custom">
                                <li><strong>Service Provision:</strong> To provide, maintain, and improve our streaming service</li>
                                <li><strong>Account Management:</strong> To create and manage your account, authenticate users</li>
                                <li><strong>Personalization:</strong> To customize content recommendations and user experience</li>
                                <li><strong>Communication:</strong> To send updates, notifications, and respond to inquiries</li>
                                <li><strong>Analytics:</strong> To analyze usage patterns and improve our Service</li>
                                <li><strong>Security:</strong> To detect, prevent, and address security issues and fraudulent activity</li>
                                <li><strong>Legal Compliance:</strong> To comply with legal obligations and enforce our terms</li>
                                <li><strong>Marketing:</strong> To send promotional materials (with your consent)</li>
                            </ul>
                        </div>
                        
                        <!-- Information Sharing -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">How We Share Your Information</h3>
                            <p class="text-muted-custom">We do not sell, trade, or rent your personal information to third parties. We may share your information in the following circumstances:</p>
                            
                            <h5 class="mb-3">Service Providers</h5>
                            <p class="text-muted-custom">
                                We may share information with trusted third-party service providers who assist us in operating our Service, such as:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Cloud hosting and storage providers</li>
                                <li>Payment processors</li>
                                <li>Analytics and performance monitoring services</li>
                                <li>Customer support platforms</li>
                                <li>Content delivery networks</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Legal Requirements</h5>
                            <p class="text-muted-custom">We may disclose your information if required by law or in good faith belief that such action is necessary to:</p>
                            <ul class="text-muted-custom">
                                <li>Comply with legal obligations or court orders</li>
                                <li>Protect and defend our rights or property</li>
                                <li>Prevent or investigate possible wrongdoing</li>
                                <li>Protect the safety of users or the public</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Business Transfers</h5>
                            <p class="text-muted-custom">
                                In the event of a merger, acquisition, or asset sale, your information may be transferred as part of the business transaction.
                            </p>
                        </div>
                        
                        <!-- Data Security -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Data Security</h3>
                            <p class="text-muted-custom">
                                We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Encryption of data in transit and at rest</li>
                                <li>Regular security assessments and updates</li>
                                <li>Access controls and authentication mechanisms</li>
                                <li>Secure data centers and infrastructure</li>
                                <li>Employee training on data protection practices</li>
                            </ul>
                            <p class="text-muted-custom">
                                However, no method of transmission over the internet or electronic storage is 100% secure. While we strive to protect your information, we cannot guarantee absolute security.
                            </p>
                        </div>
                        
                        <!-- Your Rights and Choices -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Your Rights and Choices</h3>
                            <p class="text-muted-custom">You have certain rights regarding your personal information:</p>
                            
                            <h5 class="mb-3">Access and Portability</h5>
                            <p class="text-muted-custom">
                                You can access and update your account information through the app settings. You may also request a copy of your personal data.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Correction and Deletion</h5>
                            <p class="text-muted-custom">
                                You can correct inaccurate information or request deletion of your account and associated data. Some information may be retained for legal or legitimate business purposes.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Marketing Communications</h5>
                            <p class="text-muted-custom">
                                You can opt out of marketing communications by following the unsubscribe instructions in emails or adjusting your preferences in the app.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Cookies and Tracking</h5>
                            <p class="text-muted-custom">
                                You can control cookie preferences through your device settings. However, disabling certain cookies may affect app functionality.
                            </p>
                        </div>
                        
                        <!-- Data Retention -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Data Retention</h3>
                            <p class="text-muted-custom">
                                We retain your personal information for as long as necessary to provide our Service and fulfill the purposes outlined in this Privacy Policy. Retention periods vary based on the type of information and legal requirements:
                            </p>
                            <ul class="text-muted-custom">
                                <li><strong>Account Information:</strong> Retained while your account is active and for a reasonable period after deletion</li>
                                <li><strong>Usage Data:</strong> Typically retained for 2-3 years for analytics purposes</li>
                                <li><strong>Communication Records:</strong> Retained for customer service and legal compliance purposes</li>
                                <li><strong>Financial Records:</strong> Retained as required by applicable tax and financial regulations</li>
                            </ul>
                        </div>
                        
                        <!-- Children's Privacy -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Children's Privacy</h3>
                            <p class="text-muted-custom">
                                Our Service is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you are a parent or guardian and believe your child has provided personal information, please contact us immediately.
                            </p>
                            <p class="text-muted-custom">
                                For users between 13 and 18, we recommend parental supervision and encourage parents to monitor their children's online activities.
                            </p>
                        </div>
                        
                        <!-- International Transfers -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">International Data Transfers</h3>
                            <p class="text-muted-custom">
                                Your information may be transferred to and processed in countries other than your country of residence. These countries may have different data protection laws. We ensure appropriate safeguards are in place to protect your information during such transfers.
                            </p>
                        </div>
                        
                        <!-- Changes to Privacy Policy -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Changes to This Privacy Policy</h3>
                            <p class="text-muted-custom">
                                We may update this Privacy Policy from time to time. We will notify you of significant changes by posting the new Privacy Policy in the app and updating the "Last updated" date. Your continued use of the Service after changes become effective constitutes acceptance of the updated Privacy Policy.
                            </p>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Contact Us</h3>
                            <p class="text-muted-custom">
                                If you have any questions about this Privacy Policy or our privacy practices, please contact us:
                            </p>
                            <ul class="list-unstyled text-muted-custom">
                                <li><strong>Email:</strong> <a href="mailto:{{ $contactEmail }}" class="text-primary">{{ $contactEmail }}</a></li>
                                <li><strong>Company:</strong> {{ $companyName }}</li>
                                <li><strong>App:</strong> {{ $siteName }}</li>
                            </ul>
                            <p class="text-muted-custom">
                                We are committed to addressing your concerns and resolving any privacy-related issues promptly.
                            </p>
                        </div>
                        
                        <!-- Effective Date -->
                        <div class="border-top border-custom pt-4">
                            <p class="text-muted-custom small mb-0">
                                This Privacy Policy is effective as of {{ date('F j, Y') }} and applies to all information collected by {{ $siteName }}.
                            </p>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
