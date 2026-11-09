<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\EncodedContentPage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EncodedContentPage::class)]
class EncodedContentPageTest extends TestCase
{
    public function testJsonEncodingPreservesNestedBodyWithoutStructMetadata(): void
    {
        $page = new EncodedContentPage(['tree' => [['id' => 'root', 'value' => null]], 'total' => 0], 'content_full_page');

        static::assertSame('{"tree":[{"id":"root","value":null}],"total":0}', json_encode($page, \JSON_THROW_ON_ERROR));
        static::assertSame('content_full_page', $page->getApiAlias());
    }

    public function testEmptyBodyUsesFallbackAlias(): void
    {
        $page = new EncodedContentPage([]);

        static::assertSame([], $page->jsonSerialize());
        static::assertSame('content_encoded_page', $page->getApiAlias());
    }

    public function testAliasDiscoveryWithoutConstructionUsesFallback(): void
    {
        $page = EncodedContentPage::createFrom(new class extends Struct {});

        static::assertSame('content_encoded_page', $page->getApiAlias());
    }
}
