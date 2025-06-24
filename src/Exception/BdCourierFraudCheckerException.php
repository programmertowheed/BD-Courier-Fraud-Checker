<?php

namespace Programmertowheed\BdCourierFraudChecker\Exception;

use Exception;

class BdCourierFraudCheckerException extends Exception
{
    protected $status;

    public function __construct(string $message = "Something went wrong", int $status = 500)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

}
