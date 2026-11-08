<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopwell\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategorySpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<CategoryContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CategoryContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->contextFactory->supports($path, $this->definition);
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->contextFactory->resolveLayoutId($path, $context, $this->repository, $this->definition);
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        return $this->contextFactory->resolveSpecificationData($path, $request, $context, $this->definition);
    }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return $this->contextFactory->resolveTargetElementId($request);
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        return $this->contextFactory->resolveCacheTags($path, $this->definition);
    }

    public function supportsEntityType(string $entityType): bool
    {
        return $this->definition->getContentLayoutEntityType() === $entityType;
    }

    public function resolveSpecificationDataForEntity(string $entityId, Request $request, SalesChannelContext $context): SpecificationData
    {
        return $this->contextFactory->buildSpecificationData($entityId, $request, $context, $this->definition);
    }

    public function providedRootContext(Context $context): array
    {
        return $this->contextFactory->providedRootContext($this->definition);
    }

    public function rootSource(): string
    {
        return $this->definition->getContentLayoutEntityType();
    }
}
