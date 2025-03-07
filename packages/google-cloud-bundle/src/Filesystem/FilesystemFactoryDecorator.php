<?php

namespace Shopsys\GoogleCloudBundle\Filesystem;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use Shopsys\FrameworkBundle\Component\Filesystem\FilesystemFactoryInterface;

class FilesystemFactoryDecorator implements FilesystemFactoryInterface
{
    /**
     * @var \Shopsys\FrameworkBundle\Component\Filesystem\FilesystemFactoryInterface
     */
    private $inner;

    /**
     * @var string
     */
    private $googleCloudProjectId;

    /**
     * @var string
     */
    private $googleCloudStorageBucketName;

    /**
     * @param \Shopsys\FrameworkBundle\Component\Filesystem\FilesystemFactoryInterface $inner
     * @param string $googleCloudProjectId
     * @param string $googleCloudStorageBucketName
     */
    public function __construct(
        FilesystemFactoryInterface $inner,
        string $googleCloudProjectId,
        string $googleCloudStorageBucketName
    ) {
        $this->inner = $inner;
        $this->googleCloudProjectId = $googleCloudProjectId;
        $this->googleCloudStorageBucketName = $googleCloudStorageBucketName;
    }

    /**
     * @return \League\Flysystem\FilesystemOperator
     */
    public function create(): FilesystemOperator
    {
        if ($this->googleCloudStorageBucketName !== '') {
            $storageClient = new StorageClient(['projectId' => $this->googleCloudProjectId]);
            $bucket = $storageClient->bucket($this->googleCloudStorageBucketName);
            $adapter = new GoogleCloudStorageAdapter($bucket);

            return new Filesystem($adapter);
        }

        return $this->inner->create();
    }
}
