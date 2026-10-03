<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Event\WishlistProductAddedEvent;
use Shopwell\Core\Checkout\Customer\Extension\AddWishlistProductRouteExtension;
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
class AddWishlistProductRoute extends AbstractAddWishlistProductRoute
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
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractAddWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/customer/wishlist/add/{productId}',
        name: 'store-api.customer.wishlist.add',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function add(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: AddWishlistProductRouteExtension::NAME,
            extension: new AddWishlistProductRouteExtension($productId, $context, $customer),
            function: $this->_add(...),
        );
    }

    private function _add(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannelId())) {
            throw CustomerException::customerWishlistNotActivated();
        }

        $this->validateProduct($productId, $context);
        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $this->wishlistRepository->upsert([
            [
                'id' => $wishlistId,
                'customerId' => $customer->getId(),
                'salesChannelId' => $context->getSalesChannelId(),
                'products' => [
                    [
                        'productId' => $productId,
                        'productVersionId' => Defaults::LIVE_VERSION,
                    ],
                ],
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistProductAddedEvent($wishlistId, $productId, $context));

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

    private function validateProduct(string $productId, SalesChannelContext $context): void
    {
        $total = $this->productRepository->searchIds(new Criteria([$productId]), $context)->getTotal();
        if ($total === 0) {
            throw CustomerException::productNotFound($productId);
        }
    }
}
