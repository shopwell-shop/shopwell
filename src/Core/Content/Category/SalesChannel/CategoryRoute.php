<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\SalesChannel;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Content\Category\Extension\CategoryRouteExtension;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CategoryRoute extends AbstractCategoryRoute
{
    final public const HOME = 'home';

    /**
     * @internal
     *
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $categoryRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'category-route-' . $id;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/category/{navigationId}',
        name: 'store-api.category.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        return $this->extensions->publish(
            name: CategoryRouteExtension::NAME,
            extension: new CategoryRouteExtension($navigationId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        if ($navigationId === self::HOME) {
            $navigationId = $context->getSalesChannel()->getNavigationCategoryId();
            $request->attributes->set('navigationId', $navigationId);

            $routeParams = $request->attributes->get('_route_params', []);
            $routeParams['navigationId'] = $navigationId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $this->cacheTagCollector->addTag(self::buildName($navigationId));

        $category = $this->loadCategory($navigationId, $context);

        $categoryHasContentlessPageType = \in_array($category->getType(), [CategoryDefinition::TYPE_FOLDER, CategoryDefinition::TYPE_LINK], true);
        if ($categoryHasContentlessPageType && $context->getSalesChannel()->getNavigationCategoryId() !== $navigationId) {
            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                return new CategoryRouteResponse($category);
            }

            throw CategoryException::categoryNotFound($navigationId);
        }

        return new CategoryRouteResponse($category);
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');

        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');

        $category = $this->categoryRepository->search($criteria, $context)->getEntities()->get($categoryId);
        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return $category;
    }
}
