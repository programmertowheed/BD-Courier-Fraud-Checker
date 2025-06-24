<?php

namespace Programmertowheed\BdCourierFraudChecker\Courier;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Programmertowheed\BdCourierFraudChecker\Traits\Helpers;

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

        $maxRetries = 2;
        $attempt = 0;
        $accessToken = null;
        while ($attempt < $maxRetries) {
            $accessToken = $this->getAccessToken();

            if ($accessToken) {
                break;
            }
            Cache::forget($this->cacheKey);
            $attempt++;
        }

        if ($accessToken) {

            $maxRetries = 2;
            $attempt = 0;
            $response = null;
            while ($attempt < $maxRetries) {
                $response = $this->getOrderData($accessToken, $phone);

                if ($response->successful()) {
                    break;
                }
                $attempt++;
            }

            if ($response && $response->successful()) {
                $object = $response->json();
                $customer = isset($object['data']['customer']) ? $object['data']['customer'] : [];

                $success = isset($customer['successful_delivery']) ? (int)$customer['successful_delivery'] : 0;
                $total = isset($customer['total_delivery']) ? (int)$customer['total_delivery'] : 0;
                $cancel = max($total - $success, 0);

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
                    'message' => 'Successful.',
                    'data' => $data,
                ];
            } else {
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

        return [
            'status' => false,
            'message' => "Authentication failed",
        ];
    }


    private function getOrderData($accessToken, $phone)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post('https://merchant.pathao.com/api/v1/user/success', [
            'phone' => $phone,
        ]);
    }


}
