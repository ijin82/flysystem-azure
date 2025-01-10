<?php

namespace Ijin82\Flysystem\Azure;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class AzureBlobServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('azure_blob', function ($app, $config) {

            $blobService = BlobRestProxy::createBlobService(
                $config['endpoint'],
                [] // $optionsWithMiddlewares
            );

            $adapter = new \League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter(
                $blobService,
                $config['container'],
            );

            $filesystem = new AzureFilesystem(
                $adapter,
                [
                    'visibility' => 'public',
                    'public_url' => ($config['blob_service_url'] . '/' . $config['container']),
                    'container' => $config['container']
                ]
            );

            return $filesystem;
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}