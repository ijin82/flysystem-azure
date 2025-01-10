<?php

declare(strict_types=1);

namespace Ijin82\Flysystem\Azure;
//namespace League\Flysystem;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathNormalizer;
use League\Flysystem\WhitespacePathNormalizer;
use League\Flysystem\Config;
use League\Flysystem\DirectoryListing;
use League\Flysystem\UnableToListContents;
use League\Flysystem\ResolveIdenticalPathConflict;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\InvalidStreamProvided;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\CalculateChecksumFromStream;
use DateTimeInterface;
use Generator;
use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\ShardedPrefixPublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Throwable;

use function array_key_exists;
use function is_array;

class AzureFilesystem implements FilesystemOperator
{
    use CalculateChecksumFromStream;

    private Config $config;
    private PathNormalizer $pathNormalizer;

    public function __construct(
        private FilesystemAdapter      $adapter,
        array                          $config = [],
        ?PathNormalizer                $pathNormalizer = null,
        private ?PublicUrlGenerator    $publicUrlGenerator = null,
        private ?TemporaryUrlGenerator $temporaryUrlGenerator = null,
    ) {
        $this->config = new Config($config);
        $this->pathNormalizer = $pathNormalizer ?? new WhitespacePathNormalizer();
    }

    public function url(string $path): string
    {
        return $this->publicUrl($path, []);
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        $contents = ((is_string($file) && file_exists($file)) ? file_get_contents($file) : $file->get());

        $config = $this->config->extend($options);

        $this->adapter->write(
            $path . '/' . $name,
            $contents,
            $config
        );
    }

    public function put($path, $contents, $options = [])
    {
        /**
         * todo: set up mimetype from options
         * Storage::disk('account_fonts')->put('font-' . $font->id . '.css', $content, [
         *   'mimetype' => '    text/css',
         * ]);
         */

        $config = $this->config->extend($options);

        return $this->adapter->write(
            $path,
            $contents,
            $config
        );
    }

    public function delete(string $location): void
    {
        $this->adapter->delete($location);
    }

    public function deleteDir($folder)
    {
        return $this->adapter->deleteDirectory($folder);
    }

    public function exists(string $location): bool
    {
        return $this->fileExists($location);
    }

    public function get(string $location): string
    {
        return $this->read($location);
    }

    // original Filesystem src

    public function fileExists(string $location): bool
    {
        return $this->adapter->fileExists($this->pathNormalizer->normalizePath($location));
    }

    public function directoryExists(string $location): bool
    {
        return $this->adapter->directoryExists($this->pathNormalizer->normalizePath($location));
    }

    public function has(string $location): bool
    {
        $path = $this->pathNormalizer->normalizePath($location);

        return $this->adapter->fileExists($path) || $this->adapter->directoryExists($path);
    }

    public function write(string $location, string $contents, array $config = []): void
    {
        $this->adapter->write(
            $this->pathNormalizer->normalizePath($location),
            $contents,
            $this->config->extend($config)
        );
    }

    public function writeStream(string $location, $contents, array $config = []): void
    {
        /* @var resource $contents */
        $this->assertIsResource($contents);
        $this->rewindStream($contents);
        $this->adapter->writeStream(
            $this->pathNormalizer->normalizePath($location),
            $contents,
            $this->config->extend($config)
        );
    }

    public function read(string $location): string
    {
        return $this->adapter->read($this->pathNormalizer->normalizePath($location));
    }

    public function readStream(string $location)
    {
        return $this->adapter->readStream($this->pathNormalizer->normalizePath($location));
    }

    // redeclared
//    public function delete(string $location): void
//    {
//        $this->adapter->delete($this->pathNormalizer->normalizePath($location));
//    }

    public function deleteDirectory(string $location): void
    {
        $this->adapter->deleteDirectory($this->pathNormalizer->normalizePath($location));
    }

    public function createDirectory(string $location, array $config = []): void
    {
        $this->adapter->createDirectory(
            $this->pathNormalizer->normalizePath($location),
            $this->config->extend($config)
        );
    }

    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        $path = $this->pathNormalizer->normalizePath($location);
        $listing = $this->adapter->listContents($path, $deep);

        return new DirectoryListing($this->pipeListing($location, $deep, $listing));
    }

    private function pipeListing(string $location, bool $deep, iterable $listing): Generator
    {
        try {
            foreach ($listing as $item) {
                yield $item;
            }
        } catch (Throwable $exception) {
            throw UnableToListContents::atLocation($location, $deep, $exception);
        }
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $config = $this->resolveConfigForMoveAndCopy($config);
        $from = $this->pathNormalizer->normalizePath($source);
        $to = $this->pathNormalizer->normalizePath($destination);

        if ($from === $to) {
            $resolutionStrategy = $config->get(Config::OPTION_MOVE_IDENTICAL_PATH, ResolveIdenticalPathConflict::TRY);

            if ($resolutionStrategy === ResolveIdenticalPathConflict::FAIL) {
                throw UnableToMoveFile::sourceAndDestinationAreTheSame($source, $destination);
            } elseif ($resolutionStrategy === ResolveIdenticalPathConflict::IGNORE) {
                return;
            }
        }

        $this->adapter->move($from, $to, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $config = $this->resolveConfigForMoveAndCopy($config);
        $from = $this->pathNormalizer->normalizePath($source);
        $to = $this->pathNormalizer->normalizePath($destination);

        if ($from === $to) {
            $resolutionStrategy = $config->get(Config::OPTION_COPY_IDENTICAL_PATH, ResolveIdenticalPathConflict::TRY);

            if ($resolutionStrategy === ResolveIdenticalPathConflict::FAIL) {
                throw UnableToCopyFile::sourceAndDestinationAreTheSame($source, $destination);
            } elseif ($resolutionStrategy === ResolveIdenticalPathConflict::IGNORE) {
                return;
            }
        }

        $this->adapter->copy($from, $to, $config);
    }

    public function lastModified(string $path): int
    {
        return $this->adapter->lastModified($this->pathNormalizer->normalizePath($path))->lastModified();
    }

    public function fileSize(string $path): int
    {
        return $this->adapter->fileSize($this->pathNormalizer->normalizePath($path))->fileSize();
    }

    public function mimeType(string $path): string
    {
        return $this->adapter->mimeType($this->pathNormalizer->normalizePath($path))->mimeType();
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($this->pathNormalizer->normalizePath($path), $visibility);
    }

    public function visibility(string $path): string
    {
        return $this->adapter->visibility($this->pathNormalizer->normalizePath($path))->visibility();
    }

    public function publicUrl(string $path, array $config = []): string
    {
        $this->publicUrlGenerator ??= $this->resolvePublicUrlGenerator()
            ?? throw UnableToGeneratePublicUrl::noGeneratorConfigured($path);
        $config = $this->config->extend($config);

        return $this->publicUrlGenerator->publicUrl(
            $this->pathNormalizer->normalizePath($path),
            $config,
        );
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []): string
    {
        $generator = $this->temporaryUrlGenerator ?? $this->adapter;

        if ($generator instanceof TemporaryUrlGenerator) {
            return $generator->temporaryUrl(
                $this->pathNormalizer->normalizePath($path),
                $expiresAt,
                $this->config->extend($config)
            );
        }

        throw UnableToGenerateTemporaryUrl::noGeneratorConfigured($path);
    }

    public function checksum(string $path, array $config = []): string
    {
        $config = $this->config->extend($config);

        if (!$this->adapter instanceof ChecksumProvider) {
            return $this->calculateChecksumFromStream($path, $config);
        }

        try {
            return $this->adapter->checksum(
                $this->pathNormalizer->normalizePath($path),
                $config,
            );
        } catch (ChecksumAlgoIsNotSupported) {
            return $this->calculateChecksumFromStream(
                $this->pathNormalizer->normalizePath($path),
                $config,
            );
        }
    }

    private function resolvePublicUrlGenerator(): ?PublicUrlGenerator
    {
        if ($publicUrl = $this->config->get('public_url')) {
            return match (true) {
                is_array($publicUrl) => new ShardedPrefixPublicUrlGenerator($publicUrl),
                default => new PrefixPublicUrlGenerator($publicUrl),
            };
        }

        if ($this->adapter instanceof PublicUrlGenerator) {
            return $this->adapter;
        }

        return null;
    }

    /**
     * @param mixed $contents
     */
    private function assertIsResource($contents): void
    {
        if (is_resource($contents) === false) {
            throw new InvalidStreamProvided(
                "Invalid stream provided, expected stream resource, received " . gettype($contents)
            );
        } elseif ($type = get_resource_type($contents) !== 'stream') {
            throw new InvalidStreamProvided(
                "Invalid stream provided, expected stream resource, received resource of type " . $type
            );
        }
    }

    /**
     * @param resource $resource
     */
    private function rewindStream($resource): void
    {
        if (ftell($resource) !== 0 && stream_get_meta_data($resource)['seekable']) {
            rewind($resource);
        }
    }

    private function resolveConfigForMoveAndCopy(array $config): Config
    {
        $retainVisibility = $this->config->get(Config::OPTION_RETAIN_VISIBILITY, $config[Config::OPTION_RETAIN_VISIBILITY] ?? true);
        $fullConfig = $this->config->extend($config);

        /*
         * By default, we retain visibility. When we do not retain visibility, the visibility setting
         * from the default configuration is ignored. Only when it is set explicitly, we propagate the
         * setting.
         */
        if ($retainVisibility && !array_key_exists(Config::OPTION_VISIBILITY, $config)) {
            $fullConfig = $fullConfig->withoutSettings(Config::OPTION_VISIBILITY)->extend($config);
        }

        return $fullConfig;
    }
}
