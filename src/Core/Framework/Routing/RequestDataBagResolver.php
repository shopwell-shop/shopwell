<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('framework')]
class RequestDataBagResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<RequestDataBag>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== RequestDataBag::class) {
            return;
        }

        yield new RequestDataBag($request->request->all());
    }
}
