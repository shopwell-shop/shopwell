<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Extension\WalletTopUpSimulateRouteExtension;
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
class WalletTopUpSimulateRoute
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
        path: '/store-api/wallet/top-up/{topUpId}/simulate',
        name: 'store-api.wallet.top-up.simulate',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function simulate(string $topUpId, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        return $this->extensions->publish(
            name: WalletTopUpSimulateRouteExtension::NAME,
            extension: new WalletTopUpSimulateRouteExtension($topUpId, $context, $customer),
            function: $this->_simulate(...),
        );
    }

    private function _simulate(string $topUpId, SalesChannelContext $context, CustomerEntity $customer): WalletTopUpRouteResponse
    {
        return new WalletTopUpRouteResponse(new ArrayStruct(
            $this->topUpService->simulate($topUpId, $customer->getId(), $context->getContext())
        ));
    }
}
