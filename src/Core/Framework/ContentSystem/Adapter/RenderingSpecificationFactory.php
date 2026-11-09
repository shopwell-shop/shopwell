<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter;

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
class RenderingSpecificationFactory
{
    public function create(
        AbstractSpecificationSource $source,
        string $path,
        Request $request,
        SalesChannelContext $context,
    ): ResolvedContentLayout {
        $layoutId = $source->resolveLayoutId($path, $request, $context);
        $data = $source->resolveSpecificationData($path, $request, $context);

        return ResolvedContentLayout::create(
            $layoutId,
            new RenderingSpecification(
                dataRequirements: $data->dataRequirements,
                placeholderValues: $data->placeholderValues,
                request: $request,
                targetElementId: $source->resolveTargetElementId($path, $request, $context),
                cacheTags: $source->resolveCacheTags($path, $request, $context),
                rootSource: $source->rootSource(),
            ),
        );
    }

    public function createWithoutLayout(
        AbstractSpecificationSource $source,
        string $entityId,
        Request $request,
        SalesChannelContext $context,
    ): RenderingSpecification {
        $data = $source->resolveSpecificationDataForEntity($entityId, $request, $context);

        return new RenderingSpecification(
            dataRequirements: $data->dataRequirements,
            placeholderValues: $data->placeholderValues,
            request: $request,
            targetElementId: $source->resolveTargetElementId('', $request, $context),
            cacheTags: [],
            rootSource: $source->rootSource(),
        );
    }
}
