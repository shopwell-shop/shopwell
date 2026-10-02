<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\SalesChannel;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Extension\ContextRouteExtension;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextRoute extends AbstractContextRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractContextRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context', name: 'store-api.context', methods: ['GET'])]
    public function load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        return $this->extensions->publish(
            name: ContextRouteExtension::NAME,
            extension: new ContextRouteExtension($context),
            function: $this->_load(...),
        );
    }

    private function _load(SalesChannelContext $context): ContextLoadRouteResponse
    {
        $response = new ContextLoadRouteResponse($context);
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        return $response;
    }
}
