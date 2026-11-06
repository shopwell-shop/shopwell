<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\Extension\MembershipPurchaseRouteExtension;
use Shopwell\Core\Checkout\Membership\Service\MembershipPurchaseService;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
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
        private readonly MembershipPurchaseService $purchases,
        private readonly ExtensionDispatcher $extensions,
        private readonly StorefrontPaymentContextResolver $paymentContextResolver,
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
        $result = $this->purchases->purchase(
            customerId: $customer->getId(),
            planId: (string) $request->request->get('planId', ''),
            method: (string) $request->request->get('method', ''),
            currencyId: $context->getCurrency()->getId(),
            paymentContext: $this->paymentContextResolver->resolve($request, $context->getCurrency()->getIsoCode()),
            returnUrl: $this->returnUrlOf($request),
            clientIp: $request->getClientIp(),
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

        return $request->getSchemeAndHttpHost() . '/account/membership';
    }
}
