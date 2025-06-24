<?php

use Programmertowheed\BdCourierFraudChecker\Facade\BdCourierFraudChecker;

it('detects fraudulent phone numbers', function () {
    $checker = new BdCourierFraudChecker();

    expect($checker->check('01810000000'))->toBeTrue();
    expect($checker->check('01711111111'))->toBeFalse();
});
