<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Context;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('framework')]
class ContextValueResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<Context>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== Context::class) {
            return;
        }

        yield $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT)
            ?? $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
    }
}
