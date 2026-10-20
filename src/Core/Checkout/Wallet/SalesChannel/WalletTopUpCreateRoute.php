<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Extension\WalletTopUpCreateRouteExtension;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
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
class WalletTopUpCreateRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WalletTopUpService $topUpService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/wallet/top-up',
        name: 'store-api.wallet.top-up.create',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function create(Request $request, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        return $this->extensions->publish(
            name: WalletTopUpCreateRouteExtension::NAME,
            extension: new WalletTopUpCreateRouteExtension($request, $context, $customer),
            function: $this->_create(...),
        );
    }

    private function _create(Request $request, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        $result = $this->topUpService->create(
            customerId: $customer->getId(),
            currencyId: $context->getCurrency()->getId(),
            currency: $context->getCurrency()->getIsoCode(),
            amount: (float) $request->request->get('amount', 0),
            channel: (string) $request->request->get('channel', ''),
            returnUrl: $this->returnUrlOf($request),
            clientIp: $request->getClientIp(),
            device: $this->deviceOf($request),
            context: $context->getContext(),
            salesChannelId: $context->getSalesChannelId(),
        );

        return new WalletTopUpRouteResponse(new ArrayStruct($result));
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

        return $request->getSchemeAndHttpHost() . '/account/wallet';
    }

    private function deviceOf(Request $request): ?string
    {
        $device = $request->request->get('device') ?? $request->query->get('device');

        return \is_string($device) && $device !== '' ? strtolower($device) : null;
    }
}
