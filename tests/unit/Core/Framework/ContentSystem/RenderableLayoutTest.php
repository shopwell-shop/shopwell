<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\RenderableLayout;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderableLayout::class)]
class RenderableLayoutTest extends TestCase
{
    public function testCreateRetainsReferenceAndElementOrder(): void
    {
        $reference = LayoutReference::create('layout-id', 'Preview', null);
        $first = StoredElementBuilder::create('Sw:Text', 'first')->build();
        $second = StoredElementBuilder::create('Sw:Text', 'second')->build();

        $layout = RenderableLayout::create($reference, [$second, $first]);

        static::assertSame($reference, $layout->reference);
        static::assertSame([$second, $first], $layout->elements);
    }

    public function testFromEntityRetainsLayoutAndIdentity(): void
    {
        $element = StoredElementBuilder::create('Sw:Text', 'text')->build();
        $entity = new ContentLayoutEntity();
        $entity->setId('layout-id');
        $entity->setName('Product page');
        $entity->setVersion('live-version');
        $entity->setLayout([$element]);

        $layout = RenderableLayout::fromEntity($entity);

        static::assertSame('layout-id', $layout->reference->id);
        static::assertSame('Product page', $layout->reference->name);
        static::assertSame('live-version', $layout->reference->version);
        static::assertSame([$element], $layout->elements);
    }

    public function testEmptyLayoutRemainsEmpty(): void
    {
        $layout = RenderableLayout::create(LayoutReference::create('empty', 'Empty', null), []);

        static::assertSame([], $layout->elements);
    }
}
