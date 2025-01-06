[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://poser.pugx.org/ijin82/flysystem-azure/downloads)](https://packagist.org/packages/ijin82/flysystem-azure)

# DEV-MASTER now  

# Azure Blob custom filesystem for Laravel 5-10
This repo is fork of [League\Flysystem\Azure](https://github.com/thephpleague/flysystem-azure)

# Why forked?
Need to integrate with L5 out of the box, and **url** method for **Storage** interface  
All examples below L5 related.  
**Add update to L10 desk** 

# How to install in Laravel 5 application

Install package
```bash
composer require ijin82/flysystem-azure
```

Open **config/app.php** and add this to providers section
```
Ijin82\Flysystem\Azure\AzureBlobServiceProvider::class,
```

Open **config/filesystems.php** and add this stuff to disks section
```
'my_azure_disk1' => [
    'driver' => 'azure_blob',
    'endpoint' => env('AZURE_BLOB_STORAGE_ENDPOINT'),
    'container' => env('AZURE_BLOB_STORAGE_CONTAINER1'),
    'blob_service_url' => env('AZURE_BLOB_SERVICE_URL'),
],
```

Open your **.env** and add variables for your disk
```
AZURE_BLOB_SERVICE_URL={your-blob-service-url}
AZURE_BLOB_STORAGE_ENDPOINT="DefaultEndpointsProtocol=https;AccountName={your-account-name};AccountKey={your-account-key};"
AZURE_BLOB_STORAGE_CONTAINER1={your-container-name}
```
1. You can get **AZURE_BLOB_SERVICE_URL** variable from **Properties** section of your Storage account settings.
That is an url named *PRIMARY BLOB SERVICE ENDPOINT* or *SECONDARY BLOB SERVICE ENDPOINT*
1. You can get **AZURE_BLOB_STORAGE_ENDPOINT** variable from **Access keys** section of your Storage account settings.
That is named *CONNECTION STRING*
1. **AZURE_BLOB_STORAGE_CONTAINER1** is the name of your pre-created container, that you can add at **Overview** 
section of your Storage account settings.

# How to upload file
```php
public function someUploadFuncName(Request $request)
{
    $file = $request->file('file_name_from_request');  
    
    // .. file name logic
    // .. file folder logic
    
    $file->storeAs($fileFolder, $fileName, [
        'disk' => 'my_azure_disk1'
    ]);  
    
    // save file name logic
    // to create file URL by name later
    // maybe you want to save file name and folder separated
    $fileNameToSave = $folderName . '/' . $diskFileName;
    
    // .. save file name to DB or etc.
}
```

# How to get file URL

We got file name for selected disk (folder related if folder exists)
```php
echo Storage::disk('my_azure_disk1')->url($fileName);
```
That is also working in blade templates like this
```
<a href="{{ Storage::disk('my_azure_disk1')->url($fileName) }}"
    target="_blank">{{ $fileName }}</a>
```

# How to delete file 
```php
public function someDeleteFuncName($id)
{
    $file = SomeFileModel::findOrFail($id);
    Storage::disk('my_azure_disk1')->delete($file->name);
    $file->delete();

    // go back or etc..
}
```
# Mimetypes (this can be useful)
Sometimes you need to set up mime types manually (for CDN maybe) to get back correct mime type values. You can do that like this (couple types forced for example):
```php
$fileConents = Storage::disk('public_or_another_local_disk')->get($file);

$forcedMimes = [
    'js' => 'application/javascript',
    'json' => 'application/json',
];

$fileExt = \File::extension($file);

if (array_key_exists($fileExt, $forcedMimes)) {
    $fileMime = $forcedMimes[$fileExt];
} else {
    $fileMime = mime_content_type(Storage::disk('public_or_another_local_disk')->path($file));
}

Storage::disk('my_custom_azure_disk')->put($fileName, $fileConents, [
    'mimetype' => $fileMime,
]);
```
You can use wget to get response with headers including *Content-Type*
```
wget -S https://your-file-host.com/file-name.jpg
```

# Additions
1. Original repo is [here](https://github.com/thephpleague/flysystem-azure)
2. [How to use blob storage from PHP](https://docs.microsoft.com/en-us/azure/storage/storage-php-how-to-use-blobs)
3. [Flysystem azure adapter](http://flysystem.thephpleague.com/adapter/azure/)
4. Feel free to send pull requests and issues.
