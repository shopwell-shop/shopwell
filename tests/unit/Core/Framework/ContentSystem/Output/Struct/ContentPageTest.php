<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Output\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPage::class)]
class ContentPageTest extends TestCase
{
    public function testFindsTheFirstMatchingComponentAcrossNestedSlots(): void
    {
        $listing = new RenderedElement('listing', 'Sw:Product:Listing');
        $container = new RenderedElement('container', 'Sw:Grid:Container', slots: ['content' => [$listing]]);
        $page = ContentPage::fromRenderResult(new RenderResult(
            [$container, new RenderedElement('second-listing', 'Sw:Product:Listing')],
            LayoutReference::create('layout', 'Category', null),
            null,
        ));

        static::assertSame($listing, $page->findFirstElement('Sw:Product:Listing'));
        static::assertSame($container, $page->findFirstElement('Sw:Grid:Container'));
        static::assertSame([$container, $page->elements[1]], $page->elements);
    }

    public function testMissingComponentReturnsNull(): void
    {
        $page = ContentPage::fromRenderResult(new RenderResult(
            [new RenderedElement('text', 'Sw:Text:Text')],
            LayoutReference::create('layout', 'Text page', null),
            null,
        ));

        static::assertNull($page->findFirstElement('Sw:Product:Listing'));
    }

    public function testEmptyPageReturnsNull(): void
    {
        $page = ContentPage::fromRenderResult(new RenderResult(
            [],
            LayoutReference::create('layout', 'Empty page', null),
            null,
        ));

        static::assertNull($page->findFirstElement('Sw:Product:Listing'));
    }
}
