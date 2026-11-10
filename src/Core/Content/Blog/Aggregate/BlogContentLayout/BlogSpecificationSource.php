<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\SpecificationData;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class BlogSpecificationSource extends AbstractSpecificationSource
{
    public const PREVIEW_ATTRIBUTE = '_blog_entity_preview';

    /**
     * @param EntityRepository<BlogContentLayoutCollection> $repository
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly BlogContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory,
        private readonly EntityRepository $blogRepository,
        private readonly SystemConfigService $systemConfig,
        private readonly EntityLayoutResolver $layoutResolver,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly BlogVisibilityGuard $visibilityGuard,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return $this->contextFactory->supports($path, $this->definition);
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        $blogId = $this->contextFactory->extractEntityId($path, $this->definition);
        $blog = $this->visibleBlog($blogId, $context);
        $assigned = $this->findAssignedLayoutId($blogId, $context);
        if ($assigned !== null) {
            return $assigned;
        }

        $key = 'core.content_system.default_blog_' . $blog->type . '_content_layout';
        $this->cacheTagCollector->addTag(SystemConfigService::buildName($key));
        $default = $this->systemConfig->getString($key, $context->getSalesChannelId());
        if ($default !== '') {
            return $default;
        }

        return $this->contextFactory->resolveLayoutId($path, $context, $this->repository, $this->definition);
    }

    public function findAssignedLayoutId(string $blogId, SalesChannelContext $context): ?string
    {
        $this->cacheTagCollector->addTag(...$this->definition->getCacheTags($blogId));

        return $this->layoutResolver->findLayoutId('blogId', $blogId, $context, $this->repository);
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        $this->visibleBlog($this->contextFactory->extractEntityId($path, $this->definition), $context);

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
        return [...$this->contextFactory->resolveCacheTags($path, $this->definition), 'blog-content-layout'];
    }

    public function supportsEntityType(string $entityType): bool
    {
        return $this->definition->getContentLayoutEntityType() === $entityType;
    }

    public function resolveSpecificationDataForEntity(string $entityId, Request $request, SalesChannelContext $context): SpecificationData
    {
        // Only the authenticated layout-preview entry point calls this method.
        $request->attributes->set(self::PREVIEW_ATTRIBUTE, true);

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

    private function visibleBlog(string $blogId, SalesChannelContext $context): BlogEntity
    {
        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context->getContext())->getEntities()->first();
        if (!$blog instanceof BlogEntity || !$this->visibilityGuard->isVisible($blog, $context->getContext())) {
            throw BlogException::notFound($blogId);
        }

        return $blog;
    }
}
