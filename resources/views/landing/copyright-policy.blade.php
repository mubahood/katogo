@extends('layouts.landing')

@section('title', 'Copyright & Content Policy - ' . env('LANDING_SITE_NAME', 'LugaFlix'))
@section('description', 'Our copyright policy, content standards, and the procedure for reporting content you believe infringes your rights.')

@section('content')
<!-- Hero Section -->
<section class="hero-section" style="padding: 8rem 0 4rem;">
    <div class="container">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="hero-content">
                    <h1 class="hero-title">Copyright &amp; Content Policy</h1>
                    <p class="hero-subtitle">
                        How we handle intellectual property and how to report content you believe infringes your rights.
                    </p>
                    <p class="text-muted-custom">
                        <small>Last updated: {{ date('F j, Y') }}</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Policy Content -->
<section class="content-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">1. Respect for Intellectual Property</h3>
                            <p class="text-muted-custom">
                                {{ $siteName ?? env('LANDING_SITE_NAME', 'LugaFlix') }} respects the intellectual property rights of others and expects the same
                                from everyone who uses the Service. We take claims of copyright infringement seriously and will
                                respond promptly to notices of alleged infringement that comply with applicable law, including the
                                Copyright and Neighbouring Rights Act of Uganda and, where applicable, the United States Digital
                                Millennium Copyright Act (DMCA).
                            </p>
                        </div>

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">2. About Our Content</h3>
                            <p class="text-muted-custom">
                                Our platform features audio commentary and translation in Luganda performed by local voice
                                artists. We are actively working with voice artists and content partners to formalize and expand
                                our licensing arrangements. Where we do not hold rights to particular material, we will remove
                                it upon receipt of a valid infringement notice as described below.
                            </p>
                        </div>

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">3. Reporting Copyright Infringement</h3>
                            <p class="text-muted-custom">
                                If you are a copyright owner (or authorized to act on behalf of one) and believe that content
                                available through the Service infringes your copyright, please send a written notice to our
                                designated contact below. Your notice should include:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Identification of the copyrighted work you claim has been infringed;</li>
                                <li>Identification of the material you claim is infringing, with enough detail for us to locate it (e.g., title and URL within the app or website);</li>
                                <li>Your full name, postal address, telephone number, and email address;</li>
                                <li>A statement that you have a good-faith belief that the use of the material is not authorized by the copyright owner, its agent, or the law;</li>
                                <li>A statement, under penalty of perjury, that the information in your notice is accurate and that you are the copyright owner or authorized to act on the owner's behalf;</li>
                                <li>Your physical or electronic signature.</li>
                            </ul>
                        </div>

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">4. Our Takedown Procedure</h3>
                            <p class="text-muted-custom">
                                Upon receiving a valid infringement notice, we will:
                            </p>
                            <ul class="text-muted-custom">
                                <li>Acknowledge receipt of your notice within two (2) business days;</li>
                                <li>Remove or disable access to the identified material promptly, normally within five (5) business days;</li>
                                <li>Notify you once the material has been removed or access disabled;</li>
                                <li>Keep records of infringement notices and the actions taken.</li>
                            </ul>
                            <p class="text-muted-custom">
                                We may terminate the accounts of users who repeatedly upload or share infringing material.
                            </p>
                        </div>

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">5. Counter-Notices</h3>
                            <p class="text-muted-custom">
                                If material you provided was removed and you believe the removal was a mistake or that you have
                                the right to use the material, you may submit a written counter-notice to the same contact below,
                                including identification of the removed material, a statement under penalty of perjury explaining
                                your good-faith belief that removal was erroneous, your contact details, and your signature.
                            </p>
                        </div>

                        <div class="mb-5">
                            <h3 class="text-primary mb-3">6. Designated Copyright Contact</h3>
                            <p class="text-muted-custom">
                                <strong>Copyright Agent</strong><br>
                                {{ $companyName ?? env('LANDING_COMPANY_NAME', 'LugaFlix') }}<br>
                                Kampala, Uganda<br>
                                Email: <a href="mailto:{{ env('LANDING_COPYRIGHT_EMAIL', env('LANDING_CONTACT_EMAIL', 'farasuganda1@gmail.com')) }}">{{ env('LANDING_COPYRIGHT_EMAIL', env('LANDING_CONTACT_EMAIL', 'farasuganda1@gmail.com')) }}</a>
                            </p>
                            <p class="text-muted-custom">
                                Please use the subject line <em>"Copyright Infringement Notice"</em> so we can route your report quickly.
                            </p>
                        </div>

                        <div>
                            <h3 class="text-primary mb-3">7. Changes to This Policy</h3>
                            <p class="text-muted-custom">
                                We may update this policy from time to time. The "Last updated" date above reflects the most
                                recent revision. Continued use of the Service after changes constitutes acceptance of the updated policy.
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
