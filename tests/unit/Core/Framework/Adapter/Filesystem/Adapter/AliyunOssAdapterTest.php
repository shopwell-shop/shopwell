<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\Visibility;
use OSS\Model\ObjectInfo;
use OSS\Model\ObjectListInfo;
use OSS\Model\PrefixInfo;
use OSS\OssClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AliyunOssAdapter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunOssAdapter::class)]
class AliyunOssAdapterTest extends TestCase
{
    /**
     * The value the OSS head response carries for the last modified date of an object.
     */
    private const LAST_MODIFIED = 'Thu, 15 Oct 2026 08:00:00 GMT';

    public function testWriteUploadsTheObjectBelowTheRoot(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('putObject')
            ->willReturnCallback(static function (string $bucket, string $key, string $content, array $options): array {
                static::assertSame('test-bucket', $bucket);
                static::assertSame('media/image.png', $key);
                static::assertSame('the image', $content);
                static::assertSame('image/png', $options[OssClient::OSS_CONTENT_TYPE]);
                static::assertSame('max-age=60', $options[OssClient::OSS_CACHE_CONTROL]);
                static::assertSame(
                    [OssClient::OSS_OBJECT_ACL => OssClient::OSS_ACL_TYPE_PUBLIC_READ],
                    $options[OssClient::OSS_HEADERS]
                );

                return [];
            });

        $this->createAdapter($client)->write('image.png', 'the image', new Config([
            Config::OPTION_VISIBILITY => Visibility::PUBLIC,
            OssClient::OSS_CACHE_CONTROL => 'max-age=60',
        ]));
    }

    public function testWriteStreamUploadsTheBytesThatAreLeftInTheStream(): void
    {
        $stream = fopen('php://temp', 'rb+');
        static::assertIsResource($stream);
        fwrite($stream, 'contents');
        fseek($stream, 2);

        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('uploadStream')
            ->willReturnCallback(static function (string $bucket, string $key, $handle, array $options) use ($stream): array {
                static::assertSame('media/image.png', $key);
                static::assertSame($stream, $handle);
                static::assertSame('image/png', $options[OssClient::OSS_CONTENT_TYPE]);
                static::assertSame(6, $options[OssClient::OSS_CONTENT_LENGTH]);

                return [];
            });

        $this->createAdapter($client)->writeStream('image.png', $stream, new Config());
    }

    public function testWriteStreamBuffersAStreamThatCannotBeSeeked(): void
    {
        $sockets = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        static::assertIsArray($sockets);
        [$stream, $writer] = $sockets;
        fwrite($writer, 'contents');
        fclose($writer);

        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->never())
            ->method('uploadStream');

        $client
            ->expects($this->once())
            ->method('putObject')
            ->willReturnCallback(static function (string $bucket, string $key, string $content): array {
                static::assertSame('contents', $content);

                return [];
            });

        $this->createAdapter($client)->writeStream('image.png', $stream, new Config());
    }

    public function testFileExistsAsksTheBucketForTheObject(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('doesObjectExist')
            ->with('test-bucket', 'media/image.png')
            ->willReturn(true);

        static::assertTrue($this->createAdapter($client)->fileExists('image.png'));
    }

    public function testReadReturnsTheObjectContents(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('getObject')
            ->with('test-bucket', 'media/image.png')
            ->willReturn('the image');

        static::assertSame('the image', $this->createAdapter($client)->read('image.png'));
    }

    public function testReadStreamDownloadsTheObjectIntoAStream(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('getObject')
            ->willReturnCallback(static function (string $bucket, string $key, array $options): string {
                $stream = $options[OssClient::OSS_FILE_DOWNLOAD] ?? null;
                static::assertIsResource($stream);

                fwrite($stream, 'the image');

                return '';
            });

        $stream = $this->createAdapter($client)->readStream('image.png');
        static::assertIsResource($stream);

        static::assertSame('the image', stream_get_contents($stream));
        fclose($stream);
    }

    public function testFileSizeAndExtraMetadataAreReadFromTheHeadResponse(): void
    {
        $client = static::createStub(OssClient::class);
        $client
            ->method('getObjectMeta')
            ->willReturn([
                'Content-Type' => 'image/png',
                'Content-Length' => '4096',
                'Last-Modified' => self::LAST_MODIFIED,
                'ETag' => '"etag"',
                'x-oss-storage-class' => 'IA',
            ]);

        $attributes = $this->createAdapter($client)->fileSize('image.png');

        static::assertSame(4096, $attributes->fileSize());
        static::assertSame(
            ['etag' => '"etag"', 'x-oss-storage-class' => 'IA'],
            $attributes->extraMetadata()
        );
    }

    public function testMimeTypeIsReadFromTheHeadResponse(): void
    {
        $client = static::createStub(OssClient::class);
        $client
            ->method('getObjectMeta')
            ->willReturn(['Content-Type' => 'image/png']);

        static::assertSame('image/png', $this->createAdapter($client)->mimeType('image.png')->mimeType());
    }

    public function testLastModifiedIsReadFromTheHeadResponse(): void
    {
        $client = static::createStub(OssClient::class);
        $client
            ->method('getObjectMeta')
            ->willReturn(['Last-Modified' => self::LAST_MODIFIED]);

        static::assertSame(
            strtotime(self::LAST_MODIFIED),
            $this->createAdapter($client)->lastModified('image.png')->lastModified()
        );
    }

    public function testMimeTypeIsMissingWhenTheHeadResponseHasNoContentType(): void
    {
        $client = static::createStub(OssClient::class);
        $client
            ->method('getObjectMeta')
            ->willReturn(['Content-Length' => '4096']);

        static::expectExceptionObject(UnableToRetrieveMetadata::mimeType('image.png'));

        $this->createAdapter($client)->mimeType('image.png');
    }

    public function testVisibilityReportsAPublicObject(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('getObject')
            ->with('test-bucket', 'media/image.png', [OssClient::OSS_SUB_RESOURCE => 'acl'])
            ->willReturn($this->createAclResponse('http://acs.aliyun-inc.com/groups/global/AllUsers'));

        static::assertSame(Visibility::PUBLIC, $this->createAdapter($client)->visibility('image.png')->visibility());
    }

    public function testVisibilityReportsAPrivateObject(): void
    {
        $client = static::createStub(OssClient::class);
        $client
            ->method('getObject')
            ->willReturn($this->createAclResponse('uid-123456'));

        static::assertSame(Visibility::PRIVATE, $this->createAdapter($client)->visibility('image.png')->visibility());
    }

    public function testSetVisibilityWritesTheObjectAcl(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('putObjectAcl')
            ->with('test-bucket', 'media/image.png', OssClient::OSS_ACL_TYPE_PUBLIC_READ)
            ->willReturn(null);

        $this->createAdapter($client)->setVisibility('image.png', Visibility::PUBLIC);
    }

    public function testListContentsMapsObjectsAndDirectories(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', [
                OssClient::OSS_PREFIX => 'media/',
                OssClient::OSS_MARKER => '',
                OssClient::OSS_MAX_KEYS => 1000,
                OssClient::OSS_DELIMITER => '/',
            ])
            ->willReturn($this->createListing('media/', [
                new ObjectInfo('media/', self::LAST_MODIFIED, '"dir"', 'Normal', '0', 'Standard'),
                new ObjectInfo('media/image.png', self::LAST_MODIFIED, '"etag"', 'Normal', '4096', 'Standard'),
            ], [
                new PrefixInfo('media/thumbnails/'),
            ]));

        /** @var iterable<StorageAttributes> $items */
        $items = $this->createAdapter($client)->listContents('', false);

        $contents = [];

        foreach ($items as $item) {
            $contents[] = $item;
        }

        static::assertCount(2, $contents);

        $file = $contents[0];
        static::assertInstanceOf(FileAttributes::class, $file);
        static::assertSame('image.png', $file->path());
        static::assertSame(4096, $file->fileSize());
        static::assertSame(strtotime(self::LAST_MODIFIED), $file->lastModified());

        $directory = $contents[1];
        static::assertInstanceOf(DirectoryAttributes::class, $directory);
        static::assertSame('thumbnails', $directory->path());
    }

    public function testListContentsFollowsThePaginationOfTheBucket(): void
    {
        $markers = [];

        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->exactly(3))
            ->method('listObjects')
            ->willReturnCallback(function (string $bucket, array $options) use (&$markers): ObjectListInfo {
                $markers[] = $options[OssClient::OSS_MARKER];

                return match (\count($markers)) {
                    // OSS only reports a next marker for a listing that was grouped by a delimiter,
                    // a deep listing continues behind the last key that was returned.
                    1 => $this->createTruncatedListing('media/', [new ObjectInfo('media/a.png', self::LAST_MODIFIED, '"etag"', 'Normal', '1', 'Standard')], ''),
                    2 => $this->createTruncatedListing('media/', [new ObjectInfo('media/b.png', self::LAST_MODIFIED, '"etag"', 'Normal', '1', 'Standard')], 'media/b.png'),
                    default => $this->createListing('media/', [new ObjectInfo('media/c.png', self::LAST_MODIFIED, '"etag"', 'Normal', '1', 'Standard')]),
                };
            });

        /** @var iterable<StorageAttributes> $items */
        $items = $this->createAdapter($client)->listContents('', true);

        $paths = [];

        foreach ($items as $item) {
            $paths[] = $item->path();
        }

        static::assertSame(['', 'media/a.png', 'media/b.png'], $markers);
        static::assertSame(['a.png', 'b.png', 'c.png'], $paths);
    }

    public function testDirectoryExistsIsFalseForAnEmptyPrefix(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('listObjects')
            ->with('test-bucket', [
                OssClient::OSS_PREFIX => 'media/thumbnails/',
                OssClient::OSS_MARKER => '',
                OssClient::OSS_MAX_KEYS => 1,
                OssClient::OSS_DELIMITER => '/',
            ])
            ->willReturn($this->createListing('media/thumbnails/', [], [], '', '1'));

        static::assertFalse($this->createAdapter($client)->directoryExists('thumbnails'));
    }

    public function testCreateDirectoryCreatesADirectoryMarker(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('createObjectDir')
            ->with('test-bucket', 'media/thumbnails', [
                OssClient::OSS_HEADERS => [OssClient::OSS_OBJECT_ACL => OssClient::OSS_ACL_TYPE_PUBLIC_READ],
            ])
            ->willReturn(null);

        $this->createAdapter($client)->createDirectory('thumbnails', new Config([
            Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC,
        ]));
    }

    public function testDeleteDirectoryDeletesEveryObjectBelowThePrefix(): void
    {
        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('listObjects')
            ->willReturn($this->createListing('media/thumbnails/', [
                new ObjectInfo('media/thumbnails/small.png', self::LAST_MODIFIED, '"etag"', 'Normal', '1024', 'Standard'),
                new ObjectInfo('media/thumbnails/large.png', self::LAST_MODIFIED, '"etag"', 'Normal', '2048', 'Standard'),
            ]));

        $client
            ->expects($this->once())
            ->method('deleteObjects')
            ->with('test-bucket', ['media/thumbnails/small.png', 'media/thumbnails/large.png'])
            ->willReturn([]);

        $this->createAdapter($client)->deleteDirectory('thumbnails');
    }

    public function testTemporaryUrlSignsThePathBelowTheRoot(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-10-15 18:00:00', new \DateTimeZone('UTC'));

        $client = $this->createMock(OssClient::class);
        $client
            ->expects($this->once())
            ->method('generatePresignedUrl')
            ->with('test-bucket', 'media/image.png', $expiresAt->getTimestamp())
            ->willReturn('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/media/image.png?Signature=signature');

        static::assertSame(
            'https://test-bucket.oss-cn-hangzhou.aliyuncs.com/media/image.png?Signature=signature',
            $this->createAdapter($client)->temporaryUrl('image.png', $expiresAt, new Config())
        );
    }

    private function createAdapter(OssClient $client, string $root = 'media'): AliyunOssAdapter
    {
        return new AliyunOssAdapter($client, 'test-bucket', $root);
    }

    /**
     * A listing that is not truncated, so a request does not have to be repeated.
     *
     * @param list<ObjectInfo> $objects
     * @param list<PrefixInfo> $prefixes
     */
    private function createListing(
        string $prefix,
        array $objects,
        array $prefixes = [],
        string $nextMarker = '',
        string $maxKeys = '1000',
    ): ObjectListInfo {
        return new ObjectListInfo('test-bucket', $prefix, '', $nextMarker, $maxKeys, '/', null, $objects, $prefixes);
    }

    /**
     * A listing that reports itself as truncated.
     *
     * The constructor of the SDK documents the truncated flag as null although a real response
     * carries the string "true" or "false" there, see OSS\Result\ListObjectsResult.
     *
     * @param list<ObjectInfo> $objects
     */
    private function createTruncatedListing(string $prefix, array $objects, string $nextMarker): ObjectListInfo
    {
        return new class($prefix, $objects, $nextMarker) extends ObjectListInfo {
            /**
             * @param list<ObjectInfo> $objects
             */
            public function __construct(string $prefix, array $objects, string $nextMarker)
            {
                parent::__construct('test-bucket', $prefix, '', $nextMarker, '1000', '/', null, $objects, []);
            }

            public function getIsTruncated(): string
            {
                return 'true';
            }
        };
    }

    private function createAclResponse(string $granteeId): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<AccessControlPolicy>
    <Owner>
        <ID>uid-123456</ID>
        <DisplayName>owner</DisplayName>
    </Owner>
    <AccessControlList>
        <Grant>
            <Grantee xsi:type="Group" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                <ID>' . $granteeId . '</ID>
            </Grantee>
            <Permission>Read</Permission>
        </Grant>
    </AccessControlList>
</AccessControlPolicy>';
    }
}
