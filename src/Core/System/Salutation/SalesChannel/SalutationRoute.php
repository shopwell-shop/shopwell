<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\SalesChannel;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $salutationRepository,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(): string
    {
        return 'salutation-route';
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/salutation',
        name: 'store-api.salutation',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => SalutationDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName());

        return new SalutationRouteResponse($this->salutationRepository->search($criteria, $context));
    }
}
