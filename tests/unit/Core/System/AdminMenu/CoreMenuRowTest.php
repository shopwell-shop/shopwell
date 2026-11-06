<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\CoreMenuRow;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CoreMenuRow::class)]
class CoreMenuRowTest extends TestCase
{
    public function testSeparatesDeclarationFieldsFromPlacementAndMetadata(): void
    {
        $row = new CoreMenuRow('row', 'menu-key', 'link', 'icon', 'blue', 'catalog:read', '_blank', 'parent', 'previous', ['label' => 'menu.label'], 'catalog');
        static::assertSame([
            'internalKey' => 'menu-key', 'type' => 'link', 'icon' => 'icon', 'color' => 'blue',
            'privilege' => 'catalog:read', 'linkTarget' => '_blank', 'groupName' => 'catalog',
        ], $row->declarationFields());
        static::assertSame(['id' => 'row', 'parentId' => 'parent', 'afterMenuItemId' => 'previous'], $row->placement());
        static::assertSame([
            ...$row->declarationFields(), ...$row->placement(), 'defaultMeta' => ['label' => 'menu.label'],
        ], $row->toArray());
    }
}
