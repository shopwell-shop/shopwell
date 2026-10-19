<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Extension\WalletTopUpStatusRouteExtension;
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
class WalletTopUpStatusRoute
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
        path: '/store-api/wallet/top-up/{topUpId}',
        name: 'store-api.wallet.top-up.status',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    public function load(string $topUpId, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        return $this->extensions->publish(
            name: WalletTopUpStatusRouteExtension::NAME,
            extension: new WalletTopUpStatusRouteExtension($topUpId, $context, $customer),
            function: $this->_load(...),
        );
    }

    private function _load(string $topUpId, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        return new WalletTopUpRouteResponse(new ArrayStruct(
            $this->topUpService->status($topUpId, $customer->getId(), $context->getContext())
        ));
    }
}
