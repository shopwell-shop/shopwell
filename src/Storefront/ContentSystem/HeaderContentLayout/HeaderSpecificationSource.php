<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\HeaderContentLayout;

use Shopwell\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\ContentSection;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\PlaceholderValues;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses domain-aware resolution instead of entity-based path matching.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class HeaderSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<HeaderContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return true;
    }

    public function rootSource(): string
    {
        return 'header';
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->resolveAssignment($context)->getContentLayoutId();
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

        return new SpecificationData(
            dataRequirements: [],
            placeholderValues: PlaceholderValues::from($scalarParameters),
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        $layoutId = $this->resolveAssignment($context)->getContentLayoutId();

        return [ContentSection::HEADER->buildLayoutTag($layoutId)];
    }

    private function resolveAssignment(SalesChannelContext $context): AbstractContentLayoutAssignmentEntity
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'header',
                '',
                $context->getSalesChannel()->getId()
            );
        }

        return $assignment;
    }
}
