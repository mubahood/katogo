<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Utils;
use App\Services\GhostAccountService;
use Closure;
use Dflydev\DotAccessData\Util;
use JWTAuth;
use Exception;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Illuminate\Support\Str;

class JwtMiddleware extends BaseMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    protected $except = [
        'login',
        'auth/login',
        'auth/register',
        'auth/password-reset',
        'register',
        'users/register',
        'users/login',
        'api/otp-verify',
        'min/login',
    ];

    public function handle($request, Closure $next)
    {
        if (!$request->expectsJson()) {
            return $next($request);
        }


        //check if request is login or register

        if (
            Str::contains($_SERVER['REQUEST_URI'], 'login') ||
            Str::contains($_SERVER['REQUEST_URI'], 'otp') ||
            Str::contains($_SERVER['REQUEST_URI'], 'otp-verify') ||
            Str::contains($_SERVER['REQUEST_URI'], 'register')
        ) {
            return $next($request);
        }
        $tok = $request->header('Tok');
        // If request starts with api then we will check for token
        if (!$request->is('api/*')) {
            return $next($request);
        }

        //$request->headers->set('Authorization', $headers['authorization']);// set header in request
        try {
            //$headers = apache_request_headers(); //get header
            $headers = getallheaders(); //get header

            header('Content-Type: application/json');

            $Authorization = "";
            if (isset($headers['Authorization']) && $headers['Authorization'] != "") {
                $Authorization = $headers['Authorization'];
            } else if (isset($headers['authorization']) && $headers['authorization'] != "") {
                $Authorization = $headers['authorization'];
            } else if (isset($headers['Authorizations']) && $headers['Authorizations'] != "") {
                $Authorization = $headers['Authorizations'];
            } else if (isset($headers['authorizations']) && $headers['authorizations'] != "") {
                $Authorization = $headers['authorizations'];
            } else if (isset($headers['Tok']) && $headers['Tok'] != "") {
                $Authorization = $headers['Tok'];
            }


            $request->headers->set('Authorization', $Authorization); // set header in request
            $request->headers->set('authorization', $Authorization); // set header in request

            $user = FacadesJWTAuth::parseToken()->authenticate();

            if (!$user) {
                // Token is cryptographically valid but the account no longer exists in DB.
                // Attempt to resurrect the ghost account using the user_id from the JWT sub claim.
                $user = $this->tryResurrectGhost();
                if ($user) {
                    auth('api')->setUser($user);
                } else {
                    return response()->json(['code' => 0, 'message' => 'User not found'], 401);
                }
            }
        } catch (Exception $e) {
            // If JWT fails, the request continues and Utils::get_user will check for logged_in_user_id header
            // This allows backward compatibility with older clients that don't use JWT
        }
        return $next($request);
    }

    private function tryResurrectGhost(): ?User
    {
        try {
            $payload = FacadesJWTAuth::getPayload();
            $userId  = (int) ($payload->get('sub') ?? 0);
            if ($userId <= 0) {
                return null;
            }
            return app(GhostAccountService::class)->resurrect($userId);
        } catch (\Throwable $e) {
            Log::warning('Ghost resurrection attempt failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
