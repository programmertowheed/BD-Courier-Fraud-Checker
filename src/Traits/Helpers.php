<?php

namespace Programmertowheed\BdCourierFraudChecker\Traits;

use Illuminate\Support\Facades\Validator;
use Programmertowheed\BdCourierFraudChecker\Exception\BdCourierFraudCheckerException;

trait Helpers
{
    /**
     * @return string|null
     */
    public function getIp()
    {
        return request()->ip();
    }

    /**
     * Check that all required environment variables are set.
     *
     * @param array $requiredKeys
     * @param string $configRoot
     * @return void
     */
    public function checkRequiredConfig(array $requiredKeys, string $configRoot = 'bdcourierfraudchecker')
    {
        foreach ($requiredKeys as $key) {
            $value = config("$configRoot.{$key}");
            if (empty($value)) {
                $message = config("$configRoot.message.{$key}") ?? $configRoot . $key;
                throw new BdCourierFraudCheckerException("The env key $message is required but not set.");
            }
        }
    }

    /**
     * Validate the phone number to ensure it is a valid Bangladeshi number.
     *
     * @param $phone
     * @return array|string|string[]|null
     * @throws BdCourierFraudCheckerException
     */
    public function validateBDPhoneNumber($phone)
    {
        // Normalize phone number
        $normalizedPhone = self::normalizeBDPhoneNumber($phone);

        // Validate the normalized number
        $validator = Validator::make(
            ['phone' => $normalizedPhone],
            [
                'phone' => [
                    'required',
                    'regex:/^01[3-9][0-9]{8}$/'
                ]
            ],
            [
                'phone.regex' => 'Invalid Bangladeshi phone number.'
            ]
        );

        if ($validator->fails()) {
            throw new BdCourierFraudCheckerException($validator->errors()->first('phone'), 422);
        }

        return $normalizedPhone;
    }

    protected function normalizeBDPhoneNumber($phone)
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D+/', '', $phone);

        // Remove country code if present
        if (str_starts_with($phone, '880')) {
            $phone = substr($phone, 3);
        }

        // If number starts with '1' and is 10 digits, add leading 0
        if (strlen($phone) === 10 && str_starts_with($phone, '1')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }


}
