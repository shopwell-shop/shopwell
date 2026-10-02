<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\Extension\DownloadRouteExtension;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopwell\Core\Content\Media\File\DownloadResponseGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class DownloadRoute extends AbstractDownloadRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderLineItemDownloadCollection> $downloadRepository
     */
    public function __construct(
        private readonly EntityRepository $downloadRepository,
        private readonly DownloadResponseGenerator $downloadResponseGenerator,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractDownloadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/order/download/{orderId}/{downloadId}',
        name: 'store-api.account.order.single.download',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function load(Request $request, SalesChannelContext $context): Response
    {
        return $this->extensions->publish(
            name: DownloadRouteExtension::NAME,
            extension: new DownloadRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        $downloadId = $request->attributes->get('downloadId');
        $orderId = $request->attributes->get('orderId');

        if (!$customer) {
            throw CustomerException::customerNotLoggedIn();
        }

        if ($downloadId === null || $orderId === null) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw RoutingException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
            }
            throw CustomerException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
        }

        $criteria = (new Criteria([$downloadId]))
            ->addAssociation('media')
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderLineItem.order.id', $orderId),
                new EqualsFilter('orderLineItem.order.orderCustomer.customerId', $customer->getId()),
                new EqualsFilter('accessGranted', true),
            ]));

        $download = $this->downloadRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$download || !$download->getMedia()) {
            throw CustomerException::downloadFileNotFound($downloadId);
        }

        $media = $download->getMedia();

        return $this->downloadResponseGenerator->getResponse($media, $context);
    }
}
