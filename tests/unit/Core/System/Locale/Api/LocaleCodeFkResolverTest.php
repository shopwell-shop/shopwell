<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Locale\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Sync\FkReference;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\Api\LocaleCodeFkResolver;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LocaleCodeFkResolver::class)]
class LocaleCodeFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('locale.code', LocaleCodeFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'en-GB' => 'engb000000000000000000000000001',
                'zh-CN' => 'zhzh000000000000000000000000002',
            ]);

        $resolver = new LocaleCodeFkResolver($connection);

        $references = [
            new FkReference('ops/0/localeId', 'locale', 'code', 'en-GB', false),
            new FkReference('ops/1/localeId', 'locale', 'code', 'zh-CN', false),
            new FkReference('ops/2/localeId', 'locale', 'code', 'xx-XX', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('engb000000000000000000000000001', $result[0]->resolved);
        static::assertSame('zhzh000000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveAcceptsUnderscoreFormat(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::anything(),
                ['codes' => ['en-GB', 'zh-CN']],
                static::anything()
            )
            ->willReturn([
                'en-GB' => 'engb000000000000000000000000001',
                'zh-CN' => 'zhzh000000000000000000000000002',
            ]);

        $resolver = new LocaleCodeFkResolver($connection);

        $references = [
            new FkReference('ops/0/localeId', 'locale', 'code', 'en_GB', false),
            new FkReference('ops/1/localeId', 'locale', 'code', 'zh_CN', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('engb000000000000000000000000001', $result[0]->resolved);
        static::assertSame('zhzh000000000000000000000000002', $result[1]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new LocaleCodeFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
