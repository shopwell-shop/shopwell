<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Extension\MembershipPurchaseRouteExtension;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class MembershipPurchaseRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/membership/purchase',
        name: 'store-api.membership.purchase',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function purchase(Request $request, SalesChannelContext $context, CustomerEntity $customer): MembershipPurchaseRouteResponse
    {
        return $this->extensions->publish(
            name: MembershipPurchaseRouteExtension::NAME,
            extension: new MembershipPurchaseRouteExtension($request, $context, $customer),
            function: $this->_purchase(...),
        );
    }

    private function _purchase(Request $request, SalesChannelContext $context, CustomerEntity $customer): MembershipPurchaseRouteResponse
    {
        $result = $this->membershipService->purchase(
            customerId: $customer->getId(),
            planId: (string) $request->request->get('planId', ''),
            channel: (string) $request->request->get('channel', ''),
            currencyId: $context->getCurrency()->getId(),
            currency: $context->getCurrency()->getIsoCode(),
            returnUrl: $this->returnUrlOf($request),
            clientIp: $request->getClientIp(),
            device: $this->deviceOf($request),
            context: $context->getContext(),
        );

        return new MembershipPurchaseRouteResponse(new ArrayStruct($result));
    }

    /**
     * Where the payer lands after a redirect channel. The storefront sends its own route, the account
     * page is the fallback for callers that do not.
     */
    private function returnUrlOf(Request $request): string
    {
        $returnUrl = $request->request->get('returnUrl');

        if (\is_string($returnUrl) && $returnUrl !== '') {
            return $returnUrl;
        }

        return $request->getSchemeAndHttpHost() . '/account/loyalty';
    }

    private function deviceOf(Request $request): ?string
    {
        $device = $request->request->get('device') ?? $request->query->get('device');

        return \is_string($device) && $device !== '' ? strtolower($device) : null;
    }
}
