<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Adapter;

use Shopwell\Core\Framework\ContentSystem\ContentSection;
use Shopwell\Core\Framework\ContentSystem\PlaceholderValues;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class StandaloneLayoutSpecificationSource extends AbstractSpecificationSource
{
    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return preg_match('#^/layout/[0-9a-f]{32}$#D', $path) === 1;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return substr($path, \strlen('/layout/'));
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        $values = array_filter($request->query->all(), \is_scalar(...));

        return new SpecificationData([], PlaceholderValues::from($values));
    }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return null;
    }

    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        return [ContentSection::MAIN->buildLayoutTag($this->resolveLayoutId($path, $request, $context))];
    }

    public function rootSource(): ?string
    {
        return 'none';
    }
}
