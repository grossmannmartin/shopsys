# [Upgrade from v10.0.0 to v11.0.0-dev](https://github.com/shopsys/shopsys/compare/v10.0.0...master)

This guide contains instructions to upgrade from version v10.0.0 to v11.0.0-dev.

**Before you start, don't forget to take a look at [general instructions](https://github.com/shopsys/shopsys/blob/master/UPGRADE.md) about upgrading.**
There you can find links to upgrade notes for other versions too.

## Removed deprecations

- check that your code don't use any removed code ([#](https://github.com/shopsys/shopsys/pull/))
    - Phing property `path.env.test` was removed
    - function `getCategoriesOrderingData()` from `@shopsys/framework/assets/js/admin/components/CategoryTreeSorting.js` was removed, use `getNestedSetData()` instead
    - method `Shopsys\FrameworkBundle\Controller\Admin\CategoryController::saveOrderAction()` was removed, use `applySortingAction()` instead
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryFacade::editOrdering()` was removed, use `reorderByNestedSetValues()` instead
    - data transformer `Shopsys\FrameworkBundle\Form\Transformers\ScriptPlacementToBooleanTransformer` was removed as it's not necessary anymore
    - command `Shopsys\FrameworkBundle\Command\ElFinderPostInstallCommand` was removed, set proper public dir with `--docroot` option in `elfinder:install` command
    - class `Shopsys\FrameworkBundle\Component\Csv\CsvReader` was removed, use `SplFileObject::fgetcsv()` instead
    - command `Shopsys\FrameworkBundle\Command\Elasticsearch\ElasticsearchIndexesCreateCommand` was removed, use `ElasticsearchIndexesMigrateCommand` instead
    - method `Shopsys\FrameworkBundle\Component\Elasticsearch\IndexDefinition::getLegacyIndexAlias()` was removed
    - visibility of method `Shopsys\FrameworkBundle\Component\Elasticsearch\IndexFacade::create()` was changed to `protected`
    - method `Shopsys\FrameworkBundle\Component\Elasticsearch\Exception\ElasticsearchIndexException::indexAlreadyExists()` was removed, use `ElasticsearchIndexAlreadyExistsException` directly
    - class `Shopsys\FrameworkBundle\Component\DataFixture\AbstractNativeFixture` was removed
    - removed constant `Shopsys\FrameworkBundle\Component\Environment\EnvironmentFileSetting\ENVIRONMENTS_CONSOLE`
    - `Shopsys\FrameworkBundle\Component\Router\DomainRouterFactory`
        - property `$configLoader` was removed
        - property `$container` is no longer nullable
        - property `$cacheDir`  is no longer nullable
        - method `__construct`  changed its interface:
            ```diff
                public function __construct(
                    $routerConfiguration,
            -       ?LoaderInterface $configLoader,
                    LocalizedRouterFactory $localizedRouterFactory,
                    FriendlyUrlRouterFactory $friendlyUrlRouterFactory,
                    Domain $domain,
                    RequestStack $requestStack,
            -       ?ContainerInterface $container = null,
            +       ContainerInterface $container,
            -       ?string $cacheDir = null
            +       string $cacheDir
                )
            ```
    - `Shopsys\FrameworkBundle\Component\Router\LocalizedRouterFactory`
        - property `$configLoader` was removed
        - property `$container` is no longer nullable
        - property `$cacheDir`  is no longer nullable
        - method `__construct`  changed its interface:
            ```diff
                public function __construct(
                    $localeRoutersResourcesFilepathMask,
            -       ?LoaderInterface $configLoader = null,
            -       ?ContainerInterface $container = null,
            +       ContainerInterface $container,
            -       ?string $cacheDir = null
            +       string $cacheDir
                )
            ```
    - `Shopsys\FrameworkBundle\Model\AdvancedSearch\Filter\ProductCategoryFilter`
        - property `$domain` was removed
        - method `__construct`  changed its interface:
            ```diff
                public function __construct(
                    CategoryFacade $categoryFacade,
            -       ?Domain $domain = null,
            -       ?LocalizationAlias $localization = null
            +       Localization $localization
                )
            ```
    - class `Shopsys\FrameworkBundle\Model\Breadcrumb\ErrorPageBreadcrumbGenerator` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getTranslatedBreadcrumbForNotFoundPage()`
        - `getTranslatedBreadcrumbForErrorPage()`
    - class `Shopsys\FrameworkBundle\Model\Breadcrumb\SimpleBreadcrumbGenerator` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getTranslatedBreadcrumbsByRouteNames()`
    - class `Shopsys\FrameworkBundle\Model\Cart\Watcher\CartWatcherFacade` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getMessageForNoLongerAvailableExistingProduct()`
        - `getMessageForNoLongerAvailableProduct()`
        - `getMessageForChangedProduct()`
    - class `Shopsys\FrameworkBundle\Model\LegalConditions\LegalConditionsFacade` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getTermsAndConditionsDownloadFilename();`
    - class `Shopsys\FrameworkBundle\Model\Product\Listing\ProductListOrderingModeForBrandFacade` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getSupportedOrderingModesNamesById()`
    - class `Shopsys\FrameworkBundle\Model\Product\Listing\ProductListOrderingModeForListFacade` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getSupportedOrderingModesNamesById()`
    - class `Shopsys\FrameworkBundle\Model\Product\Listing\ProductListOrderingModeForSearchFacade` was changed to abstract, don't forget to extend this class and implement following methods:
        - `getSupportedOrderingModesNamesById()`
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryFacade::getTranslatedAll()` was removed, use `getAllTranslated()` instead
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryFacade::getTranslatedAllWithoutBranch` was removed, use `getAllTranslatedWithoutBranch()` instead
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryRepository::getTranslatedAll()` was removed, use `getAllTranslated()` instead
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryRepository::getTranslatedAllWithoutBranch()` was removed, use `getAllTranslatedWithoutBranch()` instead
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryRepository::addTranslationPublic()` was removed
        - use `addTranslation()`, which visibility was changed to public
    - method `Shopsys\FrameworkBundle\Model\Category\CategoryRepository::filterBySearchTextPublic()` was removed
        - use `filterBySearchText()`, which visibility was changed to public
    - method `Shopsys\FrameworkBundle\Model\Pricing\PriceConverter::convertPriceWithoutVatToPriceInDomainDefaultCurrency()` was removed, use `convertPriceWithoutVatToDomainDefaultCurrencyPrice()` instead
    - method `Shopsys\FrameworkBundle\Model\Pricing\PriceConverter::convertPriceWithVatToPriceInDomainDefaultCurrency()` was removed, use `convertPriceWithVatToDomainDefaultCurrencyPrice()` instead
    - method `Shopsys\FrameworkBundle\Model\Pricing\PriceConverter::__construct()` changed its interface:
        ```diff
            public function __construct(
                CurrencyFacade $currencyFacade,
                Rounding $rounding,
        -       ?Setting $setting = null
        +       Setting $setting
            )
        ```
    - method `Shopsys\FrameworkBundle\Model\Pricing\PriceConverter::convertPriceWithoutVatToPriceInDomainDefaultCurrency()` was removed, use `convertPriceWithoutVatToDomainDefaultCurrencyPrice'()` instead
    - method `Shopsys\FrameworkBundle\Model\Pricing\PriceConverter::convertPriceWithVatToPriceInDomainDefaultCurrency()` was removed, use `convertPriceWithVatToDomainDefaultCurrencyPrice'()` instead
    - method `Shopsys\FrameworkBundle\Model\Product\ProductFacade::getSellableByUuid` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product` namespace
    - method `Shopsys\FrameworkBundle\Model\Product\ProductRepository::getSellableByUuid` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product` namespace
    - method `\Shopsys\FrameworkBundle\Model\Product\ProductRepository::getListableForBrandQueryBuilderPublic()` was removed
        - use `getListableForBrandQueryBuilder()`, which visibility was changed to public
    - `Shopsys\FrameworkBundle\Model\Product\ProductOnCurrentDomainElasticFacade`:
        - method `createListableProductsInCategoryFilterQuery()` was removed, use `Shopsys\FrameworkBundle\Model\Product\Search\FilterQueryFactory::createListableProductsByCategoryId()` instead
        - method `createListableProductsForBrandFilterQuery()` was removed, use `Shopsys\FrameworkBundle\Model\Product\Search\FilterQueryFactory::createListableProductsByBrandId` instead
        - method `createListableProductsForSearchTextFilterQuery()` was removed, use `Shopsys\FrameworkBundle\Model\Product\Search\FilterQueryFactory::createListableProductsBySearchText()` instead
        - method `createFilterQueryWithProductFilterData()` was removed, use `Shopsys\FrameworkBundle\Model\Product\Search\FilterQueryFactory::createWithProductFilterData()` instead
        - method `getIndexName()` was removed, use `Shopsys\FrameworkBundle\Model\Product\Search\FilterQueryFactory::getIndexName()` instead
        - method `getProductsCountOnCurrentDomain()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
        - method `getProductsOnCurrentDomain()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
        - method `getProductsByCategory()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
    - `Shopsys\FrameworkBundle\Model\Product\ProductOnCurrentDomainFacade`
        - method `getProductsCountOnCurrentDomain()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
        - method `getProductsOnCurrentDomain()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
        - method `getProductsByCategory()` was removed, use method with the same name from the `Shopsys\FrontendApiBundle\Model\Product\ProductFacade` class
    - `Shopsys\FrameworkBundle\Model\Product\ProductOnCurrentDomainFacadeInterface`:
        - method `getProductsCountOnCurrentDomain()` was removed
        - method `getProductsOnCurrentDomain()` was removed
        - method `getProductsByCategory()` was removed
    - constant `Shopsys\FrameworkBundle\Model\Product\BestsellingProduct\BestsellingProductFacade::MAX_RESULTS` was removed
    - method `Shopsys\FrameworkBundle\Model\Script\ScriptFacade::getAllPagesScriptCodes()` was removed, use `getAllPagesBeforeContentScriptCodes()` instead
    - all unnecessary service definitions were removed from `services_test.yaml` file
    - removed `Shopsys\FrameworkBundle\Component\FileUpload\DeleteOldUploadedFilesCronModule` cron definition from shopsys/framework package, add this cron to your project config
    - removed css class `js-order-sent-page-variables` from `shopsys/framework/src/Resources/views/Admin/Content/Script/detail.html.twig`
    - `Shopsys\FrontendApiBundle\Model\Product\ProductFacade`
        - method `getProductsCountOnCurrentDomain()` was removed, use `getFilteredProductsCountOnCurrentDomain()` instead
        - method `getProductsOnCurrentDomain()` was removed, use `getFilteredProductsOnCurrentDomain()` instead
        - method `getProductsByCategory()` was removed, use `getFilteredProductsByCategory()` instead
        - method `getProductsByCategoryCount()` was removed, use `getFilteredProductsByCategoryCount()` instead
        - method `getProductsByBrand()` was removed, use `getFilteredProductsByBrand()` instead
        - method `getProductsByBrandCount()` was removed, use `getFilteredProductsByBrandCount()` instead
    - removed parameter `shopsys.var_dir` from shopsys/framework package, add this parameter to your project config
    - `Shopsys\FrontendApiBundle\Component\Constraints\ProductCanBeOrderedValidator`
        - property `$productFacade` was removed
        - property `$frontendApiProductFacade` is no longer nullable
        - method `__construct()` changed its interface:
            ```diff
                public function __construct(
            -       ProductFacade $productFacade,
                    ProductCachedAttributesFacade $productCachedAttributesFacade,
                    Domain $domain,
                    CurrentCustomerUser $currentCustomerUser,
            -       ?FrontendApiProductFacade $frontendApiProductFacade = null
            +       FrontendApiProductFacade $frontendApiProductFacade
                )
            ```
    - method `Shopsys\FrameworkBundle\Model\Article\ArticleRepository::getVisibleArticlesByDomainIdAndPlacementSortedByPositionQueryBuilder()` changed its visibility to `public`
    - method `Shopsys\FrameworkBundle\Model\Article\ArticleRepository::getAllVisibleQueryBuilder` changed its visibility to `public`
    - method `Shopsys\FrameworkBundle\Model\Article\ArticleRepository::getArticlesByDomainIdQueryBuilder` changed its visibility to `public`
    - `Shopsys\FrontendApiBundle\Model\Article\ArticleRepository`
        - property `$em` was removed
        - method `__construct()` changed its interface
            ```diff
            -   public function __construct(FrameworkArticleRepository $articleRepository, EntityManagerInterface $em)
            +   public function __construct(FrameworkArticleRepository $articleRepository)
            ```
        - method `getVisibleArticlesByDomainIdAndPlacementSortedByPositionQueryBuilder()` was removed, use method with the same name from the `Shopsys\FrameworkBundle\Model\Article\ArticleRepository` class
        - method `getArticlesByDomainIdQueryBuilder()` was removed, use method with the same name from the `Shopsys\FrameworkBundle\Model\Article\ArticleRepository` class
        - method `getAllVisibleQueryBuilder()` was removed, use method with the same name from the `Shopsys\FrameworkBundle\Model\Article\ArticleRepository` class
    - `Shopsys\FrontendApiBundle\Model\Resolver\Category\CategoryResolver`
        - property `$domain` is no longer nullable
        - property `$friendlyUrlFacade` is no longer nullable
        - method `__construct()` changed its interface
            ```diff
                public function __construct(
                    CategoryFacade $categoryFacade,
            -       ?Domain $domain = null,
            +       Domain $domain,
            -       ?FriendlyUrlFacade $friendlyUrlFacade = null
            +       FriendlyUrlFacade $friendlyUrlFacade
                )
            ```
        - method `resolver` used as resolver with an alias `category` was removed, use `resolveByUuidOrUrlSlug` method and resolver alias instead
    - class `Shopsys\FrontendApiBundle\Model\Resolver\Products\ProductResolver` used as resolver with alias `product` was removed, use `ProductDetailResolver` with alias `productDetail` instead
    - `Shopsys\FrontendApiBundle\Model\Resolver\Products\ProductResolverMap`
        - method `getProductLink()` was removed, use mapping via appropriate class `DataMapper\Product*FieldMapper` instead
        - method `getFlagsForData()` was removed, use mapping via appropriate class `DataMapper\Product*FieldMapper` instead
        - method `getCategoriesForData()`  was removed, use mapping via appropriate class `DataMapper\Product*FieldMapper` instead
        - property `$domain` was removed
        - property `$productCollectionFacade` was removed
        - property `flagFacade` was removed
        - property `$categoryFacade` was removed
        - method `__construct` changed its interface
            ```diff
                public function __construct(
            -       Domain $domain,
            -       ProductCollectionFacade $productCollectionFacade,
            -       FlagFacade $flagFacade,
            -       CategoryFacade $categoryFacade,
            -       ?ProductEntityFieldMapper $productEntityFieldMapper = null,
            +       ProductEntityFieldMapper $productEntityFieldMapper,
            -       ?ProductArrayFieldMapper $productArrayFieldMapper = null
            +       ProductArrayFieldMapper $productArrayFieldMapper
                )
            ```
    - `Shopsys\FrontendApiBundle\Model\Resolver\Products\ProductsResolver`
        - constant `EDGE_COUNT` was removed
        - property `$productOnCurrentDomainFacade` was removed
        - property `$productFacade` is no longer nullable
        - property `$productFilterFacade` is no longer nullable
        - property `$productConnectionFactory` is no longer nullable
        - method `__construct` changed its interface
            ```diff
                public function __construct(
            -       ProductOnCurrentDomainFacadeInterface $productOnCurrentDomainFacade,
            -       ?ProductFacade $productFacade = null,
            +       ProductFacade $productFacade,
            -       ?ProductFilterFacade $productFilterFacade = null,
            +       ProductFilterFacade $productFilterFacade,
            -       ?ProductConnectionFactory $productConnectionFactory = null
            +       ProductConnectionFactory $productConnectionFactory
                )
            ```
    - method `Shopsys\ReadModelBundle\Image\ImageViewFacade::getForEntityIds()` was removed, use `getMainImagesByEntityIds()` instead
    - method `Shopsys\ReadModelBundle\Product\Action\ProductActionViewFacade::getForArray()` was removed, use `ProductActionViewFactory` instead
