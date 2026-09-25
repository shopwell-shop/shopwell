<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Context\ShopApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiAware::class)]
class ApiAwareTest extends TestCase
{
    public function testDefaultAllowsBothApis(): void
    {
        $flag = new ApiAware();

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/store-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ShopApiSource::class));
        static::assertTrue($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyAdminApiAware(): void
    {
        $flag = new ApiAware(AdminApiSource::class);

        static::assertTrue($flag->isBaseUrlAllowed('/api'));
        static::assertFalse($flag->isBaseUrlAllowed('/store-api'));

        static::assertTrue($flag->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertFalse($flag->isSourceAllowed(ShopApiSource::class));
        static::assertFalse($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }

    public function testOnlyStoreApiAware(): void
    {
        $flag = new ApiAware(SalesChannelApiSource::class);

        static::assertFalse($flag->isBaseUrlAllowed('/api'));
        static::assertTrue($flag->isBaseUrlAllowed('/store-api'));

        static::assertFalse($flag->isSourceAllowed(AdminApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(ShopApiSource::class));
        static::assertTrue($flag->isSourceAllowed(AdminSalesChannelApiSource::class));
        static::assertTrue($flag->isSourceAllowed(SystemSource::class));
    }
}
