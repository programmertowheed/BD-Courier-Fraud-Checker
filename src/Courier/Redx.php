<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;
use ShahariarAhmad\CourierFraudCheckerBd\Helpers\CourierFraudCheckerHelper;
use Illuminate\Support\Facades\Cache;

class Redx
{
    use Helpers;

    protected string $cacheKey = 'redx_access_token';
    protected int $cacheMinutes = 50;

    public function __construct()
    {
        // Check for required environment variables
        $this->checkRequiredConfig(['redx_phone', 'redx_password']);
        $this->validateBDPhoneNumber(config("bdcourierfraudchecker.redx_phone"));
    }

    protected function getAccessToken()
    {
        // Try cached token first
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        // No cached token, login and get new one
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
        ])->post('https://api.redx.com.bd/v4/auth/login', [
            'phone' => '88' . $this->validateBDPhoneNumber(config("bdcourierfraudchecker.redx_phone")),
            'password' => config("bdcourierfraudchecker.redx_password"),
        ]);

        if (!$response->successful()) {
            return null;
        }

        $token = $response->json('data.accessToken');
        if ($token) {
            Cache::put($this->cacheKey, $token, now()->addMinutes($this->cacheMinutes));
        }

        return $token;
    }

    public function getCustomerDeliveryStats(string $queryPhone)
    {

        $queryPhone = $this->validateBDPhoneNumber($queryPhone);

        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return ['error' => 'Login failed or unable to get access token'];
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get("https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88{$queryPhone}");

        if ($response->successful()) {
            $object = $response->json();

            return [
                'success' => isset($object['data']['deliveredParcels']) ? (int)$object['data']['deliveredParcels'] : 0,
                'cancel' => isset($object['data']['totalParcels'], $object['data']['deliveredParcels'])
                    ? ((int)$object['data']['totalParcels'] - (int)$object['data']['deliveredParcels'])
                    : 0,
                'total' => isset($object['data']['totalParcels']) ? (int)$object['data']['totalParcels'] : 0,
                // 'returnPercentage' => isset($object['data']['returnPercentage']) ? (int)$object['data']['returnPercentage'] : 0,
                // 'customerSegment' => $object['data']['customerSegment'] ?? 'Unknown',
            ];
        } elseif ($response->status() === 401) {
            // Token expired or invalid, clear cache and suggest retry
            Cache::forget($this->cacheKey);
            return ['error' => 'Access token expired or invalid. Please retry.', 'status' => 401];
        }
        return [
            'success' => 'API Error, try sometime later',
            'cancel' => 'API Error, try sometime later',
            'total' => 'API Error, try sometime later',
        ];

    }
}
