<?php

namespace Shopsys\FrameworkBundle\Model\Product\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender;
use Shopsys\FrameworkBundle\Component\Money\Money;
use Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup;
use Shopsys\FrameworkBundle\Model\Product\Availability\Availability;
use Shopsys\FrameworkBundle\Model\Product\Pricing\ProductCalculatedPrice;
use Shopsys\FrameworkBundle\Model\Product\Product;

class ProductFilterRepository
{
    protected const DAYS_FOR_STOCK_FILTER = 0;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender
     */
    protected $queryBuilderExtender;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Filter\ParameterFilterRepository
     */
    protected $parameterFilterRepository;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender $queryBuilderExtender
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ParameterFilterRepository $parameterFilterRepository
     */
    public function __construct(
        QueryBuilderExtender $queryBuilderExtender,
        ParameterFilterRepository $parameterFilterRepository
    ) {
        $this->queryBuilderExtender = $queryBuilderExtender;
        $this->parameterFilterRepository = $parameterFilterRepository;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $productsQueryBuilder
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData $productFilterData
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     */
    public function applyFiltering(
        QueryBuilder $productsQueryBuilder,
        ProductFilterData $productFilterData,
        PricingGroup $pricingGroup
    ) {
        $this->filterByPrice(
            $productsQueryBuilder,
            $productFilterData->minimalPrice,
            $productFilterData->maximalPrice,
            $pricingGroup
        );
        $this->filterByStock(
            $productsQueryBuilder,
            $productFilterData->inStock
        );
        $this->filterByFlags(
            $productsQueryBuilder,
            $productFilterData->flags
        );
        $this->filterByBrands(
            $productsQueryBuilder,
            $productFilterData->brands
        );
        $this->parameterFilterRepository->filterByParameters(
            $productsQueryBuilder,
            $productFilterData->parameters
        );
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $productsQueryBuilder
     * @param \Shopsys\FrameworkBundle\Component\Money\Money|null $minimalPrice
     * @param \Shopsys\FrameworkBundle\Component\Money\Money|null $maximalPrice
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     */
    protected function filterByPrice(
        QueryBuilder $productsQueryBuilder,
        ?Money $minimalPrice,
        ?Money $maximalPrice,
        PricingGroup $pricingGroup
    ) {
        if ($maximalPrice !== null || $minimalPrice !== null) {
            $priceLimits = 'pcp.product = p AND pcp.pricingGroup = :pricingGroup';

            if ($minimalPrice !== null) {
                $priceLimits .= ' AND pcp.priceWithVat >= :minimalPrice';
                $productsQueryBuilder->setParameter('minimalPrice', $minimalPrice->getAmount());
            }

            if ($maximalPrice !== null) {
                $priceLimits .= ' AND pcp.priceWithVat <= :maximalPrice';
                $productsQueryBuilder->setParameter('maximalPrice', $maximalPrice->getAmount());
            }
            $this->queryBuilderExtender->addOrExtendJoin(
                $productsQueryBuilder,
                ProductCalculatedPrice::class,
                'pcp',
                $priceLimits
            );
            $productsQueryBuilder->setParameter('pricingGroup', $pricingGroup);
        }
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $productsQueryBuilder
     * @param bool $filterByStock
     */
    public function filterByStock(QueryBuilder $productsQueryBuilder, $filterByStock)
    {
        if ($filterByStock) {
            $this->queryBuilderExtender->addOrExtendJoin(
                $productsQueryBuilder,
                Availability::class,
                'a',
                'p.calculatedAvailability = a'
            );
            $productsQueryBuilder->andWhere('a.dispatchTime = :dispatchTime');
            $productsQueryBuilder->setParameter('dispatchTime', static::DAYS_FOR_STOCK_FILTER);
        }
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $productsQueryBuilder
     * @param \Shopsys\FrameworkBundle\Model\Product\Flag\Flag[] $flags
     */
    protected function filterByFlags(QueryBuilder $productsQueryBuilder, array $flags)
    {
        $flagsCount = count($flags);

        if ($flagsCount === 0) {
            return;
        }

        $flagsQueryBuilder = $this->getFlagsQueryBuilder($flags, $productsQueryBuilder->getEntityManager());

        $productsQueryBuilder->andWhere($productsQueryBuilder->expr()->exists($flagsQueryBuilder));

        foreach ($flagsQueryBuilder->getParameters() as $parameter) {
            $productsQueryBuilder->setParameter($parameter->getName(), $parameter->getValue());
        }
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $productsQueryBuilder
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand[] $brands
     */
    protected function filterByBrands(QueryBuilder $productsQueryBuilder, array $brands)
    {
        $brandsCount = count($brands);

        if ($brandsCount === 0) {
            return;
        }

        $brandsQueryBuilder = $this->getBrandsQueryBuilder($brands, $productsQueryBuilder->getEntityManager());

        $productsQueryBuilder->andWhere($productsQueryBuilder->expr()->exists($brandsQueryBuilder));

        foreach ($brandsQueryBuilder->getParameters() as $parameter) {
            $productsQueryBuilder->setParameter($parameter->getName(), $parameter->getValue());
        }
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Flag\Flag[] $flags
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getFlagsQueryBuilder(array $flags, EntityManagerInterface $em)
    {
        $flagsQueryBuilder = $em->createQueryBuilder();

        $flagsQueryBuilder
            ->select('1')
            ->from(Product::class, 'pf')
            ->join('pf.flags', 'f')
            ->where('pf = p')
            ->andWhere('f IN (:flags)')->setParameter('flags', $flags);

        return $flagsQueryBuilder;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand[] $brands
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getBrandsQueryBuilder(array $brands, EntityManagerInterface $em)
    {
        $brandsQueryBuilder = $em->createQueryBuilder();

        $brandsQueryBuilder
            ->select('1')
            ->from(Product::class, 'pb')
            ->join('pb.brand', 'b')
            ->where('pb = p')
            ->andWhere('b IN (:brands)')->setParameter('brands', $brands);

        return $brandsQueryBuilder;
    }
}
