<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopwell\Core\Framework\Gateway\Context\Extension\ContextGatewayRouteExtension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextGatewayRoute extends AbstractContextGatewayRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AppContextGateway $contextGateway,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractContextGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context/gateway', name: 'store-api.context.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ContextGatewayRouteExtension::NAME,
            extension: new ContextGatewayRouteExtension($request, $cart, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse
    {
        return $this->contextGateway->process(new ContextGatewayPayloadStruct($cart, $context, new RequestDataBag($request->request->all())));
    }
}
