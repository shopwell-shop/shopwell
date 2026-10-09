<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Context;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('framework')]
class SalesChannelContextValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<SalesChannelContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== SalesChannelContext::class) {
            return;
        }

        yield $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_SALES_CHANNEL_CONTEXT_OBJECT)
            ?? $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
    }
}
