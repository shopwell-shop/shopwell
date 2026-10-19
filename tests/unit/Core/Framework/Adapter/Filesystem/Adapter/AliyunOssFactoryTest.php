<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AliyunOssAdapter;
use Shopwell\Core\Framework\Adapter\Filesystem\Adapter\AliyunOssFactory;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AliyunOssFactory::class)]
class AliyunOssFactoryTest extends TestCase
{
    public function testGetType(): void
    {
        static::assertSame('aliyun-oss', (new AliyunOssFactory())->getType());
    }

    public function testCreateReturnsAnOssAdapter(): void
    {
        $adapter = (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
            'root' => 'media',
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
        ]);

        static::assertInstanceOf(AliyunOssAdapter::class, $adapter);
    }

    public function testCreateWithoutCredentialsFallsBackToTheOssEnvironmentVariables(): void
    {
        $adapter = (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
        ]);

        static::assertInstanceOf(AliyunOssAdapter::class, $adapter);
    }

    public function testCreateSignsTemporaryUrlsForTheConfiguredBucket(): void
    {
        $adapter = (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
            'root' => 'media',
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
        ]);
        static::assertInstanceOf(AliyunOssAdapter::class, $adapter);

        $url = $adapter->temporaryUrl(
            'image.png',
            new \DateTimeImmutable('2026-10-15 18:00:00', new \DateTimeZone('UTC')),
            new Config()
        );

        static::assertStringStartsWith('https://test-bucket.oss-cn-hangzhou.aliyuncs.com/media/image.png?', $url);
    }

    public function testCreateWithCnameEndpointKeepsTheBucketOutOfTheHost(): void
    {
        $adapter = (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
            'endpoint' => 'cdn.example.com',
            'is_cname' => true,
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
        ]);
        static::assertInstanceOf(AliyunOssAdapter::class, $adapter);

        $url = $adapter->temporaryUrl(
            'image.png',
            new \DateTimeImmutable('2026-10-15 18:00:00', new \DateTimeZone('UTC')),
            new Config()
        );

        static::assertStringStartsWith('https://cdn.example.com/image.png?', $url);
    }

    public function testCreateKeepsTheSchemeOfTheEndpoint(): void
    {
        $adapter = (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
            'endpoint' => 'http://oss-cn-hangzhou.aliyuncs.com',
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
        ]);
        static::assertInstanceOf(AliyunOssAdapter::class, $adapter);

        $url = $adapter->temporaryUrl(
            'image.png',
            new \DateTimeImmutable('2026-10-15 18:00:00', new \DateTimeZone('UTC')),
            new Config()
        );

        static::assertStringStartsWith('http://test-bucket.oss-cn-hangzhou.aliyuncs.com/image.png?', $url);
    }

    public function testCreateRequiresABucket(): void
    {
        static::expectExceptionObject(new MissingOptionsException('The required option "bucket" is missing.'));

        (new AliyunOssFactory())->create([
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
        ]);
    }

    public function testCreateRequiresAnEndpoint(): void
    {
        static::expectExceptionObject(new MissingOptionsException('The required option "endpoint" is missing.'));

        (new AliyunOssFactory())->create([
            'bucket' => 'test-bucket',
        ]);
    }
}
