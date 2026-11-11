<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\AdminMenu\AdminMenuException;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Shopwell\Core\System\AdminMenu\AdminMenuItemEntity;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\Service\AdminMenuConfigService;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The management endpoints of the database driven administration navigation.
 *
 * Creating, updating and deleting items goes through the generated DAL routes of the
 * `admin_menu_item` entity; this controller only serves the actions where a plain row
 * write is not enough - reading the management tree, moving an item with its ordering
 * chain - and the route whitelist the management UI offers as choices.
 */
#[Package('fundamentals@framework')]
class AdminMenuController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminMenuItemCollection> $menuItemRepository
     */
    public function __construct(
        private readonly EntityRepository $menuItemRepository,
        private readonly AdminMenuConfigService $configService,
        private readonly AdminMenuRouteRegistry $routeRegistry,
        private readonly VisibleMenuTreeBuilder $treeBuilder,
    ) {
    }

    #[Route(
        path: '/api/_action/admin-menu/tree',
        name: 'api.action.admin-menu.tree',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['admin_menu_item:read']],
        methods: [Request::METHOD_GET]
    )]
    public function tree(Context $context): JsonResponse
    {
        $criteria = (new Criteria())
            ->addAssociation('plugin')
            ->addAssociation('translations');

        $items = $this->menuItemRepository->search($criteria, $context)->getEntities();

        // A list, not a map: the collection iterates with the row ids as keys, and a keyed
        // array serializes to a JSON object the administration cannot read as a list.
        return new JsonResponse([
            'items' => array_values(array_map(static fn (AdminMenuItemEntity $item) => [
                'id' => $item->getId(),
                'parentId' => $item->getParentId(),
                'afterMenuItemId' => $item->getAfterMenuItemId(),
                'internalKey' => $item->getInternalKey(),
                'routeKey' => $item->getRouteKey(),
                'title' => $item->getTitle(),
                'groupName' => $item->getGroupName(),
                'translations' => self::translationsOf($item),
                'icon' => $item->getIcon(),
                'color' => $item->getColor(),
                'type' => $item->getType(),
                'linkTarget' => $item->getLinkTarget(),
                'privilege' => $item->getPrivilege(),
                'active' => $item->isActive(),
                'sysLocked' => $item->isSysLocked(),
                'archived' => $item->isArchived(),
                'configOverridden' => $item->isConfigOverridden(),
                'pluginId' => $item->getPluginId(),
                'pluginName' => $item->getPlugin()?->getName(),
            ], iterator_to_array($items, false))),
        ]);
    }

    #[Route(
        path: '/api/_action/admin-menu/move',
        name: 'api.action.admin-menu.move',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['admin_menu_item:update']],
        methods: [Request::METHOD_POST]
    )]
    public function move(Request $request, Context $context): JsonResponse
    {
        $payload = $request->request->all();

        $this->configService->move(
            $context,
            $this->requireUuid($payload['id'] ?? null),
            isset($payload['parentId']) ? $this->requireUuid($payload['parentId']) : null,
            isset($payload['afterMenuItemId']) ? $this->requireUuid($payload['afterMenuItemId']) : null
        );

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/admin-menu/route-whitelist',
        name: 'api.action.admin-menu.route-whitelist',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['admin_menu_item:read']],
        methods: [Request::METHOD_GET]
    )]
    public function routeWhitelist(): JsonResponse
    {
        return new JsonResponse([
            'routes' => array_map(static fn ($definition) => [
                'internalKey' => $definition->internalKey,
                'title' => $definition->title,
                'type' => $definition->type,
                'parent' => $definition->parent,
                'privilege' => $definition->privilege,
            ], $this->routeRegistry->getDefinitions()),
        ]);
    }

    /**
     * The navigation of the current user, not a management endpoint.
     *
     * Deliberately without an ACL: the tree is already filtered by the user's roles, and every
     * page behind an entry keeps its own route ACL. Requiring `admin_menu_item:read` would hide
     * the whole navigation from every user who may not *edit* menus.
     */
    #[Route(
        path: '/api/_action/admin-menu/visible',
        name: 'api.action.admin-menu.visible',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration']],
        methods: [Request::METHOD_GET]
    )]
    public function visible(Context $context): JsonResponse
    {
        return new JsonResponse([
            ...$this->treeBuilder->buildNavigation($context),
            'managedKeys' => array_map(
                static fn ($definition): string => $definition->internalKey,
                $this->routeRegistry->getDefinitions()
            ),
        ]);
    }

    /**
     * The labels of every installed language.
     *
     * The management page switches the language it shows without a request per language, and
     * the sidebar already receives the label of the current one - a single map serves both
     * without a second endpoint.
     *
     * @return array<string, array{title: ?string, groupTitle: ?string}>
     */
    private static function translationsOf(AdminMenuItemEntity $item): array
    {
        $translations = [];

        foreach ($item->getTranslations() ?? [] as $translation) {
            $translations[$translation->getLanguageId()] = [
                'title' => $translation->getTitle(),
                'groupTitle' => $translation->getGroupTitle(),
            ];
        }

        return $translations;
    }

    private function requireUuid(mixed $value): string
    {
        if (!\is_string($value) || !Uuid::isValid($value)) {
            throw AdminMenuException::invalidMenuItemId($value);
        }

        return $value;
    }
}
