<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\File\ResolvedUrl;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ResolvedUrl::class)]
class ResolvedUrlTest extends TestCase
{
    public function testItExposesHostAndIp(): void
    {
        $resolved = new ResolvedUrl('media.example.com', '93.184.216.34');

        static::assertSame('media.example.com', $resolved->host);
        static::assertSame('93.184.216.34', $resolved->ip);
    }
}
