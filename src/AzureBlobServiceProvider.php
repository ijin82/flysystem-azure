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

//            return new Filesystem(new AzureAdapter(
//                $blobService,
//                $config
//            ));

            $blobService = BlobRestProxy::createBlobService(
                $config['endpoint'],
                [] // $optionsWithMiddlewares
            );

            $adapter = new AzureAdapter(
                $blobService,
                $config['container'],
            );

            return new Filesystem($adapter);
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