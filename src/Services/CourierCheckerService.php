<?php

namespace Programmertowheed\BdCourierFraudChecker\Services;

use Programmertowheed\BdCourierFraudChecker\Courier\Pathao;
use Programmertowheed\BdCourierFraudChecker\Courier\Redx;
use Programmertowheed\BdCourierFraudChecker\Courier\Steadfast;

class CourierCheckerService
{
    protected $steadfast;
    protected $pathao;
    protected $redx;

    public function __construct(Steadfast $steadfast, Pathao $pathao, Redx $redx)
    {
        $this->steadfast = $steadfast;
        $this->pathao = $pathao;
        $this->redx = $redx;
    }

    public function check($phone)
    {
        return [
            'steadfast' => $this->steadfast->steadfast($phone),
//            'pathao' => $this->pathao->pathao($phone),
//            'redx' => $this->redx->getCustomerDeliveryStats($phone),
        ];
    }
}
