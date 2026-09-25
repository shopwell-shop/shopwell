<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Media\SalesChannel\MediaRouteResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaRouteResponse::class)]
class MediaRouteResponseTest extends TestCase
{
    public function testMediaRouterIsCorrectlyConstructed(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('testMediaId');
        $mediaEntity->setPath('testPath');

        $mediaCollection = new MediaCollection();
        $mediaCollection->add($mediaEntity);

        $mediaRouteResponse = new MediaRouteResponse($mediaCollection);

        static::assertSame($mediaCollection, $mediaRouteResponse->getMediaCollection());
    }
}
