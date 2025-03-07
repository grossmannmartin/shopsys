<?php

declare(strict_types=1);

namespace App\DataFixtures\Demo;

use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\MountManager;
use Shopsys\FrameworkBundle\Component\DataFixture\AbstractReferenceFixture;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\String\TransformString;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ImageDataFixture extends AbstractReferenceFixture implements DependentFixtureInterface
{
    public const IMAGES_TABLE_NAME = 'images';
    public const IMAGES_TRANSLATIONS_TABLE_NAME = 'images_translations';
    public const IMAGE_TYPE = 'jpg';

    /**
     * @param string $dataFixturesImagesDirectory
     * @param string $targetImagesDirectory
     * @param string $targetDomainImagesDirectory
     * @param \League\Flysystem\FilesystemOperator $filesystem
     * @param \Symfony\Component\Filesystem\Filesystem $localFilesystem
     * @param \League\Flysystem\MountManager $mountManager
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Shopsys\FrameworkBundle\Component\Domain\Domain $domain
     */
    public function __construct(
        private readonly string $dataFixturesImagesDirectory,
        private readonly string $targetImagesDirectory,
        private readonly string $targetDomainImagesDirectory,
        private readonly FilesystemOperator $filesystem,
        private readonly Filesystem $localFilesystem,
        private readonly MountManager $mountManager,
        private readonly EntityManagerInterface $em,
        private readonly Domain $domain,
    ) {
    }

    /**
     * @param \Doctrine\Persistence\ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->truncateImagesFromDb();

        if (!file_exists($this->dataFixturesImagesDirectory)) {
            return;
        }

        $this->moveFilesFromLocalFilesystemToFilesystem(
            $this->dataFixturesImagesDirectory . 'domain/',
            $this->targetDomainImagesDirectory . '/'
        );
        $this->moveFilesFromLocalFilesystemToFilesystem(
            $this->dataFixturesImagesDirectory,
            $this->targetImagesDirectory
        );
        $this->processDbImagesChanges();
    }

    private function processDbImagesChanges()
    {
        $this->processBrandsImages();
        $this->processCategoriesImages();
        $this->processPaymentsImages();
        $this->processTransportsImages();
        $this->processProductsImages();
        $this->processSliderItemsImages();
        $this->restartImagesIdsDbSequence();
    }

    private function processBrandsImages()
    {
        $brandsImagesData = [
            79 => BrandDataFixture::BRAND_APPLE,
            80 => BrandDataFixture::BRAND_CANON,
            81 => BrandDataFixture::BRAND_LG,
            82 => BrandDataFixture::BRAND_PHILIPS,
            83 => BrandDataFixture::BRAND_SENCOR,
            84 => BrandDataFixture::BRAND_A4TECH,
            85 => BrandDataFixture::BRAND_BROTHER,
            86 => BrandDataFixture::BRAND_VERBATIM,
            87 => BrandDataFixture::BRAND_DLINK,
            88 => BrandDataFixture::BRAND_DEFENDER,
            89 => BrandDataFixture::BRAND_DELONGHI,
            90 => BrandDataFixture::BRAND_GENIUS,
            91 => BrandDataFixture::BRAND_GIGABYTE,
            92 => BrandDataFixture::BRAND_HP,
            93 => BrandDataFixture::BRAND_HTC,
            94 => BrandDataFixture::BRAND_JURA,
            95 => BrandDataFixture::BRAND_LOGITECH,
            96 => BrandDataFixture::BRAND_MICROSOFT,
            97 => BrandDataFixture::BRAND_SAMSUNG,
            98 => BrandDataFixture::BRAND_SONY,
            99 => BrandDataFixture::BRAND_ORAVA,
            100 => BrandDataFixture::BRAND_OLYMPUS,
            101 => BrandDataFixture::BRAND_HYUNDAI,
            102 => BrandDataFixture::BRAND_NIKON,
        ];

        foreach ($brandsImagesData as $imageId => $brandName) {
            /** @var \App\Model\Product\Brand\Brand $brand */
            $brand = $this->getReference($brandName);

            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = $brandName;
            }

            $this->saveImageIntoDb($brand->getId(), 'brand', $imageId, $names);
        }
    }

    private function processCategoriesImages()
    {
        $categoriesImagesData = [
            68 => CategoryDataFixture::CATEGORY_ELECTRONICS,
            69 => CategoryDataFixture::CATEGORY_TV,
            70 => CategoryDataFixture::CATEGORY_PHOTO,
            71 => CategoryDataFixture::CATEGORY_PRINTERS,
            72 => CategoryDataFixture::CATEGORY_PC,
            73 => CategoryDataFixture::CATEGORY_PHONES,
            74 => CategoryDataFixture::CATEGORY_COFFEE,
            75 => CategoryDataFixture::CATEGORY_BOOKS,
            76 => CategoryDataFixture::CATEGORY_TOYS,
            77 => CategoryDataFixture::CATEGORY_GARDEN_TOOLS,
            78 => CategoryDataFixture::CATEGORY_FOOD,
        ];

        foreach ($categoriesImagesData as $imageId => $categoryName) {
            /** @var \App\Model\Category\Category $category */
            $category = $this->getReference($categoryName);

            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = $categoryName;
            }

            $this->saveImageIntoDb($category->getId(), 'category', $imageId, $names);
        }
    }

    private function processPaymentsImages()
    {
        $paymentsImagesData = [
            53 => PaymentDataFixture::PAYMENT_CARD,
            55 => PaymentDataFixture::PAYMENT_CASH_ON_DELIVERY,
            54 => PaymentDataFixture::PAYMENT_CASH,
        ];

        foreach ($paymentsImagesData as $imageId => $paymentName) {
            /** @var \App\Model\Payment\Payment $payment */
            $payment = $this->getReference($paymentName);

            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = $paymentName;
            }

            $this->saveImageIntoDb($payment->getId(), 'payment', $imageId, $names);
        }
    }

    private function processTransportsImages()
    {
        $transportsImagesData = [
            56 => TransportDataFixture::TRANSPORT_CZECH_POST,
            57 => TransportDataFixture::TRANSPORT_PPL,
            58 => TransportDataFixture::TRANSPORT_PERSONAL,
        ];

        foreach ($transportsImagesData as $imageId => $transportName) {
            /** @var \App\Model\Transport\Transport $transport */
            $transport = $this->getReference($transportName);

            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = $transportName;
            }

            $this->saveImageIntoDb($transport->getId(), 'transport', $imageId, $names);
        }
    }

    private function processProductsImages()
    {
        $productsIdsWithImageIdSameAsProductId = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
            11, 12, 13, 14, 15, 16, 17, 18,
            19, 20, 21, 22, 24, 25, 26, 27,
            28, 29, 30, 31, 32, 33, 35, 36,
            37, 38, 39, 41, 42, 43, 44, 45,
            46, 47, 48, 49, 51, 52,
        ];

        $specificProductsIdsIndexedByImagesIds = [
            64 => 1,
            67 => 5,
        ];

        foreach ($productsIdsWithImageIdSameAsProductId as $productId) {
            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = 'Product ' . $productId . ' image';
            }

            $this->saveImageIntoDb($productId, 'product', $productId, $names);
        }

        foreach ($specificProductsIdsIndexedByImagesIds as $imageId => $productId) {
            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = 'Product ' . $productId . ' image';
            }

            $this->saveImageIntoDb($productId, 'product', $imageId, $names);
        }
    }

    private function processSliderItemsImages()
    {
        $imagesIdsIndexedBySliderItemsIds = [
            1 => 59,
            2 => 60,
            3 => 61,
        ];

        foreach ($imagesIdsIndexedBySliderItemsIds as $sliderItemId => $imageId) {
            $names = [];

            foreach ($this->domain->getAllLocales() as $locale) {
                $names[$locale] = 'Product ' . $sliderItemId . ' image';
            }

            $this->saveImageIntoDb($sliderItemId, 'sliderItem', $imageId, $names);
        }
    }

    /**
     * @param int $entityId
     * @param string $entityName
     * @param int $imageId
     * @param array $names
     */
    private function saveImageIntoDb(int $entityId, string $entityName, int $imageId, array $names = [])
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO images (id, entity_name, entity_id, type, extension, position, modified_at)
            VALUES (:id, :entity_name, :entity_id, NULL, :extension, NULL, :modified_at)',
            [
                'id' => $imageId,
                'entity_name' => $entityName,
                'entity_id' => $entityId,
                'extension' => self::IMAGE_TYPE,
                'modified_at' => new DateTimeImmutable('2015-04-16 11:36:06'),
            ],
            [
                'id' => Types::INTEGER,
                'entity_name' => Types::STRING,
                'entity_id' => Types::INTEGER,
                'extension' => Types::STRING,
                'modified_at' => Types::DATETIME_IMMUTABLE,
            ]
        );

        foreach ($this->domain->getAllLocales() as $locale) {
            $this->em->getConnection()->executeStatement(
                'INSERT INTO images_translations ( translatable_id, name, locale)
                VALUES (:translatable_id, :name, :locale)',
                [
                    'translatable_id' => $imageId,
                    'name' => $names[$locale] ?? null,
                    'locale' => $locale,
                ],
                [
                    'translatable_id' => Types::INTEGER,
                    'name' => Types::STRING,
                    'locale' => Types::STRING,
                ]
            );
        }
    }

    /**
     * @param string $origin
     * @param string $target
     */
    private function moveFilesFromLocalFilesystemToFilesystem(string $origin, string $target)
    {
        $finder = new Finder();
        $finder->files()->in($origin);

        foreach ($finder as $file) {
            $filepath = TransformString::removeDriveLetterFromPath($file->getPathname());

            if (!$this->localFilesystem->exists($filepath)) {
                continue;
            }

            $newFilepath = $target . $file->getRelativePathname();

            if ($this->filesystem->has($newFilepath)) {
                $this->filesystem->delete($newFilepath);
            }
            $this->mountManager->copy('local://' . $filepath, 'main://' . $newFilepath);
        }
    }

    private function truncateImagesFromDb()
    {
        $this->em->getConnection()->executeStatement(
            'TRUNCATE TABLE ' . self::IMAGES_TABLE_NAME . ', ' . self::IMAGES_TRANSLATIONS_TABLE_NAME
        );
    }

    private function restartImagesIdsDbSequence()
    {
        $this->em->getConnection()->executeStatement(
            'SELECT SETVAL(pg_get_serial_sequence(\'images\', \'id\'), COALESCE((SELECT MAX(id) FROM images) + 1, 1), false)'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDependencies()
    {
        return [
            BrandDataFixture::class,
            CategoryDataFixture::class,
            PaymentDataFixture::class,
            TransportDataFixture::class,
            ProductDataFixture::class,
            SliderItemDataFixture::class,
        ];
    }
}
