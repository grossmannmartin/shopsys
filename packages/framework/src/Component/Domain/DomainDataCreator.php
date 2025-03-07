<?php

namespace Shopsys\FrameworkBundle\Component\Domain;

use Shopsys\FrameworkBundle\Component\Domain\Multidomain\MultidomainEntityDataCreator;
use Shopsys\FrameworkBundle\Component\Setting\Exception\SettingValueNotFoundException;
use Shopsys\FrameworkBundle\Component\Setting\Setting;
use Shopsys\FrameworkBundle\Component\Setting\SettingValueRepository;
use Shopsys\FrameworkBundle\Component\Translation\TranslatableEntityDataCreator;
use Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupDataFactory;
use Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupFacade;
use Shopsys\FrameworkBundle\Model\Pricing\Vat\Vat;
use Shopsys\FrameworkBundle\Model\Pricing\Vat\VatDataFactory;
use Shopsys\FrameworkBundle\Model\Pricing\Vat\VatFacade;

class DomainDataCreator
{
    public const TEMPLATE_DOMAIN_ID = 1;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Setting\Setting
     */
    protected $setting;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Setting\SettingValueRepository
     */
    protected $settingValueRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Multidomain\MultidomainEntityDataCreator
     */
    protected $multidomainEntityDataCreator;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Translation\TranslatableEntityDataCreator
     */
    protected $translatableEntityDataCreator;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupDataFactory
     */
    protected $pricingGroupDataFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupFacade
     */
    protected $pricingGroupFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Vat\VatDataFactory
     */
    protected $vatDataFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Vat\VatFacade
     */
    protected $vatFacade;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     * @param \Shopsys\FrameworkBundle\Component\Setting\Setting $setting
     * @param \Shopsys\FrameworkBundle\Component\Setting\SettingValueRepository $settingValueRepository
     * @param \Shopsys\FrameworkBundle\Component\Domain\Multidomain\MultidomainEntityDataCreator $multidomainEntityDataCreator
     * @param \Shopsys\FrameworkBundle\Component\Translation\TranslatableEntityDataCreator $translatableEntityDataCreator
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupDataFactory $pricingGroupDataFactory
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroupFacade $pricingGroupFacade
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Vat\VatDataFactory $vatDataFactory
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Vat\VatFacade $vatFacade
     */
    public function __construct(
        Domain $domain,
        Setting $setting,
        SettingValueRepository $settingValueRepository,
        MultidomainEntityDataCreator $multidomainEntityDataCreator,
        TranslatableEntityDataCreator $translatableEntityDataCreator,
        PricingGroupDataFactory $pricingGroupDataFactory,
        PricingGroupFacade $pricingGroupFacade,
        VatDataFactory $vatDataFactory,
        VatFacade $vatFacade
    ) {
        $this->domain = $domain;
        $this->setting = $setting;
        $this->settingValueRepository = $settingValueRepository;
        $this->multidomainEntityDataCreator = $multidomainEntityDataCreator;
        $this->translatableEntityDataCreator = $translatableEntityDataCreator;
        $this->pricingGroupDataFactory = $pricingGroupDataFactory;
        $this->pricingGroupFacade = $pricingGroupFacade;
        $this->vatDataFactory = $vatDataFactory;
        $this->vatFacade = $vatFacade;
    }

    /**
     * @return int
     */
    public function createNewDomainsData()
    {
        $newDomainsCount = 0;

        foreach ($this->domain->getAllIncludingDomainConfigsWithoutDataCreated() as $domainConfig) {
            $domainId = $domainConfig->getId();

            try {
                $this->setting->getForDomain(Setting::DOMAIN_DATA_CREATED, $domainId);
            } catch (SettingValueNotFoundException $ex) {
                $locale = $domainConfig->getLocale();
                $isNewLocale = $this->isNewLocale($locale);
                $this->settingValueRepository->copyAllMultidomainSettings(self::TEMPLATE_DOMAIN_ID, $domainId);
                $this->setting->clearCache();
                $this->setting->setForDomain(Setting::BASE_URL, $domainConfig->getUrl(), $domainId);

                $this->processDefaultPricingGroupForNewDomain($domainId);
                $this->processDefaultVatForNewDomain($domainId);

                $this->multidomainEntityDataCreator->copyAllMultidomainDataForNewDomain(
                    self::TEMPLATE_DOMAIN_ID,
                    $domainId
                );

                if ($isNewLocale) {
                    $this->translatableEntityDataCreator->copyAllTranslatableDataForNewLocale(
                        $this->getTemplateLocale(),
                        $locale
                    );
                }
                $newDomainsCount++;
            }
        }

        return $newDomainsCount;
    }

    /**
     * @param string $locale
     * @return bool
     */
    protected function isNewLocale($locale)
    {
        foreach ($this->domain->getAll() as $domainConfig) {
            if ($domainConfig->getLocale() === $locale) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     */
    protected function getTemplateLocale()
    {
        return $this->domain->getDomainConfigById(self::TEMPLATE_DOMAIN_ID)->getLocale();
    }

    /**
     * @param int $domainId
     */
    protected function processDefaultPricingGroupForNewDomain(int $domainId)
    {
        $pricingGroup = $this->createDefaultPricingGroupForNewDomain($domainId);
        $this->setting->setForDomain(Setting::DEFAULT_PRICING_GROUP, $pricingGroup->getId(), $domainId);
    }

    /**
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup
     */
    protected function createDefaultPricingGroupForNewDomain(int $domainId)
    {
        $pricingGroupData = $this->pricingGroupDataFactory->create();
        $pricingGroupData->name = 'Default';

        return $this->pricingGroupFacade->create($pricingGroupData, $domainId);
    }

    /**
     * @param int $domainId
     */
    protected function processDefaultVatForNewDomain(int $domainId): void
    {
        $vat = $this->createDefaultVatForNewDomain($domainId);
        $this->setting->setForDomain(Vat::SETTING_DEFAULT_VAT, $vat->getId(), $domainId);
    }

    /**
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Pricing\Vat\Vat
     */
    protected function createDefaultVatForNewDomain(int $domainId): Vat
    {
        $vatData = $this->vatDataFactory->create();
        $vatData->name = 'Default';
        $vatData->percent = '0';

        return $this->vatFacade->create($vatData, $domainId);
    }
}
