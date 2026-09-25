<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cms\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\SalesChannel\CmsRouteResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsRouteResponse::class)]
class CmsRouteResponseTest extends TestCase
{
    public function testGetCmsPage(): void
    {
        $expected = new CmsPageEntity();
        $response = new CmsRouteResponse($expected);

        $actual = $response->getCmsPage();
        static::assertSame($expected, $actual);
    }
}
