<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
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
        $this->definitions = (new CoreAdminMenuProvider((new AdminMenuXmlReader())->read(CoreAdminMenuProvider::MENU_FILE)))->getDefinitions();
    }

    public function testTheCoreProviderDoesNotClaimAPlugin(): void
    {
        static::assertNull((new CoreAdminMenuProvider((new AdminMenuXmlReader())->read(CoreAdminMenuProvider::MENU_FILE)))->getPluginTechnicalName());
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

    public function testPaymentPagesBelongDirectlyToFinanceWithIndependentPermissions(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        static::assertArrayNotHasKey('sw-payments', $definitions);
        static::assertArrayNotHasKey('sw-payment', $definitions);
        static::assertNull($definitions['sw-finance']->privilege);
        static::assertSame('sw-finance', $definitions['sw.payment.index']->parent);
        static::assertSame('payment_order.viewer', $definitions['sw.payment.index']->privilege);
        static::assertSame('sw-finance', $definitions['sw.payment.refund']->parent);
        static::assertSame('payment_refund.viewer', $definitions['sw.payment.refund']->privilege);
        static::assertSame('payment_account.viewer', $definitions['sw-settings-payment-provider']->privilege);
    }

    public function testMembershipSettingsCanBeAssignedToRoleMenus(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        foreach (['sw-settings-membership', 'sw-settings-membership-plans'] as $key) {
            static::assertArrayHasKey($key, $definitions);
            static::assertNotNull($definitions[$key]->parent);
            static::assertSame('sw-settings', $definitions[$definitions[$key]->parent]->parent);
            static::assertSame('customer', $definitions[$definitions[$key]->parent]->groupName);
            static::assertSame('loyalty.viewer', $definitions[$key]->privilege);
        }
    }

    public function testWebhookSettingsCanBeAssignedToRoleMenus(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        static::assertArrayHasKey('sw-settings-webhook', $definitions);
        $definition = $definitions['sw-settings-webhook'];
        static::assertSame('sw-settings-group-system', $definition->parent);
        static::assertSame('system', $definitions[$definition->parent]->groupName);
        static::assertSame('webhook.viewer', $definition->privilege);
    }

    public function testBlogIsALocalizedFolderWithIndependentChildren(): void
    {
        $blogs = array_values(array_filter(
            $this->definitions,
            static fn (AdminMenuDefinitionStruct $definition): bool => $definition->internalKey === 'sw-blog',
        ));

        static::assertCount(1, $blogs);
        static::assertSame('sw-content', $blogs[0]->parent);
        static::assertSame(AdminMenuItemDefinition::TYPE_FOLDER, $blogs[0]->type);
        static::assertSame('Blog', $blogs[0]->title);

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

    public function testWorkflowDesignIsAssignableWithinItsOwnDomain(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        static::assertArrayHasKey('sw-workflow', $definitions);
        static::assertNull($definitions['sw-workflow']->parent);
        static::assertSame(72, $definitions['sw-workflow']->position);
        static::assertSame(AdminMenuItemDefinition::TYPE_FOLDER, $definitions['sw-workflow']->type);
        static::assertArrayHasKey('sw-workflow-design', $definitions);
        static::assertSame('sw-workflow', $definitions['sw-workflow-design']->parent);
        static::assertSame('workflow.viewer', $definitions['sw-workflow-design']->privilege);
        static::assertArrayNotHasKey('sw-workflow-releases', $definitions);
        static::assertArrayHasKey('sw-workflow-tasks', $definitions);
        static::assertSame('sw-workflow', $definitions['sw-workflow-tasks']->parent);
        static::assertSame('workflow_participant.viewer', $definitions['sw-workflow-tasks']->privilege);
        static::assertSame('My approvals', $definitions['sw-workflow-tasks']->title);
        static::assertNull((new CoreAdminMenuProvider((new AdminMenuXmlReader())->read(CoreAdminMenuProvider::MENU_FILE)))->getPluginTechnicalName());
    }

    public function testPersonalApprovalsBelongToTheirOwnTopLevelFolder(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');
        static::assertArrayHasKey('sw-approval-flow', $definitions);
        static::assertNull($definitions['sw-approval-flow']->parent);
        static::assertSame(AdminMenuItemDefinition::TYPE_FOLDER, $definitions['sw-approval-flow']->type);
        static::assertSame('审批', $definitions['sw-approval-flow']->translations['zh-CN']['title']);
        static::assertSame('sw-approval-flow', $definitions['sw-approval-flow-definitions']->parent);
        static::assertSame('approval_flow_definition.viewer', $definitions['sw-approval-flow-definitions']->privilege);
        $personalMenu = $definitions['sw-approval-flow-todo'];
        static::assertSame('sw-approval-flow', $personalMenu->parent);
        static::assertSame('approval_flow.viewer', $personalMenu->privilege);
        static::assertSame('我的审批', $personalMenu->translations['zh-CN']['title']);
        static::assertArrayNotHasKey('sw-approval-flow-done', $definitions);
        static::assertArrayNotHasKey('sw-approval-flow-started', $definitions);
    }

    public function testSettingsRetainTheirOriginalGroups(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');
        foreach ([
            'sw-settings-rule' => 'automation',
            'sw-settings-country' => 'localization',
            'sw-settings-media' => 'content',
            'sw-settings-cart' => 'general',
            'sw-settings-logging' => 'system',
            'sw-settings-payment-provider' => 'payment',
        ] as $key => $group) {
            static::assertNotNull($definitions[$key]->parent);
            $parent = $definitions[$definitions[$key]->parent];
            static::assertSame('sw-settings', $parent->parent, $key);
            static::assertSame($group, $parent->groupName, $key);
        }
        static::assertNull($definitions['sw-order-return']->parent);
        static::assertSame('sw-system', $definitions['sw-user']->parent);
        static::assertArrayNotHasKey('sw-settings-security', $definitions);
    }

    public function testOrganizationAndPositionsBelongToSystem(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');

        static::assertArrayNotHasKey('sw-settings-organization', $definitions);
        static::assertArrayNotHasKey('sw-settings-position', $definitions);
        static::assertSame('sw-system', $definitions['sw-organization']->parent);
        static::assertSame('organization.viewer', $definitions['sw-organization']->privilege);
        static::assertNull($definitions['sw-organization']->groupName);
        static::assertSame('sw-system', $definitions['sw-position']->parent);
        static::assertSame('position.viewer', $definitions['sw-position']->privilege);
        static::assertNull($definitions['sw-position']->groupName);
    }

    public function testCoreXmlUsesReadableGroupsAndLiteralTranslatedTitles(): void
    {
        foreach ($this->definitions as $definition) {
            static::assertArrayHasKey('en-GB', $definition->translations);
            static::assertArrayHasKey('zh-CN', $definition->translations);
            static::assertSame($definition->translations['en-GB']['title'], $definition->title);
            static::assertStringNotContainsString('.general.', $definition->title ?? '');
            if ($definition->type === AdminMenuItemDefinition::TYPE_GROUP) {
                static::assertSame('sw-settings-group-' . $definition->groupName, $definition->internalKey);
            }
        }
    }

    public function testXmlPreservesModuleIconColors(): void
    {
        $definitions = array_column($this->definitions, null, 'internalKey');
        static::assertSame('var(--sw-color-module-green-default)', $definitions['sw-catalogue']->color);
        static::assertSame('var(--color-text-brand-default)', $definitions['sw-dashboard']->color);
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
                    AdminMenuItemDefinition::TYPE_GROUP,
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
