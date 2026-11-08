<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The "none" root source: the binding for a layout that needs no root-ambient context. Its four resolution
 * methods are unreachable (the resolver never routes to a source that claims no path and no entity type) and
 * fail hard if ever called.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class NoneSpecificationSource extends AbstractSpecificationSource
{
    public const ROOT_SOURCE = 'none';

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return false;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }

    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        throw ContentSystemException::noneSourceNotRenderable();
    }
}
