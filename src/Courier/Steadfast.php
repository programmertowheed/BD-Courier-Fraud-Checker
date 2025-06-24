<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;
use ShahariarAhmad\CourierFraudCheckerBd\Helpers\CourierFraudCheckerHelper;

class Steadfast
{
    use Helpers;

    protected int $tryToken = 0;
    protected int $tryLogin = 0;
    protected string $cacheKey = 'steedfast_cookie';
    protected int $cacheMinutes = 50;

    public function __construct()
    {
        // Check for required environment variables
        $this->checkRequiredConfig(['steedfast_user', 'steedfast_password']);
        $this->tryToken = 0;
        $this->tryLogin = 0;
    }

    public function steadfastOld($phoneNumber)
    {
        $phoneNumber = $this->validateBDPhoneNumber($phoneNumber);
        $email = config("bdcourierfraudchecker.steedfast_user");
        $password = config("bdcourierfraudchecker.steedfast_password");

        // First Fetch login page
        $response = Http::get('https://steadfast.com.bd/login');

        // Get CSRF token
        preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $response->body(), $matches);
        $token = $matches[1] ?? null;

        if (!$token) {
            $this->tryToken++;

            if ($this->tryToken < 2) {
                return $this->steadfast($phoneNumber);
            } else {
                return [
                    'status' => false,
                    'message' => "CSRF Token not found",
                ];
            }
        }

        // Convert all Cookies as an associative array
        $rawCookies = $response->cookies();
        $cookiesArray = [];
        foreach ($rawCookies->toArray() as $cookie) {
            $cookiesArray[$cookie['Name']] = $cookie['Value'];
        }


        // Then Log in
        $loginRequest = Http::withCookies($cookiesArray, 'steadfast.com.bd')
            ->asForm()
            ->post('https://steadfast.com.bd/login', [
                '_token' => $token,
                'email' => $email,
                'password' => $password
            ]);


        // Check if the login response
        if ($loginRequest->successful() || $loginRequest->redirect()) {
            // Again, convert Cookie
            $loginCookiesArray = [];
            foreach ($loginRequest->cookies()->toArray() as $cookie) {
                $loginCookiesArray[$cookie['Name']] = $cookie['Value'];
            }

            $authResponse = Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
                ->get('https://steadfast.com.bd/user/frauds/check/' . $phoneNumber);

            if ($authResponse->successful()) {
                $object = $authResponse->collect()->toArray();

                $steadfast = [
                    'success' => $object['total_delivered'],
                    'cancel' => $object['total_cancelled'],
                    'total' => $object['total_delivered'] + $object['total_cancelled'],
                ];

                $logoutGETRequest = Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
                    ->get('https://steadfast.com.bd/user/frauds/check');

                // Ensure the HTML is not empty
                if ($logoutGETRequest->successful()) {
                    $html = $logoutGETRequest->body();

                    // Attempt to extract CSRF token
                    if (preg_match('/<meta name="csrf-token" content="(.*?)"/', $html, $matches)) {
                        $csrfToken = $matches[1] ?? null;

                        Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
                            ->asForm()
                            ->post('https://steadfast.com.bd/logout', [
                                '_token' => $csrfToken
                            ]);
                    }
                }

                return $steadfast;
            } else {
                // Get the full response body as JSON
                $error = $authResponse->json();

                // Optionally get a specific message
                $message = $error['message'] ?? 'Unknown error occurred.';

                return [
                    'status' => false,
                    'message' => $message,
                ];
            }

        } else {
            return [
                'status' => false,
                'message' => "Authentication failed",
            ];
        }
    }

    public function steadfast($phoneNumber)
    {
        $phoneNumber = $this->validateBDPhoneNumber($phoneNumber);
        $email = config("bdcourierfraudchecker.steedfast_user");
        $password = config("bdcourierfraudchecker.steedfast_password");

        $loginCookiesArray = Cache::get($this->cacheKey);

        if (!$loginCookiesArray) {
            // First Fetch login page
            $response = Http::get('https://steadfast.com.bd/login');

            // Get CSRF token
            preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $response->body(), $matches);
            $token = $matches[1] ?? null;

            if (!$token) {
                if ($this->tryToken < 2) {
                    $this->tryToken++;
                    return $this->steadfast($phoneNumber);
                } else {
                    return [
                        'status' => false,
                        'message' => "CSRF Token not found",
                    ];
                }
            }

            // Convert all Cookies as an associative array
            $rawCookies = $response->cookies();
            $cookiesArray = [];
            foreach ($rawCookies->toArray() as $cookie) {
                $cookiesArray[$cookie['Name']] = $cookie['Value'];
            }

            // Then Log in
            $loginRequest = Http::withCookies($cookiesArray, 'steadfast.com.bd')
                ->asForm()
                ->post('https://steadfast.com.bd/login', [
                    '_token' => $token,
                    'email' => $email,
                    'password' => $password
                ]);

            // Check if the login response
            if ($loginRequest->successful() || $loginRequest->redirect()) {
                // Again, convert Cookie
                $loginCookiesArray = [];
                foreach ($loginRequest->cookies()->toArray() as $cookie) {
                    $loginCookiesArray[$cookie['Name']] = $cookie['Value'];
                }

                if (count($loginCookiesArray)) {
                    Cache::put($this->cacheKey, $loginCookiesArray, now()->addMinutes($this->cacheMinutes));
                }

            } else {
                return [
                    'status' => false,
                    'message' => "Authentication failed",
                ];
            }
        }

        // Then Access protected page
        $authResponse = Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
            ->get('https://steadfast.com.bd/user/frauds/check/' . $phoneNumber);

        if ($authResponse->successful()) {
            $object = $authResponse->collect()->toArray();

            if (count($authResponse->collect()) <= 0) {
                $logoutGETRequest = Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
                    ->get('https://steadfast.com.bd/user/frauds/check');

                // Ensure the HTML is not empty
                if ($logoutGETRequest->successful()) {
                    $html = $logoutGETRequest->body();

                    // Attempt to extract CSRF token
                    if (preg_match('/<meta name="csrf-token" content="(.*?)"/', $html, $matches)) {
                        $csrfToken = $matches[1] ?? null;

                        Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
                            ->asForm()
                            ->post('https://steadfast.com.bd/logout', [
                                '_token' => $csrfToken
                            ]);
                    }
                }

                Cache::forget($this->cacheKey);
                return $this->steadfast($phoneNumber);
            }

            $data = [
                'success' => $object['total_delivered'],
                'cancel' => $object['total_cancelled'],
                'total' => $object['total_delivered'] + $object['total_cancelled'],
            ];

            return [
                'status' => true,
                'message' => "Successful.",
                'data' => $data,
            ];
        } else {
            if ($this->tryLogin < 2) {
                $this->tryLogin++;
                Cache::forget($this->cacheKey);
                return $this->steadfast($phoneNumber);
            } else {
                // Get the full response body as JSON
                $error = $authResponse->json();

                // Optionally get a specific message
                $message = $error['message'] ?? 'Unknown error occurred.';

                return [
                    'status' => false,
                    'message' => $message,
                ];
            }
        }

    }
}
