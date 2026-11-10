<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\MenuGroupNormalizer;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(MenuGroupNormalizer::class)]
class MenuGroupNormalizerTest extends TestCase
{
    public function testLegacyGroupsBecomeIndependentContainersWithoutMutatingProviderData(): void
    {
        $first = new AdminMenuDefinitionStruct('first', parent: 'settings', position: 20, groupName: 'system');
        $second = new AdminMenuDefinitionStruct('second', parent: 'settings', position: 10, groupName: 'system');
        $other = new AdminMenuDefinitionStruct('other', parent: 'catalogue', groupName: 'system');

        $result = MenuGroupNormalizer::normalize([$first, $second, $other], null);
        $groups = array_values(array_filter($result, static fn ($item) => $item->type === 'group'));
        $pages = array_values(array_filter($result, static fn ($item) => $item->type === 'menu'));

        static::assertCount(2, $groups);
        static::assertSame('settings', $groups[0]->parent);
        static::assertSame(10, $groups[0]->position);
        static::assertSame('sw-system.menu.groups.system', $groups[0]->title);
        static::assertSame('system', $groups[0]->groupName);
        static::assertSame($groups[0]->internalKey, $pages[0]->parent);
        static::assertSame($groups[0]->internalKey, $pages[1]->parent);
        static::assertNotSame($groups[0]->internalKey, $groups[1]->internalKey);
        static::assertNull($pages[0]->groupName);
        static::assertSame('settings', $first->parent);
        static::assertSame('system', $first->groupName);
    }

    public function testOwnershipAndParentScopeArePartOfStableGroupIdentity(): void
    {
        static::assertSame(MenuGroupNormalizer::key('settings', 'system', null), MenuGroupNormalizer::key('settings', 'system', null));
        static::assertNotSame(MenuGroupNormalizer::key('settings', 'system', null), MenuGroupNormalizer::key('catalogue', 'system', null));
        static::assertNotSame(MenuGroupNormalizer::key('settings', 'system', null), MenuGroupNormalizer::key('settings', 'system', 'Example'));
    }

    public function testExplicitGroupsAreKeptAndNormalizationIsIdempotent(): void
    {
        $definitions = [new AdminMenuDefinitionStruct('custom', type: 'group', title: 'Custom')];
        static::assertSame($definitions, MenuGroupNormalizer::normalize($definitions, null));
    }
}
