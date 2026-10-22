<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\SalesChannel;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\DictionaryException;
use Shopwell\Core\System\Dictionary\Extension\DictionaryRouteExtension;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('fundamentals@discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class DictionaryRoute extends AbstractDictionaryRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<DictionaryEntryCollection> $dictionaryEntryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $dictionaryEntryRepository,
        private readonly Connection $connection,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(): string
    {
        return 'dictionary-route';
    }

    public function getDecorated(): AbstractDictionaryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/dictionary/{technicalName}',
        name: 'store-api.dictionary',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => DictionaryEntryDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): DictionaryRouteResponse
    {
        return $this->extensions->publish(
            name: DictionaryRouteExtension::NAME,
            extension: new DictionaryRouteExtension($request, $context, $criteria),
            function: fn (Request $request, SalesChannelContext $context, Criteria $criteria): DictionaryRouteResponse => $this->_load($request, $context, $criteria),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, Criteria $criteria): DictionaryRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName());

        $technicalName = (string) $request->attributes->get('technicalName', '');

        $typeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM dictionary_type WHERE technical_name = :technicalName',
            ['technicalName' => $technicalName]
        );

        if (!\is_string($typeId) || $typeId === '') {
            throw DictionaryException::dictionaryTypeNotFound($technicalName);
        }

        $criteria->addFilter(new EqualsFilter('dictionaryTypeId', $typeId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        return new DictionaryRouteResponse($this->dictionaryEntryRepository->search($criteria, $context));
    }
}
