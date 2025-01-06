<?php

namespace Ijin82\Flysystem\Azure;

use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\PathPrefixer;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Internal\StorageServiceSettings;

class AzureAdapter extends AzureBlobStorageAdapter
{
    public function __construct(
        private BlobRestProxy $client,
        private string $container,
        string $prefix = '',
        ?MimeTypeDetector $mimeTypeDetector = null,
        private int $maxResultsForContentsListing = 5000,
        private string $visibilityHandling = self::ON_VISIBILITY_THROW_ERROR,
        private ?StorageServiceSettings $serviceSettings = null,
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function getUrl($path)
    {
        //return $this->fsConfig['blob_service_url'] . '/' . $this->container . '/' . $file;

        $location = $this->prefixer->prefixPath($path);

        return $this->client->getBlobUrl($this->container, $location);
    }

    /**
     * Upload a file.
     *
     * @param string           $path     Path
     * @param string|resource  $contents Either a string or a stream.
     * @param Config           $config   Config
     *
     * @return array
     */
//    protected function upload($path, $contents, Config $config)
//    {
//        $path = $this->applyPathPrefix($path);
//
//        /** @var CopyBlobResult $result */
//        $result = $this->client->createBlockBlob(
//            $this->container,
//            $path,
//            $contents,
//            $this->getOptionsFromConfig($config)
//        );
//
//        return $this->normalize($path, $result->getLastModified()->format('U'), $contents);
//    }
}
