<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('checkout')]
class CustomerValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<CustomerEntity|null>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== CustomerEntity::class) {
            return;
        }

        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if ($loginRequired !== true) {
            $route = $request->attributes->get('_route');

            throw CustomerException::missingRouteAnnotation('LoginRequired', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            $route = $request->attributes->get('_route');

            throw CustomerException::missingRouteSalesChannel($route);
        }

        yield $context->getCustomer();
    }
}
