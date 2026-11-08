<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\RenderingSpecification;
use Shopwell\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RenderingSpecificationResolver
{
    /**
     * @param iterable<AbstractSpecificationSource> $sources
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly RenderingSpecificationFactory $factory,
        private readonly ?StandaloneLayoutSpecificationSource $standaloneSource = null,
    ) {
    }

    /**
     * @throws ContentSystemException When no source can handle the path
     */
    public function resolve(
        string $path,
        Request $request,
        SalesChannelContext $context
    ): ResolvedContentLayout {
        if ($this->standaloneSource?->supports($path, $request, $context)) {
            return $this->factory->create($this->standaloneSource, $path, $request, $context);
        }

        foreach ($this->sources as $source) {
            if ($source->supports($path, $request, $context)) {
                return $this->factory->create($source, $path, $request, $context);
            }
        }

        throw ContentSystemException::noFactoryCanHandle($path);
    }

    /**
     * Resolves a layout-free specification by entity type, for the preview action.
     * Selection is by exact entity-type match — no URL path is constructed.
     *
     * @throws ContentSystemException When no source supports the entity type
     */
    public function resolveWithoutLayout(
        string $entityType,
        string $entityId,
        Request $request,
        SalesChannelContext $context
    ): RenderingSpecification {
        foreach ($this->sources as $source) {
            if ($source->supportsEntityType($entityType)) {
                return $this->factory->createWithoutLayout($source, $entityId, $request, $context);
            }
        }

        throw ContentSystemException::unknownEntityType($entityType);
    }
}
