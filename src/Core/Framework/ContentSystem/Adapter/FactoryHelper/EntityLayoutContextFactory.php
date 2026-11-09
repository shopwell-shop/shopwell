<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopwell\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityLayoutContextFactory
{
    public function __construct(
        private readonly EntityLayoutResolver $layoutResolver,
        private readonly RootContextMapper $rootContextMapper,
        private readonly SystemConfigService $systemConfigService,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    /**
     * The root-ambient context this entity assignment supplies to a layout's top-level elements,
     * derived from the definition's page data requirements via the one shared mapping path.
     *
     * @return list<ProvidedContext>
     */
    public function providedRootContext(AbstractContentLayoutAssignableDefinition $definition): array
    {
        return $this->rootContextMapper->map($definition->getPageDataRequirements());
    }

    public function supports(string $path, AbstractContentLayoutAssignableDefinition $definition): bool
    {
        $path = '/' . ltrim($path, '/');
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        return str_starts_with($path, $pathPrefix);
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     * @param list<string> $fallbackEntityIds entity ids whose assignments are tried in order before the default layout
     */
    public function resolveLayoutId(
        string $path,
        SalesChannelContext $context,
        EntityRepository $repository,
        AbstractContentLayoutAssignableDefinition $definition,
        array $fallbackEntityIds = [],
    ): string {
        $entityId = $this->extractEntityId($path, $definition);
        $layoutId = null;

        foreach ([$entityId, ...$fallbackEntityIds] as $assignedEntityId) {
            // Tag before lookup so writing an assignment invalidates pages cached with a default layout.
            $cacheTags = $definition->getCacheTags($assignedEntityId);

            if ($cacheTags !== []) {
                $this->cacheTagCollector->addTag(...$cacheTags);
            }

            $layoutId = $this->layoutResolver->findLayoutId(
                $definition->getContentLayoutEntityIdField(),
                $assignedEntityId,
                $context,
                $repository
            );

            if ($layoutId !== null) {
                break;
            }
        }

        $layoutId ??= $this->findDefaultLayoutId($definition, $context);

        if ($layoutId === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                $definition->getContentLayoutEntityType(),
                $entityId,
                $context->getSalesChannel()->getId()
            );
        }

        return $layoutId;
    }

    public function resolveSpecificationData(
        string $path,
        Request $request,
        SalesChannelContext $context,
        AbstractContentLayoutAssignableDefinition $definition
    ): SpecificationData {
        return $this->buildSpecificationData(
            $this->extractEntityId($path, $definition),
            $request,
            $context,
            $definition,
        );
    }

    public function buildSpecificationData(
        string $entityId,
        Request $request,
        SalesChannelContext $context,
        AbstractContentLayoutAssignableDefinition $definition
    ): SpecificationData {
        return new SpecificationData(
            dataRequirements: array_values($definition->getPageDataRequirements()),
            placeholderValues: $this->layoutResolver->resolvePlaceholders(
                $definition->getContentLayoutEntityIdField(),
                $entityId,
                $request,
            ),
        );
    }

    public function resolveTargetElementId(Request $request): ?string
    {
        $elementId = $request->query->get('elementId');

        if (\is_string($elementId) && $elementId !== '') {
            return $elementId;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, AbstractContentLayoutAssignableDefinition $definition): array
    {
        $entityId = $this->extractEntityId($path, $definition);

        return $definition->getCacheTags($entityId);
    }

    /**
     * @throws ContentSystemException If path doesn't match route pattern
     */
    public function extractEntityId(string $path, AbstractContentLayoutAssignableDefinition $definition): string
    {
        $path = '/' . ltrim($path, '/');
        $routePattern = $definition->getContentLayoutRoutePattern();
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        $route = new Route($pathPrefix . $routePattern);
        $collection = new RouteCollection();
        $collection->add('entity', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            $expectedFormat = $definition->getContentLayoutPathPrefix() . $definition->getContentLayoutRoutePattern();
            throw ContentSystemException::invalidEntityPath(
                $definition->getContentLayoutEntityType(),
                $path,
                $expectedFormat
            );
        }

        $entityIdField = $definition->getContentLayoutEntityIdField();

        return $parameters[$entityIdField];
    }

    private function findDefaultLayoutId(AbstractContentLayoutAssignableDefinition $definition, SalesChannelContext $context): ?string
    {
        $configKey = $definition->getContentLayoutDefaultConfigKey();

        if ($configKey === null) {
            return null;
        }

        // Tagged before the read, so a page cached without a default (legacy CMS fallback) is invalidated once one is set
        $this->cacheTagCollector->addTag(SystemConfigService::buildName($configKey));

        $layoutId = $this->systemConfigService->getString($configKey, $context->getSalesChannelId());

        return $layoutId !== '' ? $layoutId : null;
    }
}
