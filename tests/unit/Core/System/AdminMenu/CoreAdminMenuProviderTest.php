<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;

/**
 * The declarations are the seed of the navigation table and the source of the route
 * whitelist, so the invariants the database relies on have to hold here already.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CoreAdminMenuProvider::class)]
class CoreAdminMenuProviderTest extends TestCase
{
    /**
     * @var list<AdminMenuDefinitionStruct>
     */
    private array $definitions;

    protected function setUp(): void
    {
        $this->definitions = (new CoreAdminMenuProvider())->getDefinitions();
    }

    public function testTheCoreProviderDoesNotClaimAPlugin(): void
    {
        static::assertNull((new CoreAdminMenuProvider())->getPluginTechnicalName());
    }

    public function testEveryInternalKeyIsDeclaredOnce(): void
    {
        $internalKeys = $this->internalKeys();

        static::assertSame(
            [],
            array_values(array_diff_assoc($internalKeys, array_unique($internalKeys))),
            'The navigation declares the same internal key twice.'
        );
    }

    public function testMembershipSettingsCanBeAssignedToRoleMenus(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        foreach (['sw-settings-membership', 'sw-settings-membership-plans'] as $key) {
            static::assertArrayHasKey($key, $definitions);
            static::assertSame('sw-settings', $definitions[$key]->parent);
            static::assertSame('customer', $definitions[$key]->groupName);
            static::assertSame('loyalty.viewer', $definitions[$key]->privilege);
        }
    }

    public function testBlogIsALocalizedFolderWithIndependentChildren(): void
    {
        $blogs = array_values(array_filter(
            $this->definitions,
            static fn (AdminMenuDefinitionStruct $definition): bool => $definition->internalKey === 'sw-blog',
        ));

        static::assertCount(1, $blogs);
        static::assertNull($blogs[0]->parent);
        static::assertSame(AdminMenuItemDefinition::TYPE_FOLDER, $blogs[0]->type);
        static::assertSame('sw-blog.general.mainMenuItemGeneral', $blogs[0]->title);

        $children = array_values(array_filter(
            $this->definitions,
            static fn (AdminMenuDefinitionStruct $definition): bool => $definition->parent === 'sw-blog',
        ));
        static::assertSame(['sw-blog-content', 'sw-blog-category', 'sw-blog-author', 'sw-blog-comment'], array_map(
            static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey,
            $children,
        ));
        foreach ($children as $child) {
            static::assertSame('blog.viewer', $child->privilege);
        }
    }

    public function testEveryParentIsDeclaredAsWell(): void
    {
        $internalKeys = $this->internalKeys();

        foreach ($this->definitions as $definition) {
            if ($definition->parent === null) {
                continue;
            }

            static::assertContains(
                $definition->parent,
                $internalKeys,
                \sprintf('The entry %s declares the unknown parent %s.', $definition->internalKey, $definition->parent)
            );
        }
    }

    public function testNoEntryIsItsOwnParent(): void
    {
        foreach ($this->definitions as $definition) {
            static::assertNotSame($definition->internalKey, $definition->parent);
        }
    }

    /**
     * Siblings may share a position - the administration sorts by position and keeps the
     * declaration order for ties, and the seeded chain has to resolve them the same way -
     * but a top level entry without one would silently end up behind all others.
     */
    public function testTopLevelEntriesDeclareAPosition(): void
    {
        foreach ($this->definitions as $definition) {
            if ($definition->parent !== null) {
                continue;
            }

            static::assertNotNull(
                $definition->position,
                \sprintf('The top level entry %s does not declare a position.', $definition->internalKey)
            );
        }
    }

    public function testEveryEntryCarriesTheLabelOfItsType(): void
    {
        foreach ($this->definitions as $definition) {
            static::assertContains(
                $definition->type,
                [
                    AdminMenuItemDefinition::TYPE_MENU,
                    AdminMenuItemDefinition::TYPE_FOLDER,
                    AdminMenuItemDefinition::TYPE_LINK,
                    AdminMenuItemDefinition::TYPE_HIDDEN,
                ],
                \sprintf('The entry %s declares an unknown type.', $definition->internalKey)
            );

            if ($definition->type === AdminMenuItemDefinition::TYPE_LINK) {
                static::assertNotNull(
                    $definition->linkTarget,
                    \sprintf('The link entry %s does not declare a target.', $definition->internalKey)
                );

                continue;
            }

            static::assertNotNull(
                $definition->title,
                \sprintf('The entry %s does not declare a label.', $definition->internalKey)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function internalKeys(): array
    {
        return array_map(static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey, $this->definitions);
    }
}
