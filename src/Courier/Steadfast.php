<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;

class Steadfast
{
    use Helpers;

    protected string $cacheKey = 'steedfast_cookie';
    protected int $cacheMinutes = 50;

    public function __construct()
    {
        // Check for required environment variables
        $this->checkRequiredConfig(['steedfast_user', 'steedfast_password']);
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
                return [
                    'status' => false,
                    'message' => "CSRF Token not found",
                ];
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

        $maxRetries = 2;
        $attempt = 0;
        $authResponse = null;
        while ($attempt < $maxRetries) {
            $authResponse = $this->getOrderData($loginCookiesArray, $phoneNumber);

            if ($authResponse->successful()) {
                break;
            }
            $attempt++;
        }

        if ($authResponse && $authResponse->successful()) {
            $object = $authResponse->collect()->toArray();

            if (empty($object)) {
                $this->logoutFromSteadfast($loginCookiesArray);
                Cache::forget($this->cacheKey);

                return [
                    'status' => false,
                    'message' => "Something went wrong. Try again",
                ];
            }

            // Safe extraction of data with fallback to 0
            $success = isset($object['total_delivered']) ? (int)$object['total_delivered'] : 0;
            $cancel = isset($object['total_cancelled']) ? (int)$object['total_cancelled'] : 0;
            $total = $success + $cancel;

            // Calculate percentages
            $deliveredPercentage = $total > 0 ? round(($success / $total) * 100, 2) : 0;
            $returnPercentage = $total > 0 ? round(($cancel / $total) * 100, 2) : 0;

            $data = [
                'success' => $success,
                'cancel' => $cancel,
                'total' => $total,
                'deliveredPercentage' => $deliveredPercentage,
                'returnPercentage' => $returnPercentage,
            ];

            return [
                'status' => true,
                'message' => "Successful.",
                'data' => $data,
            ];
        }

        Cache::forget($this->cacheKey);

        // Get the full response body as JSON
        $error = ($authResponse) ? $authResponse->json() : [];

        // Optionally get a specific message
        $message = $error['message'] ?? 'Unknown error occurred.';

        return [
            'status' => false,
            'message' => $message,
        ];
    }


    private function getOrderData($loginCookiesArray, $phoneNumber)
    {
        // Then Access protected page
        return Http::withCookies($loginCookiesArray, 'steadfast.com.bd')
            ->get('https://steadfast.com.bd/user/frauds/check/' . $phoneNumber);
    }


    private function logoutFromSteadfast(array $cookies): void
    {
        $logoutGETRequest = Http::withCookies($cookies, 'steadfast.com.bd')
            ->get('https://steadfast.com.bd/user/frauds/check');

        // Ensure the response is OK
        if ($logoutGETRequest->successful()) {
            $html = $logoutGETRequest->body();

            // Extract CSRF token
            if (preg_match('/<meta name="csrf-token" content="(.*?)"/', $html, $matches)) {
                $csrfToken = $matches[1] ?? null;

                Http::withCookies($cookies, 'steadfast.com.bd')
                    ->asForm()
                    ->post('https://steadfast.com.bd/logout', [
                        '_token' => $csrfToken
                    ]);
            }
        }

    }


}
