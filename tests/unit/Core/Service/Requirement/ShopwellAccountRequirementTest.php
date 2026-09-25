<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Requirement\Gate;
use Shopwell\Core\Service\Requirement\ShopwellAccountRequirement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellAccountRequirement::class)]
class ShopwellAccountRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('shopwell_account', ShopwellAccountRequirement::getName());
    }

    public function testGatesPrivileges(): void
    {
        static::assertSame(Gate::PRIVILEGES, (new ShopwellAccountRequirement(static::createStub(Connection::class)))->getGate());
    }

    public function testDispermitsStateChange(): void
    {
        static::assertFalse((new ShopwellAccountRequirement(static::createStub(Connection::class)))->permitsStateChange());
    }

    public function testIsSatisfiedWhenUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn('1');

        $requirement = new ShopwellAccountRequirement($connection);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenNoUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn(false);

        $requirement = new ShopwellAccountRequirement($connection);

        static::assertFalse($requirement->isSatisfied());
    }
}
