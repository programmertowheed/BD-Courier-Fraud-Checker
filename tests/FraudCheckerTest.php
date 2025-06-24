<?php

use Programmertowheed\BdCourierFraudChecker\Facade\BdCourierFraudChecker;

it('detects fraudulent phone numbers', function () {
    expect(BdCourierFraudChecker::check('01810000000'))->toBeTrue();
    expect(BdCourierFraudChecker::check('01711111111'))->toBeFalse();
});

