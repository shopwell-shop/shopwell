<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Dictionary\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Sync\FkReference;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\Api\DictionaryEntryTechnicalNameFkResolver;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(DictionaryEntryTechnicalNameFkResolver::class)]
#[Group('dictionary')]
class DictionaryEntryTechnicalNameFkResolverTest extends TestCase
{
    public function testResolvesTechnicalNamesToIdsWithinSalutationType(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([
            'mr' => 'aaaa0000000000000000000000000001',
            'mrs' => 'aaaa0000000000000000000000000002',
        ]);

        $resolver = new DictionaryEntryTechnicalNameFkResolver($connection);

        $map = [
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', 'mr', false),
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', 'mrs', false),
        ];

        $resolved = $resolver->resolve($map);

        static::assertSame('aaaa0000000000000000000000000001', $resolved[0]->resolved);
        static::assertSame('aaaa0000000000000000000000000002', $resolved[1]->resolved);
    }

    public function testUnknownKeysRemainUnresolved(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([
            'mr' => 'aaaa0000000000000000000000000001',
        ]);

        $resolver = new DictionaryEntryTechnicalNameFkResolver($connection);

        $map = [
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', 'unknown_key', false),
        ];

        $resolved = $resolver->resolve($map);

        static::assertNull($resolved[0]->resolved);
    }

    public function testEmptyKeysAreNotQueried(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new DictionaryEntryTechnicalNameFkResolver($connection);

        $map = [
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', '', false),
        ];

        $resolved = $resolver->resolve($map);

        static::assertNull($resolved[0]->resolved);
    }

    public function testDuplicateKeysAreQueriedOnlyOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([]);

        $resolver = new DictionaryEntryTechnicalNameFkResolver($connection);

        $map = [
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', 'mr', false),
            new FkReference('customer.salutationEntryId', 'customer', 'salutationEntryId', 'mr', false),
        ];

        $resolver->resolve($map);
    }
}
