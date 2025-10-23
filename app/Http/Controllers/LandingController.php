<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\MovieModel;
use App\Models\SeriesMovie;
use Illuminate\Support\Facades\DB;

class LandingController extends Controller
{
    /**
     * Show the landing page
     */
    public function index()
    {
        // Get featured movies and series for landing page
        $featuredMovies = MovieModel::where('status', 'Active')
            ->where('type', 'Movie')
            ->orderBy('id', 'desc')
            ->limit(12)
            ->get();

        $featuredSeries = MovieModel::where('status', 'Active')
            ->where('type', 'Series')
            ->where('is_first_episode', 'Yes')
            ->orderBy('id', 'desc')
            ->limit(12)
            ->get();

        return view('landing.index', [
            'siteName' => env('APP_NAME', 'LugaFlix - Luganda Translated Movies'),
            'companyName' => env('LANDING_COMPANY_NAME', 'UGFlix Ltd'),
            'appStoreUrl' => env('LANDING_APP_STORE_URL', 'https://apps.apple.com/ug/app/hambren/id6475098479'),
            'playStoreUrl' => env('PLAYSTORE_LINK', 'https://play.google.com/store/apps/details?id=lugaflix.movies'),
            'facebookUrl' => env('LANDING_FACEBOOK_URL', 'https://facebook.com/ugflix'),
            'twitterUrl' => env('LANDING_TWITTER_URL', 'https://twitter.com/ugflix'),
            'instagramUrl' => env('LANDING_INSTAGRAM_URL', 'https://instagram.com/ugflix'),
            'youtubeUrl' => env('LANDING_YOUTUBE_URL', 'https://youtube.com/@ugflix'),
            'featuredMovies' => $featuredMovies,
            'featuredSeries' => $featuredSeries,
        ]);
    }

    /**
     * Show movies listing page (SEO optimized)
     */
    public function movies(Request $request)
    {
        $perPage = 24;
        $page = $request->get('page', 1);

        $movies = MovieModel::where('status', 'Active')
            ->where('type', 'Movie')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return view('landing.movies', [
            'siteName' => env('APP_NAME', 'LugaFlix'),
            'playStoreUrl' => env('PLAYSTORE_LINK'),
            'movies' => $movies,
            'currentPage' => $page,
            'totalMovies' => $movies->total(),
        ]);
    }

    /**
     * Show series listing page (SEO optimized)
     */
    public function series(Request $request)
    {
        $perPage = 24;
        $page = $request->get('page', 1);

        // Get series episodes (each episode is a separate record)
        $series = MovieModel::where('status', 'Active')
            ->where('type', 'Series')
            ->where('is_first_episode', 'Yes')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return view('landing.series', [
            'siteName' => env('APP_NAME', 'LugaFlix'),
            'playStoreUrl' => env('PLAYSTORE_LINK'),
            'series' => $series,
            'currentPage' => $page,
            'totalSeries' => $series->total(),
        ]);
    }

    /**
     * Show single movie detail page (SEO optimized)
     */
    public function movieDetail($id)
    {
        $movie = MovieModel::findOrFail($id);

        // Get related movies
        $relatedMovies = MovieModel::where('status', 'Active')
            ->where('type', $movie->type)
            ->where('id', '!=', $movie->id)
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        // Get episodes if it's a series
        // Episodes are stored in movie_models table with same title pattern
        $episodes = [];
        if ($movie->type === 'Series') {
            // Extract series name from title (before " - Episode")
            $seriesName = preg_replace('/ - Episode \d+$/', '', $movie->title);

            // Get all episodes with similar title
            $episodes = MovieModel::where('status', 'Active')
                ->where('type', 'Series')
                ->where('title', 'like', $seriesName . ' - Episode%')
                ->orderBy('season_number', 'asc')
                ->orderBy('episode_number', 'asc')
                ->get();
        }

        return view('landing.movie-detail', [
            'siteName' => env('APP_NAME', 'LugaFlix'),
            'playStoreUrl' => env('PLAYSTORE_LINK'),
            'movie' => $movie,
            'relatedMovies' => $relatedMovies,
            'episodes' => $episodes,
        ]);
    }

    /**
     * Generate dynamic sitemap for movies
     */
    public function sitemap()
    {
        $movies = MovieModel::where('status', 'Active')
            ->orderBy('updated_at', 'desc')
            ->get();

        //set output to be xml

        header('Content-Type: text/xml; charset=utf-8');

        return response()->view('landing.sitemap', [
            'movies' => $movies,
        ])->header('Content-Type', 'text/xml');
    }

    /**
     * Show the support page
     */
    public function support()
    {
        return view('landing.support', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'supportEmail' => env('LANDING_SUPPORT_EMAIL'),
            'phone' => env('LANDING_PHONE'),
            'faqUrl' => env('LANDING_FAQ_URL'),
        ]);
    }

    /**
     * Show the FAQ page
     */
    public function faq()
    {
        return view('landing.faq', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
        ]);
    }

    /**
     * Show the contact page
     */
    public function contact()
    {
        return view('landing.contact', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'contactEmail' => env('LANDING_CONTACT_EMAIL'),
            'phone' => env('LANDING_PHONE'),
            'address' => env('LANDING_ADDRESS'),
        ]);
    }

    /**
     * Handle contact form submission
     */
    public function contactSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Send email notification (you can customize this)
            Mail::raw(
                "New contact form submission:\n\n" .
                    "Name: " . $request->name . "\n" .
                    "Email: " . $request->email . "\n" .
                    "Subject: " . $request->subject . "\n\n" .
                    "Message:\n" . $request->message,
                function ($message) use ($request) {
                    $message->to(env('LANDING_CONTACT_EMAIL'))
                        ->subject('Contact Form: ' . $request->subject)
                        ->replyTo($request->email, $request->name);
                }
            );

            return back()->with('success', 'Thank you for your message. We will get back to you soon!');
        } catch (\Exception $e) {
            return back()->with('error', 'Sorry, there was an error sending your message. Please try again.');
        }
    }

    /**
     * Show the privacy policy page
     */
    public function privacyPolicy()
    {
        return view('landing.privacy-policy', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'companyName' => env('LANDING_COMPANY_NAME', 'Luganda Movies Ltd'),
            'contactEmail' => env('LANDING_CONTACT_EMAIL'),
        ]);
    }

    /**
     * Show the terms of service page
     */
    public function termsOfService()
    {
        return view('landing.terms-of-service', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'companyName' => env('LANDING_COMPANY_NAME', 'Luganda Movies Ltd'),
            'contactEmail' => env('LANDING_CONTACT_EMAIL'),
        ]);
    }

    /**
     * Show the EULA page
     */
    public function eula()
    {
        return view('landing.eula', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'companyName' => env('LANDING_COMPANY_NAME', 'Luganda Movies Ltd'),
            'contactEmail' => env('LANDING_CONTACT_EMAIL'),
        ]);
    }

    /**
     * Show the about page
     */
    public function about()
    {
        return view('landing.about', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
            'companyName' => env('LANDING_COMPANY_NAME', 'Luganda Movies Ltd'),
        ]);
    }

    /**
     * Show the features page
     */
    public function features()
    {
        return view('landing.features', [
            'siteName' => env('LANDING_SITE_NAME', 'Luganda Translated Movies'),
        ]);
    }
}
