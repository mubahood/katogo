<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DynamicCrudController;
use App\Models\User;

// Test route to verify account endpoints work
Route::get('/test-account-apis', function () {
    // Create a test user if none exists
    $user = User::first();
    if (!$user) {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    // Simulate authentication
    auth()->login($user);

    $controller = new DynamicCrudController();

    try {
        // Test dashboard endpoint
        $request = new \Illuminate\Http\Request();
        $request->headers->set('Authorization', 'Bearer test-token');
        
        $dashboardResponse = $controller->get_account_dashboard($request);
        $dashboard = json_decode($dashboardResponse->getContent(), true);
        
        // Test watchlist endpoints
        $watchlistResponse = $controller->get_watchlist($request);
        $watchlist = json_decode($watchlistResponse->getContent(), true);
        
        // Test liked movies endpoints
        $likesResponse = $controller->get_liked_movies($request);
        $likes = json_decode($likesResponse->getContent(), true);
        
        return response()->json([
            'message' => 'Account API Test Results',
            'tests' => [
                'dashboard' => [
                    'status' => $dashboard['success'] ? 'PASS' : 'FAIL',
                    'data' => $dashboard
                ],
                'watchlist' => [
                    'status' => $watchlist['success'] ? 'PASS' : 'FAIL',
                    'data' => $watchlist
                ],
                'likes' => [
                    'status' => $likes['success'] ? 'PASS' : 'FAIL',
                    'data' => $likes
                ]
            ],
            'user' => $user->only(['id', 'name', 'email']),
            'timestamp' => now()->toISOString()
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'API Test Failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Test route for frontend integration
Route::get('/test-frontend', function () {
    return view('account');
});