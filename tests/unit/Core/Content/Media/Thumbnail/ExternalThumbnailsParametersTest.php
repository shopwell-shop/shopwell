<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailsParameters;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalThumbnailsParameters::class)]
class ExternalThumbnailsParametersTest extends TestCase
{
    public function testDefaultsToEmptyCollection(): void
    {
        $params = new ExternalThumbnailsParameters();

        static::assertCount(0, $params->thumbnails);
    }

    public function testConstructWithThumbnails(): void
    {
        $thumbnail = new ExternalThumbnailData('http://localhost:8000/thumb-200.jpg', 200, 200);
        $collection = new ExternalThumbnailCollection([$thumbnail]);

        $params = new ExternalThumbnailsParameters($collection);

        static::assertCount(1, $params->thumbnails);
        static::assertSame($thumbnail, $params->thumbnails->first());
    }
}
