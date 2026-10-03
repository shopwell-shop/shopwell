<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Capability\AbstractSalesChannelTypeCapabilities;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractSalesChannelTypeCapabilities::class)]
class AbstractSalesChannelTypeCapabilitiesTest extends TestCase
{
    public function testATypeThatKeepsTheDefaultsIsNotDeclaredTransactional(): void
    {
        $capabilities = new class extends AbstractSalesChannelTypeCapabilities {
            public function getSalesChannelTypeId(): string
            {
                return Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON;
            }
        };

        static::assertFalse($capabilities->isTransactional());
    }
}
