@extends('layouts.landing')

@section('title', 'End User License Agreement (EULA) - ' . env('LANDING_SITE_NAME', 'Luganda Translated Movies'))
@section('description', 'End User License Agreement for the Luganda Translated Movies mobile application.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">End User License Agreement</h1>
                    <p class="hero-subtitle">
                        License agreement for the {{ $siteName }} mobile application.
                    </p>
                    <p class="text-muted-custom">
                        <small>Last updated: {{ date('F j, Y') }}</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- EULA Content -->
<section class="content-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">
                        
                        <!-- Introduction -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Agreement Overview</h3>
                            <p class="text-muted-custom">
                                This End User License Agreement ("EULA") is a legal agreement between you ("User" or "you") and {{ $companyName }} ("Company," "we," "our," or "us") for the {{ $siteName }} mobile application ("Application" or "App").
                            </p>
                            <p class="text-muted-custom">
                                By downloading, installing, or using the Application, you agree to be bound by the terms of this EULA. If you do not agree to the terms of this EULA, do not download, install, or use the Application.
                            </p>
                        </div>
                        
                        <!-- License Grant -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">License Grant</h3>
                            
                            <h5 class="mb-3">Limited License</h5>
                            <p class="text-muted-custom">
                                Subject to the terms of this EULA, the Company grants you a limited, non-exclusive, non-transferable, revocable license to:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Download and install the Application on your personal mobile device</li>
                                <li>Use the Application for personal, non-commercial purposes</li>
                                <li>Access and stream content available through the Application</li>
                                <li>Download content for offline viewing as permitted by the Application</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">License Restrictions</h5>
                            <p class="text-muted-custom">You may not:</p>
                            <ul class="text-muted-custom">
                                <li>Copy, modify, or create derivative works of the Application</li>
                                <li>Reverse engineer, decompile, or disassemble the Application</li>
                                <li>Remove or alter any proprietary notices or labels on the Application</li>
                                <li>Use the Application for any commercial purpose without written consent</li>
                                <li>Distribute, sell, sublicense, rent, or lease the Application</li>
                                <li>Use the Application in violation of any applicable laws or regulations</li>
                                <li>Interfere with or disrupt the Application's servers or networks</li>
                            </ul>
                        </div>
                        
                        <!-- Intellectual Property Rights -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Intellectual Property Rights</h3>
                            
                            <h5 class="mb-3">Company Ownership</h5>
                            <p class="text-muted-custom">
                                The Application and all its components, including but not limited to software, graphics, text, photographs, music, videos, and interactive features, are owned by the Company or its licensors and are protected by copyright, trademark, patent, and other intellectual property laws.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Translation Content</h5>
                            <p class="text-muted-custom">
                                The Luganda translations, subtitles, and localized content provided through the Application are proprietary works of the Company. These translations are protected by copyright and may not be reproduced, distributed, or used outside the Application without express written permission.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Third-Party Content</h5>
                            <p class="text-muted-custom">
                                The Application may contain content licensed from third parties. Such content remains the property of the respective copyright holders and is used under license agreements.
                            </p>
                        </div>
                        
                        <!-- User Obligations -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">User Obligations</h3>
                            
                            <h5 class="mb-3">Account Responsibility</h5>
                            <p class="text-muted-custom">You are responsible for:</p>
                            <ul class="text-muted-custom">
                                <li>Maintaining the confidentiality of your account credentials</li>
                                <li>All activities that occur under your account</li>
                                <li>Ensuring your device meets the Application's system requirements</li>
                                <li>Maintaining a stable internet connection for streaming services</li>
                                <li>Paying all applicable subscription fees and taxes</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Prohibited Activities</h5>
                            <p class="text-muted-custom">You agree not to:</p>
                            <ul class="text-muted-custom">
                                <li>Share your account with unauthorized users</li>
                                <li>Use automated tools or bots to access the Application</li>
                                <li>Attempt to circumvent security measures or access controls</li>
                                <li>Upload malicious code or attempt to harm the Application</li>
                                <li>Collect user information without proper consent</li>
                                <li>Use the Application to violate any laws or regulations</li>
                            </ul>
                        </div>
                        
                        <!-- Content Usage and Downloads -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Content Usage and Downloads</h3>
                            
                            <h5 class="mb-3">Streaming Rights</h5>
                            <p class="text-muted-custom">
                                Your subscription grants you the right to stream content available in the Application's catalog. Content availability may vary based on licensing agreements and geographic restrictions.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Download Feature</h5>
                            <p class="text-muted-custom">
                                Where available, you may download content for offline viewing subject to the following conditions:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Downloads are for personal, non-commercial use only</li>
                                <li>Downloaded content expires according to licensing terms</li>
                                <li>Downloads may be limited by number or duration</li>
                                <li>Downloaded content cannot be transferred to other devices or users</li>
                                <li>Downloads are automatically deleted when your subscription ends</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Content Restrictions</h5>
                            <p class="text-muted-custom">
                                You may not:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Record, capture, or redistribute streaming content</li>
                                <li>Use screen recording or similar technologies</li>
                                <li>Extract audio or subtitle files from the Application</li>
                                <li>Publicly display or perform content from the Application</li>
                            </ul>
                        </div>
                        
                        <!-- Device and System Requirements -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Device and System Requirements</h3>
                            
                            <h5 class="mb-3">Supported Platforms</h5>
                            <p class="text-muted-custom">The Application is designed for:</p>
                            <ul class="text-muted-custom">
                                <li><strong>iOS:</strong> Devices running iOS 12.0 or later</li>
                                <li><strong>Android:</strong> Devices running Android 7.0 (API level 24) or higher</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Hardware Requirements</h5>
                            <p class="text-muted-custom">For optimal performance:</p>
                            <ul class="text-muted-custom">
                                <li>Minimum 2GB RAM for smooth streaming</li>
                                <li>At least 200MB free storage space</li>
                                <li>Stable internet connection (minimum 3 Mbps for standard quality)</li>
                                <li>Compatible audio and video codecs</li>
                            </ul>
                        </div>
                        
                        <!-- Updates and Modifications -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Updates and Modifications</h3>
                            
                            <h5 class="mb-3">Automatic Updates</h5>
                            <p class="text-muted-custom">
                                The Application may automatically download and install updates to improve functionality, security, and performance. You can control update settings through your device's app store settings.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Feature Changes</h5>
                            <p class="text-muted-custom">
                                We reserve the right to modify, update, or discontinue features of the Application at any time. We will provide reasonable notice of significant changes when possible.
                            </p>
                        </div>
                        
                        <!-- Privacy and Data Collection -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Privacy and Data Collection</h3>
                            <p class="text-muted-custom">
                                The Application collects and processes personal data as described in our Privacy Policy. By using the Application, you consent to such collection and processing in accordance with our Privacy Policy, which is incorporated into this EULA by reference.
                            </p>
                            
                            <h5 class="mb-3 mt-4">Analytics and Performance</h5>
                            <p class="text-muted-custom">
                                The Application may collect anonymous usage data, performance metrics, and crash reports to improve the user experience and Application functionality.
                            </p>
                        </div>
                        
                        <!-- Disclaimers and Limitations -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Disclaimers and Limitations</h3>
                            
                            <h5 class="mb-3">No Warranty</h5>
                            <p class="text-muted-custom">
                                The Application is provided "as is" and "as available" without warranties of any kind, either express or implied, including but not limited to:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Merchantability or fitness for a particular purpose</li>
                                <li>Non-infringement of third-party rights</li>
                                <li>Continuous, uninterrupted, or error-free operation</li>
                                <li>Security or freedom from viruses or malicious code</li>
                                <li>Accuracy or completeness of content</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Limitation of Liability</h5>
                            <p class="text-muted-custom">
                                To the maximum extent permitted by applicable law, the Company shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Loss of profits, data, or business opportunities</li>
                                <li>Service interruptions or content unavailability</li>
                                <li>Device damage or data corruption</li>
                                <li>Third-party actions or content</li>
                            </ul>
                        </div>
                        
                        <!-- Termination -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Termination</h3>
                            
                            <h5 class="mb-3">Termination by User</h5>
                            <p class="text-muted-custom">
                                You may terminate this EULA at any time by:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Uninstalling the Application from your device</li>
                                <li>Cancelling your subscription through the Application or app store</li>
                                <li>Deleting your account and associated data</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Termination by Company</h5>
                            <p class="text-muted-custom">
                                We may terminate this EULA immediately if you:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Violate any terms of this EULA</li>
                                <li>Engage in fraudulent or illegal activities</li>
                                <li>Compromise the security or integrity of the Application</li>
                                <li>Fail to pay applicable fees</li>
                            </ul>
                            
                            <h5 class="mb-3 mt-4">Effect of Termination</h5>
                            <p class="text-muted-custom">
                                Upon termination:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Your license to use the Application immediately ends</li>
                                <li>You must cease all use of the Application</li>
                                <li>Downloaded content becomes inaccessible</li>
                                <li>Account data may be deleted according to our retention policies</li>
                            </ul>
                        </div>
                        
                        <!-- Governing Law -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Governing Law and Jurisdiction</h3>
                            <p class="text-muted-custom">
                                This EULA shall be governed by and construed in accordance with the laws of Uganda. Any disputes arising under this EULA shall be subject to the exclusive jurisdiction of the courts of Uganda.
                            </p>
                        </div>
                        
                        <!-- Changes to EULA -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Changes to This EULA</h3>
                            <p class="text-muted-custom">
                                We reserve the right to modify this EULA at any time. We will notify you of material changes through the Application or other reasonable means. Your continued use of the Application after such modifications constitutes acceptance of the updated EULA.
                            </p>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Contact Information</h3>
                            <p class="text-muted-custom">
                                If you have any questions about this EULA, please contact us:
                            </p>
                            <ul class="list-unstyled text-muted-custom">
                                <li><strong>Email:</strong> <a href="mailto:{{ $contactEmail }}" class="text-primary">{{ $contactEmail }}</a></li>
                                <li><strong>Company:</strong> {{ $companyName }}</li>
                                <li><strong>Application:</strong> {{ $siteName }}</li>
                            </ul>
                        </div>
                        
                        <!-- Severability -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Severability</h3>
                            <p class="text-muted-custom">
                                If any provision of this EULA is held to be invalid or unenforceable, the remaining provisions shall remain in full force and effect to the maximum extent permitted by law.
                            </p>
                        </div>
                        
                        <!-- Acknowledgment -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">Acknowledgment</h3>
                            <p class="text-muted-custom">
                                By downloading, installing, or using the Application, you acknowledge that you have read this EULA, understand it, and agree to be bound by its terms and conditions.
                            </p>
                        </div>
                        
                        <!-- Effective Date -->
                        <div class="border-top border-custom pt-4">
                            <p class="text-muted-custom small mb-0">
                                This End User License Agreement is effective as of {{ date('F j, Y') }} and applies to all users of the {{ $siteName }} mobile application.
                            </p>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
