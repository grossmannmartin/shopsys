<?php

declare(strict_types=1);

namespace Shopsys\FrameworkBundle\Model\Product;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender;
use Shopsys\FrameworkBundle\Component\Paginator\PaginationResult;
use Shopsys\FrameworkBundle\Component\Paginator\QueryPaginator;
use Shopsys\FrameworkBundle\Model\Category\Category;
use Shopsys\FrameworkBundle\Model\Localization\Localization;
use Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup;
use Shopsys\FrameworkBundle\Model\Product\Availability\Availability;
use Shopsys\FrameworkBundle\Model\Product\Brand\Brand;
use Shopsys\FrameworkBundle\Model\Product\Exception\InvalidOrderingModeException;
use Shopsys\FrameworkBundle\Model\Product\Exception\ProductNotFoundException;
use Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData;
use Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterRepository;
use Shopsys\FrameworkBundle\Model\Product\Flag\Flag;
use Shopsys\FrameworkBundle\Model\Product\Listing\ProductListOrderingConfig;
use Shopsys\FrameworkBundle\Model\Product\Parameter\Parameter;
use Shopsys\FrameworkBundle\Model\Product\Parameter\ProductParameterValue;
use Shopsys\FrameworkBundle\Model\Product\Pricing\ProductCalculatedPrice;
use Shopsys\FrameworkBundle\Model\Product\Search\ProductElasticsearchRepository;
use Shopsys\FrameworkBundle\Model\Product\Unit\Unit;

class ProductRepository
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterRepository
     */
    protected $productFilterRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender
     */
    protected $queryBuilderExtender;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Localization\Localization
     */
    protected $localization;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Search\ProductElasticsearchRepository
     */
    protected $productElasticsearchRepository;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterRepository $productFilterRepository
     * @param \Shopsys\FrameworkBundle\Component\Doctrine\QueryBuilderExtender $queryBuilderExtender
     * @param \Shopsys\FrameworkBundle\Model\Localization\Localization $localization
     * @param \Shopsys\FrameworkBundle\Model\Product\Search\ProductElasticsearchRepository $productElasticsearchRepository
     */
    public function __construct(
        EntityManagerInterface $em,
        ProductFilterRepository $productFilterRepository,
        QueryBuilderExtender $queryBuilderExtender,
        Localization $localization,
        ProductElasticsearchRepository $productElasticsearchRepository
    ) {
        $this->em = $em;
        $this->productFilterRepository = $productFilterRepository;
        $this->queryBuilderExtender = $queryBuilderExtender;
        $this->localization = $localization;
        $this->productElasticsearchRepository = $productElasticsearchRepository;
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getProductRepository()
    {
        return $this->em->getRepository(Product::class);
    }

    /**
     * @param int $id
     * @return \Shopsys\FrameworkBundle\Model\Product\Product|null
     */
    public function findById($id)
    {
        return $this->getProductRepository()->find($id);
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllListableQueryBuilder($domainId, PricingGroup $pricingGroup)
    {
        $queryBuilder = $this->getAllOfferedQueryBuilder($domainId, $pricingGroup);
        $queryBuilder->andWhere('p.variantType != :variantTypeVariant')
            ->setParameter('variantTypeVariant', Product::VARIANT_TYPE_VARIANT);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllSellableQueryBuilder($domainId, PricingGroup $pricingGroup)
    {
        $queryBuilder = $this->getAllOfferedQueryBuilder($domainId, $pricingGroup);
        $queryBuilder->andWhere('p.variantType != :variantTypeMain')
            ->setParameter('variantTypeMain', Product::VARIANT_TYPE_MAIN);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllOfferedQueryBuilder($domainId, PricingGroup $pricingGroup)
    {
        $queryBuilder = $this->getAllVisibleQueryBuilder($domainId, $pricingGroup);
        $queryBuilder->andWhere('p.calculatedSellingDenied = FALSE');

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllVisibleQueryBuilder($domainId, PricingGroup $pricingGroup)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->join(ProductVisibility::class, 'prv', Join::WITH, 'prv.product = p.id')
            ->where('prv.domainId = :domainId')
                ->andWhere('prv.pricingGroup = :pricingGroup')
                ->andWhere('prv.visible = TRUE')
            ->orderBy('p.id');

        $queryBuilder->setParameter('domainId', $domainId);
        $queryBuilder->setParameter('pricingGroup', $pricingGroup);

        return $queryBuilder;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param string $locale
     */
    public function addTranslation(QueryBuilder $queryBuilder, $locale)
    {
        $queryBuilder->addSelect('pt')
            ->join('p.translations', 'pt', Join::WITH, 'pt.locale = :locale');

        $queryBuilder->setParameter('locale', $locale);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param int $domainId
     */
    public function addDomain(QueryBuilder $queryBuilder, $domainId)
    {
        $queryBuilder->addSelect('pd')
            ->join('p.domains', 'pd', Join::WITH, 'pd.domainId = :domainId');

        $queryBuilder->setParameter('domainId', $domainId);
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getListableInCategoryQueryBuilder(
        $domainId,
        PricingGroup $pricingGroup,
        Category $category
    ) {
        $queryBuilder = $this->getAllListableQueryBuilder($domainId, $pricingGroup);
        $this->filterByCategory($queryBuilder, $category, $domainId);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand $brand
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getListableForBrandQueryBuilder(
        $domainId,
        PricingGroup $pricingGroup,
        Brand $brand
    ) {
        $queryBuilder = $this->getAllListableQueryBuilder($domainId, $pricingGroup);
        $this->filterByBrand($queryBuilder, $brand);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getSellableInCategoryQueryBuilder(
        $domainId,
        PricingGroup $pricingGroup,
        Category $category
    ) {
        $queryBuilder = $this->getAllSellableQueryBuilder($domainId, $pricingGroup);
        $this->filterByCategory($queryBuilder, $category, $domainId);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getOfferedInCategoryQueryBuilder(
        $domainId,
        PricingGroup $pricingGroup,
        Category $category
    ) {
        $queryBuilder = $this->getAllOfferedQueryBuilder($domainId, $pricingGroup);
        $this->filterByCategory($queryBuilder, $category, $domainId);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param string $locale
     * @param string|null $searchText
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getListableBySearchTextQueryBuilder(
        $domainId,
        PricingGroup $pricingGroup,
        $locale,
        $searchText
    ) {
        $queryBuilder = $this->getAllListableQueryBuilder($domainId, $pricingGroup);

        $this->addTranslation($queryBuilder, $locale);
        $this->addDomain($queryBuilder, $domainId);

        $this->productElasticsearchRepository->filterBySearchText($queryBuilder, $searchText);

        return $queryBuilder;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @param int $domainId
     */
    protected function filterByCategory(QueryBuilder $queryBuilder, Category $category, $domainId)
    {
        $queryBuilder->join(
            'p.productCategoryDomains',
            'pcd',
            Join::WITH,
            'pcd.category = :category AND pcd.domainId = :domainId'
        );
        $queryBuilder->setParameter('category', $category);
        $queryBuilder->setParameter('domainId', $domainId);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand $brand
     */
    protected function filterByBrand(QueryBuilder $queryBuilder, Brand $brand)
    {
        $queryBuilder->andWhere('p.brand = :brand');
        $queryBuilder->setParameter('brand', $brand);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @param int $domainId
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData $productFilterData
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param int $page
     * @param int $limit
     * @return \Shopsys\FrameworkBundle\Component\Paginator\PaginationResult
     */
    public function getPaginationResultForListableInCategory(
        Category $category,
        $domainId,
        $locale,
        ProductFilterData $productFilterData,
        $orderingModeId,
        PricingGroup $pricingGroup,
        $page,
        $limit
    ) {
        $queryBuilder = $this->getFilteredListableInCategoryQueryBuilder(
            $category,
            $domainId,
            $locale,
            $productFilterData,
            $pricingGroup
        );

        $this->applyOrdering($queryBuilder, $orderingModeId, $pricingGroup, $locale);

        $queryPaginator = new QueryPaginator($queryBuilder);

        return $queryPaginator->getResult($page, $limit);
    }

    /**
     * @param int $domainId
     * @param string $locale
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllListableTranslatedAndOrderedQueryBuilder(
        int $domainId,
        string $locale,
        string $orderingModeId,
        PricingGroup $pricingGroup
    ): QueryBuilder {
        $queryBuilder = $this->getAllListableQueryBuilder(
            $domainId,
            $pricingGroup
        );

        $this->addTranslation($queryBuilder, $locale);
        $this->applyOrdering($queryBuilder, $orderingModeId, $pricingGroup, $locale);

        return $queryBuilder;
    }

    /**
     * @param int $domainId
     * @param string $locale
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllListableTranslatedAndOrderedQueryBuilderByCategory(
        int $domainId,
        string $locale,
        string $orderingModeId,
        PricingGroup $pricingGroup,
        Category $category
    ): QueryBuilder {
        $queryBuilder = $this->getListableInCategoryQueryBuilder(
            $domainId,
            $pricingGroup,
            $category
        );

        $this->addTranslation($queryBuilder, $locale);
        $this->applyOrdering($queryBuilder, $orderingModeId, $pricingGroup, $locale);

        return $queryBuilder;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand $brand
     * @param int $domainId
     * @param string $locale
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param int $page
     * @param int $limit
     * @return \Shopsys\FrameworkBundle\Component\Paginator\PaginationResult
     */
    public function getPaginationResultForListableForBrand(
        Brand $brand,
        $domainId,
        $locale,
        $orderingModeId,
        PricingGroup $pricingGroup,
        $page,
        $limit
    ) {
        $queryBuilder = $this->getListableForBrandQueryBuilder(
            $domainId,
            $pricingGroup,
            $brand
        );

        $this->addTranslation($queryBuilder, $locale);
        $this->addDomain($queryBuilder, $domainId);
        $this->applyOrdering($queryBuilder, $orderingModeId, $pricingGroup, $locale);

        $queryPaginator = new QueryPaginator($queryBuilder);

        return $queryPaginator->getResult($page, $limit);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @param int $domainId
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData $productFilterData
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getFilteredListableInCategoryQueryBuilder(
        Category $category,
        $domainId,
        $locale,
        ProductFilterData $productFilterData,
        PricingGroup $pricingGroup
    ) {
        $queryBuilder = $this->getListableInCategoryQueryBuilder(
            $domainId,
            $pricingGroup,
            $category
        );

        $this->addTranslation($queryBuilder, $locale);
        $this->addDomain($queryBuilder, $domainId);
        $this->productFilterRepository->applyFiltering(
            $queryBuilder,
            $productFilterData,
            $pricingGroup
        );

        return $queryBuilder;
    }

    /**
     * @param string|null $searchText
     * @param int $domainId
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData $productFilterData
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param int $page
     * @param int $limit
     * @return \Shopsys\FrameworkBundle\Component\Paginator\PaginationResult
     */
    public function getPaginationResultForSearchListable(
        $searchText,
        $domainId,
        $locale,
        ProductFilterData $productFilterData,
        $orderingModeId,
        PricingGroup $pricingGroup,
        $page,
        $limit
    ) {
        $queryBuilder = $this->getFilteredListableForSearchQueryBuilder(
            $searchText,
            $domainId,
            $locale,
            $productFilterData,
            $pricingGroup
        );

        $this->productElasticsearchRepository->addRelevance($queryBuilder, $searchText);
        $this->applyOrdering($queryBuilder, $orderingModeId, $pricingGroup, $locale);

        $queryPaginator = new QueryPaginator($queryBuilder);

        return $queryPaginator->getResult($page, $limit);
    }

    /**
     * @param string|null $searchText
     * @param int $domainId
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Product\Filter\ProductFilterData $productFilterData
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getFilteredListableForSearchQueryBuilder(
        $searchText,
        $domainId,
        $locale,
        ProductFilterData $productFilterData,
        PricingGroup $pricingGroup
    ) {
        $queryBuilder = $this->getListableBySearchTextQueryBuilder(
            $domainId,
            $pricingGroup,
            $locale,
            $searchText
        );

        $this->productFilterRepository->applyFiltering(
            $queryBuilder,
            $productFilterData,
            $pricingGroup
        );

        return $queryBuilder;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     * @param string $orderingModeId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param string $locale
     */
    protected function applyOrdering(
        QueryBuilder $queryBuilder,
        $orderingModeId,
        PricingGroup $pricingGroup,
        $locale
    ) {
        if ($orderingModeId === ProductListOrderingConfig::ORDER_BY_RELEVANCE) {
            $queryBuilder->addOrderBy('relevance', 'asc');
            $queryBuilder->addOrderBy('p.id', 'asc');

            return;
        }

        $queryBuilder->join('p.calculatedAvailability', 'pca');
        $queryBuilder->addSelect('CASE WHEN pca.dispatchTime IS NULL THEN 1 ELSE 0 END as HIDDEN dispatchTimeIsNull');
        $queryBuilder->orderBy('dispatchTimeIsNull', 'ASC');
        $queryBuilder->addOrderBy('pca.dispatchTime', 'ASC');

        switch ($orderingModeId) {
            case ProductListOrderingConfig::ORDER_BY_NAME_ASC:
                $collation = $this->localization->getCollationByLocale($locale);
                $queryBuilder->addOrderBy("COLLATE(pt.name, '" . $collation . "')", 'asc');

                break;

            case ProductListOrderingConfig::ORDER_BY_NAME_DESC:
                $collation = $this->localization->getCollationByLocale($locale);
                $queryBuilder->addOrderBy("COLLATE(pt.name, '" . $collation . "')", 'desc');

                break;

            case ProductListOrderingConfig::ORDER_BY_PRICE_ASC:
                $this->queryBuilderExtender->addOrExtendJoin(
                    $queryBuilder,
                    ProductCalculatedPrice::class,
                    'pcp',
                    'pcp.product = p AND pcp.pricingGroup = :pricingGroup'
                );
                $queryBuilder->addOrderBy('pcp.priceWithVat', 'asc');
                $queryBuilder->setParameter('pricingGroup', $pricingGroup);

                break;

            case ProductListOrderingConfig::ORDER_BY_PRICE_DESC:
                $this->queryBuilderExtender->addOrExtendJoin(
                    $queryBuilder,
                    ProductCalculatedPrice::class,
                    'pcp',
                    'pcp.product = p AND pcp.pricingGroup = :pricingGroup'
                );
                $queryBuilder->addOrderBy('pcp.priceWithVat', 'desc');
                $queryBuilder->setParameter('pricingGroup', $pricingGroup);

                break;

            case ProductListOrderingConfig::ORDER_BY_PRIORITY:
                $queryBuilder->addOrderBy('p.orderingPriority', 'desc');
                $collation = $this->localization->getCollationByLocale($locale);
                $queryBuilder->addOrderBy("COLLATE(pt.name, '" . $collation . "')", 'asc');

                break;

            default:
                $message = 'Product list ordering mode "' . $orderingModeId . '" is not supported.';

                throw new InvalidOrderingModeException($message);
        }

        $queryBuilder->addOrderBy('p.id', 'asc');
    }

    /**
     * @param int $id
     * @return \Shopsys\FrameworkBundle\Model\Product\Product
     */
    public function getById($id)
    {
        $product = $this->findById($id);

        if ($product === null) {
            throw new ProductNotFoundException('Product with ID ' . $id . ' does not exist.');
        }

        return $product;
    }

    /**
     * @param int[] $ids
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getAllByIds($ids)
    {
        return $this->getProductRepository()->findBy(['id' => $ids]);
    }

    /**
     * @param int $id
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Shopsys\FrameworkBundle\Model\Product\Product
     */
    public function getVisible($id, $domainId, PricingGroup $pricingGroup)
    {
        $qb = $this->getAllVisibleQueryBuilder($domainId, $pricingGroup);
        $qb->andWhere('p.id = :productId');
        $qb->setParameter('productId', $id);

        $product = $qb->getQuery()->getOneOrNullResult();

        if ($product === null) {
            throw new ProductNotFoundException();
        }

        return $product;
    }

    /**
     * @param int $id
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Shopsys\FrameworkBundle\Model\Product\Product
     */
    public function getSellableById($id, $domainId, PricingGroup $pricingGroup)
    {
        $qb = $this->getAllSellableQueryBuilder($domainId, $pricingGroup);
        $qb->andWhere('p.id = :productId');
        $qb->setParameter('productId', $id);

        $product = $qb->getQuery()->getOneOrNullResult();

        if ($product === null) {
            throw new ProductNotFoundException();
        }

        return $product;
    }

    /**
     * @return \Doctrine\ORM\Internal\Hydration\IterableResult|\Shopsys\FrameworkBundle\Model\Product\Product[][]
     */
    public function getProductIteratorForReplaceVat()
    {
        $query = $this->em->createQuery('
            SELECT DISTINCT p
            FROM ' . Product::class . ' p
            JOIN ' . ProductDomain::class . ' pd WITH pd.product = p
            JOIN pd.vat v
            WHERE v.replaceWith IS NOT NULL
        ');

        return $query->iterate();
    }

    public function markAllProductsForAvailabilityRecalculation()
    {
        $this->em
            ->createQuery('UPDATE ' . Product::class . ' p SET p.recalculateAvailability = TRUE
                WHERE p.recalculateAvailability = FALSE')
            ->execute();
    }

    public function markAllProductsForPriceRecalculation()
    {
        // Performance optimization:
        // Main variant price recalculation is triggered by variants visibility recalculation
        // and visibility recalculation is triggered by variant price recalculation.
        // Therefore main variant price recalculation is useless here.
        $this->em
            ->createQuery('UPDATE ' . Product::class . ' p SET p.recalculatePrice = TRUE
                WHERE p.variantType != :variantTypeMain AND p.recalculateAvailability = FALSE')
            ->setParameter('variantTypeMain', Product::VARIANT_TYPE_MAIN)
            ->execute();
    }

    /**
     * @return \Doctrine\ORM\Internal\Hydration\IterableResult|\Shopsys\FrameworkBundle\Model\Product\Product[][]
     */
    public function getProductsForPriceRecalculationIterator()
    {
        return $this->getProductRepository()
            ->createQueryBuilder('p')
            ->where('p.recalculatePrice = TRUE')
            ->getQuery()
            ->iterate();
    }

    /**
     * @return \Doctrine\ORM\Internal\Hydration\IterableResult|\Shopsys\FrameworkBundle\Model\Product\Product[][]
     */
    public function getProductsForAvailabilityRecalculationIterator()
    {
        return $this->getProductRepository()
            ->createQueryBuilder('p')
            ->where('p.recalculateAvailability = TRUE')
            ->getQuery()
            ->iterate();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product $mainVariant
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getAllSellableVariantsByMainVariant(Product $mainVariant, $domainId, PricingGroup $pricingGroup)
    {
        $queryBuilder = $this->getAllSellableQueryBuilder($domainId, $pricingGroup);
        $queryBuilder
            ->andWhere('p.mainVariant = :mainVariant')
            ->setParameter('mainVariant', $mainVariant);

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getAllSellableUsingStockInStockQueryBuilder($domainId, $pricingGroup)
    {
        $queryBuilder = $this->getAllSellableQueryBuilder($domainId, $pricingGroup);
        $queryBuilder
            ->andWhere('p.usingStock = TRUE')
            ->andWhere('p.stockQuantity > 0');

        return $queryBuilder;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product $mainVariant
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getAtLeastSomewhereSellableVariantsByMainVariant(Product $mainVariant)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->andWhere('p.calculatedVisibility = TRUE')
            ->andWhere('p.calculatedSellingDenied = FALSE')
            ->andWhere('p.variantType = :variantTypeVariant')->setParameter(
                'variantTypeVariant',
                Product::VARIANT_TYPE_VARIANT
            )
            ->andWhere('p.mainVariant = :mainVariant')->setParameter('mainVariant', $mainVariant);

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param int[] $sortedProductIds
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getOfferedByIds($domainId, PricingGroup $pricingGroup, array $sortedProductIds)
    {
        if (count($sortedProductIds) === 0) {
            return [];
        }

        $queryBuilder = $this->getAllOfferedQueryBuilder($domainId, $pricingGroup);
        $queryBuilder
            ->andWhere('p.id IN (:productIds)')
            ->setParameter('productIds', $sortedProductIds)
            ->addSelect('field(p.id, ' . implode(',', $sortedProductIds) . ') AS HIDDEN relevance')
            ->orderBy('relevance');

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @param int[] $sortedProductIds
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getListableByIds(int $domainId, PricingGroup $pricingGroup, array $sortedProductIds): array
    {
        if (count($sortedProductIds) === 0) {
            return [];
        }

        $queryBuilder = $this->getAllListableQueryBuilder($domainId, $pricingGroup);
        $queryBuilder
            ->andWhere('p.id IN (:productIds)')
            ->setParameter('productIds', $sortedProductIds)
            ->addSelect('field(p.id, ' . implode(',', $sortedProductIds) . ') AS HIDDEN relevance')
            ->orderBy('relevance');

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * @param string $productCatnum
     * @return \Shopsys\FrameworkBundle\Model\Product\Product
     */
    public function getOneByCatnumExcludeMainVariants($productCatnum)
    {
        $queryBuilder = $this->getProductRepository()->createQueryBuilder('p')
            ->andWhere('p.catnum = :catnum')
            ->andWhere('p.variantType != :variantTypeMain')
            ->setParameter('catnum', $productCatnum)
            ->setParameter('variantTypeMain', Product::VARIANT_TYPE_MAIN);
        $product = $queryBuilder->getQuery()->getOneOrNullResult();

        if ($product === null) {
            throw new ProductNotFoundException(
                'Product with catnum ' . $productCatnum . ' does not exist.'
            );
        }

        return $product;
    }

    /**
     * @param string $uuid
     * @return \Shopsys\FrameworkBundle\Model\Product\Product
     */
    public function getOneByUuid(string $uuid): Product
    {
        $product = $this->getProductRepository()->findOneBy(['uuid' => $uuid]);

        if ($product === null) {
            throw new ProductNotFoundException('Product with UUID ' . $uuid . ' does not exist.');
        }

        return $product;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\ProductQueryParams $query
     * @return \Shopsys\FrameworkBundle\Component\Paginator\PaginationResult
     */
    public function findByProductQueryParams(ProductQueryParams $query): PaginationResult
    {
        $queryBuilder = $this->getProductRepository()->createQueryBuilder('p');
        $queryBuilder->orderBy('p.id');

        if ($query->getUuids()) {
            $queryBuilder->andWhere('p.uuid IN (:uuids)');
            $queryBuilder->setParameter(':uuids', $query->getUuids());
        }

        $queryPaginator = new QueryPaginator($queryBuilder);

        return $queryPaginator->getResult($query->getPage(), $query->getPageSize());
    }

    /**
     * @param int $domainId
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Group\PricingGroup $pricingGroup
     * @return array
     */
    public function getAllOfferedProducts(int $domainId, PricingGroup $pricingGroup): array
    {
        return $this->getAllOfferedQueryBuilder($domainId, $pricingGroup)->getQuery()->execute();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product[] $products
     */
    public function markProductsForExport(array $products): void
    {
        $this->em->createQuery('UPDATE ' . Product::class . ' p SET p.exportProduct = TRUE WHERE p IN (:products)')
            ->setParameter('products', $products)
            ->execute();
    }

    public function markAllProductsForExport(): void
    {
        $this->em->createQuery('UPDATE ' . Product::class . ' p SET p.exportProduct = TRUE')
            ->execute();
    }

    public function markAllProductsAsExported(): void
    {
        $this->em->createQuery('UPDATE ' . Product::class . ' p SET p.exportProduct = FALSE')
            ->execute();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Parameter\Parameter $parameter
     * @return array
     */
    public function getProductsWithParameter(Parameter $parameter): array
    {
        return $this->getProductRepository()->createQueryBuilder('p')
            ->innerJoin(ProductParameterValue::class, 'ppv', 'WITH', 'ppv.product = p')
            ->where('ppv.parameter = :parameter')
            ->setParameter('parameter', $parameter)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Availability\Availability $availability
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getProductsWithAvailability(Availability $availability): array
    {
        return $this->getProductRepository()->createQueryBuilder('p')
            ->where('p.calculatedAvailability = :availability')
            ->setParameter('availability', $availability)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Brand\Brand $brand
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getProductsWithBrand(Brand $brand): array
    {
        return $this->getProductRepository()->createQueryBuilder('p')
            ->where('p.brand = :brand')
            ->setParameter('brand', $brand)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Flag\Flag $flag
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getProductsWithFlag(Flag $flag): array
    {
        return $this->getProductRepository()->createQueryBuilder('p')
            ->leftJoin('p.flags', 'f')
            ->where('f.id = :flag')
            ->setParameter('flag', $flag)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Unit\Unit $unit
     * @return \Shopsys\FrameworkBundle\Model\Product\Product[]
     */
    public function getProductsWithUnit(Unit $unit): array
    {
        return $this->getProductRepository()->createQueryBuilder('p')
            ->where('p.unit = :unit')
            ->setParameter('unit', $unit)
            ->getQuery()
            ->getResult();
    }
}
