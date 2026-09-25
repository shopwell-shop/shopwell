<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlPurifierConfigProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HtmlPurifierConfigProvider::class)]
class HtmlPurifierConfigProviderTest extends TestCase
{
    #[TestDox('Returns a fresh instance on each call so callers cannot share mutated state')]
    public function testReturnsFreshInstanceOnEachCall(): void
    {
        $provider = new HtmlPurifierConfigProvider();

        static::assertNotSame($provider->getConfig(), $provider->getConfig());
    }
}
