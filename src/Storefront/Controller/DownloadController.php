<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDownloadRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class DownloadController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDownloadRoute $downloadRoute)
    {
    }

    #[Route(path: '/account/order/download/{orderId}/{downloadId}', name: 'frontend.account.order.single.download', methods: ['GET'])]
    public function downloadFile(Request $request, SalesChannelContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute(
                'frontend.account.order.single.page',
                [
                    'deepLinkCode' => $request->query->get('deepLinkCode') ?? false,
                ]
            );
        }

        return $this->downloadRoute->load($request, $context);
    }
}
