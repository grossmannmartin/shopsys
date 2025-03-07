<?php

declare(strict_types=1);

namespace App\DataFixtures\Demo;

use Doctrine\Persistence\ObjectManager;
use Shopsys\FrameworkBundle\Component\DataFixture\AbstractReferenceFixture;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyDataFactoryInterface;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade;

class CurrencyDataFixture extends AbstractReferenceFixture
{
    public const CURRENCY_CZK = 'currency_czk';
    public const CURRENCY_EUR = 'currency_eur';
    private const CZK_EXCHANGE_RATE_TO_EUR = '0.04';

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade
     */
    private $currencyFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyDataFactory
     */
    private $currencyDataFactory;

    /**
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade $currencyFacade
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyDataFactory $currencyDataFactory
     */
    public function __construct(
        CurrencyFacade $currencyFacade,
        CurrencyDataFactoryInterface $currencyDataFactory
    ) {
        $this->currencyFacade = $currencyFacade;
        $this->currencyDataFactory = $currencyDataFactory;
    }

    /**
     * @param \Doctrine\Persistence\ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        /**
         * The "CZK" and "EUR" currencies are created in database migrations.
         *
         * @see \Shopsys\FrameworkBundle\Migrations\Version20180603135342
         */
        $currencyCzk = $this->currencyFacade->getById(1);
        $currencyData = $this->currencyDataFactory->createFromCurrency($currencyCzk);
        $currencyData->minFractionDigits = Currency::DEFAULT_MIN_FRACTION_DIGITS;
        $currencyData->roundingType = Currency::ROUNDING_TYPE_INTEGER;
        $currencyData->exchangeRate = self::CZK_EXCHANGE_RATE_TO_EUR;
        $currencyCzk = $this->currencyFacade->edit($currencyCzk->getId(), $currencyData);
        $this->addReference(self::CURRENCY_CZK, $currencyCzk);

        $currencyEur = $this->currencyFacade->getById(2);
        $currencyData = $this->currencyDataFactory->createFromCurrency($currencyEur);
        $currencyData->exchangeRate = Currency::DEFAULT_EXCHANGE_RATE;
        $currencyEur = $this->currencyFacade->edit($currencyEur->getId(), $currencyData);
        $this->addReference(self::CURRENCY_EUR, $currencyEur);
    }
}
