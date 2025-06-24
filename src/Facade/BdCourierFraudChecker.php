<?php

namespace Programmertowheed\BdCourierFraudChecker\Facade;

use Illuminate\Support\Facades\Facade;
use Programmertowheed\BdCourierFraudChecker\Services\CourierCheckerService;

/**
 * @method static array check($phoneNumber)
 */
class BdCourierFraudChecker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return CourierCheckerService::class;
    }

}
