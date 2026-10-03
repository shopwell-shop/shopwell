<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Event\WishlistMergedEvent;
use Shopwell\Core\Checkout\Customer\Extension\MergeWishlistProductRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class MergeWishlistProductRoute extends AbstractMergeWishlistProductRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWishlistCollection> $wishlistRepository
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractMergeWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/customer/wishlist/merge',
        name: 'store-api.customer.wishlist.merge',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function merge(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: MergeWishlistProductRouteExtension::NAME,
            extension: new MergeWishlistProductRouteExtension($data, $context, $customer),
            function: $this->_merge(...),
        );
    }

    private function _merge(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannelId())) {
            throw CustomerException::customerWishlistNotActivated();
        }

        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $upsertData = $this->buildUpsertProducts($data, $wishlistId, $context);

        $this->wishlistRepository->upsert([[
            'id' => $wishlistId,
            'customerId' => $customer->getId(),
            'salesChannelId' => $context->getSalesChannelId(),
            'products' => $upsertData,
        ]], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistMergedEvent($upsertData, $context));

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, string $customerId): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            ]));

        return $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId() ?? Uuid::randomHex();
    }

    /**
     * @return array<array{id: string, productId?: string, productVersionId?: Defaults::LIVE_VERSION}>
     */
    private function buildUpsertProducts(RequestDataBag $data, string $wishlistId, SalesChannelContext $context): array
    {
        $productIds = $data->get('productIds');
        if (!$productIds instanceof DataBag) {
            throw CustomerException::productIdsParameterIsMissing();
        }

        $ids = array_unique(array_filter($productIds->all()));

        if ($ids === []) {
            return [];
        }

        $ids = $this->productRepository->searchIds(new Criteria($ids), $context)->getIds();

        $customerProducts = $this->loadCustomerProducts($wishlistId, $ids);

        $upsertData = [];

        foreach ($ids as $id) {
            if (\array_key_exists($id, $customerProducts)) {
                $upsertData[] = [
                    'id' => $customerProducts[$id],
                ];

                continue;
            }

            $upsertData[] = [
                'id' => Uuid::randomHex(),
                'productId' => $id,
                'productVersionId' => Defaults::LIVE_VERSION,
            ];
        }

        return $upsertData;
    }

    /**
     * @param array<string> $productIds
     *
     * @return array<string, string>
     */
    private function loadCustomerProducts(string $wishlistId, array $productIds): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(`product_id`)) as `product_id`',
            'LOWER(HEX(`id`)) as id',
        );
        $query->from('`customer_wishlist_product`');
        $query->where('`customer_wishlist_id` = :id');
        $query->andWhere('`product_id` IN (:productIds)');
        $query->setParameter('id', Uuid::fromHexToBytes($wishlistId));
        $query->setParameter('productIds', Uuid::fromHexToBytesList($productIds), ArrayParameterType::BINARY);
        $result = $query->executeQuery();

        /** @var array<string, string> $values */
        $values = $result->fetchAllKeyValue();

        return $values;
    }
}
