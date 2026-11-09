<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutReference::class)]
class LayoutReferenceTest extends TestCase
{
    public function testCreateRetainsIdentityAndNullableVersion(): void
    {
        $reference = LayoutReference::create('layout-id', 'Preview', null);

        static::assertSame('layout-id', $reference->id);
        static::assertSame('Preview', $reference->name);
        static::assertNull($reference->version);
    }

    public function testFromEntityCopiesTheCompleteIdentity(): void
    {
        $entity = new ContentLayoutEntity();
        $entity->setId('layout-id');
        $entity->setName('Product page');
        $entity->setVersion('draft-version');

        $reference = LayoutReference::fromEntity($entity);
        $entity->setName('Changed later');

        static::assertSame('layout-id', $reference->id);
        static::assertSame('Product page', $reference->name);
        static::assertSame('draft-version', $reference->version);
    }
}
