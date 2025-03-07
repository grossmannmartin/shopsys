<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Model\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\Image\ImageFacade;
use Shopsys\FrameworkBundle\Model\Payment\PaymentRepository;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade;
use Shopsys\FrameworkBundle\Model\Pricing\Price;

class TransportFacade
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Payment\PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Transport\TransportRepository
     */
    protected $transportRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Transport\TransportVisibilityCalculation
     */
    protected $transportVisibilityCalculation;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Image\ImageFacade
     */
    protected $imageFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade
     */
    protected $currencyFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Transport\TransportPriceCalculation
     */
    protected $transportPriceCalculation;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Transport\TransportFactoryInterface
     */
    protected $transportFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Transport\TransportPriceFactoryInterface
     */
    protected $transportPriceFactory;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportRepository $transportRepository
     * @param \Shopsys\FrameworkBundle\Model\Payment\PaymentRepository $paymentRepository
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportVisibilityCalculation $transportVisibilityCalculation
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     * @param \Shopsys\FrameworkBundle\Component\Image\ImageFacade $imageFacade
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade $currencyFacade
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportPriceCalculation $transportPriceCalculation
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportFactoryInterface $transportFactory
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportPriceFactoryInterface $transportPriceFactory
     */
    public function __construct(
        EntityManagerInterface $em,
        TransportRepository $transportRepository,
        PaymentRepository $paymentRepository,
        TransportVisibilityCalculation $transportVisibilityCalculation,
        Domain $domain,
        ImageFacade $imageFacade,
        CurrencyFacade $currencyFacade,
        TransportPriceCalculation $transportPriceCalculation,
        TransportFactoryInterface $transportFactory,
        TransportPriceFactoryInterface $transportPriceFactory
    ) {
        $this->em = $em;
        $this->transportRepository = $transportRepository;
        $this->paymentRepository = $paymentRepository;
        $this->transportVisibilityCalculation = $transportVisibilityCalculation;
        $this->domain = $domain;
        $this->imageFacade = $imageFacade;
        $this->currencyFacade = $currencyFacade;
        $this->transportPriceCalculation = $transportPriceCalculation;
        $this->transportFactory = $transportFactory;
        $this->transportPriceFactory = $transportPriceFactory;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportData $transportData
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport
     */
    public function create(TransportData $transportData)
    {
        $transport = $this->transportFactory->create($transportData);
        $this->em->persist($transport);
        $this->em->flush();
        $this->updateTransportPrices($transport, $transportData->pricesIndexedByDomainId);
        $this->imageFacade->manageImages($transport, $transportData->image);
        $transport->setPayments($transportData->payments);
        $this->em->flush();

        return $transport;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Transport\Transport $transport
     * @param \Shopsys\FrameworkBundle\Model\Transport\TransportData $transportData
     */
    public function edit(Transport $transport, TransportData $transportData)
    {
        $transport->edit($transportData);
        $this->updateTransportPrices($transport, $transportData->pricesIndexedByDomainId);
        $this->imageFacade->manageImages($transport, $transportData->image);
        $transport->setPayments($transportData->payments);
        $this->em->flush();
    }

    /**
     * @param int $id
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport
     */
    public function getById($id)
    {
        return $this->transportRepository->getById($id);
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        $transport = $this->getById($id);
        $transport->markAsDeleted();
        $paymentsByTransport = $this->paymentRepository->getAllByTransport($transport);

        foreach ($paymentsByTransport as $payment) {
            $payment->removeTransport($transport);
        }
        $this->em->flush();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Payment\Payment[] $visiblePayments
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport[]
     */
    public function getVisibleOnCurrentDomain(array $visiblePayments)
    {
        return $this->getVisibleByDomainId($this->domain->getId(), $visiblePayments);
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Payment\Payment[] $visiblePaymentsOnDomain
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport[]
     */
    public function getVisibleByDomainId($domainId, $visiblePaymentsOnDomain)
    {
        $transports = $this->transportRepository->getAllByDomainId($domainId);

        return $this->transportVisibilityCalculation->filterVisible($transports, $visiblePaymentsOnDomain, $domainId);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Transport\Transport $transport
     * @param \Shopsys\FrameworkBundle\Component\Money\Money[] $pricesIndexedByDomainId
     */
    protected function updateTransportPrices(Transport $transport, array $pricesIndexedByDomainId): void
    {
        foreach ($this->domain->getAllIds() as $domainId) {
            $existPriceForDomain = $transport->hasPriceForDomain($domainId);
            $transport->setPrice($pricesIndexedByDomainId[$domainId], $domainId);

            if ($existPriceForDomain !== false) {
                continue;
            }

            $transport->addPrice(
                $this->transportPriceFactory->create($transport, $pricesIndexedByDomainId[$domainId], $domainId)
            );
        }
    }

    public function getAll()
    {
        return $this->transportRepository->getAll();
    }

    /**
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport[]
     */
    public function getAllIncludingDeleted()
    {
        return $this->transportRepository->getAllIncludingDeleted();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency $currency
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Component\Money\Money[]
     */
    public function getTransportPricesWithVatByCurrencyAndDomainIdIndexedByTransportId(Currency $currency, int $domainId): array
    {
        $transportPricesWithVatByTransportId = [];
        $transports = $this->getAllIncludingDeleted();

        foreach ($transports as $transport) {
            $transportPrice = $this->transportPriceCalculation->calculateIndependentPrice(
                $transport,
                $currency,
                $domainId
            );
            $transportPricesWithVatByTransportId[$transport->getId()] = $transportPrice->getPriceWithVat();
        }

        return $transportPricesWithVatByTransportId;
    }

    /**
     * @param int $domainId
     * @return string[]
     */
    public function getTransportVatPercentsByDomainIdIndexedByTransportId(int $domainId): array
    {
        $transportVatPercentsByTransportId = [];
        $transports = $this->getAllIncludingDeleted();

        foreach ($transports as $transport) {
            $transportVatPercentsByTransportId[$transport->getId()] = $transport->getTransportDomain(
                $domainId
            )->getVat()->getPercent();
        }

        return $transportVatPercentsByTransportId;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Transport\Transport $transport
     * @return \Shopsys\FrameworkBundle\Model\Pricing\Price[]
     */
    public function getIndependentBasePricesIndexedByDomainId(Transport $transport): array
    {
        $prices = [];

        foreach ($transport->getPrices() as $transportInputPrice) {
            $domainId = $transportInputPrice->getDomainId();
            $currency = $this->currencyFacade->getDomainDefaultCurrencyByDomainId($domainId);
            $prices[$domainId] = $this->transportPriceCalculation->calculateIndependentPrice(
                $transport,
                $currency,
                $domainId
            );
        }

        return $prices;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Transport\Transport|null $transport
     * @return \Shopsys\FrameworkBundle\Model\Pricing\Price[]
     */
    public function getPricesIndexedByDomainId(?Transport $transport): array
    {
        $prices = [];

        foreach ($this->domain->getAllIds() as $domainId) {
            $currency = $this->currencyFacade->getDomainDefaultCurrencyByDomainId($domainId);

            if ($transport === null) {
                $prices[$domainId] = Price::zero();

                continue;
            }

            $prices[$domainId] = $this->transportPriceCalculation->calculateIndependentPrice(
                $transport,
                $currency,
                $domainId
            );
        }

        return $prices;
    }

    /**
     * @param string $uuid
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport
     */
    public function getByUuid(string $uuid): Transport
    {
        return $this->transportRepository->getOneByUuid($uuid);
    }

    /**
     * @param string $uuid
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Transport\Transport
     */
    public function getEnabledOnDomainByUuid(string $uuid, int $domainId): Transport
    {
        return $this->transportRepository->getEnabledOnDomainByUuid($uuid, $domainId);
    }
}
