<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\InstanceService;
use Shopwell\Core\Kernel;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InstanceService::class)]
class InstanceServiceTest extends TestCase
{
    public function testItReturnsInstanceIdIfNull(): void
    {
        $instanceService = new InstanceService(
            '6.4.0.0',
            null
        );

        static::assertNull($instanceService->getInstanceId());
    }

    public function testItReturnsInstanceIdIfSet(): void
    {
        $instanceService = new InstanceService(
            '6.4.0.0',
            'i-am-unique'
        );

        static::assertSame('i-am-unique', $instanceService->getInstanceId());
    }

    public function testItReturnsSpecificShopwellVersion(): void
    {
        $instanceService = new InstanceService(
            '6.1.0.0',
            null
        );

        static::assertSame('6.1.0.0', $instanceService->getShopwellVersion());
    }

    public function testItReturnsShopwellVersionStringIfVersionIsDeveloperVersion(): void
    {
        $instanceService = new InstanceService(
            Kernel::SHOPWARE_FALLBACK_VERSION,
            null
        );

        static::assertSame('___VERSION___', $instanceService->getShopwellVersion());
    }
}
