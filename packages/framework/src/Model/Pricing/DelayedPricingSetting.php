<?php

namespace Shopsys\FrameworkBundle\Model\Pricing;

use Shopsys\FrameworkBundle\Model\Pricing\Exception\InvalidInputPriceTypeException;

class DelayedPricingSetting
{
    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\PricingSetting
     */
    protected $pricingSetting;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\InputPriceRecalculationScheduler
     */
    protected $inputPriceRecalculationScheduler;

    /**
     * @param \Shopsys\FrameworkBundle\Model\Pricing\PricingSetting $pricingSetting
     * @param \Shopsys\FrameworkBundle\Model\Pricing\InputPriceRecalculationScheduler $inputPriceRecalculationScheduler
     */
    public function __construct(
        PricingSetting $pricingSetting,
        InputPriceRecalculationScheduler $inputPriceRecalculationScheduler
    ) {
        $this->pricingSetting = $pricingSetting;
        $this->inputPriceRecalculationScheduler = $inputPriceRecalculationScheduler;
    }

    /**
     * @param int $inputPriceType
     */
    public function scheduleSetInputPriceType($inputPriceType)
    {
        if (!in_array($inputPriceType, PricingSetting::getInputPriceTypes(), true)) {
            throw new InvalidInputPriceTypeException('Unknown input price type');
        }

        $currentInputPriceType = $this->pricingSetting->getInputPriceType();

        if ($currentInputPriceType === $inputPriceType) {
            return;
        }

        switch ($inputPriceType) {
            case PricingSetting::INPUT_PRICE_TYPE_WITHOUT_VAT:
                $this->inputPriceRecalculationScheduler->scheduleSetInputPricesWithoutVat();

                break;

            case PricingSetting::INPUT_PRICE_TYPE_WITH_VAT:
                $this->inputPriceRecalculationScheduler->scheduleSetInputPricesWithVat();

                break;
        }
    }
}
