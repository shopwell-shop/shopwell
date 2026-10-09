<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing\Annotation;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('framework')]
class CriteriaValueResolver implements ValueResolverInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    /**
     * @return \Generator<Criteria>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== Criteria::class) {
            return;
        }

        $entity = $request->attributes->getString(PlatformRequest::ATTRIBUTE_ENTITY);
        if ($entity === '') {
            $route = $request->attributes->get('_route');

            throw RoutingException::missingRouteAttribute('default "_entity" value', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        }

        if (!$context instanceof Context) {
            $route = $request->attributes->get('_route');

            throw RoutingException::missingRouteAttribute('context', $route);
        }

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->registry->getByEntityName($entity),
            $context
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CRITERIA, $criteria);

        yield $criteria;
    }
}
