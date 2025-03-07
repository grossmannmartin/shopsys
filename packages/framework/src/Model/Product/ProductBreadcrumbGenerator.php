<?php

namespace Shopsys\FrameworkBundle\Model\Product;

use Shopsys\FrameworkBundle\Component\Breadcrumb\BreadcrumbGeneratorInterface;
use Shopsys\FrameworkBundle\Component\Breadcrumb\BreadcrumbItem;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Model\Category\Category;
use Shopsys\FrameworkBundle\Model\Category\CategoryFacade;

class ProductBreadcrumbGenerator implements BreadcrumbGeneratorInterface
{
    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Category\CategoryFacade
     */
    protected $categoryFacade;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\ProductRepository $productRepository
     * @param \Shopsys\FrameworkBundle\Model\Category\CategoryFacade $categoryFacade
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     */
    public function __construct(
        ProductRepository $productRepository,
        CategoryFacade $categoryFacade,
        Domain $domain
    ) {
        $this->productRepository = $productRepository;
        $this->categoryFacade = $categoryFacade;
        $this->domain = $domain;
    }

    /**
     * {@inheritDoc}
     */
    public function getBreadcrumbItems($routeName, array $routeParameters = [])
    {
        $product = $this->productRepository->getById($routeParameters['id']);

        $productMainCategory = $this->categoryFacade->getProductMainCategoryByDomainId(
            $product,
            $this->domain->getId()
        );

        $breadcrumbItems = $this->getCategoryBreadcrumbItems($productMainCategory);

        $breadcrumbItems[] = new BreadcrumbItem(
            $product->getName()
        );

        return $breadcrumbItems;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Category\Category $category
     * @return \Shopsys\FrameworkBundle\Component\Breadcrumb\BreadcrumbItem[]
     */
    protected function getCategoryBreadcrumbItems(Category $category)
    {
        $categoriesInPath = $this->categoryFacade->getVisibleCategoriesInPathFromRootOnDomain(
            $category,
            $this->domain->getId()
        );

        $breadcrumbItems = [];

        foreach ($categoriesInPath as $categoryInPath) {
            $breadcrumbItems[] = new BreadcrumbItem(
                $categoryInPath->getName(),
                'front_product_list',
                ['id' => $categoryInPath->getId()]
            );
        }

        return $breadcrumbItems;
    }

    /**
     * {@inheritDoc}
     */
    public function getRouteNames()
    {
        return ['front_product_detail'];
    }
}
