<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use OSS\Model\ObjectInfo;
use OSS\Model\ObjectListInfo;
use OSS\Model\PrefixInfo;
use OSS\OssClient;
use Shopwell\Core\Framework\Log\Package;

/**
 * Flysystem adapter for Alibaba Cloud OSS.
 *
 * OSS has no real directories, they are zero byte objects whose key ends in a slash. This adapter
 * creates them that way and reports them as directories when listing.
 *
 * @internal
 */
#[Package('framework')]
class AliyunOssAdapter implements FilesystemAdapter, TemporaryUrlGenerator
{
    /**
     * The OSS ACL that a write applies for a Flysystem visibility.
     *
     * @var array<string, string>
     */
    private const ACL_BY_VISIBILITY = [
        Visibility::PRIVATE => OssClient::OSS_ACL_TYPE_PRIVATE,
        Visibility::PUBLIC => OssClient::OSS_ACL_TYPE_PUBLIC_READ,
    ];

    /**
     * The Flysystem config options that are forwarded to a write request.
     *
     * @var list<string>
     */
    private const FORWARDED_OPTIONS = [
        OssClient::OSS_CACHE_CONTROL,
        OssClient::OSS_CONTENT_DISPOSTION,
        OssClient::OSS_EXPIRES,
    ];

    /**
     * The response headers of a head request that are exposed as extra metadata.
     *
     * @var list<string>
     */
    private const METADATA_HEADERS = [
        'etag',
        'x-oss-storage-class',
    ];

    /**
     * The maximum of objects that one list or delete request may cover, see the OSS API reference.
     */
    private const MAX_OBJECTS_PER_REQUEST = 1000;

    /**
     * The group an object ACL grants read access to when the object is public.
     */
    private const ALL_USERS_GROUP = 'AllUsers';

    /**
     * The ACL sub resource of an object, used to read the object ACL.
     */
    private const ACL_SUB_RESOURCE = 'acl';

    private PathPrefixer $prefixer;

    private MimeTypeDetector $mimeTypeDetector;

    /**
     * @param array<string, mixed> $options request options that every write carries
     */
    public function __construct(
        private readonly OssClient $client,
        private readonly string $bucket,
        string $root = '',
        ?MimeTypeDetector $mimeTypeDetector = null,
        private readonly array $options = [],
    ) {
        $this->prefixer = new PathPrefixer($root);
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            return (bool) $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixPath($path));
        } catch (\Throwable $exception) {
            throw UnableToCheckFileExistence::forLocation($path, $exception);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $listing = $this->listObjects($this->prefixer->prefixDirectoryPath($path), deep: false, maxKeys: 1);
        } catch (\Throwable $exception) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $exception);
        }

        if (!$listing instanceof ObjectListInfo) {
            return false;
        }

        return $listing->getObjectList() !== [] || $listing->getPrefixList() !== [];
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);

        try {
            $this->client->putObject($this->bucket, $key, $contents, $this->createUploadOptions($key, $contents, $config));
        } catch (\Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!\is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'The stream to write must be a resource.');
        }

        if (!$this->isSeekable($contents)) {
            // The length of a stream has to be known up front to upload it, a non seekable stream
            // cannot report it, so it is buffered and sent as a single request.
            $buffer = stream_get_contents($contents);

            if ($buffer === false) {
                throw UnableToWriteFile::atLocation($path, 'The stream to write could not be read.');
            }

            $this->write($path, $buffer, $config);

            return;
        }

        $key = $this->prefixer->prefixPath($path);
        $options = $this->createUploadOptions($key, $contents, $config);
        $length = $this->streamLength($contents);

        if ($length !== null) {
            $options[OssClient::OSS_CONTENT_LENGTH] = $length;
        }

        try {
            $this->client->uploadStream($this->bucket, $key, $contents, $options);
        } catch (\Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            $contents = $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path));
        } catch (\Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }

        if (!\is_string($contents)) {
            throw UnableToReadFile::fromLocation($path, 'The object returned no contents.');
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'rb+');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'A temporary stream could not be opened.');
        }

        try {
            $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path), [OssClient::OSS_FILE_DOWNLOAD => $stream]);
        } catch (\Throwable $exception) {
            fclose($stream);

            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }

        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $this->client->deleteObject($this->bucket, $this->prefixer->prefixPath($path));
        } catch (\Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = $this->prefixer->prefixDirectoryPath($path);

        try {
            foreach ($this->paginatedListing($prefix, deep: true) as $listing) {
                $keys = [];

                foreach ($listing->getObjectList() as $object) {
                    $keys[] = $object->getKey();
                }

                foreach (array_chunk($keys, self::MAX_OBJECTS_PER_REQUEST) as $chunk) {
                    $this->client->deleteObjects($this->bucket, $chunk);
                }
            }
        } catch (\Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $config = $config->withDefaults([
            Config::OPTION_VISIBILITY => $config->get(Config::OPTION_DIRECTORY_VISIBILITY, Visibility::PRIVATE),
        ]);

        try {
            $this->client->createObjectDir(
                $this->bucket,
                $this->prefixer->prefixPath(rtrim($path, '/')),
                $this->createOptions($config)
            );
        } catch (\Throwable $exception) {
            throw UnableToCreateDirectory::dueToFailure($path, $exception);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->putObjectAcl($this->bucket, $this->prefixer->prefixPath($path), $this->aclForVisibility($visibility));
        } catch (\Throwable $exception) {
            throw UnableToSetVisibility::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $visibility = $this->fetchObjectVisibility($this->prefixer->prefixPath($path));
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getMessage(), $exception);
        }

        if ($visibility === null) {
            throw UnableToRetrieveMetadata::visibility($path, 'The object ACL could not be read.');
        }

        return new FileAttributes($path, null, $visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);

        if ($attributes->mimeType() === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $attributes;
    }

    public function lastModified(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);

        if ($attributes->lastModified() === null) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return $attributes;
    }

    public function fileSize(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);

        if ($attributes->fileSize() === null) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return $attributes;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $normalizedPath = trim($path, '/');
        $prefix = trim($this->prefixer->prefixPath($normalizedPath), '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';

        try {
            foreach ($this->paginatedListing($prefix, $deep) as $listing) {
                foreach ($listing->getObjectList() as $object) {
                    $item = $this->mapObject($object);

                    if ($item->path() === $normalizedPath) {
                        continue;
                    }

                    yield $item;
                }

                foreach ($listing->getPrefixList() as $commonPrefix) {
                    yield $this->mapCommonPrefix($commonPrefix);
                }
            }
        } catch (\Throwable $exception) {
            throw UnableToListContents::atLocation($path, $deep, $exception);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }

        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }

        try {
            $this->client->copyObject(
                $this->bucket,
                $this->prefixer->prefixPath($source),
                $this->bucket,
                $this->prefixer->prefixPath($destination),
                $this->createOptions($config)
            );
        } catch (\Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        try {
            return $this->client->generatePresignedUrl(
                $this->bucket,
                $this->prefixer->prefixPath($path),
                $expiresAt->getTimestamp()
            );
        } catch (\Throwable $exception) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $exception);
        }
    }

    /**
     * @param string|resource $contents
     *
     * @return array<string, mixed>
     */
    private function createUploadOptions(string $key, $contents, Config $config): array
    {
        $options = $this->createOptions($config);

        if ($contents === '' || \array_key_exists(OssClient::OSS_CONTENT_TYPE, $options)) {
            return $options;
        }

        $mimeType = $this->mimeTypeDetector->detectMimeType($key, $contents);

        if ($mimeType !== null) {
            $options[OssClient::OSS_CONTENT_TYPE] = $mimeType;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function createOptions(Config $config): array
    {
        $options = $this->options;
        $configOptions = $config->toArray();

        foreach (self::FORWARDED_OPTIONS as $option) {
            if (\array_key_exists($option, $configOptions)) {
                $options[$option] = $configOptions[$option];
            }
        }

        $headers = $options[OssClient::OSS_HEADERS] ?? [];

        if (!\is_array($headers)) {
            $headers = [];
        }

        $headers[OssClient::OSS_OBJECT_ACL] = $this->aclForVisibility(
            (string) $config->get(Config::OPTION_VISIBILITY, Visibility::PRIVATE)
        );

        $options[OssClient::OSS_HEADERS] = $headers;

        return $options;
    }

    private function aclForVisibility(string $visibility): string
    {
        return self::ACL_BY_VISIBILITY[$visibility] ?? OssClient::OSS_ACL_TYPE_PRIVATE;
    }

    private function listObjects(string $prefix, bool $deep, string $marker = '', int $maxKeys = self::MAX_OBJECTS_PER_REQUEST): ?ObjectListInfo
    {
        return $this->client->listObjects($this->bucket, [
            OssClient::OSS_PREFIX => $prefix,
            OssClient::OSS_MARKER => $marker,
            OssClient::OSS_MAX_KEYS => $maxKeys,
            OssClient::OSS_DELIMITER => $deep ? '' : '/',
        ]);
    }

    /**
     * @return \Generator<int, ObjectListInfo>
     */
    private function paginatedListing(string $prefix, bool $deep): \Generator
    {
        $marker = '';

        while (true) {
            $listing = $this->listObjects($prefix, $deep, $marker);

            if (!$listing instanceof ObjectListInfo) {
                return;
            }

            yield $listing;

            $isTruncated = $listing->getIsTruncated();

            if (!\is_string($isTruncated) || $isTruncated !== 'true') {
                return;
            }

            $nextMarker = $this->nextMarker($listing, $marker);

            if ($nextMarker === null) {
                return;
            }

            $marker = $nextMarker;
        }
    }

    /**
     * The marker of the next page.
     *
     * OSS reports a next marker only when it grouped the listing by a delimiter, a listing without
     * one continues behind the last key that was returned.
     */
    private function nextMarker(ObjectListInfo $listing, string $marker): ?string
    {
        $nextMarker = $listing->getNextMarker();

        if ($nextMarker === '') {
            $objects = $listing->getObjectList();
            $lastObject = end($objects);
            $nextMarker = $lastObject === false ? '' : $lastObject->getKey();
        }

        if ($nextMarker === '' || $nextMarker === $marker) {
            return null;
        }

        return $nextMarker;
    }

    private function mapObject(ObjectInfo $object): StorageAttributes
    {
        $path = $this->prefixer->stripPrefix($object->getKey());

        if (str_ends_with($path, '/')) {
            return new DirectoryAttributes(rtrim($path, '/'));
        }

        $lastModified = strtotime($object->getLastModified());

        return new FileAttributes(
            $path,
            $object->getSize(),
            null,
            $lastModified === false ? null : $lastModified
        );
    }

    private function mapCommonPrefix(PrefixInfo $commonPrefix): DirectoryAttributes
    {
        return new DirectoryAttributes(rtrim($this->prefixer->stripPrefix($commonPrefix->getPrefix()), '/'));
    }

    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        try {
            $headers = $this->fetchObjectHeaders($path);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, $exception->getMessage(), $exception);
        }

        $size = $headers['content-length'] ?? null;
        $lastModified = $headers['last-modified'] ?? null;
        $timestamp = $lastModified === null ? false : strtotime($lastModified);

        $metadata = [];

        foreach (self::METADATA_HEADERS as $header) {
            if (isset($headers[$header])) {
                $metadata[$header] = $headers[$header];
            }
        }

        return new FileAttributes(
            $path,
            $size === null || !ctype_digit($size) ? null : (int) $size,
            null,
            $timestamp === false ? null : $timestamp,
            $headers['content-type'] ?? null,
            $metadata
        );
    }

    /**
     * @return array<string, string>
     */
    private function fetchObjectHeaders(string $path): array
    {
        $headers = $this->client->getObjectMeta($this->bucket, $this->prefixer->prefixPath($path));

        if (!\is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (\is_string($name) && \is_string($value)) {
                $normalized[strtolower($name)] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Reads the ACL of an object and maps it to a Flysystem visibility.
     *
     * The OSS head response carries no object ACL and the grant of an object is only part of the
     * response body of a request to the ACL sub resource, so it is requested and parsed here.
     */
    private function fetchObjectVisibility(string $key): ?string
    {
        $body = $this->client->getObject($this->bucket, $key, [OssClient::OSS_SUB_RESOURCE => self::ACL_SUB_RESOURCE]);

        if (!\is_string($body) || $body === '') {
            return null;
        }

        $document = new \DOMDocument();

        if (!@$document->loadXML($body)) {
            return null;
        }

        $grants = (new \DOMXPath($document))->query('//Grant[Grantee/ID[contains(., "' . self::ALL_USERS_GROUP . '")]]');

        if ($grants === false) {
            return null;
        }

        return $grants->length > 0 ? Visibility::PUBLIC : Visibility::PRIVATE;
    }

    /**
     * @param resource $stream
     */
    private function isSeekable($stream): bool
    {
        return stream_get_meta_data($stream)['seekable'];
    }

    /**
     * The number of bytes that are left in a stream, or null when it cannot be determined.
     *
     * @param resource $stream
     */
    private function streamLength($stream): ?int
    {
        $position = ftell($stream);

        if ($position === false || fseek($stream, 0, \SEEK_END) !== 0) {
            return null;
        }

        $end = ftell($stream);

        // The upload continues where it was left off, so the position is restored here.
        fseek($stream, $position, \SEEK_SET);

        if ($end === false || $end < $position) {
            return null;
        }

        return $end - $position;
    }
}
