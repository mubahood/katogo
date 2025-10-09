<?php

namespace App\Services;

use App\Models\MovieCrawlerWebsite;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling Munowatch authentication and token refresh
 */
class MunowatchAuthService
{
    /**
     * Login to munowatch and get fresh session ID
     * 
     * @return array Array containing 'success' (bool), 'session_id' (string), 'user_id' (int), 'error' (string)
     */
    public static function login()
    {
        try {
            // Munowatch login credentials
            $email = 'Jumaperejunior@gmail.com';
            $password = 'uganda7766';
            $version = '3.4.0';
            $deviceInfo = 'Laravel Katogo Crawler';
            $device = 'server';
            
            // Login endpoint
            $loginUrl = 'https://munowatch.org/api/users/login/v2';
            
            // Prepare POST data
            $postData = [
                'email' => $email,
                'password' => $password,
                'version' => $version,
                'deviceinfo' => $deviceInfo,
                'device' => $device
            ];
            
            // Get current app token for authentication
            $munowatchWebsite = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
            $currentAppToken = $munowatchWebsite ? $munowatchWebsite->token : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';
            
            // Initialize cURL
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $loginUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: Laravel Katogo v3.4',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $currentAppToken
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($error) {
                Log::error('Munowatch login cURL error', ['error' => $error]);
                return [
                    'success' => false,
                    'session_id' => null,
                    'user_id' => null,
                    'error' => 'Network error: ' . $error
                ];
            }
            
            if ($httpCode !== 200) {
                Log::error('Munowatch login HTTP error', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return [
                    'success' => false,
                    'session_id' => null,
                    'user_id' => null,
                    'error' => "HTTP error: $httpCode"
                ];
            }
            
            // Parse JSON response
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Munowatch login JSON decode error', [
                    'json_error' => json_last_error_msg(),
                    'response' => $response
                ]);
                return [
                    'success' => false,
                    'session_id' => null,
                    'user_id' => null,
                    'error' => 'Invalid JSON response'
                ];
            }
            
            // Check if login was successful and extract session_id
            if (isset($data['user']['session_id']) && !empty($data['user']['session_id'])) {
                $sessionId = $data['user']['session_id'];
                $userId = $data['user']['id'];
                
                Log::info('Munowatch login successful', [
                    'user_id' => $userId,
                    'session_id_prefix' => substr($sessionId, 0, 8) . '...'
                ]);
                
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'error' => null
                ];
                
            } else {
                $errorMsg = isset($data['error']) ? $data['error'] : 'No session ID in response';
                
                Log::error('Munowatch login failed', [
                    'error_message' => $errorMsg,
                    'response_data' => $data
                ]);
                
                return [
                    'success' => false,
                    'session_id' => null,
                    'user_id' => null,
                    'error' => $errorMsg
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Munowatch login exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'session_id' => null,
                'user_id' => null,
                'error' => 'Login exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if API response indicates authentication failure
     * 
     * @param string $response API response
     * @return bool True if authentication failed
     */
    public static function isAuthenticationFailure($response)
    {
        if (empty($response)) {
            return false;
        }
        
        // Try to decode as JSON first
        $data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Check for specific error messages indicating authentication failure
            $authFailureMessages = [
                'token expired',
                'token has expired',
                'jwt expired',
                'jwt token expired',
                'unauthorized',
                'authentication failed',
                'invalid token',
                'access denied',
                'permission denied',
                'forbidden'
            ];
            
            // Check error field
            if (isset($data['error'])) {
                $errorMsg = strtolower(trim($data['error']));
                foreach ($authFailureMessages as $failureMessage) {
                    if (strpos($errorMsg, $failureMessage) !== false) {
                        return true;
                    }
                }
            }
            
            // Check message field
            if (isset($data['message'])) {
                $message = strtolower(trim($data['message']));
                foreach ($authFailureMessages as $failureMessage) {
                    if (strpos($message, $failureMessage) !== false) {
                        return true;
                    }
                }
            }
            
            // Check for HTTP error status codes
            if (isset($data['status'])) {
                $statusCodes = [401, 403];
                if (in_array($data['status'], $statusCodes)) {
                    return true;
                }
            }
        }
        
        // Check for HTTP error patterns in raw response
        $lowerResponse = strtolower($response);
        $httpErrorPatterns = [
            'http/1.1 401',
            'http/1.1 403',
            'unauthorized',
            'forbidden'
        ];
        
        foreach ($httpErrorPatterns as $pattern) {
            if (strpos($lowerResponse, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Make an API call with automatic authentication refresh
     * 
     * @param string $url API endpoint URL
     * @param string $bearerToken Bearer token (app token)
     * @param string $apiKey API key
     * @param string $method HTTP method
     * @param array $data Request data
     * @param int $maxRetries Maximum retry attempts
     * @return string JSON response
     */
    public static function callApiWithAutoRefresh($url, $bearerToken, $apiKey, $method = 'GET', $data = [], $maxRetries = 3)
    {
        try {
            // First attempt with current token
            $response = self::makeApiCall($url, $bearerToken, $apiKey, $method, $data);
            
            // Check if authentication failed
            if (self::isAuthenticationFailure($response)) {
                Log::info('Authentication failure detected, attempting user login', [
                    'url' => $url
                ]);
                
                // Attempt user login to get fresh session
                $loginResult = self::login();
                
                if ($loginResult['success']) {
                    Log::info('User login successful, but app token might still be expired', [
                        'user_id' => $loginResult['user_id']
                    ]);
                    
                    // Note: The session_id from user login is different from app JWT token
                    // For now, we'll just return the failed response and log the issue
                    // In a production system, you might need to handle app token refresh differently
                    Log::warning('App JWT token appears to be expired but no refresh mechanism available', [
                        'url' => $url,
                        'response_snippet' => substr($response, 0, 100)
                    ]);
                }
                
                return $response;
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Munowatch API call with auto-refresh failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Make a basic API call to munowatch
     * 
     * @param string $url API endpoint URL
     * @param string $bearerToken Bearer token
     * @param string $apiKey API key
     * @param string $method HTTP method
     * @param array $data Request data
     * @return string Response
     */
    private static function makeApiCall($url, $bearerToken, $apiKey, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $bearerToken,
            'X-Api-Key: ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: Laravel Katogo v3.4',
            'Accept: application/json'
        ];
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ];
        
        if (strtoupper($method) === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            if (!empty($data)) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            Log::warning('Non-200 HTTP response from munowatch API', [
                'url' => $url,
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200)
            ]);
        }
        
        return $response;
    }
}