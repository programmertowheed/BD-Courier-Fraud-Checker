<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;
use ShahariarAhmad\CourierFraudCheckerBd\Helpers\CourierFraudCheckerHelper;

class Pathao
{
    use Helpers;

    protected string $cacheKey = 'pathao_access_token';
    protected int $cacheMinutes = 50;

    public function __construct()
    {
        //Check required environment variables
        $this->checkRequiredConfig(['pathao_user', 'pathao_password']);
    }

    protected function getAccessToken()
    {
        // Try cached token first
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        // No cached token, login and get new one
        $response = Http::post('https://merchant.pathao.com/api/v1/login', [
            'username' => config("bdcourierfraudchecker.pathao_user"),
            'password' => config("bdcourierfraudchecker.pathao_password"),
        ]);

        // Check if the response is not success
        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $token = trim($data['access_token']);
        if ($token) {
            Cache::put($this->cacheKey, $token, now()->addMinutes($this->cacheMinutes));
        }

        return $token;
    }

    public function pathao($phone)
    {
        $phone = $this->validateBDPhoneNumber($phone);
        $accessToken = $this->getAccessToken();

        if ($accessToken) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->post('https://merchant.pathao.com/api/v1/user/success', [
                'phone' => $phone,
            ]);

            if ($response->successful()) {
                $object = $response->json();

                $data = [
                    'success' => $object['data']['customer']['successful_delivery'] ?? 0,
                    'cancel' => ($object['data']['customer']['total_delivery'] ?? 0) - ($object['data']['customer']['successful_delivery'] ?? 0),
                    'total' => $object['data']['customer']['total_delivery'] ?? 0,
                ];

                return [
                    'status' => true,
                    'message' => "Successful.",
                    'data' => $data,
                ];
            } else {
                // Get the full response body as JSON
                $error = $response->json();

                // Optionally get a specific message
                $message = $error['message'] ?? 'Unknown error occurred.';

                return [
                    'status' => false,
                    'message' => $message,
                ];
            }

        }

        return [
            'status' => false,
            'message' => "Authentication failed",
        ];
    }
}
