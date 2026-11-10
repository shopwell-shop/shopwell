<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuException;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationCollection;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationEntity;
use Shopwell\Core\System\AdminMenu\Api\AdminMenuController;
use Shopwell\Core\System\AdminMenu\Service\AdminMenuConfigService;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuController::class)]
class AdminMenuControllerTest extends TestCase
{
    private const ITEM_ID = '1234567890abcdef1234567890abcdef';

    public function testTreeSerializesAListAndIncludesTranslationsWithoutRequiringPluginOrRoles(): void
    {
        $item = new AdminMenuItemEntity();
        $item->assign([
            'id' => self::ITEM_ID,
            'type' => 'menu',
            'active' => true,
            'sysLocked' => false,
            'archived' => false,
            'configOverridden' => true,
        ]);
        $translation = new AdminMenuItemTranslationEntity();
        $translation->assign([
            'adminMenuItemId' => self::ITEM_ID,
            'languageId' => 'language-id',
            'title' => '客户',
            'groupTitle' => '业务',
        ]);
        $translation->setUniqueIdentifier('translation-id');
        $item->setTranslations(new AdminMenuItemTranslationCollection([$translation]));
        $repository = StaticEntityRepository::of(AdminMenuItemCollection::class, [new AdminMenuItemCollection([$item])]);
        $controller = new AdminMenuController(
            $repository,
            static::createStub(AdminMenuConfigService::class),
            static::createStub(AdminMenuRouteRegistry::class),
            static::createStub(VisibleMenuTreeBuilder::class)
        );

        $content = $controller->tree(Context::createDefaultContext())->getContent();
        static::assertIsString($content);
        $items = json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['items'];
        static::assertIsArray($items);
        static::assertTrue(array_is_list($items));
        static::assertSame(self::ITEM_ID, $items[0]['id']);
        static::assertSame(['language-id' => ['title' => '客户', 'groupTitle' => '业务']], $items[0]['translations']);
        static::assertSame([], $items[0]['roleIds']);
        static::assertNull($items[0]['pluginName']);
        static::assertSame(['roles', 'plugin', 'translations'], array_keys($repository->searchedCriteria[0]->getAssociations()));
    }

    public function testMoveForwardsValidatedIdentityAndNullablePlacement(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->createMock(AdminMenuConfigService::class);
        $service->expects($this->once())->method('move')->with($context, self::ITEM_ID, null, null);

        $response = $this->controller($service)->move(new Request([], ['id' => self::ITEM_ID]), $context);
        static::assertSame(204, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPlacements(): iterable
    {
        yield 'missing identity' => [[]];
        yield 'non string identity' => [['id' => 17]];
        yield 'malformed parent' => [['id' => self::ITEM_ID, 'parentId' => 'not-a-uuid']];
        yield 'malformed predecessor' => [['id' => self::ITEM_ID, 'afterMenuItemId' => []]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPlacements')]
    public function testMoveRejectsInvalidPlacementBeforeCallingTheService(array $payload): void
    {
        $service = $this->createMock(AdminMenuConfigService::class);
        $service->expects($this->never())->method('move');
        $this->expectException(AdminMenuException::class);

        $this->controller($service)->move(new Request([], $payload), Context::createDefaultContext());
    }

    public function testRoleAssignmentDiscardsNonStringValuesAndNormalizesTheList(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->createMock(AdminMenuConfigService::class);
        $service->expects($this->once())->method('assignRoles')->with($context, self::ITEM_ID, ['role-a', 'role-b']);

        $response = $this->controller($service)->assignRoles(new Request([], [
            'id' => self::ITEM_ID,
            'roleIds' => [5 => 'role-a', 6 => null, 9 => 3, 12 => 'role-b'],
        ]), $context);
        static::assertSame(204, $response->getStatusCode());
    }

    public function testMalformedRoleListBecomesAnEmptyAssignment(): void
    {
        $context = Context::createDefaultContext();
        $service = $this->createMock(AdminMenuConfigService::class);
        $service->expects($this->once())->method('assignRoles')->with($context, self::ITEM_ID, []);

        $this->controller($service)->assignRoles(new Request([], ['id' => self::ITEM_ID, 'roleIds' => 'role-a']), $context);
    }

    public function testWhitelistExposesTheDeclaredRouteContract(): void
    {
        $registry = static::createStub(AdminMenuRouteRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            new AdminMenuDefinitionStruct('sw.customer.index', title: 'customer.title', parent: 'sw-customer', privilege: 'customer.viewer'),
        ]);
        $controller = new AdminMenuController(
            StaticEntityRepository::of(AdminMenuItemCollection::class),
            static::createStub(AdminMenuConfigService::class),
            $registry,
            static::createStub(VisibleMenuTreeBuilder::class)
        );

        $content = $controller->routeWhitelist()->getContent();
        static::assertIsString($content);
        static::assertSame(['routes' => [[
            'internalKey' => 'sw.customer.index', 'title' => 'customer.title', 'type' => 'menu',
            'parent' => 'sw-customer', 'privilege' => 'customer.viewer',
        ]]], json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testVisibleMenuUsesTheCurrentUserContextAndNeedsNoManagementPrivilege(): void
    {
        $context = Context::createDefaultContext();
        $tree = [['id' => self::ITEM_ID, 'children' => []]];
        $builder = $this->createMock(VisibleMenuTreeBuilder::class);
        $builder->expects($this->once())->method('build')->with($context)->willReturn($tree);
        $controller = new AdminMenuController(
            StaticEntityRepository::of(AdminMenuItemCollection::class),
            static::createStub(AdminMenuConfigService::class),
            static::createStub(AdminMenuRouteRegistry::class),
            $builder
        );

        static::assertSame(json_encode(['menu' => $tree, 'managedKeys' => []], \JSON_THROW_ON_ERROR), $controller->visible($context)->getContent());
        $route = (new AttributeRouteControllerLoader())->load(AdminMenuController::class)->get('api.action.admin-menu.visible');
        static::assertNotNull($route);
        static::assertTrue($route->getDefault('auth_required'));
        static::assertNull($route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    private function controller(AdminMenuConfigService $service): AdminMenuController
    {
        return new AdminMenuController(
            StaticEntityRepository::of(AdminMenuItemCollection::class),
            $service,
            static::createStub(AdminMenuRouteRegistry::class),
            static::createStub(VisibleMenuTreeBuilder::class)
        );
    }
}
