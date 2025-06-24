<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;
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
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => 'https://redx.com.bd',
            'referer' => 'https://redx.com.bd/',
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

    public function redx(string $queryPhone)
    {
        $queryPhone = $this->validateBDPhoneNumber($queryPhone);

        $maxRetries = 2;
        $attempt = 0;
        $accessToken = null;
        while ($attempt < $maxRetries) {
            $accessToken = $this->getAccessToken();

            if ($accessToken) {
                break;
            }
            $attempt++;
        }

        if (!$accessToken) {
            return [
                'status' => false,
                'message' => "Login failed or unable to get access token",
            ];
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'Origin' => 'https://redx.com.bd',
            'referer' => 'https://redx.com.bd/',
        ])->get("https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88{$queryPhone}");

        if ($response->successful()) {
            $json = $response->json();

            $data = isset($json['data']) ? $json['data'] : [];

            $delivered = isset($data['deliveredParcels']) ? (int)$data['deliveredParcels'] : 0;
            $total = isset($data['totalParcels']) ? (int)$data['totalParcels'] : 0;
            $cancel = max($total - $delivered, 0);

            // $returnPercentage = (int)($data['returnedPercentage'] ?? 0);
            // $customerSegment = (string)($data['customerSegment'] ?? "");

            $deliveredPercentage = $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
            $returnPercentage = $total > 0 ? round(($cancel / $total) * 100, 2) : 0;

            $result = [
                'success' => $delivered,
                'cancel' => $cancel,
                'total' => $total,
                'deliveredPercentage' => $deliveredPercentage,
                'returnPercentage' => $returnPercentage,
            ];

            return [
                'status' => true,
                'message' => "Successful.",
                'data' => $result,
            ];
        } elseif ($response->status() === 401) {
            // Token expired or invalid, clear cache and suggest retry
            Cache::forget($this->cacheKey);

            return [
                'status' => false,
                'message' => "Access token expired or invalid. Please retry.",
            ];
        }

        // Get the full response body as JSON
        $error = ($response) ? $response->json() : [];

        // Optionally get a specific message
        $message = $error['message'] ?? 'Unknown error occurred.';
        return [
            'status' => false,
            'message' => $message,
        ];

    }


}
